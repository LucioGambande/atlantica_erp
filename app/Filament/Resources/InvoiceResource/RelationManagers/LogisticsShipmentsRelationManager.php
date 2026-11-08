<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Models\LogisticsShipment;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LogisticsShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'logisticsShipments';

    protected static ?string $title = 'Envíos logísticos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ErpAuthorization::userCan('manage logistics');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('logistics_provider_id')
                    ->label('Proveedor')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('logistics_rate_id')
                    ->label('Tarifa')
                    ->relationship('rate', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('origin_location_id')
                    ->label('Origen')
                    ->relationship('originLocation', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('destination_location_id')
                    ->label('Destino')
                    ->relationship('destinationLocation', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('weight_kg')
                    ->label('Peso (kg)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('volume_m3')
                    ->label('Volumen (m³)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('actual_cost')
                    ->label('Coste pagado')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\Select::make('currency')
                    ->label('Moneda')
                    ->options(['EUR' => 'EUR', 'USD' => 'USD', 'ARS' => 'ARS', 'GBP' => 'GBP'])
                    ->default('EUR')
                    ->required(),
                Forms\Components\DatePicker::make('shipped_at')
                    ->label('Fecha de envío')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Observaciones')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('source_document_path')
                    ->label('Documento del transportista')
                    ->disk('local')
                    ->directory('logistics-shipments')
                    ->acceptedFileTypes(['application/pdf'])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('actual_cost')
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('route')
                    ->label('Ruta')
                    ->state(fn (LogisticsShipment $record): string => $record->originLocation || $record->destinationLocation
                        ? trim(($record->originLocation?->name).' → '.($record->destinationLocation?->name), ' →')
                        : '—'),
                Tables\Columns\TextColumn::make('weight_kg')
                    ->label('Peso (kg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_cost')
                    ->label('Coste pagado')
                    ->money(fn (LogisticsShipment $record): string => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipped_at')
                    ->label('Fecha de envío')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('shipped_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('viewDocument')
                    ->label('Ver documento')
                    ->icon('heroicon-o-document')
                    ->visible(fn (LogisticsShipment $record): bool => filled($record->source_document_path))
                    ->action(fn (LogisticsShipment $record) => Storage::disk('local')->download(
                        $record->source_document_path,
                        "envio-{$record->id}.pdf"
                    )),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
