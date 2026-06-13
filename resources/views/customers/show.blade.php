@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ $customer->name }}</h1>
                <p class="text-sm text-slate-500">Customer details</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('customers.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                    Back
                </a>
                <a href="{{ route('customers.edit', $customer) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                    Edit
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500">Name</dt>
                        <dd>{{ $customer->name }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Tax ID</dt>
                        <dd>{{ $customer->tax_id ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Email</dt>
                        <dd>{{ $customer->email ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Phone</dt>
                        <dd>{{ $customer->phone ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Address</dt>
                        <dd>{{ $customer->address ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500">Customer Type</dt>
                        <dd class="capitalize">{{ $customer->customer_type }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Credit Limit</dt>
                        <dd>{{ number_format($customer->credit_limit, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Balance</dt>
                        <dd>{{ number_format($customer->balance, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Created At</dt>
                        <dd>{{ $customer->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
