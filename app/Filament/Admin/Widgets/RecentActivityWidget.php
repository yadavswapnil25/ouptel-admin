<?php

namespace App\Filament\Admin\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

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
        // Use a simple approach - just show recent users for now
        // This avoids the complex union query issue
        return DB::table('Wo_Users')
            ->select(
                'user_id as id', 
                'username', 
                'joined as time', 
                DB::raw("'user' as type"), 
                DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as title"), 
                'username as user'
            )
            ->where('joined', '>=', strtotime('-7 days'))
            ->orderBy('joined', 'desc')
            ->limit(20);
    }
}
