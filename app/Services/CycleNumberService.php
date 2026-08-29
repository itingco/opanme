<?php

namespace App\Services;

use Carbon\CarbonInterface;
use App\Models\StockOpnameCycle;

class CycleNumberService
{
    public function format(CarbonInterface $date, int $sequence): string
    {
        return sprintf('SO-%s-%03d', $date->format('Ymd'), $sequence);
    }

    public function next(CarbonInterface $date): string
    {
        $prefix = 'SO-'.$date->format('Ymd').'-';
        $last = StockOpnameCycle::query()
            ->where('cycle_no', 'like', $prefix.'%')
            ->orderByDesc('cycle_no')
            ->value('cycle_no');

        $sequence = $last ? ((int) substr((string) $last, -3)) + 1 : 1;

        return $this->format($date, $sequence);
    }
}
