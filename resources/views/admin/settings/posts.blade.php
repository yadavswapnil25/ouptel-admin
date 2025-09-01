<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Posts Settings</title>
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
                                <i class="fas fa-edit me-2"></i>Posts Settings
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">Posts</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'posts') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-toggle-on me-2"></i>Post Features</h6>
                                
                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-edit me-2"></i>Posts System</h6>
                                        <small>Enable or disable the entire posts functionality</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="posts_enabled" name="posts_enabled" 
                                               value="1" {{ old('posts_enabled', $settings['posts_enabled'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-image me-2"></i>Image Posts</h6>
                                        <small>Allow users to attach images to their posts</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_images" name="allow_images" 
                                               value="1" {{ old('allow_images', $settings['allow_images'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-video me-2"></i>Video Posts</h6>
                                        <small>Allow users to attach videos to their posts</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_videos" name="allow_videos" 
                                               value="1" {{ old('allow_videos', $settings['allow_videos'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-link me-2"></i>Link Posts</h6>
                                        <small>Allow users to share links in their posts</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_links" name="allow_links" 
                                               value="1" {{ old('allow_links', $settings['allow_links'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>

                                <div class="feature-toggle">
                                    <div class="feature-info">
                                        <h6><i class="fas fa-map-marker-alt me-2"></i>Location Posts</h6>
                                        <small>Allow users to add location information to posts</small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_location" name="allow_location" 
                                               value="1" {{ old('allow_location', $settings['allow_location'] ?? true) ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-cog me-2"></i>Post Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_post_length" class="form-label">Maximum Post Length *</label>
                                        <input type="number" class="form-control" id="max_post_length" name="max_post_length" 
                                               value="{{ old('max_post_length', $settings['max_post_length'] ?? 5000) }}" 
                                               min="100" max="50000" required>
                                        <small class="form-text text-muted">Maximum characters allowed in a post</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_images_per_post" class="form-label">Max Images Per Post</label>
                                        <input type="number" class="form-control" id="max_images_per_post" name="max_images_per_post" 
                                               value="{{ old('max_images_per_post', $settings['max_images_per_post'] ?? 10) }}" 
                                               min="1" max="20">
                                        <small class="form-text text-muted">Maximum number of images per post</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_videos_per_post" class="form-label">Max Videos Per Post</label>
                                        <input type="number" class="form-control" id="max_videos_per_post" name="max_videos_per_post" 
                                               value="{{ old('max_videos_per_post', $settings['max_videos_per_post'] ?? 3) }}" 
                                               min="1" max="10">
                                        <small class="form-text text-muted">Maximum number of videos per post</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="posts_per_page" class="form-label">Posts Per Page</label>
                                        <input type="number" class="form-control" id="posts_per_page" name="posts_per_page" 
                                               value="{{ old('posts_per_page', $settings['posts_per_page'] ?? 20) }}" 
                                               min="5" max="100">
                                        <small class="form-text text-muted">Number of posts to display per page</small>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-shield-alt me-2"></i>Moderation Settings</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_approve_posts" name="auto_approve_posts" 
                                                   value="1" {{ old('auto_approve_posts', $settings['auto_approve_posts'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="auto_approve_posts">
                                                Auto Approve Posts
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="content_filtering" name="content_filtering" 
                                                   value="1" {{ old('content_filtering', $settings['content_filtering'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="content_filtering">
                                                Enable Content Filtering
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="profanity_filter" name="profanity_filter" 
                                                   value="1" {{ old('profanity_filter', $settings['profanity_filter'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="profanity_filter">
                                                Enable Profanity Filter
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="spam_detection" name="spam_detection" 
                                                   value="1" {{ old('spam_detection', $settings['spam_detection'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="spam_detection">
                                                Enable Spam Detection
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="moderation_keywords" class="form-label">Moderation Keywords</label>
                                        <textarea class="form-control" id="moderation_keywords" name="moderation_keywords" rows="3">{{ old('moderation_keywords', $settings['moderation_keywords'] ?? '') }}</textarea>
                                        <small class="form-text text-muted">Comma-separated keywords that trigger moderation</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="banned_keywords" class="form-label">Banned Keywords</label>
                                        <textarea class="form-control" id="banned_keywords" name="banned_keywords" rows="3">{{ old('banned_keywords', $settings['banned_keywords'] ?? '') }}</textarea>
                                        <small class="form-text text-muted">Comma-separated keywords that are completely banned</small>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-heart me-2"></i>Engagement Settings</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_likes" name="enable_likes" 
                                                   value="1" {{ old('enable_likes', $settings['enable_likes'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enable_likes">
                                                Enable Likes
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_comments" name="enable_comments" 
                                                   value="1" {{ old('enable_comments', $settings['enable_comments'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enable_comments">
                                                Enable Comments
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_shares" name="enable_shares" 
                                                   value="1" {{ old('enable_shares', $settings['enable_shares'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enable_shares">
                                                Enable Shares
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="enable_bookmarks" name="enable_bookmarks" 
                                                   value="1" {{ old('enable_bookmarks', $settings['enable_bookmarks'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enable_bookmarks">
                                                Enable Bookmarks
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_comments_per_post" class="form-label">Max Comments Per Post</label>
                                        <input type="number" class="form-control" id="max_comments_per_post" name="max_comments_per_post" 
                                               value="{{ old('max_comments_per_post', $settings['max_comments_per_post'] ?? 1000) }}" 
                                               min="10" max="10000">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_comment_length" class="form-label">Max Comment Length</label>
                                        <input type="number" class="form-control" id="max_comment_length" name="max_comment_length" 
                                               value="{{ old('max_comment_length', $settings['max_comment_length'] ?? 500) }}" 
                                               min="50" max="2000">
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-clock me-2"></i>Timing Settings</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="post_edit_time_limit" class="form-label">Post Edit Time Limit (minutes)</label>
                                        <input type="number" class="form-control" id="post_edit_time_limit" name="post_edit_time_limit" 
                                               value="{{ old('post_edit_time_limit', $settings['post_edit_time_limit'] ?? 15) }}" 
                                               min="0" max="1440">
                                        <small class="form-text text-muted">Time limit for editing posts (0 = no limit)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="post_delete_time_limit" class="form-label">Post Delete Time Limit (minutes)</label>
                                        <input type="number" class="form-control" id="post_delete_time_limit" name="post_delete_time_limit" 
                                               value="{{ old('post_delete_time_limit', $settings['post_delete_time_limit'] ?? 60) }}" 
                                               min="0" max="1440">
                                        <small class="form-text text-muted">Time limit for deleting posts (0 = no limit)</small>
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






