<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReviewResource\Pages;
use App\Models\ProductReview;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;

class ReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Manage Reviews';

    protected static ?string $modelLabel = 'Review';

    protected static ?string $pluralModelLabel = 'Reviews';

    protected static ?string $navigationGroup = 'Store';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Review Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('Reviewer')
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

                        TextInput::make('star')
                            ->label('Rating')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->required()
                            ->helperText('Rating from 1 to 5 stars'),

                        Textarea::make('review')
                            ->label('Review Text')
                            ->rows(4)
                            ->columnSpanFull(),
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
                TextColumn::make('user_id')
                    ->label('Reviewer')
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

                TextColumn::make('star')
                    ->label('Rating')
                    ->formatStateUsing(function ($state) {
                        return str_repeat('★', $state) . str_repeat('☆', 5 - $state);
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1, 2 => 'danger',
                        3 => 'warning',
                        4, 5 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('review')
                    ->label('Review')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('reviewed_date')
                    ->label('Reviewed')
                    ->date('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Reviewer')
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

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->options(function () {
                        return Product::select('id', 'name')
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $productId): Builder => $query->where('product_id', $productId),
                        );
                    }),

                SelectFilter::make('star')
                    ->label('Rating')
                    ->options([
                        1 => '1 Star',
                        2 => '2 Stars',
                        3 => '3 Stars',
                        4 => '4 Stars',
                        5 => '5 Stars',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $rating): Builder => $query->where('star', $rating),
                        );
                    }),

                Tables\Filters\Filter::make('high_ratings')
                    ->label('High Ratings (4-5 stars)')
                    ->query(fn (Builder $query): Builder => $query->whereIn('star', [4, 5])),

                Tables\Filters\Filter::make('low_ratings')
                    ->label('Low Ratings (1-2 stars)')
                    ->query(fn (Builder $query): Builder => $query->whereIn('star', [1, 2])),

                Tables\Filters\Filter::make('recent_reviews')
                    ->label('Recent Reviews (Last 7 days)')
                    ->query(fn (Builder $query): Builder => $query->where('time', '>=', time() - (7 * 24 * 60 * 60))),
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
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}



