<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GroupResource\Pages;
use App\Filament\Admin\Resources\GroupResource\RelationManagers;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Concerns\HasPanelAccess;

class GroupResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-groups';
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Groups';
    protected static ?string $modelLabel = 'Group';
    protected static ?string $pluralModelLabel = 'Groups';
    protected static ?string $navigationGroup = 'Manage Features';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Group Information')
                    ->schema([
                        Forms\Components\TextInput::make('group_name')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(32),
                        Forms\Components\TextInput::make('group_title')
                            ->label('Group Title')
                            ->required()
                            ->maxLength(40),
                        Forms\Components\Textarea::make('about')
                            ->label('About Group')
                            ->rows(3)
                            ->maxLength(500),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                1 => 'General',
                                2 => 'Technology',
                                3 => 'Business',
                                4 => 'Entertainment',
                                5 => 'Sports',
                                6 => 'Education',
                                7 => 'Health',
                                8 => 'Travel',
                            ])
                            ->default(1),
                        Forms\Components\Select::make('privacy')
                            ->label('Privacy')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'secret' => 'Secret',
                            ])
                            ->default('public'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Group Avatar')
                            ->image()
                            ->directory('groups/avatars')
                            ->visibility('public')
                            ->dehydrated(fn ($state) => filled($state)),
                        Forms\Components\FileUpload::make('cover')
                            ->label('Group Cover')
                            ->image()
                            ->directory('groups/covers')
                            ->visibility('public')
                            ->dehydrated(fn ($state) => filled($state)),
                    ])->columns(2),
                
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Group Owner')
                            ->options(function () {
                                return \App\Models\User::pluck('username', 'user_id')->toArray();
                            })
                            ->searchable()
                            ->required(),
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->size(50),
                Tables\Columns\TextColumn::make('group_name')
                    ->label('Group Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('Owner ID')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->user ? $record->user->username : 'Unknown';
                    }),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        '1' => 'General',
                        '2' => 'Technology',
                        '3' => 'Business',
                        '4' => 'Entertainment',
                        '5' => 'Sports',
                        '6' => 'Education',
                        '7' => 'Health',
                        '8' => 'Travel',
                        default => 'General',
                    })
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('privacy')
                    ->label('Privacy')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'public' => 'success',
                        'private' => 'warning',
                        'secret' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('time')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        1 => 'General',
                        2 => 'Technology',
                        3 => 'Business',
                        4 => 'Entertainment',
                        5 => 'Sports',
                        6 => 'Education',
                        7 => 'Health',
                        8 => 'Travel',
                    ]),
                Tables\Filters\SelectFilter::make('privacy')
                    ->label('Privacy')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'secret' => 'Secret',
                    ]),
                Tables\Filters\Filter::make('active')
                    ->label('Active Groups')
                    ->query(fn (Builder $query): Builder => $query->where('active', '1'))
                    ->indicateUsing(fn (): string => 'Active Groups Only'),
                Tables\Filters\Filter::make('inactive')
                    ->label('Inactive Groups')
                    ->query(fn (Builder $query): Builder => $query->where('active', '0'))
                    ->indicateUsing(fn (): string => 'Inactive Groups Only'),

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('time', 'desc');
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
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
