
$ErrorActionPreference = "SilentlyContinue"

$projectPath = "C:\laragon\www\HRIS-Management-System"
$statusFile = Join-Path $projectPath "public\rfid-reader-status.json"

while ($true) {
    $readerFound = $false

    $devices = Get-PnpDevice -PresentOnly

    foreach ($device in $devices) {
        if (
            $device.InstanceId -like "*VID_FFFF&PID_0035*" -or
            $device.FriendlyName -like "*SY C ID*IC USB Reader*"
        ) {
            $readerFound = $true
            break
        }
    }

    $status = @{
        connected = $readerFound
        reader = "JT308"
        checked_at = (Get-Date).ToString("o")
    } | ConvertTo-Json -Compress

    Set-Content -Path $statusFile -Value $status -Encoding UTF8

    Start-Sleep -Seconds 2
}
