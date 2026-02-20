<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VerificationRequestsResource\Pages;
use App\Models\VerificationRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\ViewField;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Filament\Admin\Concerns\HasPanelAccess;

class VerificationRequestsResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-verification-requests';
    protected static ?string $model = VerificationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Verification Requests';

    protected static ?string $modelLabel = 'Verification Request';

    protected static ?string $pluralModelLabel = 'Verification Requests';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNotNull('badge_type')
            ->where('status', 'pending')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        TextInput::make('user_name')
                            ->label('Full Name')
                            ->maxLength(150)
                            ->disabled(),

                        Select::make('type')
                            ->label('Verification Type')
                            ->options([
                                'User' => 'User Verification',
                                'Page' => 'Page Verification',
                            ])
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Badge Verification Details')
                    ->schema([
                        Select::make('badge_type')
                            ->label('Badge Type Requested')
                            ->options(VerificationRequest::BADGE_TYPES)
                            ->disabled(),

                        Select::make('id_proof_type')
                            ->label('ID Proof Type')
                            ->options(VerificationRequest::ID_PROOF_TYPES)
                            ->disabled(),

                        TextInput::make('id_proof_number')
                            ->label('ID Proof Number')
                            ->disabled(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->disabled(),

                        Select::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->options(VerificationRequest::REJECTION_REASONS)
                            ->visible(fn ($record) => $record?->status === 'rejected')
                            ->disabled(),

                        Placeholder::make('submitted_at')
                            ->label('Submitted At')
                            ->content(fn ($record) => $record?->submitted_at?->format('M d, Y H:i:s') ?? 'N/A'),

                        Placeholder::make('reviewed_at')
                            ->label('Reviewed At')
                            ->content(fn ($record) => $record?->reviewed_at?->format('M d, Y H:i:s') ?? 'Not reviewed yet'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record?->badge_type !== null),

                Section::make('ID Proof Images')
                    ->schema([
                        Placeholder::make('front_image_preview')
                            ->label('Front Image')
                            ->content(function ($record) {
                                if ($record?->id_proof_front_image) {
                                    $url = asset('storage/' . $record->id_proof_front_image);
                                    return new \Illuminate\Support\HtmlString(
                                        "<a href='{$url}' target='_blank'><img src='{$url}' class='max-w-md max-h-64 rounded-lg shadow-lg cursor-pointer hover:opacity-80' /></a>"
                                    );
                                }
                                return 'No image uploaded';
                            }),

                        Placeholder::make('back_image_preview')
                            ->label('Back Image')
                            ->content(function ($record) {
                                if ($record?->id_proof_back_image) {
                                    $url = asset('storage/' . $record->id_proof_back_image);
                                    return new \Illuminate\Support\HtmlString(
                                        "<a href='{$url}' target='_blank'><img src='{$url}' class='max-w-md max-h-64 rounded-lg shadow-lg cursor-pointer hover:opacity-80' /></a>"
                                    );
                                }
                                return 'No image uploaded';
                            }),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record?->badge_type !== null),

                Section::make('Legacy Verification Information')
                    ->schema([
                        TextInput::make('message')
                            ->label('Message')
                            ->maxLength(500)
                            ->disabled(),

                        TextInput::make('passport')
                            ->label('Passport/Document URL')
                            ->maxLength(3000)
                            ->disabled(),

                        TextInput::make('photo')
                            ->label('Photo URL')
                            ->maxLength(3000)
                            ->disabled(),
                    ])
                    ->columns(1)
                    ->collapsed()
                    ->visible(fn ($record) => $record?->badge_type === null),
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

                TextColumn::make('user_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('badge_type')
                    ->label('Badge Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'blue' => 'info',
                        'golden' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'blue' => '🔵 Blue',
                        'golden' => '🏆 Golden',
                        default => 'N/A',
                    }),

                TextColumn::make('id_proof_type')
                    ->label('ID Proof')
                    ->formatStateUsing(fn (?string $state): string => 
                        VerificationRequest::ID_PROOF_TYPES[$state] ?? $state ?? 'N/A'
                    )
                    ->toggleable(),

                TextColumn::make('id_proof_number')
                    ->label('ID Number')
                    ->limit(20)
                    ->toggleable()
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => '⏳ Pending',
                        'approved' => '✅ Approved',
                        'rejected' => '❌ Rejected',
                        default => 'Legacy',
                    }),

                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->formatStateUsing(fn (?string $state): string => 
                        VerificationRequest::REJECTION_REASONS[$state] ?? $state ?? '-'
                    )
                    ->limit(30)
                    ->tooltip(fn ($record): ?string => 
                        VerificationRequest::REJECTION_REASONS[$record->rejection_reason] ?? null
                    )
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => '⏳ Pending Review',
                        'approved' => '✅ Approved',
                        'rejected' => '❌ Rejected',
                    ])
                    ->default('pending'),

                SelectFilter::make('badge_type')
                    ->label('Badge Type')
                    ->options([
                        'blue' => '🔵 Blue Badge',
                        'golden' => '🏆 Golden Badge',
                    ]),

                SelectFilter::make('id_proof_type')
                    ->label('ID Proof Type')
                    ->options(VerificationRequest::ID_PROOF_TYPES),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Approve Action
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Verification Request')
                    ->modalDescription(fn ($record) => "Are you sure you want to approve this verification request for {$record->user_name}? They will receive a {$record->badge_type} badge.")
                    ->modalSubmitActionLabel('Yes, Approve')
                    ->visible(fn ($record) => $record->badge_type !== null && $record->status === 'pending')
                    ->action(function ($record) {
                        $adminUserId = Auth::id() ?? 1; // Get current admin user ID
                        
                        if ($record->approve($adminUserId)) {
                            Notification::make()
                                ->title('Verification Approved')
                                ->body("User {$record->user_name} has been verified with a {$record->badge_type} badge.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Approval Failed')
                                ->body('Failed to approve the verification request.')
                                ->danger()
                                ->send();
                        }
                    }),

                // Reject Action
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Verification Request')
                    ->modalDescription(fn ($record) => "Please select a reason for rejecting the verification request for {$record->user_name}.")
                    ->form([
                        Select::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->options(VerificationRequest::REJECTION_REASONS)
                            ->required()
                            ->helperText('The user will be notified with this reason.'),
                    ])
                    ->modalSubmitActionLabel('Reject Request')
                    ->visible(fn ($record) => $record->badge_type !== null && $record->status === 'pending')
                    ->action(function ($record, array $data) {
                        $adminUserId = Auth::id() ?? 1; // Get current admin user ID
                        
                        if ($record->reject($adminUserId, $data['rejection_reason'])) {
                            Notification::make()
                                ->title('Verification Rejected')
                                ->body("Verification request for {$record->user_name} has been rejected.")
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Rejection Failed')
                                ->body('Failed to reject the verification request.')
                                ->danger()
                                ->send();
                        }
                    }),

                // View Images Action
                Action::make('view_images')
                    ->label('View ID')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->modalHeading('ID Proof Images')
                    ->modalContent(function ($record) {
                        $frontUrl = $record->id_proof_front_image ? asset('storage/' . $record->id_proof_front_image) : null;
                        $backUrl = $record->id_proof_back_image ? asset('storage/' . $record->id_proof_back_image) : null;
                        
                        return view('filament.modals.verification-images', [
                            'frontUrl' => $frontUrl,
                            'backUrl' => $backUrl,
                            'idProofType' => VerificationRequest::ID_PROOF_TYPES[$record->id_proof_type] ?? 'Unknown',
                            'idProofNumber' => $record->id_proof_number,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn ($record) => $record->badge_type !== null && ($record->id_proof_front_image || $record->id_proof_back_image)),

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
            ->defaultPaginationPageOption(10)
            ->poll('30s'); // Auto-refresh every 30 seconds
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
            'index' => Pages\ListVerificationRequests::route('/'),
            'create' => Pages\CreateVerificationRequest::route('/create'),
            'edit' => Pages\EditVerificationRequest::route('/{record}/edit'),
        ];
    }
}
