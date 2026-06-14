<?php

namespace App\Filament\Forms;

use App\Models\PaymentMethod;
use App\Support\PaymentDetailType;
use Filament\Forms;
use Filament\Forms\Get;

class PaymentDetailForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(?int $paymentMethodId): array
    {
        if (! $paymentMethodId) {
            return [];
        }

        $method = PaymentMethod::query()->find($paymentMethodId);

        if ($method === null) {
            return [];
        }

        return match ($method->detail_type) {
            PaymentDetailType::BANK_TRANSFER => [
                Forms\Components\TextInput::make('detail.transaction_number')
                    ->label('Número de transacción')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('detail.bank_reference')
                    ->label('Referencia bancaria')
                    ->maxLength(255),
            ],
            PaymentDetailType::CARD => [
                Forms\Components\TextInput::make('detail.authorization_code')
                    ->label('Código de autorización')
                    ->maxLength(255),
                Forms\Components\TextInput::make('detail.card_last_four')
                    ->label('Últimos 4 dígitos')
                    ->maxLength(4)
                    ->minLength(4),
            ],
            PaymentDetailType::CASH => [
                Forms\Components\Textarea::make('detail.notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ],
            PaymentDetailType::BIZUM => [
                Forms\Components\TextInput::make('detail.operation_code')
                    ->label('Código de operación')
                    ->maxLength(255),
                Forms\Components\TextInput::make('detail.phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(255),
            ],
            PaymentDetailType::CHEQUE => [
                Forms\Components\TextInput::make('detail.cheque_number')
                    ->label('Número de cheque')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('detail.bank_name')
                    ->label('Banco')
                    ->maxLength(255),
            ],
            default => [
                Forms\Components\Textarea::make('detail.notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ],
        };
    }

    public static function methodSelect(string $name = 'payment_method_id'): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->label('Forma de pago')
            ->options(fn (): array => PaymentMethod::activeOptions())
            ->required()
            ->live()
            ->searchable();
    }

    public static function detailsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Detalles del pago')
            ->schema(fn (Get $get): array => static::schema((int) $get('payment_method_id')))
            ->visible(fn (Get $get): bool => filled($get('payment_method_id')))
            ->columns(2);
    }
}
