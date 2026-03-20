<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::with('stock')->where('is_active', true)->get();
        $users    = User::all();

        if ($products->isEmpty() || $users->isEmpty()) {
            $this->command->error('Jalankan DatabaseSeeder terlebih dahulu!');
            return;
        }

        // Isi ulang stok semua produk supaya cukup untuk transaksi
        $stockMap = [];
        foreach ($products as $product) {
            $stock = $product->stock ?? Stock::create(['product_id' => $product->id, 'quantity' => 0]);
            $stock->update(['quantity' => 999]);
            $stockMap[$product->id] = 999;
        }

        $paymentWeights = [
            'cash'     => 50,   // 50%
            'qris'     => 35,   // 35%
            'transfer' => 15,   // 15%
        ];

        $invoiceCounter  = [];
        $totalCreated    = 0;

        // Generate 90 hari terakhir (kecuali hari ini)
        $startDate = Carbon::today()->subDays(90);
        $endDate   = Carbon::yesterday();

        $this->command->info("Membuat transaksi dari {$startDate->format('d M Y')} s/d {$endDate->format('d M Y')}...");

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr    = $date->format('Y-m-d');
            $isWeekend  = $date->isWeekend();
            $isMidMonth = $date->day >= 14 && $date->day <= 16; // gajian mid-month
            $isEndMonth = $date->day >= 28;                      // gajian akhir bulan

            // Jumlah transaksi per hari — lebih ramai akhir pekan dan tanggal gajian
            $numTrx = match (true) {
                $isWeekend && ($isMidMonth || $isEndMonth) => rand(18, 25),
                $isWeekend   => rand(12, 18),
                $isMidMonth || $isEndMonth => rand(10, 15),
                default      => rand(4, 10),
            };

            $invoiceCounter[$dateStr] = 0;

            for ($t = 0; $t < $numTrx; $t++) {
                $invoiceCounter[$dateStr]++;
                $seq       = str_pad($invoiceCounter[$dateStr], 3, '0', STR_PAD_LEFT);
                $invoiceNo = 'INV-' . $date->format('Ymd') . '-' . $seq;

                $user          = $users->random();
                $paymentMethod = $this->weightedRandom($paymentWeights);

                // 1–4 produk per transaksi, kuantitas lebih tinggi di produk murah
                $numItems       = rand(1, min(4, $products->count()));
                $pickedProducts = $products->shuffle()->take($numItems);

                $items    = [];
                $subtotal = 0;

                foreach ($pickedProducts as $product) {
                    // Produk murah dibeli lebih banyak
                    $maxQty = $product->selling_price < 20000 ? 6 : 3;
                    $qty    = rand(1, $maxQty);

                    // Pastikan stok cukup
                    if ($stockMap[$product->id] < $qty) {
                        $qty = max(1, $stockMap[$product->id]);
                    }
                    if ($qty <= 0) continue;

                    $price        = (float) $product->selling_price;
                    $itemSubtotal = $qty * $price;

                    $items[]   = compact('product', 'qty', 'price', 'itemSubtotal');
                    $subtotal += $itemSubtotal;
                }

                if (empty($items)) continue;

                // Diskon: 25% peluang, kelipatan Rp 500, maks 15% dari subtotal
                $discount = 0;
                if (rand(1, 100) <= 25) {
                    $maxDisc  = (int) ($subtotal * 0.15);
                    $discount = round(rand(500, max(500, $maxDisc)) / 500) * 500;
                }

                $total  = max(0, $subtotal - $discount);
                $paid   = $paymentMethod === 'cash'
                    ? (ceil($total / 1000) * 1000)
                    : $total;
                $change = $paid - $total;

                // ~4% transaksi di-void
                $isVoided   = rand(1, 100) <= 4;
                $voidedAt   = $isVoided ? $date->copy()->setTime(rand(8, 21), rand(0, 59)) : null;
                $voidReason = $isVoided ? $this->randomVoidReason() : null;

                $sale = Sale::create([
                    'invoice_no'     => $invoiceNo,
                    'user_id'        => $user->id,
                    'date'           => $dateStr,
                    'subtotal'       => $subtotal,
                    'discount'       => $discount,
                    'total'          => $total,
                    'paid'           => $paid,
                    'change'         => $change,
                    'payment_method' => $paymentMethod,
                    'notes'          => null,
                    'voided_at'      => $voidedAt,
                    'voided_by'      => $isVoided ? 1 : null,
                    'void_reason'    => $voidReason,
                ]);

                foreach ($items as $item) {
                    $product = $item['product'];

                    SaleItem::create([
                        'sale_id'    => $sale->id,
                        'product_id' => $product->id,
                        'quantity'   => $item['qty'],
                        'price'      => $item['price'],
                        'subtotal'   => $item['itemSubtotal'],
                    ]);

                    // Stok hanya berkurang untuk transaksi yang tidak di-void
                    if (!$isVoided) {
                        $before = $stockMap[$product->id];
                        $after  = max(0, $before - $item['qty']);

                        $stockMap[$product->id] = $after;

                        Stock::where('product_id', $product->id)
                            ->update(['quantity' => $after]);

                        StockMutation::create([
                            'product_id'      => $product->id,
                            'user_id'         => $user->id,
                            'type'            => 'sale',
                            'reference_id'    => $sale->id,
                            'reference_type'  => 'sale',
                            'quantity_change'  => -$item['qty'],
                            'quantity_before' => $before,
                            'quantity_after'  => $after,
                            'notes'           => "Penjualan #{$invoiceNo}",
                        ]);

                        // Kalau stok di bawah 50, isi ulang (simulasi stok masuk)
                        if ($after < 50) {
                            $refill                  = 200;
                            $stockMap[$product->id] += $refill;
                            Stock::where('product_id', $product->id)
                                ->update(['quantity' => $stockMap[$product->id]]);
                        }
                    }
                }

                $totalCreated++;
            }
        }

        $this->command->info("✓ Selesai: {$totalCreated} transaksi berhasil dibuat.");
    }

    private function weightedRandom(array $weights): string
    {
        $rand  = rand(1, array_sum($weights));
        $total = 0;
        foreach ($weights as $key => $weight) {
            $total += $weight;
            if ($rand <= $total) return $key;
        }
        return array_key_first($weights);
    }

    private function randomVoidReason(): string
    {
        return collect([
            'Salah input produk',
            'Pembatalan oleh pelanggan',
            'Stok tidak tersedia',
            'Kesalahan kasir',
            'Pelanggan batal beli',
        ])->random();
    }
}
