<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StateResource\Pages\CreateState;
use App\Filament\Admin\Resources\StateResource\Pages\EditState;
use App\Filament\Admin\Resources\StateResource\Pages\ListStates;
use App\Http\Controllers\Api\V1\CountriesController;
use App\Models\State;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'States';

    protected static ?string $modelLabel = 'State';

    protected static ?string $pluralModelLabel = 'States';

    protected static ?string $navigationGroup = 'Website Settings';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && $user->admin == '1';
    }

    public static function canViewAny(): bool
    {
        return static::canAccess() && Schema::hasTable('Wo_States');
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('State Details')
                    ->schema([
                        Select::make('country_id')
                            ->label('Country')
                            ->options(fn (): array => CountriesController::getCountrySelectOptions())
                            ->searchable()
                            ->nullable()
                            ->placeholder('Select country'),

                        TextInput::make('name')
                            ->label('State Name')
                            ->required()
                            ->maxLength(191),

                        FileUpload::make('photo')
                            ->label('State Photo')
                            ->image()
                            ->disk('public')
                            ->directory('upload/states')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Upload state image (jpg, png, webp).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('id', 'desc');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('State Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('country_id')
                    ->label('Country')
                    ->formatStateUsing(fn ($state): string => CountriesController::getCountryNameById($state))
                    ->sortable(),

                ImageColumn::make('photo')
                    ->label('Photo')
                    ->disk('public')
                    ->defaultImageUrl(asset('user.png')),
            ])
            ->filters([])
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
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStates::route('/'),
            'create' => CreateState::route('/create'),
            'edit' => EditState::route('/{record}/edit'),
        ];
    }
}

