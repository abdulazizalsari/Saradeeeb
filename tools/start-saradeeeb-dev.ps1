param([int]$Port = 8010)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$php='C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
if(-not (Test-Path -LiteralPath $php)){throw "PHP executable not found: $php"}
if(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue){throw "Port $Port is already in use; choose another port."}
Write-Host "Serving $projectRoot at http://127.0.0.1:$Port"
Push-Location -LiteralPath $projectRoot
try { & $php -S "127.0.0.1:$Port" router.php }
finally { Pop-Location }
