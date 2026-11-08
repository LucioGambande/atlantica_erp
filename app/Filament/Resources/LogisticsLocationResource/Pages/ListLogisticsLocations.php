<?php

namespace App\Filament\Resources\LogisticsLocationResource\Pages;

use App\Filament\Resources\LogisticsLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticsLocations extends ListRecords
{
    protected static string $resource = LogisticsLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
