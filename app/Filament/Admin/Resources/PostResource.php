<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PostResource\Pages;
use App\Models\Post;
use App\Models\User;
use App\Models\Page;
use App\Models\Group;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Posts';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Post Information')
                    ->schema([
                        Forms\Components\Textarea::make('postText')
                            ->label('Post Content')
                            ->rows(4)
                            ->columnSpanFull(),
                        
                        Forms\Components\Select::make('user_id')
                            ->label('Author')
                            ->options(User::pluck('username', 'user_id')->toArray())
                            ->searchable()
                            ->required(),
                        
                        Forms\Components\Select::make('postPrivacy')
                            ->label('Privacy')
                            ->options([
                                '0' => 'Public',
                                '1' => 'Friends',
                                '2' => 'Only Me',
                                '3' => 'Custom',
                                '4' => 'Group',
                            ])
                            ->default('1')
                            ->required(),
                        
                        Forms\Components\Select::make('postType')
                            ->label('Post Type')
                            ->options([
                                'text' => 'Text',
                                'photo' => 'Photo',
                                'video' => 'Video',
                                'file' => 'File',
                                'link' => 'Link',
                                'location' => 'Location',
                                'audio' => 'Audio',
                                'sticker' => 'Sticker',
                            ])
                            ->default('text'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Media & Links')
                    ->schema([
                        Forms\Components\TextInput::make('postPhoto')
                            ->label('Photo URL')
                            ->url(),
                        
                        Forms\Components\TextInput::make('postYoutube')
                            ->label('YouTube URL'),
                        
                        Forms\Components\TextInput::make('postFile')
                            ->label('File URL'),
                        
                        Forms\Components\TextInput::make('postLink')
                            ->label('Link URL')
                            ->url(),
                        
                        Forms\Components\TextInput::make('postLinkTitle')
                            ->label('Link Title'),
                        
                        Forms\Components\TextInput::make('postRecord')
                            ->label('Audio URL'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Context & Settings')
                    ->schema([
                        Forms\Components\Select::make('page_id')
                            ->label('Page')
                            ->options(Page::pluck('page_name', 'page_id')->toArray())
                            ->searchable(),
                        
                        Forms\Components\Select::make('group_id')
                            ->label('Group')
                            ->options(Group::pluck('group_name', 'id')->toArray())
                            ->searchable(),
                        
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('boosted')
                            ->label('Boosted'),
                        
                        Forms\Components\Toggle::make('comments_status')
                            ->label('Comments Enabled')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('post_image_url')
                    ->label('Media')
                    ->size(60)
                    ->circular(false),
                
                Tables\Columns\TextColumn::make('postText')
                    ->label('Content')
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('postType')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'photo' => 'success',
                        'video' => 'warning',
                        'file' => 'info',
                        'link' => 'primary',
                        'text' => 'gray',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('postPrivacyText')
                    ->label('Privacy')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Public' => 'success',
                        'Friends' => 'info',
                        'Only Me' => 'warning',
                        'Group' => 'primary',
                        default => 'gray',
                    }),
                
                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('boosted')
                    ->label('Boosted')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('time')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('videoViews')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('postType')
                    ->options([
                        'text' => 'Text',
                        'photo' => 'Photo',
                        'video' => 'Video',
                        'file' => 'File',
                        'link' => 'Link',
                        'location' => 'Location',
                        'audio' => 'Audio',
                        'sticker' => 'Sticker',
                    ]),
                
                Tables\Filters\SelectFilter::make('postPrivacy')
                    ->label('Privacy')
                    ->options([
                        '0' => 'Public',
                        '1' => 'Friends',
                        '2' => 'Only Me',
                        '3' => 'Custom',
                        '4' => 'Group',
                    ]),
                
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Active'),
                
                Tables\Filters\TernaryFilter::make('boosted')
                    ->label('Boosted'),
                
                Tables\Filters\Filter::make('has_media')
                    ->label('Has Media')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNotNull('postPhoto')
                          ->orWhereNotNull('postYoutube')
                          ->orWhereNotNull('postFile')
                          ->orWhereNotNull('postRecord');
                    })),
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
            ->defaultSort('time', 'desc');
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'view' => Pages\ViewPost::route('/{record}'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}



