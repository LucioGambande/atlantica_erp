<x-filament-panels::page>
    <form wire:submit="printRange" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-printer">
            Imprimir rango
        </x-filament::button>
    </form>

    @script
        <script>
            $wire.on('open-print-window', ({ url }) => {
                window.open(url, '_blank');
            });
        </script>
    @endscript
</x-filament-panels::page>
