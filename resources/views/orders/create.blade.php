@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            products: {{ Illuminate\Support\Js::from(($products ?? collect())->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'sale_price' => (float) $product->sale_price,
                'stock' => $product->stock,
            ])->values()) }},
            items: [{ product_id: '', quantity: 1, unit_price: 0 }],
            addItem() {
                this.items.push({ product_id: '', quantity: 1, unit_price: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },
            syncPrice(index) {
                const product = this.products.find(product => String(product.id) === String(this.items[index].product_id));
                this.items[index].unit_price = product ? product.sale_price : 0;
            },
            total() {
                return this.items.reduce((carry, item) => {
                    return carry + ((Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
                }, 0).toFixed(2);
            }
        }"
    >
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Create Order</h1>
                <p class="text-sm text-slate-500">Create a new order and calculate totals in real time.</p>
            </div>

            <a href="{{ route('orders.index') }}" class="text-sm text-slate-600">Back to orders</a>
        </div>

        <form action="{{ route('orders.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium">Customer</label>
                        <select name="customer_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Select customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium">Status</label>
                        <select name="status" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="pending" @selected(old('status', 'pending') === 'pending')>Pending</option>
                            <option value="completed" @selected(old('status') === 'completed')>Completed</option>
                            <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Order Items</h2>
                    <button type="button" @click="addItem()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">
                        Add product
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="grid gap-4 rounded-lg border border-slate-200 p-4 md:grid-cols-12">
                            <div class="md:col-span-5">
                                <label class="mb-2 block text-sm font-medium">Product</label>
                                <select
                                    x-model="item.product_id"
                                    x-on:change="syncPrice(index)"
                                    :name="`items[${index}][product_id]`"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                >
                                    <option value="">Select product</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="product.id" x-text="`${product.name} (${product.sku})`"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium">Quantity</label>
                                <input type="number" min="1" x-model="item.quantity" :name="`items[${index}][quantity]`" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            </div>

                            <div class="md:col-span-3">
                                <label class="mb-2 block text-sm font-medium">Unit Price</label>
                                <input type="number" min="0" step="0.01" x-model="item.unit_price" :name="`items[${index}][unit_price]`" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium">Actions</label>
                                <button type="button" @click="removeItem(index)" class="w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-600">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex justify-end">
                    <div class="rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold">
                        Total: $<span x-text="total()"></span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                    Save order
                </button>
            </div>
        </form>
    </div>
@endsection
