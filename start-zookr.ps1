Clear-Host

Write-Host ""
Write-Host "======================================" -ForegroundColor Green
Write-Host "  ZOOKR DEVELOPMENT ENVIRONMENT START  " -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

Set-Location "D:\projects\zookr"

Write-Host "▶ Lokale Vite (Windows) stoppen..." -ForegroundColor Cyan
$viteProcessIds = @()

$viteProcessIds += Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -match 'node.exe' -and
        $_.CommandLine -match 'vite' -and
        $_.CommandLine -match 'zookr'
    } |
    Select-Object -ExpandProperty ProcessId

$viteProcessIds += Get-CimInstance Win32_Process |
    Where-Object {
        $_.Name -match 'npm.exe' -and
        $_.CommandLine -match 'run dev' -and
        $_.CommandLine -match 'zookr'
    } |
    Select-Object -ExpandProperty ProcessId

$viteProcessIds = $viteProcessIds | Sort-Object -Unique

if ($viteProcessIds.Count -gt 0) {
    $viteProcessIds | ForEach-Object {
        Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue
    }
    Write-Host "✔ Lokale Vite processen gestopt." -ForegroundColor Green
} else {
    Write-Host "✔ Geen lokale Vite processen gevonden." -ForegroundColor Green
}

if (Test-Path "public\hot") {
    Remove-Item -Force "public\hot"
}

Write-Host "▶ Docker containers starten..." -ForegroundColor Cyan
docker compose up -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "✖ Docker start mislukt" -ForegroundColor Red
    Pause
    exit 1
}

Write-Host ""
Write-Host "▶ Containers actief:" -ForegroundColor Cyan
docker compose ps

Write-Host ""
Write-Host "▶ Zookr beschikbaar op:" -ForegroundColor Green
Write-Host "   • App:      http://localhost" -ForegroundColor White
Write-Host "   • Mailpit:  http://localhost:8025" -ForegroundColor White
Write-Host "   • Vite:     http://localhost:5174" -ForegroundColor White

Write-Host ""
Write-Host "======================================" -ForegroundColor Green
Write-Host "  ZOOKR IS KLAAR VOOR GEBRUIK         " -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

Pause

