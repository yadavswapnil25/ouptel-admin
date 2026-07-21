<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\UserAdResource\Pages;
use App\Models\UserAd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserAdResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-user-ads';

    protected static ?string $model = UserAd::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'User Ads';

    protected static ?string $modelLabel = 'User Ad';

    protected static ?string $pluralModelLabel = 'User Ads';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Schema::hasTable('Wo_UserAds') && static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (!Schema::hasTable('Wo_UserAds')) {
            return null;
        }

        $count = UserAd::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        $schema = [
            Forms\Components\Section::make('Advertisement')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Business / Brand')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('url')
                        ->label('Website URL')
                        ->url()
                        ->required()
                        ->maxLength(512),
                    Forms\Components\TextInput::make('headline')
                        ->label('Headline')
                        ->required()
                        ->maxLength(200),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Targeting')
                ->schema([
                    Forms\Components\Select::make('gender')
                        ->options([
                            'all' => 'All genders',
                            'male' => 'Male',
                            'female' => 'Female',
                        ])
                        ->required(),
                    Forms\Components\Select::make('age_group')
                        ->label('Age group')
                        ->options([
                            'all' => 'All ages',
                            '18-24' => '18–24',
                            '25-34' => '25–34',
                            '35-44' => '35–44',
                            '45-54' => '45–54',
                            '55+' => '55+',
                        ])
                        ->visible(fn () => Schema::hasColumn('Wo_UserAds', 'age_group'))
                        ->default('all'),
                    Forms\Components\Select::make('appears')
                        ->label('Placement')
                        ->options([
                            'post' => 'News feed',
                            'sidebar' => 'Sidebar',
                            'video' => 'Video feed',
                        ])
                        ->required(),
                    Forms\Components\Select::make('bidding')
                        ->label('Billing')
                        ->options([
                            'views' => 'Pay per view',
                            'clicks' => 'Pay per click',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('community_preferences')
                        ->label('Community preference IDs')
                        ->helperText('Comma-separated community preference IDs')
                        ->visible(fn () => Schema::hasColumn('Wo_UserAds', 'community_preferences'))
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Budget & schedule')
                ->schema([
                    Forms\Components\TextInput::make('budget')
                        ->label('Budget (INR)')
                        ->numeric()
                        ->prefix('₹')
                        ->minValue(100)
                        ->maxValue(25000)
                        ->required(),
                    Forms\Components\Placeholder::make('gst_preview')
                        ->label('GST (18%)')
                        ->content(fn (?UserAd $record): string => $record
                            ? '₹' . number_format((float) $record->gst_amount, 2)
                            : '—'),
                    Forms\Components\Placeholder::make('total_preview')
                        ->label('Total with GST')
                        ->content(fn (?UserAd $record): string => $record
                            ? '₹' . number_format((float) $record->total_with_gst, 2)
                            : '—'),
                    Forms\Components\TextInput::make('start')
                        ->label('Start date')
                        ->placeholder('YYYY-MM-DD'),
                    Forms\Components\TextInput::make('end')
                        ->label('End date')
                        ->placeholder('YYYY-MM-DD'),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'paused' => 'Paused',
                        ])
                        ->required()
                        ->formatStateUsing(fn (?UserAd $record) => $record?->status_label ?? 'paused')
                        ->dehydrateStateUsing(fn ($state) => $state),
                ])
                ->columns(3),

            Forms\Components\Section::make('Performance')
                ->schema([
                    Forms\Components\Placeholder::make('user_name')
                        ->label('Created by')
                        ->content(fn (?UserAd $record): string => $record?->user?->username
                            ?? (string) ($record?->user_id ?? '—')),
                    Forms\Components\Placeholder::make('views_display')
                        ->label('Views')
                        ->content(fn (?UserAd $record): string => (string) ($record?->views ?? 0)),
                    Forms\Components\Placeholder::make('clicks_display')
                        ->label('Clicks')
                        ->content(fn (?UserAd $record): string => (string) ($record?->clicks ?? 0)),
                    Forms\Components\TextInput::make('media_url')
                        ->label('Media URL')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?UserAd $record): string => $record?->media_url ?? 'No media')
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ];

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (!Schema::hasTable('Wo_UserAds')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->with('user')->orderByDesc('id');
            })
            ->columns([
                Tables\Columns\ImageColumn::make('media_url')
                    ->label('Media')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl(url('images/placeholders/post-avatar.svg')),
                Tables\Columns\TextColumn::make('headline')
                    ->label('Ad')
                    ->description(fn (UserAd $record): string => $record->name)
                    ->searchable(['headline', 'name'])
                    ->limit(40)
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('appears')
                    ->label('Placement')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'post' => 'Feed',
                        'sidebar' => 'Sidebar',
                        'video' => 'Video',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('budget')
                    ->label('Budget')
                    ->formatStateUsing(fn ($state): string => '₹' . number_format((float) $state, 0))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_with_gst')
                    ->label('Total + GST')
                    ->formatStateUsing(fn ($state): string => '₹' . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('views')
                    ->label('Views')
                    ->sortable(),
                Tables\Columns\TextColumn::make('clicks')
                    ->label('Clicks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('posted')
                    ->label('Created')
                    ->formatStateUsing(fn ($state): string => $state ? date('d M Y', (int) $state) : '—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === 'active') {
                            return $query->active();
                        }
                        if ($value === 'paused') {
                            return $query->paused();
                        }

                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('appears')
                    ->label('Placement')
                    ->options([
                        'post' => 'News feed',
                        'sidebar' => 'Sidebar',
                        'video' => 'Video feed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('pause')
                        ->label('Pause')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->visible(fn (UserAd $record): bool => $record->status_label === 'active')
                        ->requiresConfirmation()
                        ->action(function (UserAd $record): void {
                            $record->status = 'paused';
                            $record->save();
                            Notification::make()->title('Ad paused')->success()->send();
                        }),
                    Tables\Actions\Action::make('resume')
                        ->label('Resume')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (UserAd $record): bool => $record->status_label !== 'active')
                        ->requiresConfirmation()
                        ->action(function (UserAd $record): void {
                            $record->status = 'active';
                            $record->save();
                            Notification::make()->title('Ad resumed')->success()->send();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->after(function (UserAd $record): void {
                            $path = trim((string) ($record->ad_media ?? ''));
                            if ($path !== '' && !str_starts_with($path, 'http')) {
                                Storage::disk('public')->delete($path);
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserAds::route('/'),
            'view' => Pages\ViewUserAd::route('/{record}'),
            'edit' => Pages\EditUserAd::route('/{record}/edit'),
        ];
    }
}
