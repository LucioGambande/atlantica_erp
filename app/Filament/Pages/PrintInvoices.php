<?php

namespace App\Filament\Pages;

use App\Support\InvoicePrintAuthorization;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class PrintInvoices extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationGroup = 'ERP';

    protected static ?string $navigationLabel = 'Imprimir facturas';

    protected static ?string $title = 'Imprimir facturas por rango';

    protected static ?int $navigationSort = 6;

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
                TextInput::make('from_number')
                    ->label('Desde número')
                    ->placeholder('HORECA2025-00001')
                    ->required()
                    ->maxLength(255),
                TextInput::make('to_number')
                    ->label('Hasta número')
                    ->placeholder('HORECA2025-00099')
                    ->required()
                    ->maxLength(255),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function printRange(): void
    {
        $data = $this->form->getState();

        $url = route('invoices.print.range', [
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
