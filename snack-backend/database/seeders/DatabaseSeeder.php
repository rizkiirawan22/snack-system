<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        User::firstOrCreate(['email' => 'admin@snack.com'], [
            'name'     => 'Admin Utama',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        User::firstOrCreate(['email' => 'pegawai@snack.com'], [
            'name'     => 'Pegawai 1',
            'password' => bcrypt('password'),
            'role'     => 'pegawai',
        ]);

        // Kategori
        $categories = [
            ['name' => 'Manis', 'description' => 'Snack dengan rasa manis'],
            ['name' => 'Asin',  'description' => 'Snack dengan rasa asin'],
            ['name' => 'Pedas', 'description' => 'Snack dengan rasa pedas'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }

        // Produk sample
        $products = [
            ['category_id' => 1, 'code' => 'MNS-001', 'name' => 'Coklat Kiloan 250gr',  'weight' => 250,  'selling_price' => 15000, 'purchase_price' => 11000],
            ['category_id' => 1, 'code' => 'MNS-002', 'name' => 'Permen Mix 500gr',      'weight' => 500,  'selling_price' => 22000, 'purchase_price' => 16000],
            ['category_id' => 2, 'code' => 'ASN-001', 'name' => 'Keripik Singkong 250gr', 'weight' => 250,  'selling_price' => 12000, 'purchase_price' => 8500],
            ['category_id' => 2, 'code' => 'ASN-002', 'name' => 'Kacang Kulit 1kg',      'weight' => 1000, 'selling_price' => 45000, 'purchase_price' => 33000],
            ['category_id' => 3, 'code' => 'PDS-001', 'name' => 'Keripik Pedas 250gr',   'weight' => 250,  'selling_price' => 13000, 'purchase_price' => 9000],
            ['category_id' => 3, 'code' => 'PDS-002', 'name' => 'Basreng 500gr',         'weight' => 500,  'selling_price' => 28000, 'purchase_price' => 20000],
        ];

        foreach ($products as $p) {
            $product = Product::firstOrCreate(['code' => $p['code']], array_merge($p, [
                'unit'      => 'gram',
                'min_stock' => 10,
            ]));

            Stock::firstOrCreate(['product_id' => $product->id], ['quantity' => 50]);
        }
    }
}
