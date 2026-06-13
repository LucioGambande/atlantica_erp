<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Jobs\SyncHubSpotCompaniesJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncHubSpot')
                ->label('Sincronizar desde HubSpot')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar clientes desde HubSpot')
                ->modalDescription('Se encolará una importación completa de todas las empresas de HubSpot. Los webhooks seguirán actualizando cambios en tiempo real.')
                ->action(function (): void {
                    SyncHubSpotCompaniesJob::dispatch(full: true);

                    Notification::make()
                        ->title('Sincronización encolada')
                        ->body('Los clientes se importarán en segundo plano. Asegúrate de que el worker de colas esté activo.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
