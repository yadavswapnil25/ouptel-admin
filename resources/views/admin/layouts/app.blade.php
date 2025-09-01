<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Ouptel Admin Panel')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .main-content {
            padding: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.secondary { border-left-color: #6c757d; }
        .stat-card.dark { border-left-color: #343a40; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.light { border-left-color: #f8f9fa; }
        
        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .stat-card {
            position: relative;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .settings-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .settings-item:hover {
            background-color: #f8f9fa;
            border-color: #667eea;
            transform: translateY(-2px);
        }
        .settings-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
        }
        .settings-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .settings-icon.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .settings-icon.warning { background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%); }
        .settings-icon.info { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        .settings-icon.danger { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); }
        .settings-icon.secondary { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
        .settings-icon.dark { background: linear-gradient(135deg, #343a40 0%, #212529 100%); }
        .settings-icon.light { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #333 !important; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
                <i class="fas fa-shield-alt me-2"></i>
                <strong>Ouptel Admin</strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            {{ session('admin_user.name', 'Admin') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                        <a class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings') }}">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-edit me-2"></i>Posts
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-file-alt me-2"></i>Pages
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-users-cog me-2"></i>Groups
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-flag me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-line me-2"></i>Analytics
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            fetch('/admin/api/stats')
                .then(response => response.json())
                .then(data => {
                    // Update stat numbers with animation
                    updateStatNumbers(data);
                })
                .catch(error => console.log('Error fetching stats:', error));
        }, 30000);
        
        function updateStatNumbers(data) {
            // Animate number updates
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[data-stat="${key}"]`);
                if (element) {
                    animateNumber(element, data[key]);
                }
            });
        }
        
        function animateNumber(element, newValue) {
            const currentValue = parseInt(element.textContent) || 0;
            const increment = (newValue - currentValue) / 20;
            let current = currentValue;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
                    current = newValue;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 50);
        }
    </script>
    
    @yield('scripts')
</body>
</html>






