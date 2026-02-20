<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductSubCategoryResource\Pages;
use App\Models\ProductSubCategory;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
use App\Filament\Admin\Concerns\HasPanelAccess;

class ProductSubCategoryResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-product-sub-categories';
    protected static ?string $model = ProductSubCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Product Sub Categories';

    protected static ?string $modelLabel = 'Product Sub Category';

    protected static ?string $pluralModelLabel = 'Products Sub Categories';

    protected static ?string $navigationGroup = 'Store';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Sub Category Information')
                    ->schema([
                        Select::make('category_id')
                            ->label('Parent Category')
                            ->options(function () {
                                return ProductCategory::all()->mapWithKeys(function ($category) {
                                    return [$category->id => $category->name];
                                });
                            })
                            ->required()
                            ->searchable(),

                        TextInput::make('lang_key')
                            ->label('Language Key')
                            ->required()
                            ->maxLength(200)
                            ->helperText('Unique identifier for this sub-category (e.g., smartphones, laptops)')
                            ->columnSpanFull(),

                        TextInput::make('type')
                            ->label('Type')
                            ->default('product')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Automatically set to "product" for product sub-categories')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Sub Category Name')
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
                return $query->orderBy('category_id', 'asc')->orderBy('id', 'asc');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->sortable()
                    ->badge()
                    ->color('secondary'),

                TextColumn::make('lang_key')
                    ->label('Language Key')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label('Sub Category Name')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Parent Category')
                    ->options(function () {
                        return ProductCategory::all()->mapWithKeys(function ($category) {
                            return [$category->id => $category->name];
                        });
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $categoryId): Builder => $query->where('category_id', $categoryId),
                        );
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
            ->defaultSort('category_id', 'asc')
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
            'index' => Pages\ListProductSubCategories::route('/'),
            'create' => Pages\CreateProductSubCategory::route('/create'),
            'edit' => Pages\EditProductSubCategory::route('/{record}/edit'),
        ];
    }
}
