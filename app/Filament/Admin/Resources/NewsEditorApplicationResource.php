<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsEditorApplicationResource\Pages;
use App\Models\NewsEditorApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class NewsEditorApplicationResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-editor-applications';
    protected static ?string $model = NewsEditorApplication::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Editor Applications';
    protected static ?string $modelLabel = 'Editor Application';
    protected static ?string $pluralModelLabel = 'Editor Applications';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Applicant')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')->disabled(),
                        Forms\Components\TextInput::make('email')->disabled(),
                        Forms\Components\TextInput::make('phone')->disabled(),
                        Forms\Components\TextInput::make('city')->disabled(),
                        Forms\Components\TextInput::make('state')->disabled(),
                        Forms\Components\TextInput::make('user_id')->label('User ID')->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Application')
                    ->schema([
                        Forms\Components\TagsInput::make('preferred_categories')->disabled(),
                        Forms\Components\Textarea::make('bio')->rows(4)->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('portfolio_link')->disabled()->columnSpanFull(),
                        Forms\Components\Textarea::make('reason')->rows(4)->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('id_proof_name')->label('ID Proof')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->disabled(),
                        Forms\Components\Textarea::make('review_note')->rows(3)->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('preferred_categories')
                    ->label('Beats')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->wrap()
                    ->limit(40),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve editor application')
                    ->modalDescription(fn (NewsEditorApplication $record) => "Approve {$record->full_name} as a news portal editor?")
                    ->visible(fn (NewsEditorApplication $record) => $record->isPending())
                    ->action(function (NewsEditorApplication $record) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->approve($adminId ? (int) $adminId : null)) {
                            Notification::make()
                                ->title('Editor approved')
                                ->body("{$record->full_name} can now access the Editor Panel.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Approval failed')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('review_note')
                            ->label('Rejection reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (NewsEditorApplication $record) => $record->isPending())
                    ->action(function (NewsEditorApplication $record, array $data) {
                        $adminId = Auth::user()?->user_id ?? Auth::id();
                        if ($record->reject($adminId ? (int) $adminId : null, $data['review_note'] ?? null)) {
                            Notification::make()
                                ->title('Application rejected')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Rejection failed')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsEditorApplications::route('/'),
            'view' => Pages\ViewNewsEditorApplication::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
