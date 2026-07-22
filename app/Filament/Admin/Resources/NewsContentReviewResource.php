<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsContentReviewResource\Pages;
use App\Models\NewsArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NewsContentReviewResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-content-review';
    protected static ?string $model = NewsArticle::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Content Review';
    protected static ?string $modelLabel = 'Content Review';
    protected static ?string $pluralModelLabel = 'Content Review';
    protected static ?string $slug = 'news-content-review';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending_review')->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'pending_review')
            ->with(['categories', 'author']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article')
                    ->schema([
                        Forms\Components\TextInput::make('title')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('author_name')->disabled(),
                        Forms\Components\TextInput::make('author_id')->label('Author User ID')->disabled(),
                        Forms\Components\Textarea::make('excerpt')->rows(3)->disabled()->columnSpanFull(),
                        Forms\Components\RichEditor::make('content')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('featured_image')->disabled()->columnSpanFull(),
                        Forms\Components\TagsInput::make('tags')->disabled(),
                        Forms\Components\DateTimePicker::make('submitted_at')->disabled(),
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
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Author')
                    ->sortable(),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (NewsArticle $record) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->publishFromReview($adminId ? (int) $adminId : null)) {
                            Notification::make()->title('Article published')->success()->send();
                        }
                    }),
                Tables\Actions\Action::make('sendBack')
                    ->label('Send Back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('feedback')
                            ->label('Feedback for editor')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (NewsArticle $record, array $data) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->sendBackFromReview($data['feedback'] ?? null, $adminId ? (int) $adminId : null)) {
                            Notification::make()->title('Sent back to editor')->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('feedback')
                            ->label('Rejection reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (NewsArticle $record, array $data) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->rejectFromReview($data['feedback'] ?? null, $adminId ? (int) $adminId : null)) {
                            Notification::make()->title('Article rejected')->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsContentReviews::route('/'),
            'view' => Pages\ViewNewsContentReview::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
