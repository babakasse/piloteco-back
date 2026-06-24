<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Import\ImportStats;
use PHPUnit\Framework\TestCase;

class ImportStatsTest extends TestCase
{
    public function testInitialCountsAreZero(): void
    {
        $stats = new ImportStats();

        $this->assertSame(0, $stats->getCreated());
        $this->assertSame(0, $stats->getUpdated());
        $this->assertSame(0, $stats->getSkipped());
        $this->assertSame(0, $stats->getTotal());
    }

    public function testTotalExcludesSkipped(): void
    {
        $stats = new ImportStats();
        $stats->incrementCreated();
        $stats->incrementCreated();
        $stats->incrementUpdated();
        $stats->incrementSkipped();

        $this->assertSame(2, $stats->getCreated());
        $this->assertSame(1, $stats->getUpdated());
        $this->assertSame(1, $stats->getSkipped());
        $this->assertSame(3, $stats->getTotal()); // created + updated, not skipped
    }
}
