<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ClientBalanceWidget extends Widget
{
    protected static string $view = 'filament.resources.customer-resource.widgets.client-balance-widget';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function getCustomer(): ?Customer
    {
        return $this->record instanceof Customer ? $this->record : null;
    }

    public function getStatementUrl(): ?string
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return null;
        }

        return CustomerResource::getUrl('statement', ['record' => $customer->getKey()]);
    }

    public function getLastInvoice(): ?Invoice
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return null;
        }

        return $customer->invoices()
            ->whereIn('status', ['issued', 'paid'])
            ->where('document_type', 'invoice')
            ->orderByDesc('issued_at')
            ->first();
    }

    public function getLastPayment(): ?Payment
    {
        $customer = $this->getCustomer();

        if ($customer === null) {
            return null;
        }

        return $customer->payments()
            ->orderByDesc('paid_at')
            ->first();
    }
}
