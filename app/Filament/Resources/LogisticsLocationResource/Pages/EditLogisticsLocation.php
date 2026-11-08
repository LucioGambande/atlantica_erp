<?php

namespace App\Filament\Resources\LogisticsLocationResource\Pages;

use App\Filament\Resources\LogisticsLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLogisticsLocation extends EditRecord
{
    protected static string $resource = LogisticsLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
