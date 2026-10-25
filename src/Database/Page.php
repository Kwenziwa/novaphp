<?php

declare(strict_types=1);

namespace Nova\Database;

final class Page implements \JsonSerializable
{
    public function __construct(public readonly array $items, public readonly int $total,
        public readonly int $perPage, public readonly int $currentPage) {}

    public function jsonSerialize(): array
    {
        $start = ($this->currentPage - 1) * $this->perPage;
        return ['data' => $this->items, 'meta' => [
            'total' => $this->total, 'per_page' => $this->perPage, 'current_page' => $this->currentPage,
            'last_page' => max(1, (int) ceil($this->total / $this->perPage)),
            'from' => $this->items === [] ? null : $start + 1,
            'to' => $this->items === [] ? null : $start + count($this->items),
        ]];
    }
}
