<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - File Upload Configuration</title>
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
        .file-type-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #e9ecef;
            border-radius: 4px;
            font-size: 0.8rem;
            margin: 2px;
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
                                <i class="fas fa-upload me-2"></i>File Upload Configuration
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                                    <li class="breadcrumb-item active">File Upload</li>
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

                        <form method="POST" action="{{ route('admin.settings.update', 'file_upload') }}">
                            @csrf
                            
                            <div class="setting-section">
                                <h6><i class="fas fa-file-upload me-2"></i>Upload Limits</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_file_size" class="form-label">Maximum File Size (MB) *</label>
                                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                               value="{{ old('max_file_size', $settings['max_file_size'] ?? 10) }}" 
                                               min="1" max="100" required>
                                        <small class="form-text text-muted">Maximum file size allowed for uploads</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_files_per_upload" class="form-label">Max Files Per Upload</label>
                                        <input type="number" class="form-control" id="max_files_per_upload" name="max_files_per_upload" 
                                               value="{{ old('max_files_per_upload', $settings['max_files_per_upload'] ?? 5) }}" 
                                               min="1" max="20">
                                        <small class="form-text text-muted">Maximum number of files per upload</small>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-file-alt me-2"></i>Allowed File Types</h6>
                                <div class="mb-3">
                                    <label for="allowed_extensions" class="form-label">Allowed Extensions *</label>
                                    <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                                           value="{{ old('allowed_extensions', $settings['allowed_extensions'] ?? 'jpg,jpeg,png,gif,mp4,avi,mov,pdf,doc,docx') }}" 
                                           required>
                                    <small class="form-text text-muted">Comma-separated list of allowed file extensions</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">File Type Categories</label>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="allow_images" name="allow_images" 
                                                       value="1" {{ old('allow_images', $settings['allow_images'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="allow_images">
                                                    <i class="fas fa-image me-1"></i>Images
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="allow_videos" name="allow_videos" 
                                                       value="1" {{ old('allow_videos', $settings['allow_videos'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="allow_videos">
                                                    <i class="fas fa-video me-1"></i>Videos
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="allow_audio" name="allow_audio" 
                                                       value="1" {{ old('allow_audio', $settings['allow_audio'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="allow_audio">
                                                    <i class="fas fa-music me-1"></i>Audio
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="allow_documents" name="allow_documents" 
                                                       value="1" {{ old('allow_documents', $settings['allow_documents'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="allow_documents">
                                                    <i class="fas fa-file-alt me-1"></i>Documents
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-folder me-2"></i>Storage Configuration</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="upload_path" class="form-label">Upload Directory *</label>
                                        <input type="text" class="form-control" id="upload_path" name="upload_path" 
                                               value="{{ old('upload_path', $settings['upload_path'] ?? 'uploads') }}" required>
                                        <small class="form-text text-muted">Directory where files will be stored</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="storage_driver" class="form-label">Storage Driver</label>
                                        <select class="form-control" id="storage_driver" name="storage_driver">
                                            <option value="local" {{ old('storage_driver', $settings['storage_driver'] ?? 'local') == 'local' ? 'selected' : '' }}>Local Storage</option>
                                            <option value="s3" {{ old('storage_driver', $settings['storage_driver'] ?? '') == 's3' ? 'selected' : '' }}>Amazon S3</option>
                                            <option value="gcs" {{ old('storage_driver', $settings['storage_driver'] ?? '') == 'gcs' ? 'selected' : '' }}>Google Cloud Storage</option>
                                            <option value="ftp" {{ old('storage_driver', $settings['storage_driver'] ?? '') == 'ftp' ? 'selected' : '' }}>FTP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-cog me-2"></i>Image Processing</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="image_quality" class="form-label">Image Quality *</label>
                                        <input type="number" class="form-control" id="image_quality" name="image_quality" 
                                               value="{{ old('image_quality', $settings['image_quality'] ?? 85) }}" 
                                               min="1" max="100" required>
                                        <small class="form-text text-muted">Image compression quality (1-100)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="max_image_width" class="form-label">Max Image Width</label>
                                        <input type="number" class="form-control" id="max_image_width" name="max_image_width" 
                                               value="{{ old('max_image_width', $settings['max_image_width'] ?? 1920) }}" 
                                               min="100" max="4000">
                                        <small class="form-text text-muted">Maximum width for uploaded images</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="max_image_height" class="form-label">Max Image Height</label>
                                        <input type="number" class="form-control" id="max_image_height" name="max_image_height" 
                                               value="{{ old('max_image_height', $settings['max_image_height'] ?? 1080) }}" 
                                               min="100" max="4000">
                                        <small class="form-text text-muted">Maximum height for uploaded images</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="generate_thumbnails" class="form-label">Generate Thumbnails</label>
                                        <select class="form-control" id="generate_thumbnails" name="generate_thumbnails">
                                            <option value="1" {{ old('generate_thumbnails', $settings['generate_thumbnails'] ?? true) ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ old('generate_thumbnails', $settings['generate_thumbnails'] ?? '') ? 'selected' : '' }}>No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="setting-section">
                                <h6><i class="fas fa-shield-alt me-2"></i>Security Settings</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="scan_uploads" name="scan_uploads" 
                                                   value="1" {{ old('scan_uploads', $settings['scan_uploads'] ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="scan_uploads">
                                                Scan Uploads for Malware
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="watermark_images" name="watermark_images" 
                                                   value="1" {{ old('watermark_images', $settings['watermark_images'] ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="watermark_images">
                                                Add Watermark to Images
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






