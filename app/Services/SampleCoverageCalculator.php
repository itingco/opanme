<?php

namespace App\Services;

class SampleCoverageCalculator
{
    /** @return array{target:int,completed:int,remaining:int,percentage:float} */
    public function calculate(array $targetItemCodes, array $sampledItemCodes): array
    {
        $normalize = static fn (array $values): array => array_values(array_unique(array_filter(array_map(
            static fn ($v) => strtoupper(trim((string) $v)),
            $values
        ), static fn ($v) => $v !== '')));

        $targets = $normalize($targetItemCodes);
        $sampled = array_flip($normalize($sampledItemCodes));
        $completed = count(array_filter($targets, static fn ($code) => isset($sampled[$code])));
        $target = count($targets);

        return [
            'target' => $target,
            'completed' => $completed,
            'remaining' => max(0, $target - $completed),
            'percentage' => $target === 0 ? 100.0 : round(($completed / $target) * 100, 2),
        ];
    }
}
