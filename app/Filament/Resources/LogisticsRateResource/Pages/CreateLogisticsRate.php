<?php

namespace App\Filament\Resources\LogisticsRateResource\Pages;

use App\Filament\Resources\LogisticsRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLogisticsRate extends CreateRecord
{
    protected static string $resource = LogisticsRateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
