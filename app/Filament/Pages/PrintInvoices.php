<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Models\Customer;
use App\Support\InvoicePrintAuthorization;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PrintInvoices extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationGroup = NavigationGroups::FACTURACION;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Imprimir facturas';

    protected static ?string $title = 'Imprimir facturas por rango';

    protected static string $view = 'filament.pages.print-invoices';

    protected static ?string $slug = 'print-invoices';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return InvoicePrintAuthorization::canPrint();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Radio::make('range_type')
                    ->label('Filtrar por')
                    ->options([
                        'number' => 'Rango de números',
                        'date' => 'Rango de fechas',
                    ])
                    ->default('number')
                    ->inline()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('from_number')
                    ->label('Desde número')
                    ->placeholder('HORECA2025-00001')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('range_type') === 'number'),
                TextInput::make('to_number')
                    ->label('Hasta número')
                    ->placeholder('HORECA2025-00099')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (callable $get) => $get('range_type') === 'number'),
                DatePicker::make('from_date')
                    ->label('Desde fecha')
                    ->required()
                    ->visible(fn (callable $get) => $get('range_type') === 'date'),
                DatePicker::make('to_date')
                    ->label('Hasta fecha')
                    ->required()
                    ->visible(fn (callable $get) => $get('range_type') === 'date'),
                Select::make('customer_id')
                    ->label('Cliente')
                    ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos los clientes')
                    ->visible(fn (callable $get) => $get('range_type') === 'date')
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function printRange(): void
    {
        $data = $this->form->getState();

        $url = $data['range_type'] === 'date'
            ? route('invoices.print.range', array_filter([
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'],
                'customer_id' => $data['customer_id'] ?? null,
            ]))
            : route('invoices.print.range', [
                'from' => $data['from_number'],
                'to' => $data['to_number'],
            ]);

        $this->dispatch('open-print-window', url: $url);
    }

    protected function getForms(): array
    {
        return ['form'];
    }
}
