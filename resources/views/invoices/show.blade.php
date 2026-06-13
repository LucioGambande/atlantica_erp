@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ $invoice->invoice_number }}</h1>
                <p class="text-sm text-slate-500">Invoice details</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('invoices.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                    Back
                </a>

                @if ($invoice->status !== 'paid')
                    <form action="{{ route('payments.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                        <input type="hidden" name="amount" value="{{ $invoice->total_amount }}">
                        <input type="hidden" name="payment_method" value="manual">
                        <input type="hidden" name="paid_at" value="{{ now()->format('Y-m-d H:i:s') }}">
                        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white">
                            Mark as paid
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Customer</p>
                <p class="mt-2 text-sm">{{ $invoice->customer?->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Status</p>
                <p class="mt-2 text-sm capitalize">{{ $invoice->status }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Total</p>
                <p class="mt-2 text-sm">{{ number_format($invoice->total_amount, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <p class="text-sm font-semibold text-slate-500">Issued At</p>
                <p class="mt-2 text-sm">{{ $invoice->issued_at?->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Description</th>
                        <th class="px-4 py-3 text-left font-semibold">Quantity</th>
                        <th class="px-4 py-3 text-left font-semibold">Unit Price</th>
                        <th class="px-4 py-3 text-left font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($invoice->invoiceItems as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->description }}</td>
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
