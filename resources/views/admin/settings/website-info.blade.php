<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Website Information</title>
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
                                <i class="fas fa-info-circle me-2"></i>Website Information
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">Website Information</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'website_info') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-globe me-2"></i>Basic Website Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="site_title" class="form-label">Site Title *</label>
                                        <input type="text" class="form-control" id="site_title" name="site_title" 
                                               value="{{ old('site_title', $settings['site_title'] ?? 'Ouptel') }}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="site_author" class="form-label">Site Author</label>
                                        <input type="text" class="form-control" id="site_author" name="site_author" 
                                               value="{{ old('site_author', $settings['site_author'] ?? 'Ouptel Team') }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="site_keywords" class="form-label">Site Keywords</label>
                                    <textarea class="form-control" id="site_keywords" name="site_keywords" rows="2" 
                                              placeholder="social network, community, chat, video, audio">{{ old('site_keywords', $settings['site_keywords'] ?? '') }}</textarea>
                                    <small class="form-text text-muted">Separate keywords with commas</small>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_email" class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                               value="{{ old('contact_email', $settings['contact_email'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_phone" class="form-label">Contact Phone</label>
                                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                               value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="support_email" class="form-label">Support Email</label>
                                        <input type="email" class="form-control" id="support_email" name="support_email" 
                                               value="{{ old('support_email', $settings['support_email'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="business_email" class="form-label">Business Email</label>
                                        <input type="email" class="form-control" id="business_email" name="business_email" 
                                               value="{{ old('business_email', $settings['business_email'] ?? '') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-link me-2"></i>Social Media Links</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="facebook_url" class="form-label">Facebook URL</label>
                                        <input type="url" class="form-control" id="facebook_url" name="facebook_url" 
                                               value="{{ old('facebook_url', $settings['facebook_url'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="twitter_url" class="form-label">Twitter URL</label>
                                        <input type="url" class="form-control" id="twitter_url" name="twitter_url" 
                                               value="{{ old('twitter_url', $settings['twitter_url'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="instagram_url" class="form-label">Instagram URL</label>
                                        <input type="url" class="form-control" id="instagram_url" name="instagram_url" 
                                               value="{{ old('instagram_url', $settings['instagram_url'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                               value="{{ old('linkedin_url', $settings['linkedin_url'] ?? '') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-map-marker-alt me-2"></i>Company Information</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="{{ old('company_name', $settings['company_name'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <input type="text" class="form-control" id="company_address" name="company_address" 
                                               value="{{ old('company_address', $settings['company_address'] ?? '') }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="company_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="company_city" name="company_city" 
                                               value="{{ old('company_city', $settings['company_city'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="company_country" name="company_country" 
                                               value="{{ old('company_country', $settings['company_country'] ?? '') }}">
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






