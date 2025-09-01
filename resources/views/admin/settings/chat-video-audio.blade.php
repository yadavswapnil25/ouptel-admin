<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Chat & Video/Audio Settings</title>
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
        .feature-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .feature-info {
            flex-grow: 1;
        }
        .feature-info h6 {
            margin-bottom: 0.25rem;
            color: #333;
        }
        .feature-info small {
            color: #6c757d;
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
                                <i class="fas fa-video me-2"></i>Chat & Video/Audio Settings
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">Chat & Video/Audio</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'chat_video_audio') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-comments me-2"></i>Chat Features</h6>
                                
                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-comments me-2"></i>Text Chat</h6>
                                        <small>Enable real-time text messaging between users</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="chat_enabled" name="chat_enabled" 
                                               value="1" {{ old('chat_enabled', $settings['chat_enabled'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-video me-2"></i>Video Chat</h6>
                                        <small>Enable video calling and conferencing features</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="video_chat_enabled" name="video_chat_enabled" 
                                               value="1" {{ old('video_chat_enabled', $settings['video_chat_enabled'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-microphone me-2"></i>Audio Chat</h6>
                                        <small>Enable voice calling and audio conferencing</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="audio_chat_enabled" name="audio_chat_enabled" 
                                               value="1" {{ old('audio_chat_enabled', $settings['audio_chat_enabled'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-users me-2"></i>Group Chat</h6>
                                        <small>Enable group messaging and group calls</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="group_chat_enabled" name="group_chat_enabled" 
                                               value="1" {{ old('group_chat_enabled', $settings['group_chat_enabled'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-cog me-2"></i>Agora Configuration</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="agora_app_id" class="form-label">Agora App ID</label>
                                        <input type="text" class="form-control" id="agora_app_id" name="agora_app_id" 
                                               value="{{ old('agora_app_id', $settings['agora_app_id'] ?? '') }}">
                                        <small class="form-text text-muted">Your Agora.io application ID</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="agora_app_certificate" class="form-label">Agora App Certificate</label>
                                        <input type="password" class="form-control" id="agora_app_certificate" name="agora_app_certificate" 
                                               value="{{ old('agora_app_certificate', $settings['agora_app_certificate'] ?? '') }}">
                                        <small class="form-text text-muted">Your Agora.io application certificate</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="agora_channel_type" class="form-label">Channel Type</label>
                                        <select class="form-control" id="agora_channel_type" name="agora_channel_type">
                                            <option value="communication" {{ old('agora_channel_type', $settings['agora_channel_type'] ?? 'communication') == 'communication' ? 'selected' : '' }}>Communication</option>
                                            <option value="live_broadcasting" {{ old('agora_channel_type', $settings['agora_channel_type'] ?? '') == 'live_broadcasting' ? 'selected' : '' }}>Live Broadcasting</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="agora_token_expiry" class="form-label">Token Expiry (seconds)</label>
                                        <input type="number" class="form-control" id="agora_token_expiry" name="agora_token_expiry" 
                                               value="{{ old('agora_token_expiry', $settings['agora_token_expiry'] ?? 3600) }}" 
                                               min="60" max="86400">
                                        <small class="form-text text-muted">Token validity period in seconds</small>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-sliders-h me-2"></i>Chat Settings</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_message_length" class="form-label">Max Message Length</label>
                                        <input type="number" class="form-control" id="max_message_length" name="max_message_length" 
                                               value="{{ old('max_message_length', $settings['max_message_length'] ?? 1000) }}" 
                                               min="100" max="5000">
                                        <small class="form-text text-muted">Maximum characters per message</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="message_retention_days" class="form-label">Message Retention (days)</label>
                                        <input type="number" class="form-control" id="message_retention_days" name="message_retention_days" 
                                               value="{{ old('message_retention_days', $settings['message_retention_days'] ?? 30) }}" 
                                               min="1" max="365">
                                        <small class="form-text text-muted">How long to keep chat messages</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_group_members" class="form-label">Max Group Members</label>
                                        <input type="number" class="form-control" id="max_group_members" name="max_group_members" 
                                               value="{{ old('max_group_members', $settings['max_group_members'] ?? 50) }}" 
                                               min="2" max="1000">
                                        <small class="form-text text-muted">Maximum members in a group chat</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_video_participants" class="form-label">Max Video Participants</label>
                                        <input type="number" class="form-control" id="max_video_participants" name="max_video_participants" 
                                               value="{{ old('max_video_participants', $settings['max_video_participants'] ?? 10) }}" 
                                               min="2" max="50">
                                        <small class="form-text text-muted">Maximum participants in video calls</small>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-shield-alt me-2"></i>Security & Moderation</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="message_moderation" name="message_moderation" 
                                                   value="1" {{ old('message_moderation', $settings['message_moderation'] ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="message_moderation">
                                                Enable Message Moderation
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="profanity_filter" name="profanity_filter" 
                                                   value="1" {{ old('profanity_filter', $settings['profanity_filter'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="profanity_filter">
                                                Enable Profanity Filter
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="message_encryption" name="message_encryption" 
                                                   value="1" {{ old('message_encryption', $settings['message_encryption'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="message_encryption">
                                                Enable Message Encryption
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="call_recording" name="call_recording" 
                                                   value="1" {{ old('call_recording', $settings['call_recording'] ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="call_recording">
                                                Enable Call Recording
                                            </label>
                                        </div>
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






