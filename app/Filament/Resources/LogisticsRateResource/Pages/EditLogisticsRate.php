<?php

namespace App\Filament\Resources\LogisticsRateResource\Pages;

use App\Filament\Resources\LogisticsRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogisticsRate extends EditRecord
{
    protected static string $resource = LogisticsRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->versions()->count() === 0),
            Actions\RestoreAction::make(),
        ];
    }
}
