<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Notifications\Notification;
use App\Filament\Admin\Concerns\HasPanelAccess;

class PageResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-pages';
    protected static ?string $model = Page::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Manage Pages';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static ?string $title = 'Pages';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Page Information')
                    ->schema([
                        TextInput::make('page_name')
                            ->label('Page Name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        
                        TextInput::make('page_title')
                            ->label('Page Title')
                            ->required()
                            ->maxLength(255),
                        
                        Textarea::make('page_description')
                            ->label('Description')
                            ->rows(3),
                        
                        Select::make('page_category')
                            ->label('Category')
                            ->options([
                                'business' => 'Business',
                                'entertainment' => 'Entertainment',
                                'education' => 'Education',
                                'health' => 'Health',
                                'technology' => 'Technology',
                                'sports' => 'Sports',
                                'news' => 'News',
                                'other' => 'Other',
                            ])
                            ->default('other'),
                        
                        Select::make('user_id')
                            ->label('Owner')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->required(),
                        
                        Toggle::make('verified')
                            ->label('Verified Page'),
                        
                        Toggle::make('active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Page Details')
                    ->schema([
                        TextInput::make('website')
                            ->label('Website')
                            ->url(),
                        
                        TextInput::make('phone')
                            ->label('Phone'),
                        
                        TextInput::make('address')
                            ->label('Address'),
                        
                        Textarea::make('page_description')
                            ->label('About')
                            ->rows(4),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Media')
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Avatar')
                            ->image()
                            ->directory('pages/avatars'),
                        
                        FileUpload::make('cover')
                            ->label('Cover Photo')
                            ->image()
                            ->directory('pages/covers'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\CheckboxColumn::make('page_id'),
                
                TextColumn::make('page_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->size(40),
                
                TextColumn::make('page_name')
                    ->label('Page Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn (Page $record): string => $record->url)
                    ->openUrlInNewTab(),
                
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable()
                    ->searchable()
                    ->url(fn (Page $record): string => $record->owner->url ?? '#')
                    ->openUrlInNewTab(),
                
                TextColumn::make('category_name')
                    ->label('Category')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Business' => 'success',
                        'Entertainment' => 'warning',
                        'Education' => 'info',
                        'Health' => 'danger',
                        'Technology' => 'primary',
                        'Sports' => 'secondary',
                        'News' => 'gray',
                        'Other' => 'gray',
                        'Uncategorized' => 'gray',
                        default => 'gray',
                    }),
                
                BooleanColumn::make('verified')
                    ->label('Verified')
                    ->sortable(),
                
                BooleanColumn::make('active')
                    ->label('Active')
                    ->sortable(),
                
                TextColumn::make('page_created')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('page_category')
                    ->label('Category')
                    ->options([
                        'business' => 'Business',
                        'entertainment' => 'Entertainment',
                        'education' => 'Education',
                        'health' => 'Health',
                        'technology' => 'Technology',
                        'sports' => 'Sports',
                        'news' => 'News',
                        'other' => 'Other',
                    ]),
                
                Filter::make('verified')
                    ->label('Verified Pages')
                    ->query(fn (Builder $query): Builder => $query->where('verified', true))
                    ->indicateUsing(fn (): string => 'Verified Pages Only'),
                
                Filter::make('active')
                    ->label('Active Pages')
                    ->query(fn (Builder $query): Builder => $query->where('active', true)),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->each->delete();
                            
                            Notification::make()
                                ->title("Deleted {$count} pages successfully!")
                                ->success()
                                ->send();
                        }),
                    
                    BulkAction::make('verify')
                        ->label('Verify Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->each->update(['verified' => true]);
                            
                            Notification::make()
                                ->title("Verified {$count} pages successfully!")
                                ->success()
                                ->send();
                        }),
                    
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $count = $records->count();
                            $records->each->update(['active' => true]);
                            
                            Notification::make()
                                ->title("Activated {$count} pages successfully!")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('page_id', 'desc');
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'view' => Pages\ViewPage::route('/{record}'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}





