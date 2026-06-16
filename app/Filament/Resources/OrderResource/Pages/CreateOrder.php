<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['ordered_at'] ?? null)) {
            $data['ordered_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotalFromItems();
    }
}
