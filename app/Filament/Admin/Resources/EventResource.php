<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Event Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(300)
                            ->columnSpanFull(),

                        Select::make('poster_id')
                            ->label('Event Creator')
                            ->options(function () {
                                return User::select('user_id', 'username')
                                    ->orderBy('username')
                                    ->limit(50)
                                    ->pluck('username', 'user_id');
                            })
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => 
                                User::where('username', 'like', "%{$search}%")
                                    ->limit(20)
                                    ->pluck('username', 'user_id')
                                    ->toArray()
                            )
                            ->required(),

                        FileUpload::make('cover')
                            ->label('Event Cover')
                            ->image()
                            ->directory('events/covers')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxSize(5120) // 5MB limit
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('450')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Event Schedule')
                    ->schema([
                        DateTimePicker::make('start_date')
                            ->label('Start Date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),

                        DateTimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->native(false)
                            ->displayFormat('H:i'),

                        DateTimePicker::make('end_date')
                            ->label('End Date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d'),

                        DateTimePicker::make('end_time')
                            ->label('End Time')
                            ->required()
                            ->native(false)
                            ->displayFormat('H:i'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->orderBy('start_date', 'desc');
            })
            ->columns([
                ImageColumn::make('cover_url')
                    ->label('Cover')
                    ->circular()
                    ->size(60),

                TextColumn::make('name')
                    ->label('Event Name')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->weight('bold'),

                TextColumn::make('poster_id')
                    ->label('Creator')
                    ->formatStateUsing(function ($state) {
                        $user = User::find($state);
                        return $user ? $user->username : "User {$state}";
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Location')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Start Time')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('status_text')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Upcoming' => 'success',
                        'Ongoing' => 'warning',
                        'Past' => 'gray',
                    }),

                TextColumn::make('going_count')
                    ->label('Going')
                    ->badge()
                    ->color('info'),

                TextColumn::make('interested_count')
                    ->label('Interested')
                    ->badge()
                    ->color('warning'),

                // Comments table doesn't exist for events
                // TextColumn::make('comments_count')
                //     ->label('Comments')
                //     ->badge()
                //     ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('poster_id')
                    ->label('Creator')
                    ->options(function () {
                        return User::select('user_id', 'username')
                            ->orderBy('username')
                            ->limit(50)
                            ->pluck('username', 'user_id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $userId): Builder => $query->where('poster_id', $userId),
                        );
                    }),

                TernaryFilter::make('status')
                    ->label('Event Status')
                    ->placeholder('All events')
                    ->trueLabel('Upcoming events')
                    ->falseLabel('Past events')
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === true,
                            fn (Builder $query): Builder => $query->where('start_date', '>', now()->toDateString()),
                        )->when(
                            $data['value'] === false,
                            fn (Builder $query): Builder => $query->where('end_date', '<', now()->toDateString()),
                        );
                    }),

                Tables\Filters\Filter::make('has_attendees')
                    ->label('Has Attendees')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(SELECT COUNT(*) FROM Wo_Egoing WHERE Wo_Egoing.event_id = Wo_Events.id) > 0')),

                Tables\Filters\Filter::make('popular_events')
                    ->label('Popular Events')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('(SELECT COUNT(*) FROM Wo_Egoing WHERE Wo_Egoing.event_id = Wo_Events.id) >= 10')),
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
            ->defaultSort('start_date', 'desc')
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
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    // Widgets removed as requested
    // public static function getWidgets(): array
    // {
    //     return [
    //         Widgets\EventsStatsWidget::class,
    //     ];
    // }
}
