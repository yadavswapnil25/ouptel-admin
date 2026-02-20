<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\User;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-products';
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Manage Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $navigationGroup = 'Store';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Product Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Product Name')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Product Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('user_id')
                            ->label('Product Owner')
                            ->options(function () {
                                return User::select('user_id', 'username')
                                    ->orderBy('username')
                                    ->limit(50)
                                    ->pluck('username', 'user_id');
                            })
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => 
                                User::where('username', 'like', "%{$search}%")
                                    ->limit(20)
                                    ->pluck('username', 'user_id')
                                    ->toArray()
                            )
                            ->required(),

                        Select::make('category')
                            ->label('Category')
                            ->options(function () {
                                return ProductCategory::all()->mapWithKeys(function ($category) {
                                    return [$category->id => $category->name];
                                });
                            })
                            ->searchable()
                            ->required(),

                        TextInput::make('sub_category')
                            ->label('Sub Category')
                            ->maxLength(50),

                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                                'GBP' => 'GBP',
                                'JPY' => 'JPY',
                            ])
                            ->default('USD')
                            ->required(),

                        TextInput::make('units')
                            ->label('Available Units')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Product Details')
                    ->schema([
                        Select::make('type')
                            ->label('Product Type')
                            ->options([
                                0 => 'Physical',
                                1 => 'Digital',
                            ])
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                0 => 'Draft',
                                1 => 'Active',
                                2 => 'Sold Out',
                                3 => 'Discontinued',
                            ])
                            ->required(),

                        Select::make('active')
                            ->label('Active')
                            ->options([
                                0 => 'Inactive',
                                1 => 'Active',
                            ])
                            ->required(),

                        Textarea::make('location')
                            ->label('Location')
                            ->rows(2),

                        TextInput::make('lat')
                            ->label('Latitude')
                            ->maxLength(100),

                        TextInput::make('lng')
                            ->label('Longitude')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Section::make('Product Images')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Product Images')
                            ->image()
                            ->multiple()
                            ->directory('products/images')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('time', 'desc');
            })
            ->columns([
                ImageColumn::make('main_image')
                    ->label('Image')
                    ->circular()
                    ->size(60),

                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),

                TextColumn::make('user_id')
                    ->label('Owner')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(function ($state) {
                        $category = ProductCategory::find($state);
                        return $category ? $category->name : "Category {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price_formatted')
                    ->label('Price')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('units')
                    ->label('Units')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Active' => 'success',
                        'Sold Out' => 'warning',
                        'Discontinued' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('type_text')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Physical' => 'primary',
                        'Digital' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('reviews_count')
                    ->label('Reviews')
                    ->badge()
                    ->color('secondary'),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->badge()
                    ->color('success'),

                TextColumn::make('posted_date')
                    ->label('Posted')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Owner')
                    ->options(function () {
                        return User::select('user_id', 'username')
                            ->orderBy('username')
                            ->limit(50)
                            ->pluck('username', 'user_id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $userId): Builder => $query->where('user_id', $userId),
                        );
                    }),

                SelectFilter::make('category')
                    ->label('Category')
                    ->options(function () {
                        return ProductCategory::all()->mapWithKeys(function ($category) {
                            return [$category->id => $category->name];
                        });
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $categoryId): Builder => $query->where('category', $categoryId),
                        );
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        0 => 'Draft',
                        1 => 'Active',
                        2 => 'Sold Out',
                        3 => 'Discontinued',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->where('status', $status),
                        );
                    }),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        0 => 'Physical',
                        1 => 'Digital',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $type): Builder => $query->where('type', $type),
                        );
                    }),

                SelectFilter::make('active')
                    ->label('Active')
                    ->options([
                        0 => 'Inactive',
                        1 => 'Active',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $active): Builder => $query->where('active', $active),
                        );
                    }),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock (≤10 units)')
                    ->query(fn (Builder $query): Builder => $query->where('units', '<=', 10)),

                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('units', '<=', 0)),
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
            ->defaultSort('time', 'desc')
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}



