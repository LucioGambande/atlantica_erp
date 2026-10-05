<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_horeca_and_particular_series_have_independent_correlatives(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Test',
            'customer_type' => 'horeca',
        ]);

        $generator = app(InvoiceNumberGenerator::class);

        $firstHoreca = $generator->next();
        $this->assertStringStartsWith('HORECA', $firstHoreca);

        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => $firstHoreca,
            'status' => 'issued',
            'total_amount' => 10,
            'issued_at' => now(),
        ]);

        $firstParticular = $generator->next($generator->particularPrefix());
        $this->assertStringStartsWith('PARTICULAR', $firstParticular);

        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => $firstParticular,
            'status' => 'issued',
            'total_amount' => 20,
            'issued_at' => now(),
        ]);

        // Crear una PARTICULAR no debe afectar el próximo número HORECA, y viceversa.
        $secondHoreca = $generator->next();
        $this->assertStringStartsWith('HORECA', $secondHoreca);
        $this->assertNotSame($firstHoreca, $secondHoreca);

        $secondParticular = $generator->next($generator->particularPrefix());
        $this->assertStringStartsWith('PARTICULAR', $secondParticular);
        $this->assertNotSame($firstParticular, $secondParticular);

        $this->assertSame(1, $generator->extractSequence($firstHoreca, $generator->patternForYear()));
        $this->assertSame(2, $generator->extractSequence($secondHoreca, $generator->patternForYear()));
        $this->assertSame(1, $generator->extractSequence($firstParticular, $generator->patternForYear($generator->particularPrefix())));
        $this->assertSame(2, $generator->extractSequence($secondParticular, $generator->patternForYear($generator->particularPrefix())));
    }

    public function test_an_internal_invoice_number_is_ignored_by_parse(): void
    {
        $generator = app(InvoiceNumberGenerator::class);

        $this->assertNull($generator->parse('INTERNO-000123'));
    }
}
