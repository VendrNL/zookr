$projectPath = "D:\projects\zookr"

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "  ZOOKR – Stoppen" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $projectPath

Write-Host "Lokale Vite (Windows) stoppen..." -ForegroundColor Yellow
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

Write-Host "Containers worden gestopt..." -ForegroundColor Yellow
docker compose down

Write-Host ""
Write-Host "Zookr is volledig gestopt." -ForegroundColor Green
Write-Host ""
Pause
