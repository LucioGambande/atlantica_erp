<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentDetails\BankTransferPaymentDetail;
use App\Models\PaymentDetails\BizumPaymentDetail;
use App\Models\PaymentDetails\CardPaymentDetail;
use App\Models\PaymentDetails\CashPaymentDetail;
use App\Models\PaymentDetails\ChequePaymentDetail;
use App\Models\PaymentDetails\GenericPaymentDetail;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Support\PaymentDetailType;
use Carbon\Carbon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Relation::morphMap([
            PaymentDetailType::BANK_TRANSFER => BankTransferPaymentDetail::class,
            PaymentDetailType::CARD => CardPaymentDetail::class,
            PaymentDetailType::CASH => CashPaymentDetail::class,
            PaymentDetailType::BIZUM => BizumPaymentDetail::class,
            PaymentDetailType::CHEQUE => ChequePaymentDetail::class,
            PaymentDetailType::GENERIC => GenericPaymentDetail::class,
        ]);

        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        Table::configureUsing(function (Table $table): void {
            $table
                ->filtersLayout(FiltersLayout::AboveContentCollapsible)
                ->filtersFormColumns([
                    'default' => 1,
                    'sm' => 2,
                    'lg' => 4,
                ])
                ->persistFiltersInSession()
                ->deferFilters(false)
                ->striped();
        });
    }
}
