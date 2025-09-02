<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\JobResource\Pages;
use App\Filament\Admin\Resources\JobResource\Widgets;
use App\Models\Job;
use App\Models\User;
use App\Models\Page;
use App\Models\JobCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Jobs';

    protected static ?string $navigationGroup = 'Content Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Job Title')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Job Description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('user_id')
                            ->label('Publisher')
                            ->relationship('user', 'username')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('page_id')
                            ->label('Page')
                            ->relationship('page', 'page_name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(JobCategory::all()->mapWithKeys(function ($category) {
                                return [$category->id => $category->name];
                            }))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('job_type')
                            ->label('Job Type')
                            ->options([
                                'full_time' => 'Full Time',
                                'part_time' => 'Part Time',
                                'contract' => 'Contract',
                                'freelance' => 'Freelance',
                                'internship' => 'Internship',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.000001),

                        Forms\Components\TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.000001),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Salary Information')
                    ->schema([
                        Forms\Components\TextInput::make('minimum')
                            ->label('Minimum Salary')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),

                        Forms\Components\TextInput::make('maximum')
                            ->label('Maximum Salary')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01),

                        Forms\Components\TextInput::make('salary_date')
                            ->label('Salary Date')
                            ->maxLength(50),

                        Forms\Components\Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                                'GBP' => 'GBP',
                                'CAD' => 'CAD',
                            ])
                            ->default('USD'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Application Questions')
                    ->schema([
                        Forms\Components\TextInput::make('question_one')
                            ->label('Question 1')
                            ->maxLength(200),

                        Forms\Components\Select::make('question_one_type')
                            ->label('Question 1 Type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'select' => 'Select',
                                'radio' => 'Radio',
                                'checkbox' => 'Checkbox',
                            ]),

                        Forms\Components\Textarea::make('question_one_answers')
                            ->label('Question 1 Answers (one per line)')
                            ->rows(3),

                        Forms\Components\TextInput::make('question_two')
                            ->label('Question 2')
                            ->maxLength(200),

                        Forms\Components\Select::make('question_two_type')
                            ->label('Question 2 Type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'select' => 'Select',
                                'radio' => 'Radio',
                                'checkbox' => 'Checkbox',
                            ]),

                        Forms\Components\Textarea::make('question_two_answers')
                            ->label('Question 2 Answers (one per line)')
                            ->rows(3),

                        Forms\Components\TextInput::make('question_three')
                            ->label('Question 3')
                            ->maxLength(200),

                        Forms\Components\Select::make('question_three_type')
                            ->label('Question 3 Type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'select' => 'Select',
                                'radio' => 'Radio',
                                'checkbox' => 'Checkbox',
                            ]),

                        Forms\Components\Textarea::make('question_three_answers')
                            ->label('Question 3 Answers (one per line)')
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media & Settings')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Job Image')
                            ->image()
                            ->directory('jobs')
                            ->visibility('public')
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\Select::make('image_type')
                            ->label('Image Type')
                            ->options([
                                'image' => 'Image',
                                'video' => 'Video',
                            ])
                            ->default('image'),

                        Forms\Components\Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this job is active and visible to users'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('user.username')
                    ->label('Publisher')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        return $record->user ? $record->user->username : 'Unknown';
                    }),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('category_name')
                    ->label('Category')
                    ->formatStateUsing(function ($record) {
                        if ($record->category) {
                            $category = JobCategory::find($record->category);
                            return $category ? $category->name : "Category {$record->category}";
                        }
                        return 'No Category';
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('job_type_text')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Full Time' => 'success',
                        'Part Time' => 'warning',
                        'Contract' => 'info',
                        'Freelance' => 'gray',
                        'Internship' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('salary_range')
                    ->label('Salary Range')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('minimum', $direction);
                    }),

                TextColumn::make('applications_count')
                    ->label('Applications')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('posted_date')
                    ->label('Posted')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('time', $direction);
                    }),

                IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('actions')
                    ->label('Actions')
                    ->formatStateUsing(function ($record) {
                        return view('filament.admin.resources.job-resource.actions', compact('record'));
                    })
                    ->html(),
            ])
            ->filters([
                SelectFilter::make('job_type')
                    ->label('Job Type')
                    ->options([
                        'full_time' => 'Full Time',
                        'part_time' => 'Part Time',
                        'contract' => 'Contract',
                        'freelance' => 'Freelance',
                        'internship' => 'Internship',
                    ]),

                SelectFilter::make('category')
                    ->label('Category')
                    ->options(JobCategory::all()->mapWithKeys(function ($category) {
                        return [$category->id => $category->name];
                    }))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $categoryId): Builder => $query->where('category', $categoryId),
                        );
                    }),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('All jobs')
                    ->trueLabel('Active jobs')
                    ->falseLabel('Inactive jobs'),

                SelectFilter::make('user_id')
                    ->label('Publisher')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_applications')
                    ->label('Has Applications')
                    ->query(fn (Builder $query): Builder => $query->whereHas('applications')),

                Tables\Filters\Filter::make('salary_range')
                    ->form([
                        Forms\Components\TextInput::make('min_salary')
                            ->label('Minimum Salary')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('max_salary')
                            ->label('Maximum Salary')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_salary'],
                                fn (Builder $query, $amount): Builder => $query->where('minimum', '>=', $amount),
                            )
                            ->when(
                                $data['max_salary'],
                                fn (Builder $query, $amount): Builder => $query->where('maximum', '<=', $amount),
                            );
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Job $record): string => $record->job_url)
                    ->openUrlInNewTab(),

                Action::make('applications')
                    ->label('Applications')
                    ->icon('heroicon-o-users')
                    ->url(fn (Job $record): string => route('filament.admin.resources.jobs.applications', $record))
                    ->visible(fn (Job $record): bool => $record->applications_count > 0),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('time', 'desc')
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('reset_filters')
                    ->label('Reset All Filters')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        session()->forget('tableFilters');
                        return redirect()->to(request()->url());
                    })
                    ->visible(fn () => request()->has('tableFilters') || session()->has('tableFilters')),
            ]);
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
            'index' => Pages\ListJobs::route('/'),
            'create' => Pages\CreateJob::route('/create'),
            'edit' => Pages\EditJob::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\JobsStatsWidget::class,
        ];
    }
}
