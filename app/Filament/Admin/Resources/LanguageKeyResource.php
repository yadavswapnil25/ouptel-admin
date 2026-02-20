<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LanguageKeyResource\Pages;
use App\Models\LanguageKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Admin\Concerns\HasPanelAccess;

class LanguageKeyResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-language-keys';
    protected static ?string $model = LanguageKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Language Keys';

    protected static ?string $modelLabel = 'Language Key';

    protected static ?string $pluralModelLabel = 'Language Keys';

    protected static ?string $navigationGroup = 'Languages';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Language Key Information')
                    ->schema([
                        TextInput::make('lang_key')
                            ->label('Key Name')
                            ->required()
                            ->maxLength(160)
                            ->helperText('Use only english letters, no spaces allowed, example: this_is_a_key'),

                        TextInput::make('type')
                            ->label('Type')
                            ->maxLength(100)
                            ->helperText('Optional type classification for this key'),
                    ])
                    ->columns(2),

                Section::make('Translations')
                    ->schema([
                        Textarea::make('english')
                            ->label('English')
                            ->rows(3)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Textarea::make('arabic')
                                    ->label('Arabic')
                                    ->rows(3),

                                Textarea::make('french')
                                    ->label('French')
                                    ->rows(3),

                                Textarea::make('german')
                                    ->label('German')
                                    ->rows(3),

                                Textarea::make('italian')
                                    ->label('Italian')
                                    ->rows(3),

                                Textarea::make('russian')
                                    ->label('Russian')
                                    ->rows(3),

                                Textarea::make('spanish')
                                    ->label('Spanish')
                                    ->rows(3),
                            ]),
                    ]),
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

                TextColumn::make('lang_key')
                    ->label('Key Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('secondary')
                    ->placeholder('No type'),

                TextColumn::make('english')
                    ->label('English')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('arabic')
                    ->label('Arabic')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('french')
                    ->label('French')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('german')
                    ->label('German')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('italian')
                    ->label('Italian')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('russian')
                    ->label('Russian')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('spanish')
                    ->label('Spanish')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_translations')
                    ->label('Has Translations')
                    ->query(function (Builder $query): Builder {
                        return $query->where(function ($q) {
                            $q->whereNotNull('arabic')
                              ->orWhereNotNull('french')
                              ->orWhereNotNull('german')
                              ->orWhereNotNull('italian')
                              ->orWhereNotNull('russian')
                              ->orWhereNotNull('spanish');
                        });
                    }),
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
            ->defaultSort('id', 'desc')
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
            'index' => Pages\ListLanguageKeys::route('/'),
            'create' => Pages\CreateLanguageKey::route('/create'),
            'edit' => Pages\EditLanguageKey::route('/{record}/edit'),
        ];
    }
}


