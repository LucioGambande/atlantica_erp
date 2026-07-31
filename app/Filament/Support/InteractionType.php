<?php

namespace App\Filament\Support;

class InteractionType
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'llamada' => 'Llamada',
            'visita' => 'Visita',
            'mail' => 'Mail',
        ];
    }

    public static function color(?string $type): string
    {
        return match ($type) {
            'llamada' => 'info',
            'visita' => 'success',
            'mail' => 'warning',
            default => 'gray',
        };
    }

    public static function icon(?string $type): string
    {
        return match ($type) {
            'llamada' => 'heroicon-o-phone',
            'visita' => 'heroicon-o-map-pin',
            'mail' => 'heroicon-o-envelope',
            default => 'heroicon-o-question-mark-circle',
        };
    }
}
