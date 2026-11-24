<?php

namespace App\Services;

use InvalidArgumentException;

/** Largest remainder, deterministic ties by source ID. Never invent an extra unit. */
class CombinedQtyAllocator
{
    public function allocate(float $physical, array $openingBySource): array
    {
        if (!is_finite($physical) || $physical < 0 || $physical > 999999999999) {
            throw new InvalidArgumentException('Qty fisik di luar batas alokasi.');
        }
        if (!$openingBySource) return [];
        ksort($openingBySource, SORT_NUMERIC);
        // Preserve legacy decimal quantities; ordinary whole-piece counts stay whole.
        $physical = round($physical,4);
        $scale = abs($physical - round($physical)) < 0.00001 ? 1 : 10000;
        $units = (int) round($physical * $scale);
        if ($units > 9007199254740991) throw new InvalidArgumentException('Qty terlalu besar untuk pembagian presisi.');
        $weights = array_map(fn($qty)=>max(0.0,(float)$qty),$openingBySource);
        $sum = array_sum($weights);
        $out = array_fill_keys(array_keys($weights),0);
        if ($sum <= 0) {
            $out[array_key_first($out)] = $units;
        } else {
            $fractions = []; $used = 0;
            foreach ($weights as $id=>$weight) {
                if ($weight <= 0) continue;
                $exact = $units * ($weight / $sum);
                $base = (int) floor($exact);
                $out[$id] = $base; $used += $base;
                $fractions[] = ['id'=>$id,'fraction'=>$exact-$base];
            }
            usort($fractions,fn($a,$b)=>($b['fraction'] <=> $a['fraction']) ?: ($a['id'] <=> $b['id']));
            $left = $units-$used;
            if ($left < 0 || $left > count($fractions)) throw new InvalidArgumentException('Presisi alokasi tidak dapat dijamin.');
            for ($i=0;$i<$left;$i++) $out[$fractions[$i]['id']]++;
        }
        return array_map(fn($units)=>$units/$scale,$out);
    }
}
