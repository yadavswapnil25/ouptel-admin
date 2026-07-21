<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\BoostCampaignResource\Pages;
use App\Models\BoostCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BoostCampaignResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-boost-campaigns';

    protected static ?string $model = BoostCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Boost Campaigns';

    protected static ?string $modelLabel = 'Boost Campaign';

    protected static ?string $pluralModelLabel = 'Boost Campaigns';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return Schema::hasTable('Wo_Boost_Campaigns') && static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (!Schema::hasTable('Wo_Boost_Campaigns')) {
            return null;
        }

        $count = BoostCampaign::query()->where('status', 'active')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Campaign')
                    ->schema([
                        Forms\Components\Placeholder::make('user_name')
                            ->label('Created by')
                            ->content(fn (?BoostCampaign $record): string => $record?->user?->username
                                ?? (string) ($record?->user_id ?? '—')),
                        Forms\Components\Placeholder::make('post_preview')
                            ->label('Post')
                            ->content(function (?BoostCampaign $record): string {
                                if (!$record) {
                                    return '—';
                                }
                                $text = trim((string) ($record->post?->postText ?? ''));
                                if ($text !== '') {
                                    return mb_substr($text, 0, 120);
                                }

                                return 'Post #' . $record->post_id;
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('goal')
                            ->options([
                                'reach' => 'Reach more people',
                                'engagement' => 'Get more engagement',
                                'traffic' => 'Drive traffic',
                            ])
                            ->required(),
                        Forms\Components\Select::make('audience_gender')
                            ->label('Audience gender')
                            ->options([
                                'all' => 'All',
                                'male' => 'Male',
                                'female' => 'Female',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Duration (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required(),
                        Forms\Components\TextInput::make('budget')
                            ->label('Budget (INR)')
                            ->numeric()
                            ->prefix('₹')
                            ->minValue(100)
                            ->maxValue(25000)
                            ->required(),
                        Forms\Components\Placeholder::make('gst_preview')
                            ->label('GST (18%)')
                            ->content(fn (?BoostCampaign $record): string => $record
                                ? '₹' . number_format((float) $record->gst_amount, 2)
                                : '—'),
                        Forms\Components\Placeholder::make('total_preview')
                            ->label('Total with GST')
                            ->content(fn (?BoostCampaign $record): string => $record
                                ? '₹' . number_format((float) $record->total_with_gst, 2)
                                : '—'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'draft' => 'Draft',
                                'completed' => 'Completed',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\Placeholder::make('starts_display')
                            ->label('Starts')
                            ->content(fn (?BoostCampaign $record): string => $record && $record->starts_at
                                ? date('d M Y, H:i', (int) $record->starts_at)
                                : '—'),
                        Forms\Components\Placeholder::make('ends_display')
                            ->label('Ends')
                            ->content(fn (?BoostCampaign $record): string => $record && $record->ends_at
                                ? date('d M Y, H:i', (int) $record->ends_at)
                                : '—'),
                        Forms\Components\Placeholder::make('created_display')
                            ->label('Created')
                            ->content(fn (?BoostCampaign $record): string => $record && $record->created_at
                                ? date('d M Y, H:i', (int) $record->created_at)
                                : '—'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (!Schema::hasTable('Wo_Boost_Campaigns')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->with(['user', 'post'])->orderByDesc('id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('post.postText')
                    ->label('Post')
                    ->formatStateUsing(function ($state, BoostCampaign $record): string {
                        $text = trim((string) $state);
                        if ($text !== '') {
                            return mb_substr($text, 0, 50);
                        }

                        return 'Post #' . $record->post_id;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('post', function (Builder $q) use ($search) {
                            $q->where('postText', 'like', "%{$search}%");
                        })->orWhere('post_id', 'like', "%{$search}%");
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('goal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('audience_gender')
                    ->label('Gender')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('duration_days')
                    ->label('Days')
                    ->suffix('d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('budget')
                    ->label('Budget')
                    ->formatStateUsing(fn ($state): string => '₹' . number_format((float) $state, 0))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_with_gst')
                    ->label('Total + GST')
                    ->formatStateUsing(fn ($state): string => '₹' . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'completed' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->formatStateUsing(fn ($state): string => $state ? date('d M Y', (int) $state) : '—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('goal')
                    ->options([
                        'reach' => 'Reach',
                        'engagement' => 'Engagement',
                        'traffic' => 'Traffic',
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
                        ->visible(fn (BoostCampaign $record): bool => $record->status === 'active')
                        ->requiresConfirmation()
                        ->action(function (BoostCampaign $record): void {
                            $record->update([
                                'status' => 'paused',
                                'updated_at' => time(),
                            ]);
                            DB::table('Wo_Posts')->where('id', $record->post_id)->update(['boosted' => '0']);
                            Notification::make()->title('Campaign paused')->success()->send();
                        }),
                    Tables\Actions\Action::make('resume')
                        ->label('Resume')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (BoostCampaign $record): bool => in_array($record->status, ['paused', 'draft'], true))
                        ->requiresConfirmation()
                        ->action(function (BoostCampaign $record): void {
                            $now = time();
                            $record->update([
                                'status' => 'active',
                                'starts_at' => $now,
                                'ends_at' => $now + ((int) $record->duration_days * 86400),
                                'updated_at' => $now,
                            ]);
                            DB::table('Wo_Posts')->where('id', $record->post_id)->update(['boosted' => '1']);
                            Notification::make()->title('Campaign resumed')->success()->send();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->after(function (BoostCampaign $record): void {
                            DB::table('Wo_Posts')->where('id', $record->post_id)->update(['boosted' => '0']);
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
            'index' => Pages\ListBoostCampaigns::route('/'),
            'view' => Pages\ViewBoostCampaign::route('/{record}'),
            'edit' => Pages\EditBoostCampaign::route('/{record}/edit'),
        ];
    }
}
