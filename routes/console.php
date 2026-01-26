<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Services\Funda\ScrapeFundaBusinessService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('organizations:import {path?}', function () {
    $path = $this->argument('path') ?? base_path('docs/Makelaars.csv');

    if (! file_exists($path)) {
        $this->error("CSV bestand niet gevonden: {$path}");
        return 1;
    }

    $handle = fopen($path, 'r');
    if (! $handle) {
        $this->error("CSV bestand kan niet worden geopend: {$path}");
        return 1;
    }

    $headerLine = fgets($handle);
    if ($headerLine === false) {
        fclose($handle);
        $this->error('CSV bestand is leeg.');
        return 1;
    }

    $delimiter = collect([",", ";", "\t", "|"])
        ->mapWithKeys(fn ($char) => [$char => substr_count($headerLine, $char)])
        ->sortDesc()
        ->keys()
        ->first();

    $stripBom = static fn (string $value): string => ltrim($value, "\xEF\xBB\xBF");

    $headers = str_getcsv($stripBom($headerLine), $delimiter);
    $headers = array_map(static fn ($header) => strtolower(trim($header)), $headers);

    $required = [
        'organization_name' => null,
        'organization_phone' => null,
        'organization_email' => null,
        'organization_website' => null,
    ];

    foreach ($headers as $index => $header) {
        if (array_key_exists($header, $required)) {
            $required[$header] = $index;
        }
    }

    if ($required['organization_name'] === null) {
        fclose($handle);
        $this->error('Kolom "Organization_name" ontbreekt in de CSV.');
        return 1;
    }

    $created = 0;
    $skipped = 0;
    $rows = 0;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows += 1;

        $name = trim((string) ($row[$required['organization_name']] ?? ''));
        if ($name === '') {
            continue;
        }

        if (Organization::where('name', $name)->exists()) {
            $skipped += 1;
            continue;
        }

        $phone = trim((string) ($row[$required['organization_phone']] ?? '')) ?: null;
        $email = trim((string) ($row[$required['organization_email']] ?? '')) ?: null;
        $website = trim((string) ($row[$required['organization_website']] ?? '')) ?: null;

        Organization::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'website' => $website,
            'is_active' => true,
        ]);

        $created += 1;
    }

    fclose($handle);

    $this->info("Rijen gelezen: {$rows}");
    $this->info("Makelaars aangemaakt: {$created}");
    $this->info("Bestaande Makelaars overgeslagen: {$skipped}");

    return 0;
})->purpose('Importeer makelaars uit CSV en sla bestaande Makelaars over.');

Artisan::command('zookr:import-funda-business {url} {--organization_id=} {--user_id=} {--contact_user_id=} {--search_request_id=} {--dry-run}', function () {
    $authUser = Auth::user();
    $organizationId = $this->option('organization_id')
        ?? ($authUser?->organization_id)
        ?? env('FUNDA_ORGANIZATION_ID');
    $userId = $this->option('user_id')
        ?? ($authUser?->id)
        ?? env('FUNDA_USER_ID');
    $contactUserId = $this->option('contact_user_id') ?? env('FUNDA_CONTACT_USER_ID');
    $searchRequestId = $this->option('search_request_id') ?? env('FUNDA_SEARCH_REQUEST_ID');

    if (! $organizationId || ! $userId) {
        $this->error('organization_id en user_id zijn verplicht (via actieve gebruiker, option of env).');
        return 1;
    }

    $service = app(ScrapeFundaBusinessService::class);
    $result = $service->import($this->argument('url'), [
        'organization_id' => (int) $organizationId,
        'user_id' => (int) $userId,
        'contact_user_id' => $contactUserId ? (int) $contactUserId : null,
        'search_request_id' => $searchRequestId ? (int) $searchRequestId : null,
    ], (bool) $this->option('dry-run'));

    $payload = $result['payload'];

    if ($this->option('dry-run')) {
        $this->info('Dry run: payload prepared but not saved.');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }

    $property = $result['property'];
    if ($property) {
        $this->info("Property opgeslagen: ID {$property->id}");
    }

    return 0;
})->purpose('Scrape en importeer Funda in Business objectdetails naar Properties.');

