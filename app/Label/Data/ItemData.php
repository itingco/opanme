<?php

namespace App\Label\Data;

final class ItemData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
    ) {
    }

    /**
     * @return array{code: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
