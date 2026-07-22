<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsArticleResource\Pages;
use App\Models\NewsArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsArticleResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-articles';
    protected static ?string $model = NewsArticle::class;
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'News Articles';
    protected static ?string $modelLabel = 'News Article';
    protected static ?string $pluralModelLabel = 'News Articles';
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
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(NewsArticle::class, 'slug', ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('excerpt')
                            ->required()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->rows(3),

                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpanFull(),
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
                            ->columnSpanFull(),

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

                Tables\Columns\IconColumn::make('featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'info',
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
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
        return [];
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
