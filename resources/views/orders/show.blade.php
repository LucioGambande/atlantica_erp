@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Order #{{ $order->id }}</h1>
                <p class="text-sm text-slate-500">Order details</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('orders.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                    Back
                </a>
                <form action="{{ route('invoices.create-from-order', $order->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                        Generate invoice
                    </button>
                </form>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Customer</p>
                <p class="mt-2 text-sm">{{ $order->customer?->name }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Status</p>
                <p class="mt-2 text-sm capitalize">{{ $order->status }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Total Amount</p>
                <p class="mt-2 text-sm">{{ number_format($order->total_amount, 2) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Product</th>
                        <th class="px-4 py-3 text-left font-semibold">Quantity</th>
                        <th class="px-4 py-3 text-left font-semibold">Unit Price</th>
                        <th class="px-4 py-3 text-left font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($order->orderItems as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->product?->name }}</td>
                            <td class="px-4 py-3">{{ $item->quantity }}</td>
                            <td class="px-4 py-3">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
