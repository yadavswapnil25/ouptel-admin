<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PageCategoryResource\Pages;
use App\Models\PageCategory;
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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class PageCategoryResource extends Resource
{
    protected static ?string $model = PageCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Pages Categories';

    protected static ?string $modelLabel = 'Page Category';

    protected static ?string $pluralModelLabel = 'Pages Categories';

    protected static ?string $navigationGroup = 'Categories';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('lang_key')
                            ->label('Language Key')
                            ->required()
                            ->maxLength(160)
                            ->helperText('Unique identifier for this category (e.g., business, technology)')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Category Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('This will be automatically generated from the language key')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('id', 'asc');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('lang_key')
                    ->label('Language Key')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label('Category Name')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                //
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
            ->defaultSort('id', 'asc')
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
            'index' => Pages\ListPageCategories::route('/'),
            'create' => Pages\CreatePageCategory::route('/create'),
            'edit' => Pages\EditPageCategory::route('/{record}/edit'),
        ];
    }
}


