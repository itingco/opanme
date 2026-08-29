<?php

namespace Tests\Unit;

use App\Services\CycleNumberService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CycleNumberServiceTest extends TestCase
{
    public function test_it_formats_cycle_number_with_date_and_sequence(): void
    {
        $service = new CycleNumberService();

        $this->assertSame(
            'SO-20260829-007',
            $service->format(CarbonImmutable::parse('2026-08-29'), 7)
        );
    }
}
