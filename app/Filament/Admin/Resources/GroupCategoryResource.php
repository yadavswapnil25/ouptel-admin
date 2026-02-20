<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GroupCategoryResource\Pages;
use App\Models\GroupCategory;
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

class GroupCategoryResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-group-categories';
    protected static ?string $model = GroupCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Groups Categories';

    protected static ?string $modelLabel = 'Group Category';

    protected static ?string $pluralModelLabel = 'Groups Categories';

    protected static ?string $navigationGroup = 'Categories';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('id')
                            ->label('Category ID')
                            ->required()
                            ->numeric()
                            ->helperText('Unique numeric identifier for this category'),

                        TextInput::make('name')
                            ->label('Category Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('This will be automatically generated from the ID')
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
                                      ->whereColumn('Wo_Langs.id', 'Wo_Groups_Categories.lang_key')
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
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('lang_key')
                    ->label('Language Key')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

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
            'index' => Pages\ListGroupCategories::route('/'),
            'create' => Pages\CreateGroupCategory::route('/create'),
            'edit' => Pages\EditGroupCategory::route('/{record}/edit'),
        ];
    }
}


