<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\ErpAuthorization;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use RuntimeException;

class GenerateMonthlyConsumerInvoice extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = NavigationGroups::FACTURACION;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Factura mensual Consumidor Final';

    protected static ?string $title = 'Generar factura mensual a Consumidor Final';

    protected static string $view = 'filament.pages.generate-monthly-consumer-invoice';

    protected static ?string $slug = 'factura-mensual-consumidor-final';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage invoices');
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'to' => Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Cliente "Consumidor Final"')
                    ->helperText('Elegí el cliente genérico creado para recibir la factura mensual.')
                    ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                DatePicker::make('from')
                    ->label('Desde')
                    ->required()
                    ->live(),
                DatePicker::make('to')
                    ->label('Hasta')
                    ->required()
                    ->live(),
                Placeholder::make('preview')
                    ->label('Ventas a incluir')
                    ->content(fn (Get $get): string => $this->previewLabel($get('from'), $get('to')))
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    protected function previewLabel(?string $from, ?string $to): string
    {
        if (blank($from) || blank($to)) {
            return 'Elegí un rango de fechas.';
        }

        $query = Invoice::query()
            ->where('is_fiscal_document', false)
            ->whereNull('cancelled_at')
            ->whereNull('consolidated_into_invoice_id')
            ->where('status', '!=', 'draft')
            ->whereBetween('issued_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->whereHas('customer', fn ($q) => $q->where('customer_type', 'individual'));

        $count = $query->count();

        if ($count === 0) {
            return 'No hay ventas a clientes individuales sin consolidar en ese período.';
        }

        $total = $query->get()->sum(fn (Invoice $invoice): float => $invoice->netAmount());

        return sprintf('%d venta(s), subtotal sin IVA: %s', $count, Number::currency($total, 'EUR'));
    }

    public function generate(): void
    {
        $data = $this->form->getState();

        try {
            $invoice = app(InvoiceService::class)->createMonthlyIndividualSummaryInvoice(
                Customer::query()->findOrFail($data['customer_id']),
                Carbon::parse($data['from'])->startOfDay(),
                Carbon::parse($data['to'])->endOfDay(),
            );

            Notification::make()
                ->title('Factura PARTICULAR generada')
                ->body("Factura {$invoice->invoice_number} generada correctamente.")
                ->success()
                ->send();

            $this->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('No se pudo generar la factura')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getForms(): array
    {
        return ['form'];
    }
}
