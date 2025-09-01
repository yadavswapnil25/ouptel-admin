<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class ManageGenders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Manage Genders';

    protected static ?string $title = 'Manage Genders';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.admin.pages.manage-genders';

    public function getTableRecordKey($record): string
    {
        return $record->gender_id ?? $record->id ?? 'unknown';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\User::query()
                    ->select('gender as gender_id', 'gender as id', DB::raw('COUNT(*) as users_count'))
                    ->whereNotNull('gender')
                    ->where('gender', '!=', '')
                    ->groupBy('gender')
                    ->orderBy('users_count', 'desc')
            )
            ->columns([
                TextColumn::make('gender_id')
                    ->label('Gender ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('gender_name')
                    ->label('Gender Name')
                    ->getStateUsing(fn ($record) => \App\Models\Gender::getGenderName($record->gender_id))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users Count')
                    ->badge()
                    ->color('info')
                    ->sortable(),
            ])
            ->defaultSort('users_count', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
}
