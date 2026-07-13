<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return (string) ($record['user_id'] ?? $record['id'] ?? uniqid('activity_', true));
        }

        $key = $record->getAttribute('user_id')
            ?? $record->getAttribute('id')
            ?? $record->getKey();

        return (string) ($key ?? uniqid('activity_', true));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'primary',
                        'post' => 'info',
                        'page' => 'warning',
                        'group' => 'secondary',
                        'comment' => 'success',
                        'game' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'user' => 'New User',
                        'post' => 'New Post',
                        'page' => 'New Page',
                        'group' => 'New Group',
                        'comment' => 'New Comment',
                        'game' => 'New Game',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('user')
                    ->label('User')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('time', 'desc')
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        // Keep user_id selected — Filament record keys require a non-null primary key.
        // Wo_Users PK is user_id (not id).
        return User::query()
            ->select(
                'user_id',
                'username',
                'joined as time',
                \DB::raw("'user' as type"),
                \DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as title"),
                'username as user'
            )
            ->where('joined', '>=', strtotime('-7 days'))
            ->orderBy('joined', 'desc')
            ->limit(20);
    }
}
