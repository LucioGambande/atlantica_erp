<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CustomerAccountsReport;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ErpAuthorization;
use App\Support\InvoicePrintAuthorization;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class DashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $monthLabel = Carbon::now()->translatedFormat('F Y');

        $collectedThisMonth = (float) Payment::query()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $invoicedThisMonth = (float) Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->whereBetween('issued_at', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $customersWithDebt = Customer::query()->withDebt()->count();

        $customersOverCreditLimit = Customer::query()
            ->where('credit_limit', '>', 0)
            ->whereColumn('balance', '>', 'credit_limit')
            ->count();

        return [
            Stat::make('Cobrado este mes', Number::currency($collectedThisMonth, 'EUR'))
                ->description($monthLabel)
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->url(ErpAuthorization::userCan('manage invoices') ? PaymentResource::getUrl('index') : null),
            Stat::make('Facturado este mes', Number::currency($invoicedThisMonth, 'EUR'))
                ->description($monthLabel)
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->url(InvoicePrintAuthorization::canPrint() ? InvoiceResource::getUrl('index') : null),
            Stat::make('Clientes con deuda', (string) $customersWithDebt)
                ->description('Saldo positivo (riesgo)')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($customersWithDebt > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-users')
                ->url(ErpAuthorization::userCan('manage customers') ? CustomerAccountsReport::getUrl() : null),
            Stat::make('Sobre límite de crédito', (string) $customersOverCreditLimit)
                ->description('Balance > límite asignado')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($customersOverCreditLimit > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-shield-exclamation')
                ->url(ErpAuthorization::userCan('manage customers') ? CustomerResource::getUrl('index') : null),
        ];
    }
}
