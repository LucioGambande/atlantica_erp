<?php

namespace App\Filament\Resources\LogisticsProviderResource\Pages;

use App\Filament\Resources\LogisticsProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogisticsProviders extends ListRecords
{
    protected static string $resource = LogisticsProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
