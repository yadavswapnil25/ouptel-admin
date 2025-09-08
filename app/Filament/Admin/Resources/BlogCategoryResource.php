<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogCategoryResource\Pages;
use App\Models\BlogCategory;
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
use Illuminate\Database\Eloquent\Builder;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Blogs Categories';

    protected static ?string $modelLabel = 'Blog Category';

    protected static ?string $pluralModelLabel = 'Blogs Categories';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 8;

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
                            ->helperText('Unique identifier for this category (e.g., technology, lifestyle)')
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
                // Handle custom search for category names
                $search = request('tableSearch');
                if ($search) {
                    $query->where(function (Builder $query) use ($search) {
                        // Search by ID
                        $query->where('id', 'like', "%{$search}%")
                              // Search by lang_key
                              ->orWhere('lang_key', 'like', "%{$search}%")
                              // Search by category name in Wo_Langs table
                              ->orWhereExists(function ($subQuery) use ($search) {
                                  $subQuery->select(\Illuminate\Support\Facades\DB::raw(1))
                                      ->from('Wo_Langs')
                                      ->whereColumn('Wo_Langs.id', 'Wo_Blogs_Categories.lang_key')
                                      ->where('Wo_Langs.type', 'category')
                                      ->where('Wo_Langs.english', 'like', "%{$search}%");
                              });
                    });
                }
                return $query->orderBy('id', 'asc');
            })
            ->searchable()
            ->searchOnBlur()
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
            'index' => Pages\ListBlogCategories::route('/'),
            'create' => Pages\CreateBlogCategory::route('/create'),
            'edit' => Pages\EditBlogCategory::route('/{record}/edit'),
        ];
    }
}


