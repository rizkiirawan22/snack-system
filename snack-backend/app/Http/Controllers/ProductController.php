<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'stock'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')));

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'code'           => 'required|string|unique:products,code',
            'name'           => 'required|string|max:255',
            'unit'           => 'required|string',
            'weight'         => 'required|integer|min:1',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'min_stock'      => 'required|integer|min:0',
            'description'    => 'nullable|string',
            'image'          => 'sometimes|nullable|mimetypes:image/jpeg,image/png,image/gif,image/bmp,image/webp|max:2048',
            'is_active'      => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        // Buat record stok awal
        $product->stock()->create(['quantity' => 0]);

        return response()->json($product->load('category', 'stock'), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('category', 'stock'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'code'           => "required|string|unique:products,code,{$product->id}",
            'name'           => 'required|string|max:255',
            'unit'           => 'required|string',
            'weight'         => 'required|integer|min:1',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'min_stock'      => 'required|integer|min:0',
            'description'    => 'nullable|string',
            'image'          => 'sometimes|nullable|mimetypes:image/jpeg,image/png,image/gif,image/bmp,image/webp|max:2048',
            'is_active'      => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json($product->load('category', 'stock'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Produk dihapus.']);
    }

    public function generateCode()
    {
        $codes = Product::withTrashed()->pluck('code');
        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^PRD-(\d+)$/', $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return response()->json(['code' => sprintf('PRD-%03d', $max + 1)]);
    }

    public function lowStock()
    {
        $products = Product::with(['category', 'stock'])
            ->whereHas('stock', fn($q) => $q->whereColumn('quantity', '<=', 'products.min_stock'))
            ->where('is_active', true)
            ->get();

        return response()->json($products);
    }
}
