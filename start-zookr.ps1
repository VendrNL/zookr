Clear-Host

Write-Host ""
Write-Host "======================================" -ForegroundColor Green
Write-Host "  ZOOKR DEVELOPMENT ENVIRONMENT START  " -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

Set-Location "D:\projects\zookr"

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
Write-Host "   • Vite:     http://localhost:5173" -ForegroundColor White

Write-Host ""
Write-Host "======================================" -ForegroundColor Green
Write-Host "  ZOOKR IS KLAAR VOOR GEBRUIK         " -ForegroundColor Green
Write-Host "======================================" -ForegroundColor Green
Write-Host ""

Pause

