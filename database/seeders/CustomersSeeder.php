<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomersSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $customers = [
            [
                'name' => 'La Cava Bistro',
                'tax_id' => '30-71234567-1',
                'email' => 'purchases@lacavabistro.com',
                'phone' => '+54 11 4123 1001',
                'address' => 'Av. Del Libertador 2450, Buenos Aires',
                'customer_type' => 'horeca',
                'credit_limit' => 250000.00,
            ],
            [
                'name' => 'Casa Roble Restaurant',
                'tax_id' => '30-72345678-2',
                'email' => 'admin@casaroble.com',
                'phone' => '+54 11 4123 1002',
                'address' => 'Armenia 1820, Buenos Aires',
                'customer_type' => 'horeca',
                'credit_limit' => 180000.00,
            ],
            [
                'name' => 'Andes Grill',
                'tax_id' => '30-73456789-3',
                'email' => 'orders@andesgrill.com',
                'phone' => '+54 261 412 3400',
                'address' => 'Sarmiento 901, Mendoza',
                'customer_type' => 'horeca',
                'credit_limit' => 220000.00,
            ],
            [
                'name' => 'Vinoteca Central',
                'tax_id' => '30-74567890-4',
                'email' => 'compras@vinotecacentral.com',
                'phone' => '+54 11 4123 1003',
                'address' => 'Av. Corrientes 3145, Buenos Aires',
                'customer_type' => 'horeca',
                'credit_limit' => 140000.00,
            ],
            [
                'name' => 'Hotel Altos del Valle',
                'tax_id' => '30-75678901-5',
                'email' => 'supply@altosdelvallehotel.com',
                'phone' => '+54 261 455 7788',
                'address' => 'Belgrano 455, Mendoza',
                'customer_type' => 'horeca',
                'credit_limit' => 300000.00,
            ],
            [
                'name' => 'Lucia Fernandez',
                'tax_id' => '27-28456789-6',
                'email' => 'lucia.fernandez@gmail.com',
                'phone' => '+54 9 11 5874 2231',
                'address' => 'Maipu 650, Buenos Aires',
                'customer_type' => 'individual',
                'credit_limit' => 20000.00,
            ],
            [
                'name' => 'Martin Suarez',
                'tax_id' => '20-31567890-7',
                'email' => 'martin.suarez@gmail.com',
                'phone' => '+54 9 261 612 9981',
                'address' => 'San Martin 1242, Mendoza',
                'customer_type' => 'individual',
                'credit_limit' => 15000.00,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['tax_id' => $customer['tax_id']],
                $customer,
            );
        }
    }
}
