<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Filament\Resources\NewsArticleResource\RelationManagers;
use App\Models\NewsArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan('full'),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(NewsArticle::class, 'slug', ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan('full'),

                        Forms\Components\Textarea::make('excerpt')
                            ->required()
                            ->maxLength(500)
                            ->columnSpan('full')
                            ->rows(3),

                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpan('full'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publishing Details')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->nullable(),

                        Forms\Components\TextInput::make('author_name')
                            ->maxLength(255)
                            ->default('News Team'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media & Visibility')
                    ->schema([
                        Forms\Components\TextInput::make('featured_image')
                            ->label('Featured Image URL')
                            ->url()
                            ->columnSpan('full'),

                        Forms\Components\Toggle::make('featured')
                            ->label('Mark as Featured')
                            ->default(false),

                        Forms\Components\Toggle::make('breaking')
                            ->label('Breaking News')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('views')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\TextInput::make('shares')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('author_name')
                    ->searchable(),

                Tables\Columns\BooleanColumn::make('featured')
                    ->label('Featured'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'draft' => 'info',
                        'published' => 'success',
                        'archived' => 'gray',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('featured')
                    ->label('Featured Only'),

                Tables\Filters\TernaryFilter::make('breaking')
                    ->label('Breaking News'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
