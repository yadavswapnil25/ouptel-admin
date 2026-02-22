<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CommunityPreferenceResource\Pages;
use App\Models\CommunityPreference;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use App\Filament\Admin\Concerns\HasPanelAccess;

class CommunityPreferenceResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-community-preferences';
    protected static ?string $model = CommunityPreference::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Community Preferences';

    protected static ?string $modelLabel = 'Community Preference';

    protected static ?string $pluralModelLabel = 'Community Preferences';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return Schema::hasTable('community_preferences');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Preference Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(160)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(160)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (!Schema::hasTable('community_preferences')) {
                    return $query->whereRaw('1 = 0');
                }
                $search = request('tableSearch') ?? request('search') ?? request('q');
                if ($search) {
                    $query->where(function (Builder $q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('slug', 'like', "%{$search}%");
                    });
                }
                return $query->orderBy('sort_order')->orderBy('name');
            })
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
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
            ->defaultSort('sort_order', 'asc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunityPreferences::route('/'),
            'create' => Pages\CreateCommunityPreference::route('/create'),
            'edit' => Pages\EditCommunityPreference::route('/{record}/edit'),
        ];
    }
}
