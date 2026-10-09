<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->boolean('generates_stock_movement')->default(true)->after('status');
            $table->boolean('stock_movements_recorded')->default(false)->after('generates_stock_movement');
        });

        // Las compras ya recibidas NO deben sumar stock retroactivamente: su
        // mercadería ya está reflejada en products.stock (se cargó a mano).
        // Marcarlas como registradas evita que un guardado futuro las duplique.
        //
        // Las que siguen en borrador se dejan sin marcar a propósito: cuando
        // se reciban de verdad, su stock tiene que entrar por primera vez.
        DB::table('purchase_invoices')
            ->whereIn('status', ['received', 'paid'])
            ->update(['stock_movements_recorded' => true]);
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropColumn(['generates_stock_movement', 'stock_movements_recorded']);
        });
    }
};
