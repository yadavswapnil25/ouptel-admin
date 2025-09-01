<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationLabel = 'Games';

    protected static ?string $modelLabel = 'Game';

    protected static ?string $pluralModelLabel = 'Games';

    protected static ?string $navigationGroup = 'Entertainment';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Game Information')
                    ->schema([
                        TextInput::make('game_name')
                            ->label('Game Name')
                            ->required()
                            ->maxLength(50)
                            ->columnSpanFull(),

                        TextInput::make('game_link')
                            ->label('Game Link')
                            ->url()
                            ->maxLength(100)
                            ->required()
                            ->helperText('URL to the game (e.g., external game link)')
                            ->columnSpanFull(),

                        FileUpload::make('game_avatar')
                            ->label('Game Avatar')
                            ->image()
                            ->directory('games/avatars')
                            ->visibility('public')
                            ->columnSpanFull(),

                        Select::make('active')
                            ->label('Status')
                            ->options([
                                0 => 'Inactive',
                                1 => 'Active',
                            ])
                            ->required()
                            ->default(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('time', 'desc');
            })
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->size(60),

                TextColumn::make('game_name')
                    ->label('Game Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('game_link')
                    ->label('Game Link')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    })
                    ->url(fn ($record) => $record->game_link)
                    ->openUrlInNewTab(),

                TextColumn::make('players_count')
                    ->label('Total Players')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('active_players_count')
                    ->label('Active Players')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('active_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_date')
                    ->label('Created')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('last_played_date')
                    ->label('Last Played')
                    ->date('M d, Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        0 => 'Inactive',
                        1 => 'Active',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $active): Builder => $query->where('active', $active),
                        );
                    }),

                Tables\Filters\Filter::make('popular_games')
                    ->label('Popular Games (5+ players)')
                    ->query(fn (Builder $query): Builder => $query->has('players', '>=', 5)),

                Tables\Filters\Filter::make('recent_games')
                    ->label('Recent Games (Last 30 days)')
                    ->query(fn (Builder $query): Builder => $query->where('time', '>=', time() - (30 * 24 * 60 * 60))),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('time', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
