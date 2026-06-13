@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Products</h1>
                <p class="text-sm text-slate-500">Product catalog and stock.</p>
            </div>

            <a href="{{ route('products.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                Create product
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Name</th>
                        <th class="px-4 py-3 text-left font-semibold">SKU</th>
                        <th class="px-4 py-3 text-left font-semibold">Purchase Price</th>
                        <th class="px-4 py-3 text-left font-semibold">Sale Price</th>
                        <th class="px-4 py-3 text-left font-semibold">Stock</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-4 py-3">{{ $product->name }}</td>
                            <td class="px-4 py-3">{{ $product->sku }}</td>
                            <td class="px-4 py-3">{{ number_format($product->purchase_price, 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($product->sale_price, 2) }}</td>
                            <td class="px-4 py-3">{{ $product->stock }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('products.show', $product) }}" class="text-slate-600">View</a>
                                    <a href="{{ route('products.edit', $product) }}" class="text-blue-600">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
