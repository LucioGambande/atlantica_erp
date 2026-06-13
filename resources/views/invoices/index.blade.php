@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Invoices</h1>
            <p class="text-sm text-slate-500">Issued invoices and payment status.</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Invoice Number</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Total</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Issued At</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3">{{ $invoice->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $invoice->customer?->name }}</td>
                            <td class="px-4 py-3">{{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $invoice->status }}</td>
                            <td class="px-4 py-3">{{ $invoice->issued_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600">View</a>

                                    @if ($invoice->status !== 'paid')
                                        <form action="{{ route('payments.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                            <input type="hidden" name="amount" value="{{ $invoice->total_amount }}">
                                            <input type="hidden" name="payment_method" value="manual">
                                            <input type="hidden" name="paid_at" value="{{ now()->format('Y-m-d H:i:s') }}">
                                            <button type="submit" class="text-green-600">Mark as paid</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
