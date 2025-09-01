@extends('admin.settings.layout')

@section('settings-title', 'Settings Overview')

@section('settings-content')
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Welcome to Settings!</strong> Use the sidebar menu to navigate between different configuration sections.
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-globe me-2"></i>Website Configuration
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">Configure your website's basic settings, mode, and information.</p>
                <a href="{{ route('admin.settings.website-mode') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog me-1"></i>Configure
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>File Upload Settings
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">Manage file upload limits, allowed extensions, and storage settings.</p>
                <a href="{{ route('admin.settings.file-upload') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog me-1"></i>Configure
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-envelope me-2"></i>Email & SMS
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">Setup SMTP configuration and SMS provider settings.</p>
                <a href="{{ route('admin.settings.email-sms') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog me-1"></i>Configure
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-share-alt me-2"></i>Social Login
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">Configure social media login integrations.</p>
                <a href="{{ route('admin.settings.social-login') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-cog me-1"></i>Configure
                </a>
            </div>
        </div>
    </div>
</div>
@endsection