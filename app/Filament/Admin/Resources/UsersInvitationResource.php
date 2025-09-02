<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UsersInvitationResource\Pages;
use App\Models\AdminInvitation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class UsersInvitationResource extends Resource
{
    protected static ?string $model = AdminInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Users Invitation';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Invitation';

    protected static ?string $pluralModelLabel = 'User Invitations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invitation Details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Invitation Code')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Auto-generated code')
                            ->helperText('Unique invitation code for user registration'),

                        Forms\Components\TextInput::make('time')
                            ->label('Posted Time')
                            ->disabled()
                            ->helperText('When this invitation was created'),

                        Forms\Components\Select::make('user_id')
                            ->label('Created By')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('invited_id')
                            ->label('Used By')
                            ->relationship('invitedUser', 'username')
                            ->searchable()
                            ->preload()
                            ->placeholder('Not used yet'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('time_elapsed')
                    ->label('Time')
                    ->getStateUsing(fn ($record) => $record->time_elapsed)
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('code')
                    ->label('Invitation Code')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Invitation code copied')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('user.username')
                    ->label('Created By')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('used_by')
                    ->label('Used By')
                    ->getStateUsing(fn ($record) => $record->used_by ?? 'Not used')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->invited_id > 0 ? 'success' : 'gray'),

                TextColumn::make('invitation_url')
                    ->label('Invitation URL')
                    ->getStateUsing(fn ($record) => $record->invitation_url)
                    ->copyable()
                    ->copyMessage('Invitation URL copied')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                IconColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->invited_id > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'used' => 'Used',
                        'unused' => 'Unused',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'used' => $query->where('invited_id', '>', 0),
                            'unused' => $query->where('invited_id', 0),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Invitation')
                        ->modalDescription('Are you sure you want to delete this invitation? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete it'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Invitations')
                        ->modalDescription('Are you sure you want to delete the selected invitations? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, delete them'),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Generate New Code')
                    ->action(function () {
                        AdminInvitation::createInvitation();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('New invitation code generated successfully')
                            ->success()
                            ->send();
                    })
                    ->color('success')
                    ->icon('heroicon-o-plus'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsersInvitations::route('/'),
            'view' => Pages\ViewUsersInvitation::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('invited_id', 0)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $unusedCount = static::getModel()::where('invited_id', 0)->count();
        return $unusedCount > 0 ? 'warning' : 'success';
    }
}

