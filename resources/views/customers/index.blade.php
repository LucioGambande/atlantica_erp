@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Customers</h1>
                <p class="text-sm text-slate-500">Internal customer list.</p>
            </div>

            <a href="{{ route('customers.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                Create customer
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Name</th>
                        <th class="px-4 py-3 text-left font-semibold">Type</th>
                        <th class="px-4 py-3 text-left font-semibold">Tax ID</th>
                        <th class="px-4 py-3 text-left font-semibold">Email</th>
                        <th class="px-4 py-3 text-left font-semibold">Phone</th>
                        <th class="px-4 py-3 text-left font-semibold">Credit Limit</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="px-4 py-3">{{ $customer->name }}</td>
                            <td class="px-4 py-3 capitalize">{{ $customer->customer_type }}</td>
                            <td class="px-4 py-3">{{ $customer->tax_id }}</td>
                            <td class="px-4 py-3">{{ $customer->email }}</td>
                            <td class="px-4 py-3">{{ $customer->phone }}</td>
                            <td class="px-4 py-3">{{ number_format($customer->credit_limit, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('customers.show', $customer) }}" class="text-slate-600">View</a>
                                    <a href="{{ route('customers.edit', $customer) }}" class="text-blue-600">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
