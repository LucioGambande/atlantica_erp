<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Widgets\ClientBalanceWidget;
use App\Integrations\HubSpot\HubSpotClient;
use App\Services\HubSpotCompanySyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncFromHubSpot')
                ->label('Sincronizar desde HubSpot')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->hubspot_company_id))
                ->requiresConfirmation()
                ->modalHeading('Sincronizar cliente desde HubSpot')
                ->modalDescription('Se traerán los datos actuales de la empresa en HubSpot y se actualizarán los campos mapeados de este cliente.')
                ->action(function (): void {
                    try {
                        $result = app(HubSpotCompanySyncService::class)->syncCustomer($this->getRecord());

                        if (($result['failed'] ?? 0) > 0) {
                            throw new \RuntimeException('HubSpot no devolvió datos válidos para este cliente.');
                        }

                        $this->record->refresh();
                        $this->fillForm();

                        Notification::make()
                            ->title('Cliente sincronizado')
                            ->body('Los datos se actualizaron desde HubSpot.')
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('No se pudo sincronizar')
                            ->body($exception->getMessage())
                            ->warning()
                            ->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Token de HubSpot incorrecto')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    } catch (RequestException $exception) {
                        Notification::make()
                            ->title('Error de conexión con HubSpot')
                            ->body(HubSpotClient::explainHttpFailure($exception))
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('No se pudo sincronizar')
                            ->body('Ocurrió un error al consultar HubSpot. Revisá el token o intentá de nuevo.')
                            ->danger()
                            ->send();
                    }
                }),
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
