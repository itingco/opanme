<?php

namespace App\Services;

use App\Models\SampleCycle;
use Carbon\CarbonInterface;

class SampleCycleNumberService
{
    public function next(CarbonInterface $date): string
    {
        $prefix = 'SMP-'.$date->format('Ymd').'-';
        $last = SampleCycle::query()
            ->where('cycle_no', 'like', $prefix.'%')
            ->orderByDesc('cycle_no')
            ->value('cycle_no');

        $next = 1;
        if (is_string($last) && preg_match('/(\d{4})$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
