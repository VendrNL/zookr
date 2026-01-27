# Dev workflow (Vite)

Deze repo draait met Docker en een **Vite service**. Om cache‑problemen en “oude JS” te voorkomen:

## Kies één Vite‑bron

Gebruik **alleen** de Docker‑Vite:

```bash
docker compose exec vite npm run dev
```

**Start geen lokale** `npm run dev` op Windows.

## Start/stop scripts

Gebruik bij voorkeur de scripts, die lokale Vite‑processen automatisch opruimen:

```bash
.\start-zookr.ps1
.\stop-zookr.ps1
```

## Na front‑end wijzigingen

1) Hard refresh (Ctrl+F5) in de browser  
2) Als je Vite om wat voor reden niet meepakt: herstart de Vite‑service

```bash
docker compose restart vite
```

## Troubleshoot

Als je een route‑error of “oude JS” ziet:

```bash
del /f /q public\hot
docker compose restart vite
```

Daarna: hard refresh.
