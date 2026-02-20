<?php

namespace App\Filament\Admin\Pages;

use App\Models\Report;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Filament\Admin\Concerns\HasPageAccess;

class ManageReports extends Page implements HasTable
{
    use HasPageAccess;

    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Manage Reports';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.manage-reports';

    public function table(Table $table): Table
    {
        return $table
            ->query(Report::query())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('report_type_display')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => $record->report_type_display)
                    ->badge()
                    ->color(fn ($record) => match($record->report_type) {
                        'post' => 'blue',
                        'profile' => 'green',
                        'page' => 'yellow',
                        'group' => 'purple',
                        'comment' => 'orange',
                        default => 'gray',
                    }),

                TextColumn::make('reporter.username')
                    ->label('Reporter')
                    ->getStateUsing(fn ($record) => $record->reporter ? $record->reporter->username : 'Unknown')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('reported_content')
                    ->label('Reported Content')
                    ->getStateUsing(fn ($record) => $record->reported_content)
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('report_reason_display')
                    ->label('Reason')
                    ->getStateUsing(fn ($record) => $record->report_reason_display)
                    ->badge()
                    ->color('danger'),

                TextColumn::make('reported_at_human')
                    ->label('Reported')
                    ->getStateUsing(fn ($record) => $record->reported_at_human)
                    ->sortable(),

                IconColumn::make('is_seen')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->is_seen)
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters([
                SelectFilter::make('report_type')
                    ->label('Report Type')
                    ->options([
                        'post' => 'Post',
                        'profile' => 'User Profile',
                        'page' => 'Page',
                        'group' => 'Group',
                        'comment' => 'Comment',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->byType($data['value'] ?? null);
                    }),

                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options([
                        'r_spam' => 'Spam',
                        'r_violence' => 'Violence',
                        'r_harassment' => 'Harassment',
                        'r_hate' => 'Hate Speech',
                        'r_terrorism' => 'Terrorism',
                        'r_nudity' => 'Nudity',
                        'r_fake' => 'Fake Account',
                        'r_other' => 'Other',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->byReason($data['value'] ?? null);
                    }),

                TernaryFilter::make('seen')
                    ->label('Status')
                    ->placeholder('All reports')
                    ->trueLabel('Seen reports')
                    ->falseLabel('Unseen reports')
                    ->queries(
                        true: fn (Builder $query) => $query->seen(),
                        false: fn (Builder $query) => $query->unseen(),
                    ),
            ])
            ->actions([
                TableAction::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Report Details')
                    ->modalContent(function ($record) {
                        return view('filament.admin.pages.report-details', ['report' => $record]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                TableAction::make('mark_safe')
                    ->label('Mark Safe')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Report as Safe')
                    ->modalDescription('Are you sure you want to mark this report as safe? This will mark the reported content as safe and remove the report.')
                    ->modalSubmitActionLabel('Mark Safe')
                    ->action(function ($record) {
                        $record->update(['seen' => 1]);
                        
                        Notification::make()
                            ->title('Report marked as safe')
                            ->success()
                            ->send();
                    }),

                TableAction::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Report')
                    ->modalDescription('Are you sure you want to delete this report? This action cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->action(function ($record) {
                        $record->delete();
                        
                        Notification::make()
                            ->title('Report deleted successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Reports')
                        ->modalDescription('Are you sure you want to delete the selected reports? This action cannot be undone.')
                        ->modalSubmitActionLabel('Delete'),

                    \Filament\Tables\Actions\BulkAction::make('mark_safe')
                        ->label('Mark as Safe')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Reports as Safe')
                        ->modalDescription('Are you sure you want to mark the selected reports as safe?')
                        ->modalSubmitActionLabel('Mark Safe')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['seen' => 1]);
                            });
                            
                            Notification::make()
                                ->title('Reports marked as safe')
                                ->body('Successfully marked ' . $records->count() . ' reports as safe.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refresh'),

            Action::make('mark_all_seen')
                ->label('Mark All as Seen')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark All Reports as Seen')
                ->modalDescription('Are you sure you want to mark all reports as seen?')
                ->modalSubmitActionLabel('Mark All Seen')
                ->action(function () {
                    Report::unseen()->update(['seen' => 1]);
                    
                    Notification::make()
                        ->title('All reports marked as seen')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }
}
