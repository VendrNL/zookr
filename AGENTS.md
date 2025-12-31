# AGENTS.md — Zookr (Laravel 12 + Inertia/Vue) Agent Instructions

Dit document beschrijft de spelregels voor automatische code-assistenten (zoals Codex) die wijzigingen in deze repository voorstellen. Doel: voorspelbare, veilige en reviewbare wijzigingen die passen bij de bestaande architectuur.

## 1) Stack & architectuur (leading principles)

**Backend**

-   Laravel 12 (PHP 8.3)
-   MySQL 8
-   Auth: Laravel Breeze (Inertia/Vue)
-   E-mail: Mailpit (dev)

**Frontend**

-   Inertia.js + Vue 3
-   Vite
-   Tailwind CSS

**Architectuurprincipes**

-   _Policy-first_: autorisatie via Policies (per model).
-   _Route-middleware-only_: gebruik `->middleware('can:ability,model')` of route-groups; vermijd controller-constructors met middleware.
-   Controllers zijn dun: orchestration + response. Business rules in Policies/Models/Services (waar passend).
-   Validatie bij voorkeur via Form Requests als het complexer wordt; anders inline `$request->validate()` is acceptabel.
-   Geen “grote refactors” zonder expliciete opdracht.

## 2) Repository-structuur (waar wat hoort)

**Routes**

-   `routes/web.php` (web, auth, verified, can:\* middleware)
-   `routes/api.php` (alleen als expliciet nodig)

**Controllers**

-   `app/Http/Controllers/*`
-   Controllers bevatten geen `authorizeResource()` tenzij expliciet gevraagd; autorisatie loopt via Policies + route middleware.

**Policies**

-   `app/Policies/*`
-   Registratie in `app/Providers/AuthServiceProvider.php` (indien aanwezig) of via auto-discovery, afhankelijk van setup.
-   Policy methods volgen Laravel conventies: `viewAny`, `view`, `create`, `update`, `delete`, plus custom abilities (bijv. `assign`, `setStatus`) als nodig.

**Models**

-   `app/Models/*`
-   Relaties en casts in model; geen controller-logica in models.

**Inertia Pages**

-   `resources/js/Pages/*`
    -   `resources/js/Pages/SearchRequests/*` voor Search Requests UI
-   Gebruik route-names vanuit backend (Ziggy indien aanwezig, anders expliciet via strings).

**Components**

-   `resources/js/Components/*` (herbruikbare UI-componenten)

## 3) Docker & lokale dev omgeving (niet onnodig aanpassen)

-   Docker Compose services: `app`, `web`, `db`, `vite`, `mailpit`
-   **Wijzig Dockerfile/docker-compose/nginx config alleen als expliciet gevraagd.**
-   Vite dev server draait via `vite` service; productie build via `npm run build`.

## 4) Logging, storage & permissions

-   Laravel log: `storage/logs/laravel.log`
-   De repo gebruikt named volumes voor `storage` en `bootstrap/cache`.
-   **Wijzig geen permissie/ownership scripts** tenzij expliciet gevraagd; liever oplossen via compose/Dockerfile in een gerichte fix.

## 5) Coding standards

**PHP**

-   PSR-12; type hints waar zinvol.
-   Gebruik `use` statements netjes; geen ongebruikte imports.
-   Houd methods kort en leesbaar; splits bij complexiteit.

**Vue**

-   Vue 3 SFC (`<script setup>` waar passend).
-   Geen extra dependencies toevoegen zonder expliciete toestemming.
-   Houd styling Tailwind-based; geen custom CSS tenzij noodzakelijk.

**Database**

-   Schema wijzigingen via migrations.
-   Geen handmatige SQL dumps als onderdeel van de oplossing, tenzij expliciet gevraagd.

## 6) Security & datakwaliteit

-   Vertrouw nooit client input; altijd valideren en autoriseren.
-   Gebruik Policies voor toegangscontrole.
-   Geen debug output / `dd()` / `dump()` in commitwaardige code.

## 7) “Search Requests” module — afspraken

**Model:** `App\Models\SearchRequest`

**Routes (namen)**

-   `search-requests.index`
-   `search-requests.create`
-   `search-requests.store`
-   `search-requests.show`
-   `search-requests.edit`
-   `search-requests.update`
-   `search-requests.assign` (PATCH)
-   `search-requests.status` (PATCH)

**Autorisatie**

-   Index: `can:viewAny,App\Models\SearchRequest`
-   Show: `can:view,search_request`
-   Create/Store: `can:create,App\Models\SearchRequest`
-   Edit/Update: `can:update,search_request`
-   Assign: `can:assign,search_request` (custom ability)
-   Status: `can:update,search_request` of `can:setStatus,search_request` (kies één en blijf consistent)

**UI**

-   Inertia pages in `resources/js/Pages/SearchRequests/*`
-   Gebruik props uit backend: `items`, `filters`, `can`
-   Simpel en zakelijk UI met Tailwind; focus op functionaliteit.

## 8) Change management (hoe bijdragen)

-   Werk in kleine, reviewbare commits.
-   Elke wijziging bevat:
    -   korte toelichting (waarom)
    -   duidelijke scope (wat)
    -   instructies om te testen (hoe)

**Minimale checks vóór commit**

-   `php artisan route:list` (relevante routes aanwezig)
-   `npm run build` (Vite compileert en `public/build/manifest.json` wordt gemaakt)
-   `php artisan test` (als tests aanwezig zijn)

## 9) Output formaat voor agenten (Codex/AI)

Als je als agent wijzigingen voorstelt:

-   Lever bij voorkeur een **diff/patch** per bestand.
-   Noem expliciet:
    -   welke bestanden wijzigen
    -   welke routes/policies geraakt worden
    -   welke commands ik moet draaien om te valideren

Einde.
