<?php

namespace App\Filament\Admin\Resources\ArticleResource\Pages;

use App\Filament\Admin\Resources\ArticleResource;
use App\Models\BlogComment;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class ManageArticleComments extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = ArticleResource::class;

    protected static string $view = 'filament.admin.resources.article-resource.pages.manage-article-comments';

    public $record;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getTableQuery(): Builder
    {
        return BlogComment::query()
            ->with('user')
            ->where('blog_id', $this->record->id)
            ->orderBy('id', 'desc');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, BlogComment $record) {
                        return $record->user ? $record->user->username : "User {$record->user_id}";
                    }),

                TextColumn::make('text')
                    ->label('Comment')
                    ->limit(100)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('replies_count')
                    ->label('Replies')
                    ->counts('replies')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->actions([
                DeleteAction::make(),
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

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getTitle(): string
    {
        return "Comments for: {$this->record->title}";
    }
}

