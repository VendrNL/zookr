<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Services\Funda\ScrapeFundaBusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FundaBusinessImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_maps_fixture_and_is_idempotent(): void
    {
        Storage::fake('public');

        $fixture = file_get_contents(base_path('tests/Fixtures/funda-business.html'));
        Http::fake(function ($request) use ($fixture) {
            if ($request->url() === 'https://www.fundainbusiness.nl/kantoor/amsterdam/object-89195754-herengracht-206-216/') {
                return Http::response($fixture, 200, ['Content-Type' => 'text/html']);
            }

            if (str_contains($request->url(), 'brochure.pdf') || str_contains($request->url(), 'drawing-1.pdf')) {
                return Http::response('PDF', 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('', 404);
        });

        $organization = Organization::create([
            'name' => 'Test Org',
            'phone' => null,
            'email' => null,
            'website' => null,
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $service = app(ScrapeFundaBusinessService::class);
        $url = 'https://www.fundainbusiness.nl/kantoor/amsterdam/object-89195754-herengracht-206-216/';

        $result = $service->import($url, [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'contact_user_id' => null,
            'search_request_id' => null,
        ], false, false);

        $property = $result['property'];
        $this->assertNotNull($property);
        $this->assertSame('Herengracht 206-216, Amsterdam', $property->name);
        $this->assertSame('Beschikbaar', $property->availability);
        $this->assertSame('In overleg', $property->acquisition);
        $this->assertSame('6035', $property->surface_area);
        $this->assertNull($property->rent_price);
        $this->assertSame(60.0, (float) $property->rent_price_per_m2);

        $notes = json_decode((string) $property->notes, true);
        $this->assertSame('fundainbusiness', $notes['source']);
        $this->assertTrue($notes['flags']['rent_on_request']);
        $this->assertSame('Grachtengordel', $notes['neighborhood']);

        $this->assertContains('https://images.example.test/2-large.jpg', $property->images);
        $this->assertNotNull($property->brochure_path);
        Storage::disk('public')->assertExists($property->brochure_path);
        $this->assertNotEmpty($property->drawings);

        $resultAgain = $service->import($url, [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'contact_user_id' => null,
            'search_request_id' => null,
        ], false, false);

        $this->assertSame($property->id, $resultAgain['property']->id);
        $this->assertSame(1, Property::where('url', $url)->count());
    }
}
