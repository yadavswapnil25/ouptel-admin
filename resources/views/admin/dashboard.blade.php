@extends('admin.layouts.app')

@section('title', 'Ouptel Admin - Dashboard')

@section('content')
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Dashboard</h1>
                        <span class="text-muted">Welcome back, {{ $user->name }}!</span>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card primary">
                                <div class="stat-number text-primary">{{ $metrics['total_users'] }}</div>
                                <div class="stat-label">Total Users</div>
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card success">
                                <div class="stat-number text-success">{{ $metrics['active_users'] }}</div>
                                <div class="stat-label">Active Users</div>
                                <div class="stat-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card warning">
                                <div class="stat-number text-warning">{{ $metrics['admin_users'] }}</div>
                                <div class="stat-label">Admin Users</div>
                                <div class="stat-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card info">
                                <div class="stat-number text-info">{{ $metrics['new_users_today'] }}</div>
                                <div class="stat-label">New Today</div>
                                <div class="stat-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Stats Row -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card secondary">
                                <div class="stat-number text-secondary">{{ $metrics['new_users_this_week'] }}</div>
                                <div class="stat-label">New This Week</div>
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card dark">
                                <div class="stat-number text-dark">{{ $metrics['new_users_this_month'] }}</div>
                                <div class="stat-label">New This Month</div>
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card danger">
                                <div class="stat-number text-danger">{{ $metrics['total_posts'] }}</div>
                                <div class="stat-label">Total Posts</div>
                                <div class="stat-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card light">
                                <div class="stat-number text-muted">{{ $metrics['total_messages'] }}</div>
                                <div class="stat-label">Total Messages</div>
                                <div class="stat-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Analytics -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">User Growth (Last 7 Days)</h5>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary active" onclick="loadChart('week')">Week</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="loadChart('month')">Month</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="loadChart('year')">Year</button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="userGrowthChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Recent Users</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($recentUsers as $recentUser)
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar me-3">
                                            {{ strtoupper(substr($recentUser->name, 0, 1)) }}
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">{{ $recentUser->name }}</div>
                                            <small class="text-muted">{{ $recentUser->created_at->diffForHumans() }}</small>
                                        </div>
                                        @if($recentUser->isAdmin())
                                            <span class="badge bg-warning">Admin</span>
                                        @else
                                            <span class="badge bg-success">User</span>
                                        @endif
                                    </div>
                                    @empty
                                    <p class="text-muted">No recent users.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions and System Status -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <a href="{{ route('admin.users') }}" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-users me-2"></i>Manage Users
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <a href="{{ route('admin.users.create') }}" class="btn btn-outline-success w-100">
                                                <i class="fas fa-plus me-2"></i>Add User
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn btn-outline-info w-100">
                                                <i class="fas fa-download me-2"></i>Export Data
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <a href="{{ route('admin.settings') }}" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-cog me-2"></i>Settings
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">System Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Database</span>
                                        <span class="badge bg-success">Online</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Server</span>
                                        <span class="badge bg-success">Running</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Storage</span>
                                        <span class="badge bg-warning">85% Used</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Memory</span>
                                        <span class="badge bg-info">2.1GB / 4GB</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
@endsection

@section('scripts')
<script>
    // User Growth Chart
    const ctx = document.getElementById('userGrowthChart').getContext('2d');
    let userGrowthChart;
    
    // Initial chart data
    const chartData = @json($userGrowthData);
    
    function initChart() {
        userGrowthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.date),
                datasets: [{
                    label: 'New Users',
                    data: chartData.map(item => item.count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    function loadChart(period) {
        // Update button states
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // Here you would typically make an AJAX call to get new data
        // For now, we'll just show a loading state
        console.log('Loading chart for period:', period);
    }
    
    // Initialize chart when page loads
    document.addEventListener('DOMContentLoaded', function() {
        initChart();
    });
</script>
@endsection
