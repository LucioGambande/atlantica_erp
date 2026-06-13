@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Create Product</h1>
            <a href="{{ route('products.index') }}" class="text-sm text-slate-600">Back to products</a>
        </div>

        <form action="{{ route('products.store') }}" method="POST" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">SKU</label>
                    <input type="text" name="sku" value="{{ old('sku') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Purchase Price</label>
                    <input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Sale Price</label>
                    <input type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Stock</label>
                    <input type="number" min="0" name="stock" value="{{ old('stock', 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                    Save product
                </button>
            </div>
        </form>
    </div>
@endsection
