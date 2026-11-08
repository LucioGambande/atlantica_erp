<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Models\LogisticsLocation;
use App\Models\LogisticsRate;
use App\Services\LogisticsShippingCalculator;
use App\Support\ErpAuthorization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

class LogisticsCalculator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = NavigationGroups::LOGISTICA;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Calculadora de envíos';

    protected static ?string $title = 'Calculadora de envíos';

    protected static string $view = 'filament.pages.logistics-calculator';

    protected static ?string $slug = 'calculadora-logistica';

    public ?array $data = [];

    public ?array $result = null;

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage logistics');
    }

    public function mount(): void
    {
        $this->form->fill(['quantity' => 1]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('logistics_rate_id')
                    ->label('Tarifa')
                    ->options(fn (): array => LogisticsRate::query()
                        ->active()
                        ->with('provider')
                        ->get()
                        ->mapWithKeys(fn (LogisticsRate $rate): array => [
                            $rate->id => "{$rate->provider->name} — {$rate->name}",
                        ])
                        ->all())
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('destination_location_id')
                    ->label('Destino')
                    ->helperText('Solo se listan los destinos mapeados a una zona de la tarifa elegida.')
                    ->options(fn (Get $get): array => LogisticsLocation::query()
                        ->whereHas('rateZoneLocations', fn ($query) => $query->where('logistics_rate_id', $get('logistics_rate_id')))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $this->isMatrix($get('logistics_rate_id')))
                    ->required(fn (Get $get): bool => $this->isMatrix($get('logistics_rate_id'))),
                TextInput::make('weight_kg')
                    ->label('Peso (kg)')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $this->isMatrix($get('logistics_rate_id')))
                    ->required(fn (Get $get): bool => $this->isMatrix($get('logistics_rate_id'))),
                TextInput::make('volume_m3')
                    ->label('Volumen (m³, opcional)')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $this->isMatrix($get('logistics_rate_id'))),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->helperText('Según la unidad de cálculo de la tarifa (pallets, cajas, viajes, etc).')
                    ->numeric()
                    ->minValue(0)
                    ->default(1)
                    ->visible(fn (Get $get): bool => ! $this->isMatrix($get('logistics_rate_id')))
                    ->required(fn (Get $get): bool => ! $this->isMatrix($get('logistics_rate_id'))),
            ])
            ->columns(2)
            ->statePath('data');
    }

    private function isMatrix(?int $rateId): bool
    {
        if ($rateId === null) {
            return false;
        }

        return LogisticsRate::find($rateId)?->pricing_type === 'matrix';
    }

    public function calculate(): void
    {
        $this->result = null;

        $data = $this->form->getState();

        $rate = LogisticsRate::query()->with('provider')->find($data['logistics_rate_id'] ?? null);

        if ($rate === null) {
            Notification::make()->title('Seleccioná una tarifa')->warning()->send();

            return;
        }

        $version = $rate->currentVersion();

        if ($version === null) {
            Notification::make()
                ->title('Sin precio vigente')
                ->body('Esta tarifa no tiene ninguna versión vigente hoy.')
                ->danger()
                ->send();

            return;
        }

        try {
            $this->result = app(LogisticsShippingCalculator::class)->calculate(
                $rate,
                $version,
                destinationLocationId: filled($data['destination_location_id'] ?? null) ? (int) $data['destination_location_id'] : null,
                weightKg: filled($data['weight_kg'] ?? null) ? (float) $data['weight_kg'] : null,
                volumeM3: filled($data['volume_m3'] ?? null) ? (float) $data['volume_m3'] : null,
                quantity: filled($data['quantity'] ?? null) ? (float) $data['quantity'] : 1,
            );
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('No se pudo calcular')
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
