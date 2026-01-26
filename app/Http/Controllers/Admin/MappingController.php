<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\ScrapeMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MappingController extends Controller
{
    private const DOMAINS = [
        'www.fundainbusiness.nl',
        'www.bedrijfspand.com',
        'www.huurbieding.nl',
    ];

    public function index()
    {
        $fields = $this->propertyFields();
        $mappings = ScrapeMapping::query()
            ->whereIn('domain', self::DOMAINS)
            ->whereIn('property_field', $fields)
            ->get(['domain', 'property_field', 'selector'])
            ->groupBy('domain')
            ->map(function ($domainMappings) {
                return $domainMappings
                    ->keyBy('property_field')
                    ->map(fn ($mapping) => $mapping->selector)
                    ->toArray();
            })
            ->toArray();

        return Inertia::render('Admin/Mapping/Index', [
            'fields' => $fields,
            'domains' => self::DOMAINS,
            'mappings' => $mappings,
        ]);
    }

    public function update(Request $request)
    {
        $fields = $this->propertyFields();

        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*' => ['array'],
            'mappings.*.*' => ['nullable', 'string', 'max:2048'],
        ]);

        DB::transaction(function () use ($data, $fields) {
            foreach ($data['mappings'] as $domain => $fieldMap) {
                if (! in_array($domain, self::DOMAINS, true)) {
                    continue;
                }
                foreach ($fieldMap as $field => $selector) {
                    if (! in_array($field, $fields, true)) {
                        continue;
                    }
                    $value = trim((string) $selector);
                    if ($value === '') {
                        ScrapeMapping::query()
                            ->where('domain', $domain)
                            ->where('property_field', $field)
                            ->delete();
                        continue;
                    }

                    ScrapeMapping::updateOrCreate(
                        ['domain' => $domain, 'property_field' => $field],
                        ['selector' => $value]
                    );
                }
            }
        });

        return back()->with('status', 'mapping-saved');
    }

    private function propertyFields(): array
    {
        $fields = (new Property())->getFillable();

        return array_values(array_filter($fields, function ($field) {
            return ! in_array($field, ['organization_id', 'user_id', 'search_request_id'], true);
        }));
    }
}
