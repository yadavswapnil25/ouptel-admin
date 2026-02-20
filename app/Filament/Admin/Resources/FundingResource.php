<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FundingResource\Pages;
use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Models\Funding;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FundingResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-funding';
    protected static ?string $model = Funding::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Funding';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Funding Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(100),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->maxLength(600)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Target Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0.01),
                        
                        Forms\Components\Select::make('user_id')
                            ->label('Creator')
                            ->options(User::pluck('username', 'user_id')->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Funding Image')
                            ->image()
                            ->directory('funding')
                            ->visibility('public')
                            ->dehydrated(fn ($state) => filled($state))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->size(60)
                    ->circular(false),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('formatted_amount')
                    ->label('Target Amount')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('amount', $direction);
                    }),
                
                Tables\Columns\TextColumn::make('formatted_total_raised')
                    ->label('Raised')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withSum('donations', 'amount')->orderBy('donations_sum_amount', $direction);
                    }),
                
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 75 => 'warning',
                        $state >= 50 => 'info',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('donation_count')
                    ->label('Donations')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\IconColumn::make('is_completed')
                    ->label('Completed')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('time')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Creator')
                    ->options(User::pluck('username', 'user_id')->toArray())
                    ->searchable(),
                
                Tables\Filters\Filter::make('completed')
                    ->label('Completed')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('id IN (
                        SELECT f.id FROM Wo_Funding f
                        INNER JOIN Wo_Funding_Raise fr ON f.id = fr.funding_id
                        GROUP BY f.id, f.amount
                        HAVING SUM(fr.amount) >= f.amount
                    )')),
                
                Tables\Filters\Filter::make('active')
                    ->label('Active')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('id NOT IN (
                        SELECT f.id FROM Wo_Funding f
                        INNER JOIN Wo_Funding_Raise fr ON f.id = fr.funding_id
                        GROUP BY f.id, f.amount
                        HAVING SUM(fr.amount) >= f.amount
                    )')),
                
                Tables\Filters\Filter::make('has_donations')
                    ->label('Has Donations')
                    ->query(fn (Builder $query): Builder => $query->has('donations')),
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
            'index' => Pages\ListFundings::route('/'),
            'create' => Pages\CreateFunding::route('/create'),
            'view' => Pages\ViewFunding::route('/{record}'),
            'edit' => Pages\EditFunding::route('/{record}/edit'),
        ];
    }
}
