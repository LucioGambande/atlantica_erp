<x-filament-panels::page>
    <form wire:submit="generate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-document-text">
            Generar factura PARTICULAR
        </x-filament::button>
    </form>
</x-filament-panels::page>
