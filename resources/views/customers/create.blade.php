@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Create Customer</h1>
            <a href="{{ route('customers.index') }}" class="text-sm text-slate-600">Back to customers</a>
        </div>

        <form action="{{ route('customers.store') }}" method="POST" class="rounded-xl border border-slate-200 bg-white p-6">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Tax ID</label>
                    <input type="text" name="tax_id" value="{{ old('tax_id') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Customer Type</label>
                    <select name="customer_type" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Select type</option>
                        <option value="horeca" @selected(old('customer_type') === 'horeca')>Horeca</option>
                        <option value="individual" @selected(old('customer_type') === 'individual')>Individual</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium">Credit Limit</label>
                    <input type="number" step="0.01" min="0" name="credit_limit" value="{{ old('credit_limit', 0) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
            </div>

            <div class="mt-6">
                <label class="mb-2 block text-sm font-medium">Address</label>
                <textarea name="address" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('address') }}</textarea>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                    Save customer
                </button>
            </div>
        </form>
    </div>
@endsection
