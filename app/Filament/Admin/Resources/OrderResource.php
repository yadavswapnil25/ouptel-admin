<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\UserOrder;
use App\Models\User;
use App\Models\Product;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-orders';
    protected static ?string $model = UserOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Manage Orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Orders';

    protected static ?string $navigationGroup = 'Store';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Order Information')
                    ->schema([
                        TextInput::make('hash_id')
                            ->label('Order ID')
                            ->required()
                            ->maxLength(100),

                        Select::make('user_id')
                            ->label('Customer')
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

                        Select::make('product_id')
                            ->label('Product')
                            ->options(function () {
                                return Product::select('id', 'name')
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => 
                                Product::where('name', 'like', "%{$search}%")
                                    ->limit(20)
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->required(),

                        Select::make('product_owner_id')
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
                    ])
                    ->columns(2),

                Section::make('Order Details')
                    ->schema([
                        TextInput::make('price')
                            ->label('Product Price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),

                        TextInput::make('commission')
                            ->label('Commission')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),

                        TextInput::make('final_price')
                            ->label('Final Price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),

                        TextInput::make('units')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'placed' => 'Placed',
                                'confirmed' => 'Confirmed',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Shipping Information')
                    ->schema([
                        TextInput::make('address_id')
                            ->label('Address ID')
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('tracking_id')
                            ->label('Tracking ID')
                            ->maxLength(50),

                        TextInput::make('tracking_url')
                            ->label('Tracking URL')
                            ->url()
                            ->maxLength(500),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('time', 'desc');
            })
            ->columns([
                TextColumn::make('hash_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user_id')
                    ->label('Customer')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product_id')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        $product = Product::find($state);
                        return $product ? $product->name : "Product {$state}";
                    })
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('product_owner_id')
                    ->label('Seller')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('units')
                    ->label('Qty')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Placed' => 'info',
                        'Confirmed' => 'primary',
                        'Processing' => 'warning',
                        'Shipped' => 'success',
                        'Delivered' => 'success',
                        'Cancelled' => 'danger',
                        'Refunded' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Customer')
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

                SelectFilter::make('product_owner_id')
                    ->label('Seller')
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
                            fn (Builder $query, $userId): Builder => $query->where('product_owner_id', $userId),
                        );
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'placed' => 'Placed',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->where('status', $status),
                        );
                    }),

                Tables\Filters\Filter::make('recent_orders')
                    ->label('Recent Orders (Last 7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('time', '>=', time() - (7 * 24 * 60 * 60))),

                Tables\Filters\Filter::make('high_value_orders')
                    ->label('High Value Orders ($100+)')
                    ->query(fn (Builder $query): Builder => $query->where('final_price', '>=', 100)),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}



