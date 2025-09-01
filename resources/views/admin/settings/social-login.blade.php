<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Social Login Settings</title>
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
        .social-provider {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .provider-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .provider-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: white;
        }
        .google { background: #db4437; }
        .facebook { background: #3b5998; }
        .twitter { background: #1da1f2; }
        .linkedin { background: #0077b5; }
        .instagram { background: #e4405f; }
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
                                <i class="fas fa-share-alt me-2"></i>Social Login Settings
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">Social Login</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'social_login') }}">
                            @csrf
                            
                            <!-- Google Login -->
                            <div class="social-provider">
                                <div class="provider-header">
                                    <div class="provider-icon google">
                                        <i class="fab fa-google"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Google Login</h6>
                                        <small class="text-muted">Allow users to sign in with Google</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="google_client_id" class="form-label">Google Client ID</label>
                                        <input type="text" class="form-control" id="google_client_id" name="google_client_id" 
                                               value="{{ old('google_client_id', $settings['google_client_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="google_client_secret" class="form-label">Google Client Secret</label>
                                        <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" 
                                               value="{{ old('google_client_secret', $settings['google_client_secret'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="google_redirect_url" class="form-label">Google Redirect URL</label>
                                    <input type="url" class="form-control" id="google_redirect_url" name="google_redirect_url" 
                                           value="{{ old('google_redirect_url', $settings['google_redirect_url'] ?? '') }}" 
                                           placeholder="https://yourdomain.com/auth/google/callback">
                                </div>
                            </div>

                            <!-- Facebook Login -->
                            <div class="social-provider">
                                <div class="provider-header">
                                    <div class="provider-icon facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Facebook Login</h6>
                                        <small class="text-muted">Allow users to sign in with Facebook</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="facebook_app_id" class="form-label">Facebook App ID</label>
                                        <input type="text" class="form-control" id="facebook_app_id" name="facebook_app_id" 
                                               value="{{ old('facebook_app_id', $settings['facebook_app_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="facebook_app_secret" class="form-label">Facebook App Secret</label>
                                        <input type="password" class="form-control" id="facebook_app_secret" name="facebook_app_secret" 
                                               value="{{ old('facebook_app_secret', $settings['facebook_app_secret'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="facebook_redirect_url" class="form-label">Facebook Redirect URL</label>
                                    <input type="url" class="form-control" id="facebook_redirect_url" name="facebook_redirect_url" 
                                           value="{{ old('facebook_redirect_url', $settings['facebook_redirect_url'] ?? '') }}" 
                                           placeholder="https://yourdomain.com/auth/facebook/callback">
                                </div>
                            </div>

                            <!-- Twitter Login -->
                            <div class="social-provider">
                                <div class="provider-header">
                                    <div class="provider-icon twitter">
                                        <i class="fab fa-twitter"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Twitter Login</h6>
                                        <small class="text-muted">Allow users to sign in with Twitter</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="twitter_client_id" class="form-label">Twitter Client ID</label>
                                        <input type="text" class="form-control" id="twitter_client_id" name="twitter_client_id" 
                                               value="{{ old('twitter_client_id', $settings['twitter_client_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="twitter_client_secret" class="form-label">Twitter Client Secret</label>
                                        <input type="password" class="form-control" id="twitter_client_secret" name="twitter_client_secret" 
                                               value="{{ old('twitter_client_secret', $settings['twitter_client_secret'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="twitter_redirect_url" class="form-label">Twitter Redirect URL</label>
                                    <input type="url" class="form-control" id="twitter_redirect_url" name="twitter_redirect_url" 
                                           value="{{ old('twitter_redirect_url', $settings['twitter_redirect_url'] ?? '') }}" 
                                           placeholder="https://yourdomain.com/auth/twitter/callback">
                                </div>
                            </div>

                            <!-- LinkedIn Login -->
                            <div class="social-provider">
                                <div class="provider-header">
                                    <div class="provider-icon linkedin">
                                        <i class="fab fa-linkedin-in"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">LinkedIn Login</h6>
                                        <small class="text-muted">Allow users to sign in with LinkedIn</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="linkedin_client_id" class="form-label">LinkedIn Client ID</label>
                                        <input type="text" class="form-control" id="linkedin_client_id" name="linkedin_client_id" 
                                               value="{{ old('linkedin_client_id', $settings['linkedin_client_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="linkedin_client_secret" class="form-label">LinkedIn Client Secret</label>
                                        <input type="password" class="form-control" id="linkedin_client_secret" name="linkedin_client_secret" 
                                               value="{{ old('linkedin_client_secret', $settings['linkedin_client_secret'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="linkedin_redirect_url" class="form-label">LinkedIn Redirect URL</label>
                                    <input type="url" class="form-control" id="linkedin_redirect_url" name="linkedin_redirect_url" 
                                           value="{{ old('linkedin_redirect_url', $settings['linkedin_redirect_url'] ?? '') }}" 
                                           placeholder="https://yourdomain.com/auth/linkedin/callback">
                                </div>
                            </div>

                            <!-- Instagram Login -->
                            <div class="social-provider">
                                <div class="provider-header">
                                    <div class="provider-icon instagram">
                                        <i class="fab fa-instagram"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">Instagram Login</h6>
                                        <small class="text-muted">Allow users to sign in with Instagram</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="instagram_client_id" class="form-label">Instagram Client ID</label>
                                        <input type="text" class="form-control" id="instagram_client_id" name="instagram_client_id" 
                                               value="{{ old('instagram_client_id', $settings['instagram_client_id'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="instagram_client_secret" class="form-label">Instagram Client Secret</label>
                                        <input type="password" class="form-control" id="instagram_client_secret" name="instagram_client_secret" 
                                               value="{{ old('instagram_client_secret', $settings['instagram_client_secret'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="instagram_redirect_url" class="form-label">Instagram Redirect URL</label>
                                    <input type="url" class="form-control" id="instagram_redirect_url" name="instagram_redirect_url" 
                                           value="{{ old('instagram_redirect_url', $settings['instagram_redirect_url'] ?? '') }}" 
                                           placeholder="https://yourdomain.com/auth/instagram/callback">
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






