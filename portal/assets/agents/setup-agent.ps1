<#
.SYNOPSIS
    CTECHLIT Station Agent Setup Script
.DESCRIPTION
    Configures the CTECHLIT Station Agent and registers it as a scheduled task that
    starts at boot under the SYSTEM account and restarts automatically if it stops.
.EXAMPLE
    .\setup-agent.ps1 -StationId 1 -StationCode 'ST-01' -ServerUrl 'http://192.168.1.10/systems' `
        -ApiKey 'same-value-as-STATION_AGENT_API_KEY' -StationMacAddress 'AA-BB-CC-DD-EE-FF'
#>

param(
    [string]$StationId,
    [string]$StationCode,
    [string]$ServerUrl = "http://localhost/systems",
    [string]$ApiKey = "",
    [string]$StationMacAddress = "",
    [int]$HeartbeatInterval = 5,
    [int]$ListenPort = 8080,
    [switch]$RelayOnly,
    [int]$OfflineGraceMinutes = 5,
    [switch]$Uninstall
)

[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
# The agent always runs from a fixed, admin-only folder, wherever this installer was unzipped.
$InstallDir = Join-Path $env:ProgramData "CTECHLIT\Agent"
$AgentScript = Join-Path $InstallDir "ctechlit-station-agent.ps1"
$ConfigFile = Join-Path $InstallDir "ctechlit-agent-config.json"
$LogFile = Join-Path $InstallDir "ctechlit-agent.log"
$TaskName = "CTECHLIT Station Agent"
$LegacyServiceName = "CTECHLITStationAgent"

$CurrentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $CurrentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "ERROR: Run this script in PowerShell opened as Administrator" -ForegroundColor Red
    exit 1
}

# Remove the earlier service-based install; powershell.exe cannot run as a native Windows service.
if (Get-Service -Name $LegacyServiceName -ErrorAction SilentlyContinue) {
    Stop-Service -Name $LegacyServiceName -Force -ErrorAction SilentlyContinue
    sc.exe delete $LegacyServiceName | Out-Null
    Write-Host "Removed legacy $LegacyServiceName service" -ForegroundColor Yellow
}

if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -like "*ctechlit-station-agent.ps1*" } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

if ($Uninstall) {
    # Give the PC its internet routes back before removing the agent.
    $routeState = Join-Path $InstallDir "ctechlit-network-routes.json"
    if (Test-Path $routeState) {
        $state = Get-Content $routeState -Raw | ConvertFrom-Json
        foreach ($route in $state.default_routes) {
            New-NetRoute -DestinationPrefix $route.destination_prefix -InterfaceIndex $route.interface_index -NextHop $route.next_hop `
                -RouteMetric $route.route_metric -PolicyStore ActiveStore -ErrorAction SilentlyContinue | Out-Null
        }
    }
    Remove-Item $InstallDir -Recurse -Force -ErrorAction SilentlyContinue
    Write-Host "CTECHLIT Station Agent removed." -ForegroundColor Green
    exit 0
}

New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
Copy-Item (Join-Path $ScriptDir "ctechlit-station-agent.ps1") $AgentScript -Force
icacls $InstallDir /inheritance:r /grant:r "SYSTEM:(OI)(CI)F" "Administrators:(OI)(CI)F" | Out-Null

if (-not $StationId -or -not $StationCode) {
    Write-Host "ERROR: StationId and StationCode are required" -ForegroundColor Red
    Write-Host "Usage: .\setup-agent.ps1 -StationId 1 -StationCode 'ST-01' -ServerUrl 'http://SERVER-IP/systems' -ApiKey '...' -StationMacAddress 'AA-BB-CC-DD-EE-FF'" -ForegroundColor Yellow
    exit 1
}
if (-not $ApiKey) {
    Write-Host "ERROR: ApiKey is required (use the STATION_AGENT_API_KEY value from the server's config/.env)" -ForegroundColor Red
    exit 1
}
if ($StationMacAddress -notmatch '^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$') {
    Write-Host "ERROR: StationMacAddress must be this PC's physical adapter MAC address (see: Get-NetAdapter -Physical)" -ForegroundColor Red
    exit 1
}
if ($ServerUrl -match '//(localhost|127\.)') {
    Write-Host "ERROR: ServerUrl must be the server's public address (https://yourdomain.com/systems) or LAN address, not localhost" -ForegroundColor Red
    exit 1
}
$ServerUrl = $ServerUrl.TrimEnd('/')
if ($ServerUrl -notmatch '^https://' -and $ServerUrl -notmatch '^http://(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)') {
    Write-Host "WARNING: $ServerUrl is not HTTPS. Use https:// for an online server so the API key is encrypted." -ForegroundColor Yellow
}

# Check the server is reachable before installing (station-session.php answers 401 without the key).
try {
    Invoke-WebRequest -Uri "$ServerUrl/portal/api/station-session.php" -Method POST -UseBasicParsing -TimeoutSec 15 -ErrorAction Stop | Out-Null
} catch {
    $status = if ($_.Exception.Response) { [int]$_.Exception.Response.StatusCode } else { 0 }
    if ($status -eq 0) {
        Write-Host "ERROR: Cannot reach $ServerUrl from this PC: $($_.Exception.Message)" -ForegroundColor Red
        exit 1
    }
    if ($status -eq 404) {
        Write-Host "ERROR: $ServerUrl/portal/api/station-session.php was not found. Check the -ServerUrl path." -ForegroundColor Red
        exit 1
    }
}
Write-Host "Server reachable: $ServerUrl" -ForegroundColor Green

@{
    server_url = $ServerUrl
    station_id = [int]$StationId
    station_code = $StationCode
    station_mac_address = $StationMacAddress
    heartbeat_interval = $HeartbeatInterval
    api_key = $ApiKey
    listen_port = $ListenPort
    relay_only = [bool]$RelayOnly
    offline_grace_minutes = $OfflineGraceMinutes
    log_level = "INFO"
} | ConvertTo-Json | Out-File -FilePath $ConfigFile -Encoding UTF8
if ($RelayOnly) {
    Write-Host "Relay-only mode: this PC relays Wake-on-LAN and device checks; its own internet is never blocked." -ForegroundColor Cyan
}
Write-Host "Configuration saved to $ConfigFile" -ForegroundColor Green

# Only SYSTEM and Administrators may read the config (it contains the shared API key).
icacls $ConfigFile /inheritance:r /grant:r "SYSTEM:F" "Administrators:F" | Out-Null

$PowerShellExe = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$action = New-ScheduledTaskAction -Execute $PowerShellExe -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$AgentScript`"" -WorkingDirectory $InstallDir
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description "CTECHLIT Station Agent - remote hardware control and timed internet access" | Out-Null

Start-ScheduledTask -TaskName $TaskName
Start-Sleep -Seconds 4

$task = Get-ScheduledTask -TaskName $TaskName
Write-Host "`nTask state: $($task.State)" -ForegroundColor Cyan
Write-Host "  Station:   $StationCode (ID $StationId)"
Write-Host "  Server:    $ServerUrl"
Write-Host "  Listener:  http://<this-pc-ip>:$ListenPort/ctechlit-agent/ (Bearer API key required)"
Write-Host "  Log file:  $LogFile"
Write-Host "`nManage with:" -ForegroundColor Yellow
Write-Host "  Start:     Start-ScheduledTask -TaskName '$TaskName'"
Write-Host "  Stop:      Stop-ScheduledTask -TaskName '$TaskName'"
Write-Host "  Uninstall: .\setup-agent.ps1 -Uninstall`n"
if ($task.State -ne 'Running') {
    Write-Host "The agent is not running. Check $LogFile for details." -ForegroundColor Red
    exit 1
}
