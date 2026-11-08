<?php

namespace App\Filament\Resources\LogisticsRateResource\Pages;

use App\Filament\Resources\LogisticsRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticsRates extends ListRecords
{
    protected static string $resource = LogisticsRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
