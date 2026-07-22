<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Concerns\HasPanelAccess;
use App\Filament\Admin\Resources\NewsAdResource\Pages;
use App\Models\NewsAd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsAdResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-news-ads';
    protected static ?string $model = NewsAd::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'News Ads';
    protected static ?string $modelLabel = 'News Ad';
    protected static ?string $pluralModelLabel = 'News Ads';
    protected static ?string $navigationGroup = 'News Management';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ad Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Internal name / Brand')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('headline')
                            ->label('Headline (optional)')
                            ->maxLength(255)
                            ->helperText('Shown under the image when set.'),

                        Forms\Components\Select::make('placement')
                            ->options(NewsAd::PLACEMENTS)
                            ->default('sidebar')
                            ->required(),

                        Forms\Components\TextInput::make('display_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),

                        Forms\Components\Toggle::make('status')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Creative & Link')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Ad Image')
                            ->image()
                            ->disk('public')
                            ->directory('news-ads')
                            ->visibility('public')
                            ->imageEditor()
                            ->helperText('Recommended: 300×250 for sidebar ads.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('link_url')
                            ->label('Click URL')
                            ->url()
                            ->maxLength(512)
                            ->placeholder('https://example.com')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->square(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('placement')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => NewsAd::PLACEMENTS[$state] ?? $state),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicks')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_order')
            ->filters([
                Tables\Filters\SelectFilter::make('placement')
                    ->options(NewsAd::PLACEMENTS),
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsAds::route('/'),
            'create' => Pages\CreateNewsAd::route('/create'),
            'edit' => Pages\EditNewsAd::route('/{record}/edit'),
        ];
    }
}
