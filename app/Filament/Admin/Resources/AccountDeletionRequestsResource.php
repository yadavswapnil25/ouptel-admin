<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\AccountDeletionRequestsResource\Pages;
use App\Models\AccountDeletionRequest;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountDeletionRequestsResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-users';

    protected static ?string $model = AccountDeletionRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationLabel = 'Account Deletion Requests';

    protected static ?string $modelLabel = 'Account Deletion Request';

    protected static ?string $pluralModelLabel = 'Account Deletion Requests';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('User')
                    ->schema([
                        TextEntry::make('user.username')
                            ->label('Username'),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('user.full_name')
                            ->label('Full Name')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                InfolistSection::make('Deletion Details')
                    ->schema([
                        TextEntry::make('reason_label')
                            ->label('Reason'),
                        TextEntry::make('deletion_reason_other')
                            ->label('Custom Reason')
                            ->placeholder('—')
                            ->visible(fn ($record) => $record->deletion_reason === 'other'),
                        TextEntry::make('display_reason')
                            ->label('Reason (display)')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'completed' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => AccountDeletionRequest::STATUSES[$state] ?? $state ?? '—'),
                        TextEntry::make('created_at')
                            ->label('Requested At')
                            ->dateTime('M d, Y H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i:s'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('id'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('reason_label')
                    ->label('Reason')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('deletion_reason_other')
                    ->label('Custom Reason')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->deletion_reason_other)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('display_reason')
                    ->label('Full Reason')
                    ->limit(50)
                    ->tooltip(fn ($record): string => $record->display_reason)
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => AccountDeletionRequest::STATUSES[$state] ?? $state ?? '—'),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AccountDeletionRequest::STATUSES)
                    ->default('pending'),

                SelectFilter::make('deletion_reason')
                    ->label('Reason')
                    ->options(AccountDeletionRequest::DELETION_REASONS),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountDeletionRequests::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
