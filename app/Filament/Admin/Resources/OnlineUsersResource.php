<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OnlineUsersResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class OnlineUsersResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Online Users';

    protected static ?string $modelLabel = 'Online User';

    protected static ?string $pluralModelLabel = 'Online Users';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('username')
                            ->required()
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('first_name')
                            ->maxLength(60),

                        TextInput::make('last_name')
                            ->maxLength(32),

                        TextInput::make('phone_number')
                            ->maxLength(32),

                        Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                '1802' => 'Other',
                                'mal****' => 'Prefer not to say',
                            ]),

                        Select::make('active')
                            ->options([
                                '1' => 'Active',
                                '0' => 'Inactive',
                                '2' => 'Banned',
                            ])
                            ->required(),

                        Select::make('admin')
                            ->options([
                                '0' => 'User',
                                '1' => 'Admin',
                                '2' => 'Moderator',
                            ])
                            ->required(),

                        Toggle::make('verified')
                            ->label('Verified User'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Only show users who were last seen within the last 60 seconds
                return $query->where('lastseen', '>', time() - 60)
                    ->orderBy('lastseen', 'desc');
            })
            ->columns([
                TextColumn::make('user_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => \App\Helpers\ImageHelper::getPlaceholder('user-avatar')),

                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'success',
                        '1802' => 'warning',
                        'mal****' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Banned' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('type_text')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Admin' => 'danger',
                        'Moderator' => 'warning',
                        'User' => 'info',
                        default => 'gray',
                    }),

                BooleanColumn::make('verified')
                    ->label('Verified')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('last_seen_date')
                    ->label('Last Seen')
                    ->date('M d, Y H:i:s')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                        '2' => 'Banned',
                    ]),

                SelectFilter::make('admin')
                    ->label('Type')
                    ->options([
                        '0' => 'User',
                        '1' => 'Admin',
                        '2' => 'Moderator',
                    ]),

                SelectFilter::make('gender')
                    ->label('Gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        '1802' => 'Other',
                        'mal****' => 'Prefer not to say',
                    ]),

                TernaryFilter::make('verified')
                    ->label('Verified')
                    ->placeholder('All users')
                    ->trueLabel('Verified only')
                    ->falseLabel('Not verified'),
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
            ->defaultSort('lastseen', 'desc')
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
            'index' => Pages\ListOnlineUsers::route('/'),
            'create' => Pages\CreateOnlineUser::route('/create'),
            'edit' => Pages\EditOnlineUser::route('/{record}/edit'),
        ];
    }
}


