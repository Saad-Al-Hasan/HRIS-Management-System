
# MYOL HRIS Desktop Launcher
# Starts the RFID monitor invisibly and opens the HRIS website.

$ErrorActionPreference = "Stop"

$projectPath = "C:\laragon\www\HRIS-Management-System"
$monitorPath = Join-Path $projectPath "tools\rfid-reader-monitor.ps1"
$websiteUrl = "http://localhost/HRIS-Management-System/public/index.php"

if (-not (Test-Path $monitorPath)) {
    [System.Windows.Forms.MessageBox]::Show(
        "RFID monitor not found:`n$monitorPath",
        "MYOL HRIS Launcher"
    ) | Out-Null
    exit 1
}

# Check whether the monitor is already running.
$existingMonitor = Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -eq "powershell.exe" -and
        $_.CommandLine -and
        $_.CommandLine -like "*rfid-reader-monitor.ps1*"
    }

# Start it in the background only if it isn't running.
if (-not $existingMonitor) {
    $arguments = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$monitorPath`""

    Start-Process `
        -FilePath "powershell.exe" `
        -ArgumentList $arguments `
        -WindowStyle Hidden

    Start-Sleep -Seconds 2
}

# Open the HRIS website in the default browser.
Start-Process $websiteUrl
