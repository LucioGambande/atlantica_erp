<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. Registra envíos reales con su costo realmente
// pagado, vinculados opcionalmente a una factura de venta, un pedido o una
// compra (para poder calcular el margen de contribución logística de esa
// operación). Los FKs hacia invoices/orders/purchase_invoices usan
// nullOnDelete() (igual patrón que invoices.order_id): un envío no debe
// bloquear el borrado de la factura/pedido/compra a la que estuvo asociado.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_provider_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('logistics_rate_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('logistics_rate_version_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('purchase_invoice_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('origin_location_id')
                ->nullable()
                ->constrained('logistics_locations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('destination_location_id')
                ->nullable()
                ->constrained('logistics_locations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 3)->nullable();
            $table->decimal('calculated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2);
            $table->string('currency', 3);
            $table->date('shipped_at');
            $table->text('notes')->nullable();
            $table->string('source_document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index('order_id');
            $table->index('purchase_invoice_id');
            $table->index(['logistics_provider_id', 'shipped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_shipments');
    }
};
