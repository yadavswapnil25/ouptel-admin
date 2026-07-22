<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsCategoryResource\Pages;
use App\Models\NewsCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsCategoryResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-categories';
    protected static ?string $model = NewsCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'News Categories';
    protected static ?string $modelLabel = 'News Category';
    protected static ?string $pluralModelLabel = 'News Categories';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(NewsCategory::class, 'name', ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(NewsCategory::class, 'slug', ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->maxLength(500)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('icon')
                    ->maxLength(255)
                    ->placeholder('Icon name (e.g., heroicon-o-newspaper)'),

                Forms\Components\ColorPicker::make('color')
                    ->default('#DC2626'),

                Forms\Components\TextInput::make('display_order')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('status')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\ColorColumn::make('color'),

                Tables\Columns\TextColumn::make('display_order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles')
                    ->getStateUsing(fn ($record) => $record->articles()->count()),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsCategories::route('/'),
            'create' => Pages\CreateNewsCategory::route('/create'),
            'edit' => Pages\EditNewsCategory::route('/{record}/edit'),
        ];
    }
}
