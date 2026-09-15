# Start the local Qdrant vector store for Sadhana (Ask Sadhana, Explain, doubt solver).
#
# Usage, from PowerShell:   D:\Sadhana\infra\qdrant\start-qdrant.ps1
# If scripts are blocked:   powershell -ExecutionPolicy Bypass -File D:\Sadhana\infra\qdrant\start-qdrant.ps1
#
# Leave the window open while you use the app. Data is kept in .\storage next to this script.

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

if (-not (Test-Path '.\qdrant.exe')) {
    Write-Host 'qdrant.exe not found. Downloading Qdrant 1.19.1...'
    Invoke-WebRequest -UseBasicParsing -OutFile 'qdrant.zip' `
        -Uri 'https://github.com/qdrant/qdrant/releases/download/v1.19.1/qdrant-x86_64-pc-windows-msvc.zip'
    Expand-Archive -Path 'qdrant.zip' -DestinationPath '.' -Force
    Remove-Item 'qdrant.zip'
}

$listening = Get-NetTCPConnection -LocalPort 6333 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Write-Host "Qdrant is already running (PID $($listening[0].OwningProcess)) on http://127.0.0.1:6333 - nothing to do."
    exit 0
}

$env:QDRANT__TELEMETRY_DISABLED = 'true'
$env:QDRANT__STORAGE__STORAGE_PATH = (Join-Path $PSScriptRoot 'storage')

Write-Host 'Starting Qdrant on http://127.0.0.1:6333 (Ctrl+C to stop)...'
& '.\qdrant.exe'
