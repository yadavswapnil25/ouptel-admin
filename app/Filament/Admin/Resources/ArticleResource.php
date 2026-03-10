<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ArticleResource\Pages;
use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\ArticleResource\Widgets;
use App\Models\Article;
use App\Models\User;
use App\Models\BlogCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class ArticleResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-articles';
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Articles (Blog)';

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'Articles';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Article Title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Short Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('content')
                            ->label('Article Content')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('user')
                            ->label('Author')
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

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(function () {
                                // Map category IDs to the same labels used on the website
                                $labels = self::getBlogCategoryLabels();

                                return BlogCategory::query()
                                    ->orderBy('id')
                                    ->get()
                                    ->mapWithKeys(function ($category) use ($labels) {
                                        $label = $labels[$category->id] ?? "Category {$category->id}";
                                        return [$category->id => $label];
                                    });
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags...')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('thumbnail')
                            ->label('Featured Image')
                            ->image()
                            ->directory('blog')
                            ->visibility('public')
                            ->dehydrated(fn ($state) => filled($state)),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Published')
                            ->default(true)
                            ->helperText('Whether this article is published and visible to users'),

                        Forms\Components\TextInput::make('view')
                            ->label('View Count')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->helperText('Number of times this article has been viewed'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('posted', 'desc');
            })
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),

                TextColumn::make('user')
                    ->label('Author')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return 'No Category';
                        }

                        $labels = self::getBlogCategoryLabels();
                        return $labels[$state] ?? "Category {$state}";
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('excerpt')
                    ->label('Excerpt')
                    ->limit(60)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 60 ? $state : null;
                    }),

                TextColumn::make('views_count')
                    ->label('Views')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-eye'),

                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-chat-bubble-left'),

                TextColumn::make('posted_date')
                    ->label('Posted')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('posted', $direction);
                    }),

                IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('actions')
                    ->label('Actions')
                    ->formatStateUsing(function ($record) {
                        return view('filament.admin.resources.article-resource.actions', compact('record'));
                    })
                    ->html(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(function () {
                        $labels = self::getBlogCategoryLabels();

                        return BlogCategory::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(function ($category) use ($labels) {
                                $label = $labels[$category->id] ?? "Category {$category->id}";
                                return [$category->id => $label];
                            });
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $categoryId): Builder => $query->where('category', $categoryId),
                        );
                    }),

                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All articles')
                    ->trueLabel('Published articles')
                    ->falseLabel('Draft articles'),

                SelectFilter::make('user')
                    ->label('Author')
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
                            fn (Builder $query, $userId): Builder => $query->where('user', $userId),
                        );
                    }),

                Tables\Filters\Filter::make('has_comments')
                    ->label('Has Comments')
                    ->query(fn (Builder $query): Builder => $query->whereHas('comments')),

                Tables\Filters\Filter::make('popular')
                    ->label('Popular Articles')
                    ->query(fn (Builder $query): Builder => $query->where('view', '>', 100)),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Article $record): string => $record->url)
                    ->openUrlInNewTab(),

                Action::make('comments')
                    ->label('Comments')
                    ->icon('heroicon-o-chat-bubble-left')
                    ->url(fn (Article $record): string => route('filament.admin.resources.articles.comments', $record))
                    ->visible(fn (Article $record): bool => $record->comments_count > 0),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('posted', 'desc')
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
            'comments' => Pages\ManageArticleComments::route('/{record}/comments'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\ArticlesStatsWidget::class,
        ];
    }

    /**
     * Get blog category labels matching the website (React) mapping.
     *
     * These IDs and names mirror `blogCategoryNames` in
     * `ouptel-web/src/constants/blogCategories.js`.
     */
    protected static function getBlogCategoryLabels(): array
    {
        return [
            2 => 'Cars and Vehicles',
            3 => 'Comedy',
            4 => 'Economics and Trade',
            5 => 'Education',
            6 => 'Entertainment',
            7 => 'Movies & Animation',
            8 => 'Gaming',
            9 => 'History and Facts',
            10 => 'Live Style',
            11 => 'Natural',
            12 => 'News and Politics',
            13 => 'People and Nations',
            14 => 'Pets and Animals',
            15 => 'Places and Regions',
            16 => 'Science and Technology',
            17 => 'Sport',
            18 => 'Travel and Events',
            19 => 'Other',
        ];
    }
}
