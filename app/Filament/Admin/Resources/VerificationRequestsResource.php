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
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class VerificationRequestsResource extends Resource
{
    protected static ?string $model = VerificationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Verification Requests';

    protected static ?string $modelLabel = 'Verification Request';

    protected static ?string $pluralModelLabel = 'Verification Requests';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Verification Information')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'user' => 'User Verification',
                                'page' => 'Page Verification',
                            ])
                            ->required(),

                        TextInput::make('user_name')
                            ->label('Name/Title')
                            ->maxLength(150)
                            ->required(),

                        TextInput::make('message')
                            ->label('Message')
                            ->maxLength(500),

                        TextInput::make('passport')
                            ->label('Passport/Document URL')
                            ->maxLength(3000),

                        TextInput::make('photo')
                            ->label('Photo URL')
                            ->maxLength(3000),

                        TextInput::make('seen')
                            ->label('Seen Timestamp')
                            ->numeric(),
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

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'info',
                        'page' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'user' => 'User',
                        'page' => 'Page',
                        default => ucfirst($state),
                    }),

                TextColumn::make('user_name')
                    ->label('Name/Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('message')
                    ->label('Message')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('passport')
                    ->label('Document URL')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('photo')
                    ->label('Photo URL')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('seen')
                    ->label('Seen')
                    ->date('M d, Y H:i')
                    ->placeholder('Not seen')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'user' => 'User Verification',
                        'page' => 'Page Verification',
                    ]),

                SelectFilter::make('seen')
                    ->label('Status')
                    ->options([
                        '0' => 'Not Seen',
                        '1' => 'Seen',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === '0',
                            fn (Builder $query): Builder => $query->where('seen', 0),
                            fn (Builder $query): Builder => $query->when(
                                $data['value'] === '1',
                                fn (Builder $query): Builder => $query->where('seen', '>', 0)
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
            'index' => Pages\ListVerificationRequests::route('/'),
            'create' => Pages\CreateVerificationRequest::route('/create'),
            'edit' => Pages\EditVerificationRequest::route('/{record}/edit'),
        ];
    }
}
