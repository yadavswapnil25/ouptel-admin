@extends('admin.layouts.app')

@section('title', 'Ouptel Admin - Settings')

@section('content')
<div class="d-flex">
    <!-- Settings Sidebar -->
    <div class="settings-sidebar">
        <div class="settings-header">
            <i class="fas fa-cog me-2"></i>
            <span>Settings</span>
            <i class="fas fa-minus ms-auto"></i>
        </div>
        
        <nav class="settings-nav">
            <a href="{{ route('admin.settings.website-mode') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.website-mode') ? 'active' : '' }}">
                <span>Website Mode</span>
            </a>
            
            <a href="{{ route('admin.settings.general') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}">
                <span>General Configuration</span>
            </a>
            
            <a href="{{ route('admin.settings.website-info') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.website-info') ? 'active' : '' }}">
                <span>Website Information</span>
            </a>
            
            <a href="{{ route('admin.settings.file-upload') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.file-upload') ? 'active' : '' }}">
                <span>File Upload Configuration</span>
                <i class="fas fa-chevron-right ms-auto"></i>
            </a>
            
            <a href="{{ route('admin.settings.email-sms') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.email-sms') ? 'active' : '' }}">
                <span>E-mail & SMS Setup</span>
            </a>
            
            <a href="{{ route('admin.settings.chat-video-audio') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.chat-video-audio') ? 'active' : '' }}">
                <span>Chat & Video/Audio</span>
            </a>
            
            <a href="{{ route('admin.settings.social-login') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.social-login') ? 'active' : '' }}">
                <span>Social Login Settings</span>
            </a>
            
            <a href="{{ route('admin.settings.nodejs') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.nodejs') ? 'active' : '' }}">
                <span>NodeJS Settings</span>
            </a>
            
            <div class="settings-nav-group">
                <a href="{{ route('admin.settings.posts') }}" class="settings-nav-item {{ request()->routeIs('admin.settings.posts') ? 'active' : '' }}">
                    <span>Posts Settings</span>
                    <i class="fas fa-plus ms-auto"></i>
                </a>
            </div>
        </nav>
    </div>
    
    <!-- Settings Content -->
    <div class="settings-content">
        <div class="settings-content-header">
            <h1 class="h3 mb-0">@yield('settings-title', 'Settings')</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings') }}">Settings</a></li>
                    <li class="breadcrumb-item active">@yield('settings-title', 'Settings')</li>
                </ol>
            </nav>
        </div>
        
        <div class="settings-content-body">
            @yield('settings-content')
        </div>
    </div>
</div>

<style>
.settings-sidebar {
    width: 280px;
    background: #2c3e50;
    min-height: calc(100vh - 56px);
    color: #ecf0f1;
    position: fixed;
    left: 0;
    top: 56px;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.settings-header {
    padding: 1.5rem 1rem;
    border-bottom: 1px solid #34495e;
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 1.1rem;
    background: #34495e;
}

.settings-nav {
    padding: 1rem 0;
}

.settings-nav-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    font-size: 0.9rem;
}

.settings-nav-item:hover {
    background: #34495e;
    color: #ecf0f1;
    text-decoration: none;
}

.settings-nav-item.active {
    background: #3498db;
    color: white;
    border-left-color: #2980b9;
    font-weight: 500;
}

.settings-nav-item i {
    font-size: 0.8rem;
    opacity: 0.7;
}

.settings-nav-group {
    border-top: 1px solid #34495e;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
}

.settings-content {
    margin-left: 280px;
    flex: 1;
    padding: 2rem;
    background: #f8f9fa;
    min-height: calc(100vh - 56px);
}

.settings-content-header {
    margin-bottom: 2rem;
}

.settings-content-body {
    background: white;
    border-radius: 10px;
    padding: 2rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
}

.breadcrumb-item a {
    color: #667eea;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #6c757d;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-sidebar {
        width: 100%;
        position: relative;
        top: 0;
    }
    
    .settings-content {
        margin-left: 0;
    }
}
</style>
@endsection






