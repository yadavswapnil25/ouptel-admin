<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\JobApplicationResource\Pages;
use App\Models\JobApplication;
use App\Models\Job;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Filament\Admin\Concerns\HasPanelAccess;

class JobApplicationResource extends Resource
{
    use HasPanelAccess;

    protected static string $permissionKey = 'manage-jobs';
    protected static ?string $model = JobApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Job Applications';
    protected static ?string $modelLabel = 'Job Application';
    protected static ?string $pluralModelLabel = 'Job Applications';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Applicant Information')
                    ->schema([
                        Forms\Components\TextInput::make('user_name')
                            ->label('Full Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone_number')
                            ->label('Phone Number')
                            ->disabled(),

                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Job Details')
                    ->schema([
                        Forms\Components\TextInput::make('job_id')
                            ->label('Job ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_id')
                            ->label('User ID')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Work Experience')
                    ->schema([
                        Forms\Components\TextInput::make('position')
                            ->label('Previous Position')
                            ->disabled(),

                        Forms\Components\TextInput::make('where_did_you_work')
                            ->label('Previous Employer')
                            ->disabled(),

                        Forms\Components\TextInput::make('experience_start_date')
                            ->label('Start Date')
                            ->disabled(),

                        Forms\Components\TextInput::make('experience_end_date')
                            ->label('End Date')
                            ->disabled(),

                        Forms\Components\Textarea::make('experience_description')
                            ->label('Experience Description')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Application Answers')
                    ->schema([
                        Forms\Components\TextInput::make('question_one_answer')
                            ->label('Answer 1')
                            ->disabled(),

                        Forms\Components\TextInput::make('question_two_answer')
                            ->label('Answer 2')
                            ->disabled(),

                        Forms\Components\TextInput::make('question_three_answer')
                            ->label('Answer 3')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Resume & Cover Letter')
                    ->schema([
                        Forms\Components\Textarea::make('cover_letter')
                            ->label('Cover Letter')
                            ->disabled()
                            ->rows(5)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('resume_url')
                            ->label('Resume URL')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('applicant')
                    ->label('Applicant')
                    ->formatStateUsing(function ($record) {
                        $userId = $record->attributes['user_id'] ?? null;
                        $userName = $record->attributes['user_name'] ?? null;

                        $name = $userName ?: 'User #' . $userId;
                        $email = $record->attributes['email'] ?? '';
                        $phone = $record->attributes['phone_number'] ?? '';

                        return view('filament.admin.resources.job-application-resource.applicant', [
                            'name'   => $name,
                            'email'  => $email,
                            'phone'  => $phone,
                            'userId' => $userId,
                        ])->render();
                    })
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('user_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('job_title')
                    ->label('Job')
                    ->formatStateUsing(function ($record) {
                        $jobId = $record->attributes['job_id'] ?? null;
                        if (!$jobId) {
                            return 'Unknown Job';
                        }
                        try {
                            $job = DB::table('Wo_Job')->where('id', $jobId)->first(['id', 'title']);
                            if (!$job) {
                                return "Job #{$jobId}";
                            }
                            return '<a href="' . route('filament.admin.resources.jobs.index') . '?tableSearch=' . urlencode($job->title) . '" class="text-blue-600 hover:underline text-sm font-medium">' . e($job->title) . '</a>';
                        } catch (\Exception $e) {
                            return "Job #{$jobId}";
                        }
                    })
                    ->html(),

                TextColumn::make('location')
                    ->label('Location')
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('applied_at')
                    ->label('Applied')
                    ->formatStateUsing(function ($record) {
                        $time = $record->attributes['time'] ?? null;
                        if (!$time || !is_numeric($time)) {
                            return 'N/A';
                        }
                        return Carbon::createFromTimestamp((int) $time)->diffForHumans();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('time', $direction);
                    })
                    ->tooltip(function ($record) {
                        $time = $record->attributes['time'] ?? null;
                        if ($time && is_numeric($time)) {
                            return Carbon::createFromTimestamp((int) $time)->format('Y-m-d H:i:s');
                        }
                        return null;
                    }),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\Select::make('range')
                            ->label('Quick Range')
                            ->options([
                                'Today'      => 'Today',
                                'Yesterday'  => 'Yesterday',
                                'This Week'  => 'This Week',
                                'This Month' => 'This Month',
                                'Last Month' => 'Last Month',
                                'This Year'  => 'This Year',
                            ])
                            ->placeholder('All'),
                        Forms\Components\DatePicker::make('start_date')->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $range = $data['range'] ?? null;
                        $startDate = $data['start_date'] ?? null;
                        $endDate   = $data['end_date'] ?? null;

                        if ($range) {
                            $now = Carbon::now();
                            [$start, $end] = match ($range) {
                                'Today'      => [$now->copy()->startOfDay()->timestamp, $now->copy()->endOfDay()->timestamp],
                                'Yesterday'  => [$now->copy()->subDay()->startOfDay()->timestamp, $now->copy()->subDay()->endOfDay()->timestamp],
                                'This Week'  => [$now->copy()->startOfWeek()->timestamp, $now->copy()->endOfWeek()->timestamp],
                                'This Month' => [$now->copy()->startOfMonth()->timestamp, $now->copy()->endOfMonth()->timestamp],
                                'Last Month' => [$now->copy()->subMonth()->startOfMonth()->timestamp, $now->copy()->subMonth()->endOfMonth()->timestamp],
                                'This Year'  => [$now->copy()->startOfYear()->timestamp, $now->copy()->endOfYear()->timestamp],
                                default      => [null, null],
                            };
                            if ($start && $end) {
                                return $query->whereBetween('time', [$start, $end]);
                            }
                        }

                        if ($startDate && $endDate) {
                            return $query->whereBetween('time', [
                                Carbon::parse($startDate)->startOfDay()->timestamp,
                                Carbon::parse($endDate)->endOfDay()->timestamp,
                            ]);
                        }

                        return $query;
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make(),
                DeleteAction::make()->color('danger'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(50)
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobApplications::route('/'),
            'view'  => Pages\ViewJobApplication::route('/{record}'),
        ];
    }
}
