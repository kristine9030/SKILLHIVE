$ErrorActionPreference = 'Stop'

$projectRoot = 'C:\xampp\htdocs\SKILLHIVE'
$serverScript = Join-Path $projectRoot 'backend\email_api\server.js'
$port = 3100

$listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    exit 0
}

if ([string]::IsNullOrWhiteSpace($env:GMAIL_SMTP_USER) -or [string]::IsNullOrWhiteSpace($env:GMAIL_APP_PASSWORD)) {
    exit 1
}

$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if (-not $nodeCommand) {
    exit 1
}

$nodeExe = $nodeCommand.Source
Start-Process -FilePath $nodeExe -ArgumentList "`"$serverScript`"" -WorkingDirectory $projectRoot -WindowStyle Hidden

Start-Sleep -Seconds 2
$confirm = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
if (-not $confirm) {
    exit 1
}

exit 0
