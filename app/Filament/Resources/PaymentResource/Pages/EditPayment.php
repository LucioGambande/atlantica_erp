<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Services\PaymentService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ViewAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->getRecord()->loadMissing('detail', 'allocations');

        if ($this->getRecord()->detail !== null) {
            $data['detail'] = $this->getRecord()->detail->toArray();
        }

        $data['allocations'] = $this->getRecord()->allocations
            ->map(fn ($allocation): array => [
                'invoice_id' => (string) $allocation->invoice_id,
                'amount' => $allocation->amount,
            ])
            ->values()
            ->all();

        $data['quick_invoice_ids'] = collect($data['allocations'])
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PaymentService::class)->updatePayment($record, $data);
    }
}
