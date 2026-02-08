<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ForumSectionResource\Pages;
use App\Models\ForumSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ForumSectionResource extends Resource
{
    protected static ?string $model = ForumSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Forum Sections';
    protected static ?string $modelLabel = 'Forum Section';
    protected static ?string $pluralModelLabel = 'Forum Sections';
    protected static ?string $navigationGroup = 'Manage Features';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Section Information')
                    ->schema([
                        Forms\Components\TextInput::make('section_name')
                            ->label('Section Name')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('Enter section name'),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(300)
                            ->placeholder('Enter section description'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('section_name')
                    ->label('Section Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_description')
                    ->label('Has Description')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('description')->where('description', '!=', ''))
                    ->indicateUsing(fn (): string => 'Has Description'),
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
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListForumSections::route('/'),
            'create' => Pages\CreateForumSection::route('/create'),
            'view' => Pages\ViewForumSection::route('/{record}'),
            'edit' => Pages\EditForumSection::route('/{record}/edit'),
        ];
    }
}

