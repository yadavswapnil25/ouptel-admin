<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserStoriesResource\Pages;
use App\Models\UserStory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class UserStoriesResource extends Resource
{
    protected static ?string $model = UserStory::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'User Stories / Status';

    protected static ?string $modelLabel = 'User Story';

    protected static ?string $pluralModelLabel = 'User Stories';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Story Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('title')
                            ->maxLength(100)
                            ->required(),

                        Textarea::make('description')
                            ->maxLength(300)
                            ->rows(3),

                        TextInput::make('thumbnail')
                            ->maxLength(100)
                            ->label('Thumbnail URL'),

                        TextInput::make('posted')
                            ->label('Posted Date')
                            ->maxLength(50)
                            ->required(),

                        TextInput::make('expire')
                            ->label('Expires Date')
                            ->maxLength(100),
                    ])
                    ->columns(2),
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

                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                ImageColumn::make('thumbnail_url')
                    ->label('Thumbnail')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => \App\Helpers\ImageHelper::getPlaceholder('default')),

                TextColumn::make('posted')
                    ->label('Posted')
                    ->sortable(),

                TextColumn::make('expire')
                    ->label('Expires')
                    ->sortable()
                    ->color(fn ($record) => $record->is_expired ? 'danger' : 'success'),

                BooleanColumn::make('is_expired')
                    ->label('Expired')
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('media_count')
                    ->label('Media Count')
                    ->getStateUsing(fn ($record) => $record->media()->count())
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_expired')
                    ->label('Status')
                    ->placeholder('All stories')
                    ->trueLabel('Expired only')
                    ->falseLabel('Active only')
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === true,
                            fn (Builder $query): Builder => $query->whereRaw('STR_TO_DATE(expire, "%Y-%m-%d %H:%i:%s") < NOW()'),
                            fn (Builder $query): Builder => $query->when(
                                $data['value'] === false,
                                fn (Builder $query): Builder => $query->whereRaw('STR_TO_DATE(expire, "%Y-%m-%d %H:%i:%s") > NOW()')
                            )
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
            'index' => Pages\ListUserStories::route('/'),
            'create' => Pages\CreateUserStory::route('/create'),
            'edit' => Pages\EditUserStory::route('/{record}/edit'),
        ];
    }
}
