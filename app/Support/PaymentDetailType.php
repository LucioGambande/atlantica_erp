<?php

namespace App\Support;

class PaymentDetailType
{
    public const BANK_TRANSFER = 'bank_transfer';

    public const CARD = 'card';

    public const CASH = 'cash';

    public const BIZUM = 'bizum';

    public const CHEQUE = 'cheque';

    public const GENERIC = 'generic';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::BANK_TRANSFER => 'Transferencia bancaria (nº de transacción)',
            self::CARD => 'Tarjeta (código de autorización)',
            self::CASH => 'Efectivo (notas opcionales)',
            self::BIZUM => 'Bizum (código de operación)',
            self::CHEQUE => 'Cheque (número de cheque)',
            self::GENERIC => 'Genérico (notas opcionales)',
        ];
    }

    /**
     * @return class-string
     */
    public static function modelClass(string $type): string
    {
        return match ($type) {
            self::BANK_TRANSFER => \App\Models\PaymentDetails\BankTransferPaymentDetail::class,
            self::CARD => \App\Models\PaymentDetails\CardPaymentDetail::class,
            self::CASH => \App\Models\PaymentDetails\CashPaymentDetail::class,
            self::BIZUM => \App\Models\PaymentDetails\BizumPaymentDetail::class,
            self::CHEQUE => \App\Models\PaymentDetails\ChequePaymentDetail::class,
            default => \App\Models\PaymentDetails\GenericPaymentDetail::class,
        };
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::labels());
    }
}
