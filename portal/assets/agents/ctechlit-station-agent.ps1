<#
.SYNOPSIS
    CTECHLIT Station Agent - remote hardware control for CTECHLIT workstations
.DESCRIPTION
    Runs as SYSTEM (scheduled task at startup) on each workstation and:
    - Checks in with the server every few seconds (heartbeat + session state)
    - Enforces timed internet access for the station's active session
    - Executes commands queued by the portal/Jarvis and reports the results
    - Accepts authenticated direct commands on the LAN (http://<ip>:8080/ctechlit-agent/)
.NOTES
    File Name      : ctechlit-station-agent.ps1
    Prerequisite   : Windows PowerShell 5.1, run as SYSTEM or Administrator
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir "ctechlit-agent-config.json"
$LogFile = Join-Path $ScriptDir "ctechlit-agent.log"
$PidFile = Join-Path $ScriptDir "ctechlit-agent.pid"
$NetworkStateFile = Join-Path $ScriptDir "ctechlit-network-routes.json"

if (-not (Test-Path $ConfigFile)) {
    @{
        server_url = "http://SERVER-IP/systems"
        station_id = 0
        station_code = "ST-UNKNOWN"
        station_mac_address = ""
        heartbeat_interval = 5
        api_key = ""
        listen_port = 8080
        relay_only = $false
        offline_grace_minutes = 5
        log_level = "INFO"
    } | ConvertTo-Json | Out-File -FilePath $ConfigFile -Encoding UTF8
    Write-Host "Created default configuration at $ConfigFile. Run setup-agent.ps1 to configure this station."
    exit 0
}

$Config = Get-Content $ConfigFile -Raw | ConvertFrom-Json
$script:PendingResults = New-Object System.Collections.ArrayList
$script:CompletedCommandIds = @{}
$script:LastCheckInSuccess = Get-Date

# Windows PowerShell 5.1 defaults to old TLS versions; hosted HTTPS servers require TLS 1.2+.
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

# relay_only: install on an always-on PC (e.g. front desk) that relays Wake-on-LAN/probes but never has its internet blocked.
$EnforceInternet = -not [bool]$Config.relay_only
# If the server cannot be reached for this long while internet is blocked, restore internet so a server outage never locks customers out.
$OfflineGraceMinutes = if ($Config.offline_grace_minutes) { [int]$Config.offline_grace_minutes } else { 5 }

# ============================================================
# LOGGING
# ============================================================

function Write-Log {
    param([string]$Level, [string]$Message)

    if ($Level -eq "DEBUG" -and $Config.log_level -ne "DEBUG") { return }
    $LogEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] [$Level] $Message"
    Write-Host $LogEntry
    try {
        Add-Content -Path $LogFile -Value $LogEntry -Encoding UTF8
        if ((Get-Item $LogFile).Length -gt 10MB) {
            Move-Item $LogFile ($LogFile -replace '\.log$', ('-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')) -Force
        }
    } catch { }
}

# ============================================================
# INTERNET ACCESS ENFORCEMENT
# ============================================================

function Get-ManagementServerAddresses {
    # Hosted servers (and CDNs) can have several IPs that change over time, so resolve on every call.
    try {
        $serverUri = [System.Uri]::new([string]$Config.server_url)
        $addresses = @([System.Net.Dns]::GetHostAddresses($serverUri.DnsSafeHost) |
            Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and $_.IPAddressToString -notlike "127.*" } |
            ForEach-Object { $_.IPAddressToString })
        if ($addresses.Count -gt 0) { $script:ManagementServerIps = $addresses }
    } catch {
        Write-Log -Level "DEBUG" -Message "Could not resolve the server: $($_.Exception.Message)"
    }
    if (-not $script:ManagementServerIps) {
        throw "The server_url must resolve to a reachable, non-loopback IPv4 address from this station."
    }
    return @($script:ManagementServerIps)
}

function Get-ManagementServerAddress {
    return (Get-ManagementServerAddresses)[0]
}

function Test-AddressInSubnet {
    param([string]$Address, [string]$NetworkAddress, [int]$PrefixLength)

    $addressBytes = [System.Net.IPAddress]::Parse($Address).GetAddressBytes()
    $networkBytes = [System.Net.IPAddress]::Parse($NetworkAddress).GetAddressBytes()
    if ($addressBytes.Length -ne 4 -or $networkBytes.Length -ne 4) { return $false }
    for ($index = 0; $index -lt 4; $index++) {
        $bits = [Math]::Min(8, [Math]::Max(0, $PrefixLength - ($index * 8)))
        $mask = if ($bits -eq 0) { 0 } else { (255 -shl (8 - $bits)) -band 255 }
        if (($addressBytes[$index] -band $mask) -ne ($networkBytes[$index] -band $mask)) { return $false }
    }
    return $true
}

function Set-StationInternetAccess {
    param([bool]$Enabled, [string]$IpAddress, [string]$MacAddress)

    $normalizedMac = $MacAddress.Replace('-', ':').ToUpperInvariant()
    $adapter = Get-NetAdapter -Physical -ErrorAction Stop |
        Where-Object { $_.MacAddress.Replace('-', ':').ToUpperInvariant() -eq $normalizedMac -and $_.Status -eq 'Up' } |
        Select-Object -First 1
    if (-not $adapter) { throw "The registered station adapter ($normalizedMac) is not available." }
    if (-not (Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $adapter.InterfaceIndex -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -eq $IpAddress })) {
        throw "The registered station IP address ($IpAddress) is not assigned to adapter $($adapter.Name)."
    }

    if ($Enabled) {
        if (-not (Test-Path $NetworkStateFile)) { return }
        $state = Get-Content $NetworkStateFile -Raw | ConvertFrom-Json
        $pinnedAddresses = @($state.pinned_addresses)
        if ($pinnedAddresses.Count -eq 0) { $pinnedAddresses = @($state.management_server_ip) }
        foreach ($address in $pinnedAddresses) {
            Get-NetRoute -DestinationPrefix "$address/32" -InterfaceIndex $adapter.InterfaceIndex -ErrorAction SilentlyContinue |
                Remove-NetRoute -Confirm:$false -ErrorAction SilentlyContinue
        }
        foreach ($route in $state.default_routes) {
            $existing = Get-NetRoute -AddressFamily $route.address_family -DestinationPrefix $route.destination_prefix `
                -InterfaceIndex $route.interface_index -ErrorAction SilentlyContinue | Where-Object { $_.NextHop -eq $route.next_hop }
            if (-not $existing) {
                New-NetRoute -DestinationPrefix $route.destination_prefix -InterfaceIndex $route.interface_index `
                    -NextHop $route.next_hop -RouteMetric $route.route_metric -PolicyStore ActiveStore -ErrorAction Stop | Out-Null
            }
        }
        Remove-Item $NetworkStateFile -Force
        $script:ManagementServerIp = $null
        Write-Log -Level "INFO" -Message "Restored internet routes for $IpAddress ($normalizedMac)."
        return
    }

    if (Test-Path $NetworkStateFile) {
        $savedState = Get-Content $NetworkStateFile -Raw | ConvertFrom-Json
        $managementServerIp = [string]$savedState.management_server_ip
        $script:ManagementServerIp = $managementServerIp
    } else {
        $managementServerIp = Get-ManagementServerAddress
        $routes = @(Get-NetRoute -InterfaceIndex $adapter.InterfaceIndex -ErrorAction SilentlyContinue |
            Where-Object { $_.DestinationPrefix -in @('0.0.0.0/0', '::/0') } |
            ForEach-Object {
                @{
                    address_family = if ($_.DestinationPrefix -eq '::/0') { 'IPv6' } else { 'IPv4' }
                    destination_prefix = $_.DestinationPrefix
                    interface_index = [int]$_.InterfaceIndex
                    next_hop = $_.NextHop
                    route_metric = [int]$_.RouteMetric
                }
            })
        if ($routes.Count -eq 0) { throw "No default gateway route was found on adapter $($adapter.Name)." }
        $pinnedAddresses = @(Get-ManagementServerAddresses) + @(
            Get-DnsClientServerAddress -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue |
                ForEach-Object { $_.ServerAddresses } | Where-Object { $_ -and $_ -ne '0.0.0.0' }
        ) | Select-Object -Unique
        $savedState = @{ management_server_ip = $managementServerIp; pinned_addresses = $pinnedAddresses; default_routes = $routes }
        $savedState | ConvertTo-Json -Depth 5 | Set-Content -Path $NetworkStateFile -Encoding UTF8
    }

    $ipv4Route = $savedState.default_routes | Where-Object { $_.address_family -eq 'IPv4' } | Select-Object -First 1
    if (-not $ipv4Route) { throw "No IPv4 default gateway is available to keep the management server reachable." }
    $ipConfig = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $adapter.InterfaceIndex |
        Where-Object { $_.IPAddress -eq $IpAddress } | Select-Object -First 1
    $pinnedAddresses = @($savedState.pinned_addresses)
    if ($pinnedAddresses.Count -eq 0) { $pinnedAddresses = @($managementServerIp) }
    # Keep the server reachable even if its IP changes while internet is blocked (DNS servers stay pinned).
    $currentServerIps = @()
    try { $currentServerIps = @(Get-ManagementServerAddresses) } catch { }
    $newIps = @($currentServerIps | Where-Object { $_ -notin $pinnedAddresses })
    if ($newIps.Count -gt 0) {
        $pinnedAddresses = @($pinnedAddresses + $newIps | Select-Object -Unique)
        $savedState.pinned_addresses = $pinnedAddresses
        $savedState | ConvertTo-Json -Depth 5 | Set-Content -Path $NetworkStateFile -Encoding UTF8
        Write-Log -Level "INFO" -Message "Pinned new server address(es): $($newIps -join ', ')"
    }
    foreach ($address in $pinnedAddresses) {
        $routeNextHop = if (Test-AddressInSubnet -Address $address -NetworkAddress $IpAddress -PrefixLength $ipConfig.PrefixLength) { '0.0.0.0' } else { $ipv4Route.next_hop }
        if (-not (Get-NetRoute -DestinationPrefix "$address/32" -InterfaceIndex $adapter.InterfaceIndex -ErrorAction SilentlyContinue)) {
            New-NetRoute -DestinationPrefix "$address/32" -InterfaceIndex $adapter.InterfaceIndex `
                -NextHop $routeNextHop -RouteMetric 1 -PolicyStore ActiveStore -ErrorAction Stop | Out-Null
        }
    }
    foreach ($route in $savedState.default_routes) {
        Get-NetRoute -AddressFamily $route.address_family -DestinationPrefix $route.destination_prefix `
            -InterfaceIndex $route.interface_index -ErrorAction SilentlyContinue |
            Where-Object { $_.NextHop -eq $route.next_hop } | Remove-NetRoute -Confirm:$false -ErrorAction Stop
    }
    Write-Log -Level "WARNING" -Message "Internet access disabled for station $IpAddress ($normalizedMac); management traffic remains routed to $managementServerIp."
}

# ============================================================
# HARDWARE STATUS
# ============================================================

function Get-StationStatus {
    try {
        $os = Get-CimInstance Win32_OperatingSystem
        $cpu = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
        $disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
        return @{
            success = $true
            message = "Status collected"
            status = @{
                cpu_usage = [math]::Round([double]$cpu, 1)
                memory_usage = [math]::Round((1 - ($os.FreePhysicalMemory / $os.TotalVisibleMemorySize)) * 100, 1)
                disk_free_percent = if ($disk) { [math]::Round(($disk.FreeSpace / $disk.Size) * 100, 1) } else { $null }
                uptime_minutes = [int]((Get-Date) - $os.LastBootUpTime).TotalMinutes
                logged_in_user = (Get-CimInstance Win32_ComputerSystem).UserName
                hostname = $env:COMPUTERNAME
            }
        }
    } catch {
        return @{ success = $false; message = $_.Exception.Message }
    }
}

# ============================================================
# COMMAND EXECUTION
# ============================================================

function Get-InteractiveUser {
    return (Get-CimInstance Win32_ComputerSystem -ErrorAction SilentlyContinue).UserName
}

function Get-ActiveSessionId {
    $lines = @(query user 2>$null)
    foreach ($line in $lines | Select-Object -Skip 1) {
        if ($line -match '\s(\d+)\s+(Active|Aktiv)\b') { return [int]$Matches[1] }
    }
    return $null
}

# Run a program inside the logged-on user's desktop session (the agent itself runs in session 0).
function Invoke-InUserSession {
    param([string]$Execute, [string]$Arguments = "")

    $user = Get-InteractiveUser
    if (-not $user) { throw "No user is signed in at this station." }
    $taskName = "CTECHLIT-UserAction-" + [guid]::NewGuid().ToString("N").Substring(0, 8)
    $action = New-ScheduledTaskAction -Execute $Execute -Argument $Arguments
    $principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName $taskName -Action $action -Principal $principal -Force | Out-Null
    try {
        Start-ScheduledTask -TaskName $taskName
        Start-Sleep -Milliseconds 1500
    } finally {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    }
}

function Invoke-StationCommand {
    param([string]$Command, $Params)

    Write-Log -Level "INFO" -Message "Executing command: $Command"
    try {
        switch ($Command.ToLower()) {
            "lock" {
                Invoke-InUserSession -Execute "rundll32.exe" -Arguments "user32.dll,LockWorkStation"
                return @{ success = $true; message = "Station locked" }
            }
            "message" {
                $text = ([string]$Params.text) -replace '[\r\n]+', ' '
                if (-not $text) { return @{ success = $false; message = "Message text is empty" } }
                $msgExe = Join-Path $env:SystemRoot "System32\msg.exe"
                if (Test-Path $msgExe) {
                    & $msgExe * /TIME:120 $text
                } else {
                    $escaped = $text.Replace("'", "''")
                    $script = "Add-Type -AssemblyName PresentationFramework; [System.Windows.MessageBox]::Show('$escaped', 'CTECHLIT')"
                    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($script))
                    Invoke-InUserSession -Execute "powershell.exe" -Arguments "-NoProfile -WindowStyle Hidden -EncodedCommand $encoded"
                }
                return @{ success = $true; message = "Message displayed" }
            }
            "logoff" {
                $sessionId = Get-ActiveSessionId
                if ($null -eq $sessionId) { return @{ success = $true; message = "No user was signed in" } }
                Start-Process -FilePath "cmd.exe" -ArgumentList "/c timeout /t 3 /nobreak >nul & logoff $sessionId" -WindowStyle Hidden
                return @{ success = $true; message = "User signed out" }
            }
            "shutdown" {
                & shutdown.exe /s /t 5 /f /c "Shutdown requested by CTECHLIT"
                return @{ success = $true; message = "Shutting down in 5 seconds" }
            }
            "restart" {
                & shutdown.exe /r /t 5 /f /c "Restart requested by CTECHLIT"
                return @{ success = $true; message = "Restarting in 5 seconds" }
            }
            "sleep" {
                Start-Process -FilePath "cmd.exe" -ArgumentList "/c timeout /t 4 /nobreak >nul & rundll32.exe powrprof.dll,SetSuspendState 0,1,0" -WindowStyle Hidden
                return @{ success = $true; message = "Sleeping in 4 seconds" }
            }
            "status" { return Get-StationStatus }
            "set_password" {
                $username = [string]$Params.username
                $password = [string]$Params.password
                if ($username -notmatch '^[A-Za-z0-9._ -]{1,64}$' -or $password.Length -lt 6) {
                    return @{ success = $false; message = "Invalid username or password" }
                }
                Set-LocalUser -Name $username -Password (ConvertTo-SecureString $password -AsPlainText -Force) -ErrorAction Stop
                return @{ success = $true; message = "Password updated for $username" }
            }
            "wake" { return @{ success = $true; message = "Station is already awake" } }
            "wol" {
                # Relay a Wake-on-LAN magic packet to another device on this LAN (used when the server is hosted online).
                $hex = ([string]$Params.mac) -replace '[^0-9A-Fa-f]', ''
                if ($hex.Length -ne 12) { return @{ success = $false; message = "Invalid MAC address" } }
                $macBytes = for ($i = 0; $i -lt 12; $i += 2) { [Convert]::ToByte($hex.Substring($i, 2), 16) }
                $packet = [byte[]](@(0xFF) * 6 + ($macBytes * 16))
                $targets = @('255.255.255.255')
                if ($Params.broadcast -and ([string]$Params.broadcast) -match '^\d{1,3}(\.\d{1,3}){3}$') { $targets += [string]$Params.broadcast }
                $udp = New-Object System.Net.Sockets.UdpClient
                try {
                    $udp.EnableBroadcast = $true
                    foreach ($target in ($targets | Select-Object -Unique)) {
                        foreach ($port in 9, 7) { [void]$udp.Send($packet, $packet.Length, $target, $port) }
                    }
                } finally { $udp.Close() }
                return @{ success = $true; message = "Magic packet sent from $env:COMPUTERNAME" }
            }
            "probe" {
                # Check whether a LAN device (printer, console, router) answers on any of the given TCP ports.
                $ip = [string]$Params.ip
                if ($ip -notmatch '^\d{1,3}(\.\d{1,3}){3}$') { return @{ success = $false; message = "Invalid IP address" } }
                $ports = @($Params.ports | ForEach-Object { [int]$_ } | Where-Object { $_ -gt 0 -and $_ -lt 65536 } | Select-Object -First 6)
                if ($ports.Count -eq 0) { $ports = @(80, 443) }
                foreach ($port in $ports) {
                    $client = New-Object System.Net.Sockets.TcpClient
                    try {
                        $attempt = $client.BeginConnect($ip, $port, $null, $null)
                        if ($attempt.AsyncWaitHandle.WaitOne(1000) -and $client.Connected) {
                            $client.EndConnect($attempt)
                            return @{ success = $true; message = "Reachable at ${ip}:$port" }
                        }
                    } catch { } finally { $client.Close() }
                }
                return @{ success = $false; message = "No response from $ip" }
            }
            default { return @{ success = $false; message = "Unknown command: $Command" } }
        }
    } catch {
        Write-Log -Level "ERROR" -Message "Command '$Command' failed: $($_.Exception.Message)"
        return @{ success = $false; message = $_.Exception.Message }
    }
}

# ============================================================
# CHECK-IN (HEARTBEAT + COMMAND QUEUE)
# ============================================================

function Get-StationAdapterInfo {
    $adapters = @(Get-NetAdapter -Physical -ErrorAction Stop | Where-Object { $_.Status -eq 'Up' })
    if ($Config.station_mac_address) {
        $registeredMac = ([string]$Config.station_mac_address).Replace('-', ':').ToUpperInvariant()
        $adapters = @($adapters | Where-Object { $_.MacAddress.Replace('-', ':').ToUpperInvariant() -eq $registeredMac })
    }
    $adapter = $adapters | Select-Object -First 1
    if (-not $adapter) { throw "No active physical network adapter matches the configured station MAC address." }
    $address = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $adapter.InterfaceIndex -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown" } | Select-Object -First 1
    if (-not $address) { throw "The station network adapter has no non-loopback IPv4 address." }
    return @{ ip_address = $address.IPAddress; mac_address = $adapter.MacAddress.Replace('-', ':').ToUpperInvariant() }
}

function Invoke-QueuedCommands {
    param($Commands)

    foreach ($queued in @($Commands)) {
        if (-not $queued -or -not $queued.command_id) { continue }
        $id = [string]$queued.command_id
        if ($script:CompletedCommandIds.ContainsKey($id)) { continue }
        $script:CompletedCommandIds[$id] = $true
        $result = Invoke-StationCommand -Command ([string]$queued.command) -Params $queued.params
        [void]$script:PendingResults.Add(@{ command_id = [int]$queued.command_id; success = [bool]$result.success; message = [string]$result.message })
    }
}

function Send-Heartbeat {
    param([string]$Status = "online")

    try {
        if (-not $Config.api_key) { throw "Station agent API key is missing from ctechlit-agent-config.json." }
        $info = Get-StationAdapterInfo
        $results = @($script:PendingResults)
        $body = @{
            station_id = $Config.station_id
            status = $Status
            ip_address = $info.ip_address
            mac_address = $info.mac_address
            command_results = $results
        } | ConvertTo-Json -Depth 5
        $headers = @{ "Authorization" = "Bearer $($Config.api_key)"; "X-Requested-With" = "XMLHttpRequest" }
        $url = "$($Config.server_url.TrimEnd('/'))/portal/api/station-session.php"
        $response = Invoke-RestMethod -Uri $url -Method POST -Body $body -Headers $headers -ContentType "application/json" -TimeoutSec 10

        if ($response.success) {
            $script:PendingResults.Clear()
            $script:LastCheckInSuccess = Get-Date
            if ($Status -eq "online") {
                if ($EnforceInternet) {
                    Set-StationInternetAccess -Enabled ([bool]$response.internet_allowed) -IpAddress $info.ip_address -MacAddress $info.mac_address
                }
                if ($response.commands -and @($response.commands).Count -gt 0) {
                    Invoke-QueuedCommands -Commands $response.commands
                    return $true # Report results right away.
                }
            }
        }
        return $false
    } catch {
        Write-Log -Level "WARNING" -Message "Check-in failed: $($_.Exception.Message)"
        return $false
    }
}

# ============================================================
# DIRECT LAN LISTENER (authenticated)
# ============================================================

function Start-AgentListener {
    try {
        $listener = [System.Net.HttpListener]::new()
        $listener.Prefixes.Add("http://+:$([int]$Config.listen_port)/ctechlit-agent/")
        $listener.Start()
        $ruleName = "CTECHLIT Station Agent (TCP $([int]$Config.listen_port))"
        if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
            New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort ([int]$Config.listen_port) -Action Allow -Profile Any | Out-Null
        }
        Write-Log -Level "INFO" -Message "Direct command listener started on port $($Config.listen_port)"
        return $listener
    } catch {
        Write-Log -Level "WARNING" -Message "Direct listener unavailable ($($_.Exception.Message)); commands will arrive through the check-in queue."
        return $null
    }
}

function Send-JsonResponse {
    param($Context, [int]$StatusCode, $Payload)

    $buffer = [System.Text.Encoding]::UTF8.GetBytes(($Payload | ConvertTo-Json -Depth 5 -Compress))
    $Context.Response.StatusCode = $StatusCode
    $Context.Response.ContentType = "application/json"
    $Context.Response.ContentLength64 = $buffer.Length
    $Context.Response.OutputStream.Write($buffer, 0, $buffer.Length)
    $Context.Response.Close()
}

function Invoke-ListenerRequest {
    param($Context)

    try {
        $request = $Context.Request
        if ($request.Headers["Authorization"] -ne "Bearer $($Config.api_key)") {
            Write-Log -Level "WARNING" -Message "Rejected unauthenticated request from $($request.RemoteEndPoint)"
            Send-JsonResponse -Context $Context -StatusCode 401 -Payload @{ success = $false; message = "Unauthorized" }
            return
        }
        $path = $request.Url.AbsolutePath.TrimEnd('/') -replace '^/ctechlit-agent/?', ''
        if ($request.HttpMethod -eq "GET" -and $path -eq "status") {
            Send-JsonResponse -Context $Context -StatusCode 200 -Payload (Get-StationStatus)
            return
        }
        if ($request.HttpMethod -eq "POST" -and $path -eq "command") {
            $reader = New-Object System.IO.StreamReader($request.InputStream, $request.ContentEncoding)
            $data = $reader.ReadToEnd() | ConvertFrom-Json
            $reader.Close()
            if (-not $data.command) {
                Send-JsonResponse -Context $Context -StatusCode 400 -Payload @{ success = $false; message = "Missing 'command'" }
                return
            }
            if ($data.command_id) { $script:CompletedCommandIds[[string]$data.command_id] = $true }
            $result = Invoke-StationCommand -Command ([string]$data.command) -Params $data.params
            Send-JsonResponse -Context $Context -StatusCode ($(if ($result.success) { 200 } else { 422 })) -Payload $result
            return
        }
        Send-JsonResponse -Context $Context -StatusCode 404 -Payload @{ success = $false; message = "Not found" }
    } catch {
        Write-Log -Level "ERROR" -Message "Listener request failed: $($_.Exception.Message)"
        try { Send-JsonResponse -Context $Context -StatusCode 500 -Payload @{ success = $false; message = "Internal error" } } catch { }
    }
}

# ============================================================
# MAIN LOOP
# ============================================================

Write-Log -Level "INFO" -Message "CTECHLIT Station Agent starting: $($Config.station_code) (ID $($Config.station_id)) -> $($Config.server_url)"
@{ pid = $PID; station_id = $Config.station_id; station_code = $Config.station_code; started_at = (Get-Date -Format "o"); hostname = $env:COMPUTERNAME } |
    ConvertTo-Json | Out-File -FilePath $PidFile -Encoding UTF8

$listener = Start-AgentListener
$contextTask = if ($listener) { $listener.GetContextAsync() } else { $null }
$pollInterval = [Math]::Max(2, [Math]::Min(5, [int]$Config.heartbeat_interval))
$lastCheckIn = [DateTime]::MinValue

try {
    while ($true) {
        if (((Get-Date) - $lastCheckIn).TotalSeconds -ge $pollInterval) {
            $reportNow = Send-Heartbeat -Status "online"
            $lastCheckIn = if ($reportNow) { [DateTime]::MinValue } else { Get-Date }

            # Fail open: if the online server is unreachable for too long, give the PC its internet back.
            if ($EnforceInternet -and (Test-Path $NetworkStateFile) -and ((Get-Date) - $script:LastCheckInSuccess).TotalMinutes -ge $OfflineGraceMinutes) {
                try {
                    $info = Get-StationAdapterInfo
                    Set-StationInternetAccess -Enabled $true -IpAddress $info.ip_address -MacAddress $info.mac_address
                    Write-Log -Level "WARNING" -Message "Server unreachable for $OfflineGraceMinutes min; internet restored until it is back."
                } catch {
                    Write-Log -Level "ERROR" -Message "Could not restore internet after losing the server: $($_.Exception.Message)"
                }
            }
        }

        if ($contextTask) {
            # Wait up to 500 ms for a direct command without blocking the check-in loop.
            if ($contextTask.AsyncWaitHandle.WaitOne(500)) {
                try { Invoke-ListenerRequest -Context $contextTask.Result } catch { Write-Log -Level "WARNING" -Message "Listener error: $($_.Exception.Message)" }
                $contextTask = $listener.GetContextAsync()
            }
        } else {
            Start-Sleep -Milliseconds 500
        }
    }
} catch {
    Write-Log -Level "ERROR" -Message "Fatal error: $($_.Exception.Message)"
} finally {
    if ($listener) { try { $listener.Stop(); $listener.Close() } catch { } }
    Send-Heartbeat -Status "offline" | Out-Null
    if (Test-Path $PidFile) { Remove-Item $PidFile -Force }
    Write-Log -Level "INFO" -Message "Station agent stopped."
}
