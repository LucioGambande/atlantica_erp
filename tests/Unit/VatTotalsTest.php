<?php

namespace Tests\Unit;

use App\Support\VatTotals;
use Tests\TestCase;

class VatTotalsTest extends TestCase
{
    public function test_breakdown_returns_net_vat_and_gross(): void
    {
        $breakdown = VatTotals::breakdown(100.0);

        $this->assertSame(100.0, $breakdown['net']);
        $this->assertSame(21.0, $breakdown['vat']);
        $this->assertSame(121.0, $breakdown['gross']);
    }

    public function test_breakdown_clamps_negative_net_to_zero(): void
    {
        $breakdown = VatTotals::breakdown(-50.0);

        $this->assertSame(0.0, $breakdown['net']);
        $this->assertSame(0.0, $breakdown['vat']);
        $this->assertSame(0.0, $breakdown['gross']);
    }
}
