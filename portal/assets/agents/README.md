# CTECHLIT Station Agent

A small PowerShell agent that runs on each café PC (as SYSTEM, started by a scheduled task at boot).
It lets the portal lock, message, sign out, sleep, restart and shut down PCs, enforces timed internet
access for paid sessions, and relays Wake-on-LAN and device checks when the server is hosted online.

## How it talks to the server

The agent only makes **outgoing HTTPS requests** to `<server>/portal/api/station-session.php` every
~5 seconds. Each check-in reports the PC's status, collects queued commands and returns their results.
No router port forwarding is needed, so this works with the server hosted online.

| Server location | Commands | Wake-on-LAN, printer/console checks |
|---|---|---|
| Online (cloud) | Queued, delivered on the next check-in (≤ ~5 s) | Relayed by an online agent in the café |
| Café network (LAN) | Pushed instantly to the agent on TCP 8080, queue as fallback | Sent by the server directly |

`DEVICE_CONTROL_MODE` in `config/.env` selects the mode (`auto` detects it from the server address).

## Install on a PC (recommended)

1. In the portal, add the PC under **Stations** with its MAC address (`getmac /v` on the PC).
2. Open **Stations → Device Control**, pick the station, check the server address
   (e.g. `https://yourdomain.com/systems`) and click **Download installer**.
3. Unzip on the PC and double-click **install.cmd** (accept the Administrator prompt).
4. Delete the downloaded folder afterwards — it contains the shared agent key.

The agent installs to `C:\ProgramData\CTECHLIT\Agent` (admin-only) and logs to `ctechlit-agent.log` there.
Run `uninstall.cmd` from the same package to remove it and restore internet routes.

### Relay PC

When the server is online, Wake-on-LAN must be sent from inside the café. Install the agent on one
always-on PC (e.g. the front desk) with **Relay-only** ticked. A relay PC relays Wake-on-LAN and printer/console
checks but never has its own internet blocked. Register it as a station and set its status to
*Maintenance* so it is not sold to customers or included in "all stations" commands.

## Manual install

```powershell
# PowerShell as Administrator, in the unzipped folder
.\setup-agent.ps1 -StationId 1 -StationCode 'ST-01' -ServerUrl 'https://yourdomain.com/systems' `
    -ApiKey '<STATION_AGENT_API_KEY from config/.env>' -StationMacAddress 'AA-BB-CC-DD-EE-FF' [-RelayOnly]
```

Options: `-HeartbeatInterval 5`, `-ListenPort 8080`, `-OfflineGraceMinutes 5`, `-Uninstall`.

## Internet control and safety

- With no active paid session, the agent removes the PC's default routes, keeping only routes to the
  server and DNS so it can keep checking in. Server IPs are re-resolved on every check-in, so hosting/CDN
  IP changes do not cut the PC off.
- **Fail-open:** if the server cannot be reached for `offline_grace_minutes` (default 5), internet is
  restored so a server outage never locks customers out; it is blocked again once the server responds.

## Requirements

- Windows 10/11 with Windows PowerShell 5.1 (TLS 1.2 is enabled by the agent).
- For Wake-on-LAN: enable it in the BIOS/UEFI and the network adapter; use wired Ethernet.

## Troubleshooting

| Symptom | Check |
|---|---|
| Station stays *Offline* | `C:\ProgramData\CTECHLIT\Agent\ctechlit-agent.log`; the server URL opens in a browser on the PC; the station's MAC matches the adapter |
| 401 in the log | The key in the installer differs from `STATION_AGENT_API_KEY` on the server — download a fresh installer |
| "No station agent is connected" when waking | Keep at least one PC (ideally a relay PC) on with the agent running |
| Lock/message does nothing | A user must be signed in to the PC for screen-level commands |
