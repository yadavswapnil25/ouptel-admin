<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PageFieldResource\Pages;
use App\Models\PageField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Admin\Concerns\HasPanelAccess;

class PageFieldResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-page-fields';
    protected static ?string $model = PageField::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pages Fields';

    protected static ?string $modelLabel = 'Page Field';

    protected static ?string $pluralModelLabel = 'Pages Fields';

    protected static ?string $navigationGroup = 'Custom Fields';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Field Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Field Name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Use {{LANG lang_variable}} to translate the field data. e.g: {{LANG first_name}}'),

                        Textarea::make('description')
                            ->label('Field Description')
                            ->rows(3)
                            ->helperText('The description will show under the field.'),

                        Select::make('type')
                            ->label('Field Type')
                            ->options([
                                'textbox' => 'Textbox',
                                'textarea' => 'Textarea',
                                'selectbox' => 'Select Box',
                            ])
                            ->required()
                            ->reactive(),

                        TextInput::make('length')
                            ->label('Field Length')
                            ->numeric()
                            ->default(32)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->helperText('Default value is 32, and max value is 1000'),

                        Select::make('required')
                            ->label('Required Field')
                            ->options([
                                'on' => 'Required',
                                'off' => 'Not Required',
                            ])
                            ->required()
                            ->helperText('Is this field required?'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Select Box Options')
                    ->schema([
                        Textarea::make('options')
                            ->label('Select Field Options')
                            ->rows(3)
                            ->helperText('One option per line (only for select box type)')
                            ->visible(fn (Forms\Get $get): bool => $get('type') === 'selectbox'),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'selectbox'),
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

                TextColumn::make('name')
                    ->label('Field Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type_label')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Textbox' => 'primary',
                        'Textarea' => 'success',
                        'Select Box' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('length')
                    ->label('Length')
                    ->sortable(),

                TextColumn::make('required_text')
                    ->label('Required')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Required' => 'danger',
                        'Optional' => 'success',
                        default => 'gray',
                    }),

                BooleanColumn::make('active')
                    ->label('Status')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'textbox' => 'Textbox',
                        'textarea' => 'Textarea',
                        'selectbox' => 'Select Box',
                    ]),

                SelectFilter::make('required')
                    ->options([
                        'on' => 'Required',
                        'off' => 'Not Required',
                    ]),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All fields')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
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
            'index' => Pages\ListPageFields::route('/'),
            'create' => Pages\CreatePageField::route('/create'),
            'edit' => Pages\EditPageField::route('/{record}/edit'),
        ];
    }
}
