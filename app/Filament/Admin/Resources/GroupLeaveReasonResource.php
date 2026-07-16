<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\GroupLeaveReasonResource\Pages;
use App\Models\GroupLeaveReason;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class GroupLeaveReasonResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-groups';

    protected static ?string $model = GroupLeaveReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';

    protected static ?string $navigationLabel = 'Group Leave Reasons';

    protected static ?string $modelLabel = 'Leave Reason';

    protected static ?string $pluralModelLabel = 'Group Leave Reasons';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        return static::canAccess() && Schema::hasTable('Wo_Group_Leave_Reasons');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Leave Details')
                    ->schema([
                        Forms\Components\TextInput::make('group_id')->disabled(),
                        Forms\Components\TextInput::make('user_id')->disabled(),
                        Forms\Components\Textarea::make('reason')->disabled()->rows(4)->columnSpanFull(),
                        Forms\Components\TextInput::make('time')->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['group', 'user'])->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('group.group_title')
                    ->label('Group')
                    ->placeholder('Unknown group')
                    ->description(fn (GroupLeaveReason $record): string => $record->group?->group_name ? '@'.$record->group->group_name : '')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('group', function (Builder $q) use ($search) {
                            $q->where('group_title', 'like', "%{$search}%")
                                ->orWhere('group_name', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->placeholder('Unknown user')
                    ->formatStateUsing(function ($state, GroupLeaveReason $record): string {
                        $name = trim(($record->user?->first_name ?? '').' '.($record->user?->last_name ?? ''));
                        if ($name !== '') {
                            return $name;
                        }
                        return $state ?: ('#'.$record->user_id);
                    })
                    ->description(fn (GroupLeaveReason $record): string => $record->user?->username ? '@'.$record->user->username : '')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $q) use ($search) {
                            $q->where('username', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Leave Reason')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),

                Tables\Columns\TextColumn::make('time')
                    ->label('Left At')
                    ->formatStateUsing(function ($state): string {
                        if (!$state || !is_numeric($state)) {
                            return '—';
                        }
                        return date('M j, Y g:i A', (int) $state);
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Group')
                    ->relationship('group', 'group_title')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No leave reasons yet')
            ->emptyStateDescription('When members leave a group and submit a reason, it will appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroupLeaveReasons::route('/'),
            'view' => Pages\ViewGroupLeaveReason::route('/{record}'),
        ];
    }
}
