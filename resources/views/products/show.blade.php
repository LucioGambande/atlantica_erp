@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                <p class="text-sm text-slate-500">Product details</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('products.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                    Back
                </a>
                <a href="{{ route('products.edit', $product) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                    Edit
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <dl class="grid gap-6 text-sm md:grid-cols-2">
                <div>
                    <dt class="font-semibold text-slate-500">Name</dt>
                    <dd>{{ $product->name }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">SKU</dt>
                    <dd>{{ $product->sku }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Purchase Price</dt>
                    <dd>{{ number_format($product->purchase_price, 2) }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Sale Price</dt>
                    <dd>{{ number_format($product->sale_price, 2) }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Stock</dt>
                    <dd>{{ $product->stock }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Created At</dt>
                    <dd>{{ $product->created_at?->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
