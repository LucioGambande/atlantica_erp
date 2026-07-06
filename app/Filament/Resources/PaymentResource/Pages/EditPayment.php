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
        $this->getRecord()->loadMissing('detail');

        if ($this->getRecord()->detail !== null) {
            $data['detail'] = $this->getRecord()->detail->toArray();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PaymentService::class)->updatePayment($record, $data);
    }
}
