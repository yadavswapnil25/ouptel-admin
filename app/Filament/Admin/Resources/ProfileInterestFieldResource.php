<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages\CreateProfileInterestField;
use App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages\EditProfileInterestField;
use App\Filament\Admin\Resources\ProfileInterestFieldResource\Pages\ListProfileInterestFields;
use App\Models\ProfileInterestField;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ProfileInterestFieldResource extends Resource
{
    protected static ?string $model = ProfileInterestField::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Profile Interests';

    protected static ?string $modelLabel = 'Interest Field';

    protected static ?string $pluralModelLabel = 'Interest Fields';

    protected static ?string $navigationGroup = 'Website Settings';

    protected static ?int $navigationSort = 25;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->admin == '1';
    }

    public static function canViewAny(): bool
    {
        return static::canAccess() && Schema::hasTable('Wo_Profile_Interest_Fields');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Interest Field')
                    ->schema([
                        TextInput::make('label')
                            ->label('Display Label')
                            ->required()
                            ->maxLength(191)
                            ->helperText('Shown on profile, e.g. "Favourite Cars".'),

                        TextInput::make('field_key')
                            ->label('Field Key')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->helperText('Lowercase snake_case key, e.g. favourite_cars. Used when saving user data.'),

                        TextInput::make('placeholder')
                            ->label('Input Placeholder')
                            ->maxLength(255)
                            ->helperText('Optional hint shown in the edit form.'),

                        TextInput::make('storage_column')
                            ->label('Database Column (optional)')
                            ->maxLength(100)
                            ->helperText('Leave empty for admin-added fields. Custom values are stored in profile_interests_extra JSON. Built-in fields use Wo_Users columns.'),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive fields are hidden from profile pages.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('sort_order')->orderBy('id'))
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('field_key')
                    ->label('Key')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('storage_column')
                    ->label('Column')
                    ->placeholder('JSON extra')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileInterestFields::route('/'),
            'create' => CreateProfileInterestField::route('/create'),
            'edit' => EditProfileInterestField::route('/{record}/edit'),
        ];
    }
}
