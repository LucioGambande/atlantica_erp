<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Models\Customer;
use App\Support\ErpAuthorization;
use Filament\Pages\Page;

class CustomerMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = NavigationGroups::CLIENTES;

    protected static ?string $navigationLabel = 'Mapa de clientes';

    protected static ?string $title = 'Mapa de clientes';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.customer-map';

    protected static ?string $slug = 'customer-map';

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage customers');
    }

    public function getCustomers(): array
    {
        return Customer::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withExists(['invoices as has_billed' => fn ($query) => $query->whereIn('status', ['issued', 'paid'])])
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => $customer->billingAddress(),
                'city' => $customer->city,
                'lat' => (float) $customer->latitude,
                'lng' => (float) $customer->longitude,
                'hasBilled' => (bool) $customer->has_billed,
            ])
            ->values()
            ->all();
    }

    public function getGoogleMapsApiKey(): ?string
    {
        return config('services.google_maps.api_key');
    }
}
