<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Email & SMS Setup</title>
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
        .test-btn {
            margin-top: 10px;
        }
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
                                <i class="fas fa-envelope me-2"></i>Email & SMS Setup
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">Email & SMS</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'email_sms') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-server me-2"></i>SMTP Configuration</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                               value="{{ old('smtp_port', $settings['smtp_port'] ?? '587') }}" 
                                               placeholder="587">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                               value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               value="{{ old('smtp_password', $settings['smtp_password'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="smtp_encryption" class="form-label">Encryption</label>
                                        <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                            <option value="tls" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                            <option value="ssl" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                            <option value="none" {{ old('smtp_encryption', $settings['smtp_encryption'] ?? '') == 'none' ? 'selected' : '' }}>None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="from_email" class="form-label">From Email</label>
                                        <input type="email" class="form-control" id="from_email" name="from_email" 
                                               value="{{ old('from_email', $settings['from_email'] ?? '') }}">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary test-btn">
                                    <i class="fas fa-paper-plane me-2"></i>Test Email
                                </button>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-sms me-2"></i>SMS Configuration</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sms_provider" class="form-label">SMS Provider</label>
                                        <select class="form-control" id="sms_provider" name="sms_provider">
                                            <option value="msg91" {{ old('sms_provider', $settings['sms_provider'] ?? 'msg91') == 'msg91' ? 'selected' : '' }}>MSG91</option>
                                            <option value="twilio" {{ old('sms_provider', $settings['sms_provider'] ?? '') == 'twilio' ? 'selected' : '' }}>Twilio</option>
                                            <option value="nexmo" {{ old('sms_provider', $settings['sms_provider'] ?? '') == 'nexmo' ? 'selected' : '' }}>Nexmo</option>
                                            <option value="custom" {{ old('sms_provider', $settings['sms_provider'] ?? '') == 'custom' ? 'selected' : '' }}>Custom</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="sms_api_key" class="form-label">SMS API Key</label>
                                        <input type="text" class="form-control" id="sms_api_key" name="sms_api_key" 
                                               value="{{ old('sms_api_key', $settings['sms_api_key'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sms_sender_id" class="form-label">Sender ID</label>
                                        <input type="text" class="form-control" id="sms_sender_id" name="sms_sender_id" 
                                               value="{{ old('sms_sender_id', $settings['sms_sender_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="sms_country_code" class="form-label">Default Country Code</label>
                                        <input type="text" class="form-control" id="sms_country_code" name="sms_country_code" 
                                               value="{{ old('sms_country_code', $settings['sms_country_code'] ?? '+1') }}" 
                                               placeholder="+1">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary test-btn">
                                    <i class="fas fa-mobile-alt me-2"></i>Test SMS
                                </button>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-cog me-2"></i>Email Templates</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="welcome_email_template" class="form-label">Welcome Email Template</label>
                                        <textarea class="form-control" id="welcome_email_template" name="welcome_email_template" rows="4">{{ old('welcome_email_template', $settings['welcome_email_template'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_reset_template" class="form-label">Password Reset Template</label>
                                        <textarea class="form-control" id="password_reset_template" name="password_reset_template" rows="4">{{ old('password_reset_template', $settings['password_reset_template'] ?? '') }}</textarea>
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






