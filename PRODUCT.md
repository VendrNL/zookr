# Zookr — Product Document

## 1. Productvisie

Zookr is een professioneel B2B-platform voor vastgoedmakelaars waarmee zoekprofielen centraal worden vastgelegd en gedeeld. Het platform faciliteert samenwerking tussen makelaars om sneller en gerichter passend commercieel vastgoed te matchen.

Zookr is nadrukkelijk géén consumentenplatform en richt zich uitsluitend op zakelijke gebruikers.

---

## 2. Kernconcepten

### Search Request

Een Search Request is een formeel zoekprofiel dat een makelaar aanmaakt namens een cliënt of organisatie.

Een Search Request bevat:

-   Titel
-   Omschrijving
-   Locatie
-   Budget (min/max)
-   Gewenste beslisdatum
-   Status
-   Aangemaakt door
-   Toegewezen aan (optioneel)

---

## 3. Gebruikers en rollen

### Gebruiker

-   Kan eigen zoekaanvragen aanmaken
-   Ziet eigen en toegewezen aanvragen
-   Kan aanvragen bijwerken indien bevoegd

### Beheerder (admin)

-   Ziet alle zoekaanvragen
-   Kan aanvragen toewijzen
-   Kan status aanpassen
-   Functioneert als procesregisseur

---

## 4. Autorisatie en beveiliging

-   Authenticatie via Laravel Breeze
-   E-mailverificatie verplicht
-   Autorisatie volledig via Policies
-   Controllers bevatten geen autorisatielogica
-   Routes gebruiken middleware:
    -   auth
    -   verified
    -   can:\*

Dit is een **policy-first, route-middleware-only** architectuur.

---

## 5. Functionele workflow

### Statusworkflow

-   open
-   in_behandeling
-   afgerond
-   geannuleerd

Statusovergangen worden server-side afgedwongen.

---

### Toewijzing

-   Alleen bevoegde gebruikers mogen toewijzen
-   Toewijzing bepaalt zichtbaarheid en verantwoordelijkheden
-   Notificaties volgen in latere fase

---

## 6. Frontend

-   Inertia + Vue 3
-   Tailwind CSS
-   Rustige, zakelijke UI
-   Geen businesslogica in frontend
-   UI toont alleen acties die backend toestaat

---

## 7. Techniek

-   Laravel 12
-   PHP 8.3
-   MySQL 8
-   Docker Compose
-   Vite (dev & build)
-   Mailpit (development)

---

## 8. Niet in scope (voor nu)

-   Reacties / aanbiedingen
-   Meerdere organisaties
-   Publieke zoekprofielen
-   API
-   Externe integraties

---

## 9. Ontwikkelprincipes

-   Eén feature per commit
-   Geen shortcuts in autorisatie
-   Eerst backend contract, dan frontend
-   Alles uitbreidbaar zonder refactor

Zookr wordt gebouwd als duurzaam platform, niet als MVP-throwaway.
