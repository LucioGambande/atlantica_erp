<?php

namespace App\Filament\Support;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class TableUi
{
    public static function configure(Table $table): Table
    {
        return $table
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'lg' => 4,
            ])
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->striped();
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    public static function headerSelectFilter(string $filterName, array $options): array
    {
        return [
            'data-filter-trigger' => $filterName,
            'data-filter-options' => json_encode($options, JSON_UNESCAPED_UNICODE),
        ];
    }
}
