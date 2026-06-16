<?php

namespace App\Filament\Resources\PriceListResource\Pages;

use App\Filament\Resources\PriceListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->customers()->count() === 0),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->customers()->count() === 0),
            Actions\RestoreAction::make(),
        ];
    }
}
