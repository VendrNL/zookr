param(
    [switch]$Pull
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($Pull) {
    git pull
}

docker compose up -d app web db mailpit vite

Start-Process "http://localhost"
Start-Process "http://localhost:8025"
