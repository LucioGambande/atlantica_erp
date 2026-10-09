<?php

namespace App\Filament\Support;

use App\Models\Lot;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

/**
 * Campo de lote reusable para las líneas de pedidos, facturas de venta y
 * facturas de compra. Ofrece los lotes del producto elegido en esa misma
 * línea y permite dar de alta uno nuevo sin salir del formulario.
 */
class LotField
{
    public static function make(): Select
    {
        return Select::make('lot_id')
            ->label('Lote')
            ->options(fn (Get $get): array => self::optionsForProduct($get('product_id')))
            ->searchable()
            ->preload()
            ->nullable()
            ->dehydrated()
            ->disabled(fn (Get $get): bool => blank($get('product_id')))
            ->placeholder(fn (Get $get): string => blank($get('product_id'))
                ? 'Elegí primero un producto'
                : 'Sin lote')
            ->createOptionForm([
                TextInput::make('code')
                    ->label('Código de lote')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('manufactured_at')
                    ->label('Fecha de fabricación')
                    ->native(false),
            ])
            ->createOptionModalHeading('Nuevo lote')
            ->createOptionUsing(function (array $data, Get $get): ?int {
                $productId = $get('product_id');

                if (blank($productId)) {
                    return null;
                }

                return self::resolveForProduct((int) $productId, $data)->getKey();
            });
    }

    /**
     * Crea el lote o devuelve el existente: el código es único por producto,
     * así que repetirlo no genera un lote fantasma.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolveForProduct(int $productId, array $data): Lot
    {
        $lot = Lot::query()->firstOrNew([
            'product_id' => $productId,
            'code' => trim((string) $data['code']),
        ]);

        if (filled($data['manufactured_at'] ?? null)) {
            $lot->manufactured_at = $data['manufactured_at'];
        }

        $lot->save();

        return $lot;
    }

    /**
     * Todos los lotes, etiquetados con su producto. Para filtros de tabla,
     * donde no hay una línea (ni un producto) de contexto.
     *
     * @return array<int, string>
     */
    public static function allOptions(): array
    {
        return Lot::query()
            ->with('product')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Lot $lot): array => [
                $lot->id => $lot->code.' · '.($lot->product?->name ?? '—'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForProduct(mixed $productId): array
    {
        if (blank($productId)) {
            return [];
        }

        return Lot::query()
            ->where('product_id', (int) $productId)
            ->orderByRaw('manufactured_at IS NULL')
            ->orderBy('manufactured_at')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Lot $lot): array => [
                $lot->id => $lot->manufactured_at !== null
                    ? $lot->code.' · '.$lot->manufactured_at->format('d/m/Y')
                    : $lot->code,
            ])
            ->all();
    }
}
