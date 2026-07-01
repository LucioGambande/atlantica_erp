@php
    use Filament\Support\Enums\Alignment;

    $filterName = $attributes->get('data-filter-trigger');
    $filterOptions = json_decode($attributes->get('data-filter-options', '{}'), true) ?: [];
    $hasHeaderFilter = filled($filterName) && count($filterOptions) > 0;
@endphp

@props([
    'activelySorted' => false,
    'alignment' => Alignment::Start,
    'name',
    'sortable' => false,
    'sortDirection',
    'wrap' => false,
])

@php
    if (! $alignment instanceof Alignment) {
        $alignment = filled($alignment) ? (Alignment::tryFrom($alignment) ?? $alignment) : null;
    }

    $alignmentClasses = match ($alignment) {
        Alignment::Start => 'justify-start',
        Alignment::Center => 'justify-center',
        Alignment::End => 'justify-end',
        Alignment::Left => 'justify-start rtl:flex-row-reverse',
        Alignment::Right => 'justify-end rtl:flex-row-reverse',
        Alignment::Justify, Alignment::Between => 'justify-between',
        default => $alignment,
    };
@endphp

<th
    @if ($activelySorted)
        aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}"
    @endif
    {{ $attributes->class(['fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6']) }}
>
    <div @class([
        'group flex w-full items-center gap-x-1',
        'whitespace-nowrap' => ! $wrap,
        'whitespace-normal' => $wrap,
        $alignmentClasses,
    ])>
        @if ($hasHeaderFilter)
            <x-filament::dropdown placement="bottom-start" width="xs">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="fi-ta-header-cell-label inline-flex items-center gap-x-1 text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                    >
                        {{ $slot }}
                        <x-filament::icon
                            icon="heroicon-m-chevron-down"
                            class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-primary-500"
                        />
                    </button>
                </x-slot>

                <x-filament::dropdown.list>
                    <div class="px-3 py-2" wire:key="header-filter-{{ $name }}">
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                            Filtrar
                        </label>
                        <select
                            wire:model.live="tableFilters.{{ $filterName }}.value"
                            class="fi-select-input block w-full rounded-lg border-none bg-white py-1.5 pe-8 ps-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                        >
                            <option value="">Todos</option>
                            @foreach ($filterOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @else
            <span class="fi-ta-header-cell-label text-sm font-semibold text-gray-950 dark:text-white">
                {{ $slot }}
            </span>
        @endif

        @if ($sortable)
            <button
                type="button"
                wire:click="sortTable('{{ $name }}')"
                aria-label="{{ __('filament-tables::table.sorting.fields.column.label', ['column' => trim(strip_tags($slot))]) }}"
                @class([
                    'fi-ta-header-cell-sort-button shrink-0 rounded-md p-0.5 transition duration-75',
                    'text-gray-950 dark:text-white' => $activelySorted,
                    'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300' => ! $activelySorted,
                ])
            >
                <x-filament::icon
                    :icon="$activelySorted && $sortDirection === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'"
                    class="fi-ta-header-cell-sort-icon h-5 w-5"
                />
            </button>
        @endif
    </div>
</th>
