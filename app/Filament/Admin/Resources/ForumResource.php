<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ForumResource\Pages;
use App\Models\Forum;
use App\Models\ForumSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumResource extends Resource
{
    protected static ?string $model = Forum::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Forums';
    protected static ?string $modelLabel = 'Forum';
    protected static ?string $pluralModelLabel = 'Forums';
    protected static ?string $navigationGroup = 'Manage Features';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Forum Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Forum Name')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('Enter forum name'),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(300)
                            ->placeholder('Enter forum description'),
                        Forms\Components\Select::make('sections')
                            ->label('Section')
                            ->options(function () {
                                $sections = ForumSection::orderBy('section_name')->get();
                                if ($sections->isEmpty()) {
                                    return [];
                                }
                                return $sections->pluck('section_name', 'id')->toArray();
                            })
                            ->searchable()
                            ->placeholder('Select a section (create sections first if none available)')
                            ->helperText(function () {
                                $count = ForumSection::count();
                                if ($count === 0) {
                                    return 'No sections available. Create sections in Forum Sections first.';
                                }
                                return "Select a forum section ({$count} available)";
                            })
                            ->nullable()
                            ->default(null),
                    ])->columns(1),
                
                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('posts')
                            ->label('Total Posts')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->helperText('Total number of posts in this forum'),
                        Forms\Components\TextInput::make('last_post')
                            ->label('Last Post Time')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->helperText('Timestamp of the last post'),
                    ])->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Forum Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('sections')
                    ->label('Sections')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('posts')
                    ->label('Posts')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => number_format($state)),
                Tables\Columns\TextColumn::make('last_post')
                    ->label('Last Post')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        if (empty($state) || $state == 0) {
                            return 'Never';
                        }
                        // Check if it's a timestamp (numeric)
                        if (is_numeric($state)) {
                            return date('Y-m-d H:i:s', (int)$state);
                        }
                        return $state;
                    })
                    ->default('Never'),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_posts')
                    ->label('Has Posts')
                    ->query(fn (Builder $query): Builder => $query->where('posts', '>', 0))
                    ->indicateUsing(fn (): string => 'Has Posts'),
                Tables\Filters\Filter::make('no_posts')
                    ->label('No Posts')
                    ->query(fn (Builder $query): Builder => $query->where('posts', '=', 0))
                    ->indicateUsing(fn (): string => 'No Posts'),
                Tables\Filters\Filter::make('has_sections')
                    ->label('Has Sections')
                    ->query(fn (Builder $query): Builder => $query->where('sections', '>', 0))
                    ->indicateUsing(fn (): string => 'Has Sections'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListForums::route('/'),
            'create' => Pages\CreateForum::route('/create'),
            'view' => Pages\ViewForum::route('/{record}'),
            'edit' => Pages\EditForum::route('/{record}/edit'),
        ];
    }
}

