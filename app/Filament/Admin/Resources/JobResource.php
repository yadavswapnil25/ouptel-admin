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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class JobResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Jobs';

    protected static ?string $navigationGroup = 'Manage Features';

    protected static ?int $navigationSort = 5;

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
                            ->options(function () {
                                if (!Schema::hasTable('Wo_Users')) {
                                    return [];
                                }
                                try {
                                    return User::select('user_id', 'username')
                                        ->limit(1000)
                                        ->get()
                                        ->mapWithKeys(function ($user) {
                                            $label = $user->username ?? "User {$user->user_id}";
                                            return [$user->user_id => $label];
                                        });
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                if (!Schema::hasTable('Wo_Users')) {
                                    return [];
                                }
                                try {
                                    return User::select('user_id', 'username')
                                        ->where('username', 'like', "%{$search}%")
                                        ->orWhere('user_id', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($user) {
                                            $label = $user->username ?? "User {$user->user_id}";
                                            return [$user->user_id => $label];
                                        });
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('page_id')
                            ->label('Page')
                            ->options(function () {
                                if (!Schema::hasTable('Wo_Pages')) {
                                    return [];
                                }
                                try {
                                    return Page::select('page_id', 'page_name')
                                        ->limit(1000)
                                        ->get()
                                        ->mapWithKeys(function ($page) {
                                            $label = $page->page_name ?? "Page {$page->page_id}";
                                            return [$page->page_id => $label];
                                        });
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                if (!Schema::hasTable('Wo_Pages')) {
                                    return [];
                                }
                                try {
                                    return Page::select('page_id', 'page_name')
                                        ->where('page_name', 'like', "%{$search}%")
                                        ->orWhere('page_id', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($page) {
                                            $label = $page->page_name ?? "Page {$page->page_id}";
                                            return [$page->page_id => $label];
                                        });
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(function () {
                                if (!Schema::hasTable('Wo_Job_Categories')) {
                                    return [];
                                }
                                try {
                                    $categories = JobCategory::query()->get();
                                    $options = [];
                                    foreach ($categories as $category) {
                                        $options[$category->id] = $category->name ?? "Category {$category->id}";
                                    }
                                    return $options;
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                if (!Schema::hasTable('Wo_Job_Categories')) {
                                    return [];
                                }
                                try {
                                    $categories = JobCategory::query()
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('id', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get();
                                    $options = [];
                                    foreach ($categories as $category) {
                                        $options[$category->id] = $category->name ?? "Category {$category->id}";
                                    }
                                    return $options;
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
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
            ->modifyQueryUsing(function (Builder $query) {
                // Search by title and description (matching old admin panel)
                if (request()->filled('tableSearch')) {
                    $search = request('tableSearch');
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('publisher')
                    ->label('Publisher')
                    ->formatStateUsing(function ($record) {
                        // Get user_id from record attributes (may be user_id or user column)
                        $userId = $record->attributes['user_id'] ?? $record->attributes['user'] ?? null;
                        if (!$userId || !Schema::hasTable('Wo_Users')) {
                            return 'Unknown';
                        }
                        try {
                            $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
                            if (!$user) {
                                return 'Unknown';
                            }
                            $avatar = $user->avatar ?? '';
                            $avatarUrl = $avatar ? asset('storage/' . $avatar) : asset('images/default-avatar.png');
                            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->username ?? 'Unknown');
                            
                            return view('filament.admin.resources.job-resource.publisher', [
                                'avatar' => $avatarUrl,
                                'name' => $name,
                                'username' => $user->username ?? '',
                                'userId' => $userId,
                            ])->render();
                        } catch (\Exception $e) {
                            return 'Unknown';
                        }
                    })
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (!Schema::hasTable('Wo_Users')) {
                            return $query;
                        }
                        // Check which column exists (user_id or user)
                        $hasUserId = Schema::hasColumn('Wo_Job', 'user_id');
                        $hasUser = Schema::hasColumn('Wo_Job', 'user');
                        $column = $hasUserId ? 'user_id' : ($hasUser ? 'user' : null);
                        
                        if (!$column) {
                            return $query;
                        }
                        
                        return $query->whereIn($column, function ($subQuery) use ($search) {
                            $subQuery->select('user_id')
                                ->from('Wo_Users')
                                ->where('username', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('job_link')
                    ->label('Job Link')
                    ->formatStateUsing(function ($record) {
                        // Generate job URL similar to old admin panel
                        $jobId = $record->id;
                        $baseUrl = config('app.url', 'https://ouptel.com');
                        // Try to get URL from record, or construct it
                        $jobUrl = $record->url ?? "{$baseUrl}/jobs/{$jobId}";
                        
                        return view('filament.admin.resources.job-resource.job-link', [
                            'url' => $jobUrl,
                        ])->render();
                    })
                    ->html(),

                TextColumn::make('posted')
                    ->label('Posted')
                    ->formatStateUsing(function ($record) {
                        $time = $record->time_as_timestamp ?? $record->time ?? null;
                        if (!$time) {
                            return 'N/A';
                        }
                        if (is_numeric($time)) {
                            $carbon = Carbon::createFromTimestamp($time);
                            return $carbon->diffForHumans();
                        }
                        return $time;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('time', $direction);
                    })
                    ->tooltip(function ($record) {
                        $time = $record->time_as_timestamp ?? $record->time ?? null;
                        if ($time && is_numeric($time)) {
                            return Carbon::createFromTimestamp($time)->format('Y-m-d H:i:s');
                        }
                        return null;
                    }),

                TextColumn::make('actions')
                    ->label('Action')
                    ->formatStateUsing(function ($record) {
                        return view('filament.admin.resources.job-resource.actions', compact('record'));
                    })
                    ->html(),
            ])
            ->filters([
                // Date Range Filter (matching old admin panel)
                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\Select::make('range')
                            ->label('Quick Range')
                            ->options([
                                'Today' => 'Today',
                                'Yesterday' => 'Yesterday',
                                'This Week' => 'This Week',
                                'This Month' => 'This Month',
                                'Last Month' => 'Last Month',
                                'This Year' => 'This Year',
                            ])
                            ->placeholder('All'),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $range = $data['range'] ?? null;
                        $startDate = $data['start_date'] ?? null;
                        $endDate = $data['end_date'] ?? null;

                        if ($range) {
                            $now = Carbon::now();
                            switch ($range) {
                                case 'Today':
                                    $start = $now->copy()->startOfDay()->timestamp;
                                    $end = $now->copy()->endOfDay()->timestamp;
                                    break;
                                case 'Yesterday':
                                    $start = $now->copy()->subDay()->startOfDay()->timestamp;
                                    $end = $now->copy()->subDay()->endOfDay()->timestamp;
                                    break;
                                case 'This Week':
                                    $start = $now->copy()->startOfWeek()->timestamp;
                                    $end = $now->copy()->endOfWeek()->timestamp;
                                    break;
                                case 'This Month':
                                    $start = $now->copy()->startOfMonth()->timestamp;
                                    $end = $now->copy()->endOfMonth()->timestamp;
                                    break;
                                case 'Last Month':
                                    $start = $now->copy()->subMonth()->startOfMonth()->timestamp;
                                    $end = $now->copy()->subMonth()->endOfMonth()->timestamp;
                                    break;
                                case 'This Year':
                                    $start = $now->copy()->startOfYear()->timestamp;
                                    $end = $now->copy()->endOfYear()->timestamp;
                                    break;
                                default:
                                    return $query;
                            }
                            return $query->whereBetween('time', [$start, $end]);
                        }

                        if ($startDate && $endDate) {
                            $start = Carbon::parse($startDate)->startOfDay()->timestamp;
                            $end = Carbon::parse($endDate)->endOfDay()->timestamp;
                            return $query->whereBetween('time', [$start, $end]);
                        }

                        if ($startDate) {
                            $start = Carbon::parse($startDate)->startOfDay()->timestamp;
                            return $query->where('time', '>=', $start);
                        }

                        if ($endDate) {
                            $end = Carbon::parse($endDate)->endOfDay()->timestamp;
                            return $query->where('time', '<=', $end);
                        }

                        return $query;
                    }),

                SelectFilter::make('user_id')
                    ->label('Publisher')
                    ->options(function () {
                        if (!Schema::hasTable('Wo_Users')) {
                            return [];
                        }
                        try {
                            return User::select('user_id', 'username')
                                ->limit(1000)
                                ->get()
                                ->mapWithKeys(function ($user) {
                                    $label = $user->username ?? "User {$user->user_id}";
                                    return [$user->user_id => $label];
                                });
                        } catch (\Exception $e) {
                            return [];
                        }
                    })
                    ->searchable(),

                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('All jobs')
                    ->trueLabel('Active jobs')
                    ->falseLabel('Inactive jobs')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === true) {
                            return $query->where('status', '1');
                        } elseif ($data['value'] === false) {
                            return $query->where('status', '!=', '1');
                        }
                        return $query;
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(50) // Matching old admin panel (50 per page)
            ->persistFiltersInSession();
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
