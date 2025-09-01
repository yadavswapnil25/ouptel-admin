<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - NodeJS Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .setting-section {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .setting-section h6 {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>Ouptel Admin
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <div class="sidebar">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="{{ route('admin.users') }}">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                        <a class="nav-link active" href="{{ route('admin.settings') }}">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0">
                                <i class="fab fa-node-js me-2"></i>NodeJS Settings
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">NodeJS</li>
                                </ol>
                            </nav>
                        </div>
                        <a href="{{ route('admin.settings') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Settings
                        </a>
                    </div>

                    <div class="settings-card">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.settings.update', 'nodejs') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-server me-2"></i>NodeJS Server Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="nodejs_enabled" name="nodejs_enabled" 
                                                   value="1" {{ old('nodejs_enabled', $settings['nodejs_enabled'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="nodejs_enabled">
                                                Enable NodeJS Server
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="nodejs_port" class="form-label">NodeJS Port</label>
                                        <input type="number" class="form-control" id="nodejs_port" name="nodejs_port" 
                                               value="{{ old('nodejs_port', $settings['nodejs_port'] ?? 3000) }}" 
                                               min="1000" max="65535">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nodejs_host" class="form-label">NodeJS Host</label>
                                        <input type="text" class="form-control" id="nodejs_host" name="nodejs_host" 
                                               value="{{ old('nodejs_host', $settings['nodejs_host'] ?? 'localhost') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="nodejs_environment" class="form-label">Environment</label>
                                        <select class="form-control" id="nodejs_environment" name="nodejs_environment">
                                            <option value="development" {{ old('nodejs_environment', $settings['nodejs_environment'] ?? 'development') == 'development' ? 'selected' : '' }}>Development</option>
                                            <option value="production" {{ old('nodejs_environment', $settings['nodejs_environment'] ?? '') == 'production' ? 'selected' : '' }}>Production</option>
                                            <option value="staging" {{ old('nodejs_environment', $settings['nodejs_environment'] ?? '') == 'staging' ? 'selected' : '' }}>Staging</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-plug me-2"></i>Socket.IO Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="socket_port" class="form-label">Socket.IO Port</label>
                                        <input type="number" class="form-control" id="socket_port" name="socket_port" 
                                               value="{{ old('socket_port', $settings['socket_port'] ?? 3001) }}" 
                                               min="1000" max="65535">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="socket_host" class="form-label">Socket.IO Host</label>
                                        <input type="text" class="form-control" id="socket_host" name="socket_host" 
                                               value="{{ old('socket_host', $settings['socket_host'] ?? 'localhost') }}">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="socket_cors_origin" class="form-label">CORS Origin</label>
                                        <input type="text" class="form-control" id="socket_cors_origin" name="socket_cors_origin" 
                                               value="{{ old('socket_cors_origin', $settings['socket_cors_origin'] ?? '*') }}">
                                        <small class="form-text text-muted">Allowed origins for CORS (use * for all)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="socket_transports" class="form-label">Transports</label>
                                        <select class="form-control" id="socket_transports" name="socket_transports">
                                            <option value="websocket" {{ old('socket_transports', $settings['socket_transports'] ?? 'websocket') == 'websocket' ? 'selected' : '' }}>WebSocket Only</option>
                                            <option value="polling" {{ old('socket_transports', $settings['socket_transports'] ?? '') == 'polling' ? 'selected' : '' }}>Polling Only</option>
                                            <option value="websocket,polling" {{ old('socket_transports', $settings['socket_transports'] ?? '') == 'websocket,polling' ? 'selected' : '' }}>WebSocket + Polling</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-database me-2"></i>Redis Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="redis_enabled" name="redis_enabled" 
                                                   value="1" {{ old('redis_enabled', $settings['redis_enabled'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="redis_enabled">
                                                Enable Redis
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="redis_host" class="form-label">Redis Host</label>
                                        <input type="text" class="form-control" id="redis_host" name="redis_host" 
                                               value="{{ old('redis_host', $settings['redis_host'] ?? 'localhost') }}">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="redis_port" class="form-label">Redis Port</label>
                                        <input type="number" class="form-control" id="redis_port" name="redis_port" 
                                               value="{{ old('redis_port', $settings['redis_port'] ?? 6379) }}" 
                                               min="1000" max="65535">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="redis_password" class="form-label">Redis Password</label>
                                        <input type="password" class="form-control" id="redis_password" name="redis_password" 
                                               value="{{ old('redis_password', $settings['redis_password'] ?? '') }}">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="redis_database" class="form-label">Redis Database</label>
                                        <input type="number" class="form-control" id="redis_database" name="redis_database" 
                                               value="{{ old('redis_database', $settings['redis_database'] ?? 0) }}" 
                                               min="0" max="15">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="redis_ttl" class="form-label">Default TTL (seconds)</label>
                                        <input type="number" class="form-control" id="redis_ttl" name="redis_ttl" 
                                               value="{{ old('redis_ttl', $settings['redis_ttl'] ?? 3600) }}" 
                                               min="60" max="86400">
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-cogs me-2"></i>Performance Settings</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_connections" class="form-label">Max Connections</label>
                                        <input type="number" class="form-control" id="max_connections" name="max_connections" 
                                               value="{{ old('max_connections', $settings['max_connections'] ?? 1000) }}" 
                                               min="100" max="10000">
                                        <small class="form-text text-muted">Maximum concurrent connections</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="connection_timeout" class="form-label">Connection Timeout (ms)</label>
                                        <input type="number" class="form-control" id="connection_timeout" name="connection_timeout" 
                                               value="{{ old('connection_timeout', $settings['connection_timeout'] ?? 5000) }}" 
                                               min="1000" max="30000">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="heartbeat_interval" class="form-label">Heartbeat Interval (ms)</label>
                                        <input type="number" class="form-control" id="heartbeat_interval" name="heartbeat_interval" 
                                               value="{{ old('heartbeat_interval', $settings['heartbeat_interval'] ?? 25000) }}" 
                                               min="5000" max="60000">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="heartbeat_timeout" class="form-label">Heartbeat Timeout (ms)</label>
                                        <input type="number" class="form-control" id="heartbeat_timeout" name="heartbeat_timeout" 
                                               value="{{ old('heartbeat_timeout', $settings['heartbeat_timeout'] ?? 60000) }}" 
                                               min="10000" max="120000">
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-shield-alt me-2"></i>Security Settings</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="socket_auth_required" name="socket_auth_required" 
                                                   value="1" {{ old('socket_auth_required', $settings['socket_auth_required'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="socket_auth_required">
                                                Require Authentication for Socket Connection
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="rate_limiting" name="rate_limiting" 
                                                   value="1" {{ old('rate_limiting', $settings['rate_limiting'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="rate_limiting">
                                                Enable Rate Limiting
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="rate_limit_window" class="form-label">Rate Limit Window (ms)</label>
                                        <input type="number" class="form-control" id="rate_limit_window" name="rate_limit_window" 
                                               value="{{ old('rate_limit_window', $settings['rate_limit_window'] ?? 60000) }}" 
                                               min="1000" max="300000">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="rate_limit_max" class="form-label">Rate Limit Max Requests</label>
                                        <input type="number" class="form-control" id="rate_limit_max" name="rate_limit_max" 
                                               value="{{ old('rate_limit_max', $settings['rate_limit_max'] ?? 100) }}" 
                                               min="10" max="1000">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.settings') }}" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






