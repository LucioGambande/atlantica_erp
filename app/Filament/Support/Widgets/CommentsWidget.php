<?php

namespace App\Filament\Support\Widgets;

use App\Models\Comment;
use App\Models\Invoice;
use App\Models\Order;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Comentarios';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function commentFormSchema(): array
    {
        return [
            Forms\Components\Textarea::make('body')
                ->label('Comentario')
                ->required()
                ->rows(3),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->record instanceof Invoice || $this->record instanceof Order
                ? $this->record->comments()->getQuery()
                : Comment::query()->whereRaw('1 = 0'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autor')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar comentario')
                    ->modalHeading('Agregar comentario')
                    ->form($this->commentFormSchema())
                    ->using(function (array $data): Comment {
                        return $this->record->comments()->create([
                            'body' => $data['body'],
                            'user_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form($this->commentFormSchema()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
