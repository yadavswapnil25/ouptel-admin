<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PostReactionResource\Pages;
use App\Models\PostReaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class PostReactionResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-post-reactions';
    protected static ?string $model = PostReaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Post Reactions';

    protected static ?string $modelLabel = 'Post Reaction';

    protected static ?string $pluralModelLabel = 'Post Reactions';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Reaction Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('reaction')
                            ->label('Reaction Type')
                            ->options([
                                1 => '👍 Like',
                                2 => '❤️ Love',
                                3 => '😂 Haha',
                                4 => '😮 Wow',
                                5 => '😢 Sad',
                                6 => '😠 Angry',
                            ])
                            ->required(),

                        Select::make('content_type')
                            ->label('Content Type')
                            ->options([
                                'post' => 'Post',
                                'comment' => 'Comment',
                                'story' => 'Story',
                                'message' => 'Message',
                            ])
                            ->required()
                            ->reactive(),

                        TextInput::make('post_id')
                            ->label('Post ID')
                            ->numeric()
                            ->visible(fn (Forms\Get $get): bool => $get('content_type') === 'post'),

                        TextInput::make('comment_id')
                            ->label('Comment ID')
                            ->numeric()
                            ->visible(fn (Forms\Get $get): bool => $get('content_type') === 'comment'),

                        TextInput::make('story_id')
                            ->label('Story ID')
                            ->numeric()
                            ->visible(fn (Forms\Get $get): bool => $get('content_type') === 'story'),

                        TextInput::make('message_id')
                            ->label('Message ID')
                            ->numeric()
                            ->visible(fn (Forms\Get $get): bool => $get('content_type') === 'message'),
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

                TextColumn::make('reaction')
                    ->label('Reaction')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success',
                        2 => 'danger',
                        3 => 'warning',
                        4 => 'info',
                        5 => 'gray',
                        6 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => '👍 Like',
                        2 => '❤️ Love',
                        3 => '😂 Haha',
                        4 => '😮 Wow',
                        5 => '😢 Sad',
                        6 => '😠 Angry',
                        default => "Reaction {$state}",
                    }),

                TextColumn::make('content_type')
                    ->label('Content Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Post' => 'info',
                        'Comment' => 'warning',
                        'Story' => 'success',
                        'Message' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('content_id')
                    ->label('Content ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('post_id')
                    ->label('Post ID')
                    ->sortable()
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('comment_id')
                    ->label('Comment ID')
                    ->sortable()
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('story_id')
                    ->label('Story ID')
                    ->sortable()
                    ->searchable()
                    ->placeholder('N/A'),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('reaction')
                    ->label('Reaction Type')
                    ->options([
                        1 => '👍 Like',
                        2 => '❤️ Love',
                        3 => '😂 Haha',
                        4 => '😮 Wow',
                        5 => '😢 Sad',
                        6 => '😠 Angry',
                    ]),

                SelectFilter::make('post_id')
                    ->label('Content Type')
                    ->options([
                        'has_post' => 'Posts Only',
                        'has_comment' => 'Comments Only',
                        'has_story' => 'Stories Only',
                        'has_message' => 'Messages Only',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'has_post',
                            fn (Builder $query): Builder => $query->whereNotNull('post_id')->where('post_id', '>', 0),
                            fn (Builder $query): Builder => $query->when(
                                $data['value'] === 'has_comment',
                                fn (Builder $query): Builder => $query->whereNotNull('comment_id')->where('comment_id', '>', 0),
                                fn (Builder $query): Builder => $query->when(
                                    $data['value'] === 'has_story',
                                    fn (Builder $query): Builder => $query->whereNotNull('story_id')->where('story_id', '>', 0),
                                    fn (Builder $query): Builder => $query->when(
                                        $data['value'] === 'has_message',
                                        fn (Builder $query): Builder => $query->whereNotNull('message_id')->where('message_id', '>', 0)
                                    )
                                )
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
            'index' => Pages\ListPostReactions::route('/'),
            'create' => Pages\CreatePostReaction::route('/create'),
            'edit' => Pages\EditPostReaction::route('/{record}/edit'),
        ];
    }
}
