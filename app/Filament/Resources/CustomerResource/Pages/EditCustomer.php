<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Widgets\ClientBalanceWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('statement')
                ->label('Cuenta corriente')
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => CustomerResource::getUrl('statement', ['record' => $this->getRecord()->getKey()])),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientBalanceWidget::make([
                'record' => $this->getRecord(),
            ]),
        ];
    }
}
