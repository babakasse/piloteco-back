<?php

declare(strict_types=1);

namespace App\Service\Import;

final class ImportStats
{
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;

    public function incrementCreated(): void
    {
        $this->created++;
    }

    public function incrementUpdated(): void
    {
        $this->updated++;
    }

    public function incrementSkipped(): void
    {
        $this->skipped++;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getTotal(): int
    {
        return $this->created + $this->updated;
    }
}
