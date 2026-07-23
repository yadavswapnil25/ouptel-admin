<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsPressProfileResource\Pages;
use App\Models\NewsPressProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NewsPressProfileResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-press-pages';
    protected static ?string $model = NewsPressProfile::class;
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Manage Press Pages';
    protected static ?string $modelLabel = 'Press Page';
    protected static ?string $pluralModelLabel = 'Press Pages';
    protected static ?string $slug = 'news-press-pages';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 7;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'editor', 'categories'])
            ->withCount('articles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Press Profile')
                    ->schema([
                        Forms\Components\TextInput::make('name')->disabled(),
                        Forms\Components\TextInput::make('slug')->disabled(),
                        Forms\Components\TextInput::make('tagline')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('contact_email')->disabled(),
                        Forms\Components\TextInput::make('user_id')->label('Editor User ID')->disabled(),
                        Forms\Components\TextInput::make('editor_id')->label('Editor Record ID')->disabled(),
                        Forms\Components\TextInput::make('logo')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('banner_image')->disabled()->columnSpanFull(),
                        Forms\Components\KeyValue::make('social_links')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('status')->disabled(),
                        Forms\Components\Textarea::make('suspend_reason')->disabled()->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('suspended_at')->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    ->defaultImageUrl('https://placehold.co/64x64/1e3a5f/ffffff?text=P'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Press Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn (NewsPressProfile $record) => $record->publicPath()),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Editor')
                    ->searchable()
                    ->description(fn (NewsPressProfile $record) => $record->user?->name ?: ('User #' . $record->user_id)),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => NewsPressProfile::STATUS_ACTIVE,
                        'danger' => NewsPressProfile::STATUS_SUSPENDED,
                    ]),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles')
                    ->sortable(),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        NewsPressProfile::STATUS_ACTIVE => 'Active',
                        NewsPressProfile::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (NewsPressProfile $record) => static::pressFrontendUrl($record->slug))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->visible(fn (NewsPressProfile $record) => $record->isActive())
                    ->form([
                        Forms\Components\Textarea::make('suspend_reason')
                            ->label('Reason (optional)')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Suspend press page?')
                    ->modalDescription('Readers will see “This page is temporarily unavailable” on the public press URL.')
                    ->action(function (NewsPressProfile $record, array $data) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->suspend($data['suspend_reason'] ?? null, $adminId ? (int) $adminId : null)) {
                            Notification::make()->title('Press page suspended')->warning()->send();
                        }
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (NewsPressProfile $record) => $record->isSuspended())
                    ->requiresConfirmation()
                    ->action(function (NewsPressProfile $record) {
                        if ($record->reactivate()) {
                            Notification::make()->title('Press page reactivated')->success()->send();
                        }
                    }),
                Tables\Actions\Action::make('reassignSlug')
                    ->label('Reassign Slug')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('slug')
                            ->label('New slug')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Old public link will stop working.'),
                        Forms\Components\Textarea::make('note')
                            ->label('Admin note (optional)')
                            ->rows(2),
                    ])
                    ->fillForm(fn (NewsPressProfile $record) => ['slug' => $record->slug])
                    ->action(function (NewsPressProfile $record, array $data) {
                        $slug = NewsPressProfile::normalizeSlug($data['slug'] ?? '');

                        if ($slug === '' || NewsPressProfile::isReservedSlug($slug)) {
                            Notification::make()->title('Invalid or reserved slug')->danger()->send();
                            return;
                        }

                        if (!NewsPressProfile::isSlugAvailable($slug, $record->id)) {
                            Notification::make()->title('Slug already taken')->danger()->send();
                            return;
                        }

                        $record->update(['slug' => $slug]);
                        Notification::make()
                            ->title('Slug updated to ' . $slug)
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsPressProfiles::route('/'),
            'view' => Pages\ViewNewsPressProfile::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function pressFrontendUrl(string $slug): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'https://ouptel.in')), '/');

        return $base . '/news/press/' . ltrim($slug, '/');
    }
}
