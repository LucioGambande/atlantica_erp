<?php

namespace App\Services;

use App\Models\PaymentDetails\BankTransferPaymentDetail;
use App\Models\PaymentDetails\BizumPaymentDetail;
use App\Models\PaymentDetails\CardPaymentDetail;
use App\Models\PaymentDetails\CashPaymentDetail;
use App\Models\PaymentDetails\ChequePaymentDetail;
use App\Models\PaymentDetails\GenericPaymentDetail;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\PaymentDetailType;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PaymentDetailService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForMethod(PaymentMethod $method, array $data): Model
    {
        return match ($method->detail_type) {
            PaymentDetailType::BANK_TRANSFER => BankTransferPaymentDetail::create([
                'transaction_number' => $this->optionalString($data, 'transaction_number'),
                'bank_reference' => $data['bank_reference'] ?? null,
            ]),
            PaymentDetailType::CARD => CardPaymentDetail::create([
                'authorization_code' => $data['authorization_code'] ?? null,
                'card_last_four' => $data['card_last_four'] ?? null,
            ]),
            PaymentDetailType::CASH => CashPaymentDetail::create([
                'notes' => $data['notes'] ?? null,
            ]),
            PaymentDetailType::BIZUM => BizumPaymentDetail::create([
                'operation_code' => $data['operation_code'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]),
            PaymentDetailType::CHEQUE => ChequePaymentDetail::create([
                'cheque_number' => $this->requiredString($data, 'cheque_number', 'El número de cheque es obligatorio.'),
                'bank_name' => $data['bank_name'] ?? null,
            ]),
            default => GenericPaymentDetail::create([
                'notes' => $data['notes'] ?? null,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForPayment(Payment $payment, PaymentMethod $method, array $data): void
    {
        if ((int) $payment->payment_method_id !== (int) $method->id) {
            $payment->detail?->delete();
            $detail = $this->createForMethod($method, $data);
            $payment->forceFill([
                'payment_method_id' => $method->id,
                'detail_type' => $method->detail_type,
                'detail_id' => $detail->id,
            ])->save();

            return;
        }

        $detail = $payment->detail;

        if ($detail === null) {
            $created = $this->createForMethod($method, $data);
            $payment->forceFill([
                'detail_type' => $method->detail_type,
                'detail_id' => $created->id,
            ])->save();

            return;
        }

        match ($method->detail_type) {
            PaymentDetailType::BANK_TRANSFER => $detail->update([
                'transaction_number' => $this->optionalString($data, 'transaction_number'),
                'bank_reference' => $data['bank_reference'] ?? null,
            ]),
            PaymentDetailType::CARD => $detail->update([
                'authorization_code' => $data['authorization_code'] ?? null,
                'card_last_four' => $data['card_last_four'] ?? null,
            ]),
            PaymentDetailType::CASH => $detail->update([
                'notes' => $data['notes'] ?? null,
            ]),
            PaymentDetailType::BIZUM => $detail->update([
                'operation_code' => $data['operation_code'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]),
            PaymentDetailType::CHEQUE => $detail->update([
                'cheque_number' => $this->requiredString($data, 'cheque_number', 'El número de cheque es obligatorio.'),
                'bank_name' => $data['bank_name'] ?? null,
            ]),
            default => $detail->update([
                'notes' => $data['notes'] ?? null,
            ]),
        };
    }

    public function summary(?Model $detail): string
    {
        if ($detail === null) {
            return '—';
        }

        return match ($detail::class) {
            BankTransferPaymentDetail::class => 'Transacción: '.$detail->transaction_number,
            CardPaymentDetail::class => collect([
                $detail->authorization_code ? 'Auth: '.$detail->authorization_code : null,
                $detail->card_last_four ? 'Tarjeta: ****'.$detail->card_last_four : null,
            ])->filter()->implode(' · ') ?: '—',
            CashPaymentDetail::class, GenericPaymentDetail::class => $detail->notes ?: '—',
            BizumPaymentDetail::class => collect([
                $detail->operation_code ? 'Código: '.$detail->operation_code : null,
                $detail->phone ? 'Tel: '.$detail->phone : null,
            ])->filter()->implode(' · ') ?: '—',
            ChequePaymentDetail::class => 'Cheque: '.$detail->cheque_number,
            default => '—',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function requiredString(array $data, string $key, string $message): string
    {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function optionalString(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
