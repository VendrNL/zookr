$projectPath = "D:\projects\zookr"

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "  ZOOKR – Stoppen" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $projectPath

Write-Host "Containers worden gestopt..." -ForegroundColor Yellow
docker compose down

Write-Host ""
Write-Host "Zookr is volledig gestopt." -ForegroundColor Green
Write-Host ""
Pause
