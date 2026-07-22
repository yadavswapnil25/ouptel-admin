<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsEditorResource\Pages;
use App\Models\NewsArticle;
use App\Models\NewsEditor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsEditorResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-editors';
    protected static ?string $model = NewsEditor::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Manage Editors';
    protected static ?string $modelLabel = 'News Editor';
    protected static ?string $pluralModelLabel = 'News Editors';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Editor')
                    ->schema([
                        Forms\Components\TextInput::make('user_id')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'revoked' => 'Revoked',
                            ])
                            ->disabled(),
                        Forms\Components\TagsInput::make('preferred_categories')->disabled(),
                        Forms\Components\DateTimePicker::make('approved_at')->disabled(),
                        Forms\Components\DateTimePicker::make('revoked_at')->disabled(),
                        Forms\Components\Textarea::make('revoke_note')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('preferred_categories')
                    ->label('Beats')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles')
                    ->counts('articles')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_count')
                    ->label('Published')
                    ->getStateUsing(fn (NewsEditor $record) => NewsArticle::where('author_id', $record->user_id)
                        ->where('status', 'published')
                        ->count()),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'revoked',
                    ]),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->defaultSort('approved_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'revoked' => 'Revoked',
                    ])
                    ->default('active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('revoke_note')
                            ->label('Reason')
                            ->rows(3),
                    ])
                    ->visible(fn (NewsEditor $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (NewsEditor $record, array $data) {
                        if ($record->revoke($data['revoke_note'] ?? null)) {
                            Notification::make()
                                ->title('Editor access revoked')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsEditors::route('/'),
            'view' => Pages\ViewNewsEditor::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
