<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GamePlayerResource\Pages;
use App\Models\GamePlayer;
use App\Models\User;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;

class GamePlayerResource extends Resource
{
    protected static ?string $model = GamePlayer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Game Players';

    protected static ?string $modelLabel = 'Game Player';

    protected static ?string $pluralModelLabel = 'Game Players';

    protected static ?string $navigationGroup = 'Entertainment';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Player Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('Player')
                            ->options(function () {
                                return User::select('user_id', 'username')
                                    ->orderBy('username')
                                    ->limit(50)
                                    ->pluck('username', 'user_id');
                            })
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => 
                                User::where('username', 'like', "%{$search}%")
                                    ->limit(20)
                                    ->pluck('username', 'user_id')
                                    ->toArray()
                            )
                            ->required(),

                        Select::make('game_id')
                            ->label('Game')
                            ->options(function () {
                                return Game::select('id', 'game_name')
                                    ->orderBy('game_name')
                                    ->pluck('game_name', 'id');
                            })
                            ->searchable()
                            ->required(),

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
                return $query->orderBy('last_play', 'desc');
            })
            ->columns([
                TextColumn::make('user_id')
                    ->label('Player')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('game_id')
                    ->label('Game')
                    ->formatStateUsing(function ($state) {
                        $game = Game::find($state);
                        return $game ? $game->game_name : "Game {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('last_played_date')
                    ->label('Last Played')
                    ->date('M d, Y H:i')
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('time_ago')
                    ->label('Time Ago')
                    ->badge()
                    ->color('secondary'),

                TextColumn::make('active_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Player')
                    ->options(function () {
                        return User::select('user_id', 'username')
                            ->orderBy('username')
                            ->limit(50)
                            ->pluck('username', 'user_id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $userId): Builder => $query->where('user_id', $userId),
                        );
                    }),

                SelectFilter::make('game_id')
                    ->label('Game')
                    ->options(function () {
                        return Game::select('id', 'game_name')
                            ->orderBy('game_name')
                            ->pluck('game_name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $gameId): Builder => $query->where('game_id', $gameId),
                        );
                    }),

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

                Tables\Filters\Filter::make('recent_players')
                    ->label('Recent Players (Last 7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('last_play', '>=', time() - (7 * 24 * 60 * 60))),

                Tables\Filters\Filter::make('active_players')
                    ->label('Active Players Only')
                    ->query(fn (Builder $query): Builder => $query->where('active', 1)),
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
            ->defaultSort('last_play', 'desc')
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
            'index' => Pages\ListGamePlayers::route('/'),
            'create' => Pages\CreateGamePlayer::route('/create'),
            'edit' => Pages\EditGamePlayer::route('/{record}/edit'),
        ];
    }
}
