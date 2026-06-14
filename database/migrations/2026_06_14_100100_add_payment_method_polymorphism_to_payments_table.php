<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{name: string, detail_type: string, sort_order: int}>
     */
    protected array $defaultMethods = [
        'transferencia' => ['name' => 'Transferencia bancaria', 'detail_type' => 'bank_transfer', 'sort_order' => 10],
        'efectivo' => ['name' => 'Efectivo', 'detail_type' => 'cash', 'sort_order' => 20],
        'tarjeta' => ['name' => 'Tarjeta', 'detail_type' => 'card', 'sort_order' => 30],
        'bizum' => ['name' => 'Bizum', 'detail_type' => 'bizum', 'sort_order' => 40],
        'cheque' => ['name' => 'Cheque', 'detail_type' => 'cheque', 'sort_order' => 50],
        'manual' => ['name' => 'Manual', 'detail_type' => 'generic', 'sort_order' => 60],
    ];

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->nullableMorphs('detail');
        });

        $slugToId = [];

        foreach ($this->defaultMethods as $slug => $method) {
            $slugToId[$slug] = DB::table('payment_methods')->insertGetId([
                'name' => $method['name'],
                'slug' => $slug,
                'detail_type' => $method['detail_type'],
                'is_active' => true,
                'sort_order' => $method['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $payments = DB::table('payments')->get();

        foreach ($payments as $payment) {
            $legacySlug = $payment->payment_method ?: 'manual';
            $methodId = $slugToId[$legacySlug] ?? $slugToId['manual'];

            $detail = $this->createLegacyDetail($legacySlug, $payment);

            DB::table('payments')->where('id', $payment->id)->update([
                'payment_method_id' => $methodId,
                'detail_type' => $detail['type'],
                'detail_id' => $detail['id'],
            ]);
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }

    /**
     * @return array{type: string, id: int}
     */
    protected function createLegacyDetail(string $legacySlug, object $payment): array
    {
        $detailType = $this->defaultMethods[$legacySlug]['detail_type'] ?? 'generic';
        $now = now();

        if ($detailType === 'bank_transfer') {
            $id = DB::table('bank_transfer_payment_details')->insertGetId([
                'transaction_number' => 'LEGACY-'.$payment->id,
                'bank_reference' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['type' => 'bank_transfer', 'id' => $id];
        }

        if ($detailType === 'card') {
            $id = DB::table('card_payment_details')->insertGetId([
                'authorization_code' => null,
                'card_last_four' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['type' => 'card', 'id' => $id];
        }

        if ($detailType === 'cash') {
            $id = DB::table('cash_payment_details')->insertGetId([
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['type' => 'cash', 'id' => $id];
        }

        if ($detailType === 'bizum') {
            $id = DB::table('bizum_payment_details')->insertGetId([
                'operation_code' => null,
                'phone' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['type' => 'bizum', 'id' => $id];
        }

        if ($detailType === 'cheque') {
            $id = DB::table('cheque_payment_details')->insertGetId([
                'cheque_number' => 'LEGACY-'.$payment->id,
                'bank_name' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['type' => 'cheque', 'id' => $id];
        }

        $id = DB::table('generic_payment_details')->insertGetId([
            'notes' => 'Migrado desde payment_method='.$legacySlug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['type' => 'generic', 'id' => $id];
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('payment_method')->nullable()->after('amount');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropMorphs('detail');
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
