<?php

namespace App\Filament\Admin\Pages;

use App\Models\TermsPage;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ManageTermsPages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Manage Terms Pages';

    protected static ?string $navigationGroup = 'Pages';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.manage-terms-pages';

    public function table(Table $table): Table
    {
        return $table
            ->query(TermsPage::getTermsPages()->toQuery())
            ->columns([
                TextColumn::make('display_name')
                    ->label('Page Name')
                    ->getStateUsing(fn ($record) => $record->display_name)
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->getStateUsing(fn ($record) => $record->description)
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('lang_key')
                    ->label('Page Type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', $state)),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->getStateUsing(fn ($record) => $record->updated_at ? $record->updated_at->format('Y-m-d H:i:s') : 'Never')
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->color('success')
                    ->form([
                        Forms\Components\RichEditor::make('english')
                            ->label('English Content (HTML Allowed)')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ]),
                        Forms\Components\RichEditor::make('arabic')
                            ->label('Arabic Content (HTML Allowed)')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ]),
                    ])
                    ->fillForm(fn ($record) => [
                        'english' => $record->english_content,
                        'arabic' => $record->arabic_content,
                    ])
                    ->action(function (array $data, $record) {
                        $record->update([
                            'english' => $data['english'],
                            'arabic' => $data['arabic'],
                        ]);
                        
                        Notification::make()
                            ->title('Page updated successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('lang_key')
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refresh'),
        ];
    }

    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }
}
