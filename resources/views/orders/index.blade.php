@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Orders</h1>
                <p class="text-sm text-slate-500">Sales orders overview.</p>
            </div>

            <a href="{{ route('orders.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                Create order
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Total</th>
                        <th class="px-4 py-3 text-left font-semibold">Created At</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-3">#{{ $order->id }}</td>
                            <td class="px-4 py-3">{{ $order->customer?->name }}</td>
                            <td class="px-4 py-3 capitalize">{{ $order->status }}</td>
                            <td class="px-4 py-3">{{ number_format($order->total_amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('orders.show', $order) }}" class="text-blue-600">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
