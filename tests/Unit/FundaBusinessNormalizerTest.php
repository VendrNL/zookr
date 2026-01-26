<?php

namespace Tests\Unit;

use App\Services\Funda\FundaBusinessNormalizer;
use PHPUnit\Framework\TestCase;

class FundaBusinessNormalizerTest extends TestCase
{
    public function test_parse_nl_int_handles_thousand_separator(): void
    {
        $this->assertSame(6035, FundaBusinessNormalizer::parseNlInt('6.035 m2'));
    }

    public function test_parse_money_handles_euro_and_thousand_separator(): void
    {
        $this->assertSame(2500.0, FundaBusinessNormalizer::parseMoney('€ 2.500,- per jaar'));
    }

    public function test_slugify_normalizes_text(): void
    {
        $this->assertSame('per_vierkante_meter', FundaBusinessNormalizer::slugify('Per vierkante meter'));
    }

    public function test_parse_service_costs_detects_per_m2_per_year(): void
    {
        $value = '€ 60 per vierkante meter per jaar (21% BTW)';
        $this->assertSame(60.0, FundaBusinessNormalizer::parseServiceCosts($value));
    }
}
