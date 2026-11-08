<?php

namespace App\Filament\Resources\LogisticsRateResource\RelationManagers;

use App\Models\LogisticsRateVersion;
use App\Services\LogisticsRateVersionService;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class LogisticsRateVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Historial de precios';

    /**
     * @return array<Component>
     */
    private function versionFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('price')
                ->label('Precio')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Select::make('currency')
                ->label('Moneda')
                ->options([
                    'EUR' => 'EUR',
                    'USD' => 'USD',
                    'ARS' => 'ARS',
                    'GBP' => 'GBP',
                ])
                ->default('EUR')
                ->required(),
            Forms\Components\Toggle::make('vat_included')
                ->label('IVA incluido')
                ->default(false),
            Forms\Components\TextInput::make('minimum_cost')
                ->label('Coste mínimo por operación')
                ->numeric()
                ->minValue(0),
            Forms\Components\DatePicker::make('valid_from')
                ->label('Vigente desde')
                ->required()
                ->default(now()),
            Forms\Components\Repeater::make('surcharges')
                ->label('Recargos')
                ->schema([
                    Forms\Components\TextInput::make('concept')
                        ->label('Concepto')
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(['fixed' => 'Importe fijo', 'percentage' => 'Porcentaje'])
                        ->default('fixed')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('rate')
                        ->label('Tasa (%)')
                        ->numeric()
                        ->visible(fn (Get $get): bool => $get('type') === 'percentage'),
                    Forms\Components\TextInput::make('amount')
                        ->label('Importe')
                        ->numeric()
                        ->visible(fn (Get $get): bool => $get('type') !== 'percentage'),
                    Forms\Components\TextInput::make('min')
                        ->label('Mínimo')
                        ->numeric(),
                    Forms\Components\TextInput::make('max')
                        ->label('Máximo')
                        ->numeric(),
                    Forms\Components\Toggle::make('optional')
                        ->label('Opcional (no se aplica por defecto, ej. reembolso)'),
                ])
                ->columns(3)
                ->addActionLabel('Añadir recargo')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('conditions')
                ->label('Condiciones / observaciones')
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('source_document_path')
                ->label('Documento PDF de origen')
                ->disk('local')
                ->directory('logistics-rate-versions')
                ->acceptedFileTypes(['application/pdf'])
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Vigente desde')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Vigente hasta')
                    ->date()
                    ->placeholder('Vigente')
                    ->badge()
                    ->color(fn (LogisticsRateVersion $record): string => $record->valid_until === null ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money(fn (LogisticsRateVersion $record): string => $record->currency),
                Tables\Columns\IconColumn::make('vat_included')
                    ->label('IVA incluido')
                    ->boolean(),
                Tables\Columns\TextColumn::make('minimum_cost')
                    ->label('Coste mínimo')
                    ->money(fn (LogisticsRateVersion $record): string => $record->currency)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Condiciones')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('valid_from', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('newVersion')
                    ->label('Nueva versión')
                    ->icon('heroicon-o-plus')
                    ->form($this->versionFormSchema())
                    ->action(function (array $data): void {
                        app(LogisticsRateVersionService::class)->createNewVersion(
                            $this->getOwnerRecord(),
                            $data
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDocument')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-document')
                    ->visible(fn (LogisticsRateVersion $record): bool => filled($record->source_document_path))
                    ->action(fn (LogisticsRateVersion $record) => Storage::disk('local')->download(
                        $record->source_document_path,
                        "tarifa-{$record->id}.pdf"
                    )),
            ])
            ->paginated([25, 50, 100]);
    }
}
