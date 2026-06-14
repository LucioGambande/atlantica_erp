<?php

namespace Database\Seeders;

use App\Support\PaymentDetailType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['slug' => 'transferencia', 'name' => 'Transferencia bancaria', 'detail_type' => PaymentDetailType::BANK_TRANSFER, 'sort_order' => 10],
            ['slug' => 'efectivo', 'name' => 'Efectivo', 'detail_type' => PaymentDetailType::CASH, 'sort_order' => 20],
            ['slug' => 'tarjeta', 'name' => 'Tarjeta', 'detail_type' => PaymentDetailType::CARD, 'sort_order' => 30],
            ['slug' => 'bizum', 'name' => 'Bizum', 'detail_type' => PaymentDetailType::BIZUM, 'sort_order' => 40],
            ['slug' => 'cheque', 'name' => 'Cheque', 'detail_type' => PaymentDetailType::CHEQUE, 'sort_order' => 50],
            ['slug' => 'manual', 'name' => 'Manual', 'detail_type' => PaymentDetailType::GENERIC, 'sort_order' => 60],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['slug' => $method['slug']],
                [
                    'name' => $method['name'],
                    'detail_type' => $method['detail_type'],
                    'is_active' => true,
                    'sort_order' => $method['sort_order'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
