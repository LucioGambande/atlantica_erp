<?php

namespace App\Filament\Support;

use Filament\Forms;

class InteractionForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(InteractionType::options())
                ->required()
                ->native(false),
            Forms\Components\Textarea::make('notes')
                ->label('Qué pasó')
                ->required()
                ->rows(3)
                ->autofocus()
                ->columnSpanFull(),
            Forms\Components\Select::make('next_action_type')
                ->label('Próxima acción')
                ->options(InteractionType::options())
                ->native(false),
            Forms\Components\DatePicker::make('next_action_at')
                ->label('Fecha próxima acción')
                ->native(false),
            Forms\Components\TextInput::make('next_action_notes')
                ->label('Detalle próxima acción')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }
}
