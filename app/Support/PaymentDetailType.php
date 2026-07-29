<?php

namespace App\Support;

use App\Models\PaymentDetails\BankTransferPaymentDetail;
use App\Models\PaymentDetails\BizumPaymentDetail;
use App\Models\PaymentDetails\CardPaymentDetail;
use App\Models\PaymentDetails\CashPaymentDetail;
use App\Models\PaymentDetails\ChequePaymentDetail;
use App\Models\PaymentDetails\GenericPaymentDetail;

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
            self::BANK_TRANSFER => BankTransferPaymentDetail::class,
            self::CARD => CardPaymentDetail::class,
            self::CASH => CashPaymentDetail::class,
            self::BIZUM => BizumPaymentDetail::class,
            self::CHEQUE => ChequePaymentDetail::class,
            default => GenericPaymentDetail::class,
        };
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::labels());
    }
}
