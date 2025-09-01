@extends('admin.settings.layout')

@section('settings-title', 'General Configuration')

@section('settings-content')
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

<form method="POST" action="{{ route('admin.settings.update', 'general') }}">
    @csrf
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-sliders-h me-2"></i>General Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                       value="{{ old('site_name', $settings['site_name'] ?? '') }}" 
                                       placeholder="Enter site name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="site_url" class="form-label">Site URL</label>
                                <input type="url" class="form-control" id="site_url" name="site_url" 
                                       value="{{ old('site_url', $settings['site_url'] ?? '') }}" 
                                       placeholder="https://example.com">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label for="site_description" class="form-label">Site Description</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3" 
                                      placeholder="Enter site description...">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <label for="site_keywords" class="form-label">Site Keywords</label>
                            <input type="text" class="form-control" id="site_keywords" name="site_keywords" 
                                   value="{{ old('site_keywords', $settings['site_keywords'] ?? '') }}" 
                                   placeholder="keyword1, keyword2, keyword3">
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-globe me-2"></i>Localization
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <label for="default_language" class="form-label">Default Language</label>
                                <select class="form-select" id="default_language" name="default_language">
                                    <option value="en" {{ old('default_language', $settings['default_language'] ?? 'en') == 'en' ? 'selected' : '' }}>English</option>
                                    <option value="es" {{ old('default_language', $settings['default_language'] ?? 'en') == 'es' ? 'selected' : '' }}>Spanish</option>
                                    <option value="fr" {{ old('default_language', $settings['default_language'] ?? 'en') == 'fr' ? 'selected' : '' }}>French</option>
                                    <option value="de" {{ old('default_language', $settings['default_language'] ?? 'en') == 'de' ? 'selected' : '' }}>German</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="default_timezone" class="form-label">Default Timezone</label>
                                <select class="form-select" id="default_timezone" name="default_timezone">
                                    <option value="UTC" {{ old('default_timezone', $settings['default_timezone'] ?? 'UTC') == 'UTC' ? 'selected' : '' }}>UTC</option>
                                    <option value="America/New_York" {{ old('default_timezone', $settings['default_timezone'] ?? 'UTC') == 'America/New_York' ? 'selected' : '' }}>Eastern Time</option>
                                    <option value="America/Chicago" {{ old('default_timezone', $settings['default_timezone'] ?? 'UTC') == 'America/Chicago' ? 'selected' : '' }}>Central Time</option>
                                    <option value="America/Denver" {{ old('default_timezone', $settings['default_timezone'] ?? 'UTC') == 'America/Denver' ? 'selected' : '' }}>Mountain Time</option>
                                    <option value="America/Los_Angeles" {{ old('default_timezone', $settings['default_timezone'] ?? 'UTC') == 'America/Los_Angeles' ? 'selected' : '' }}>Pacific Time</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="default_currency" class="form-label">Default Currency</label>
                                <select class="form-select" id="default_currency" name="default_currency">
                                    <option value="USD" {{ old('default_currency', $settings['default_currency'] ?? 'USD') == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                    <option value="EUR" {{ old('default_currency', $settings['default_currency'] ?? 'USD') == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                    <option value="GBP" {{ old('default_currency', $settings['default_currency'] ?? 'USD') == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                                    <option value="INR" {{ old('default_currency', $settings['default_currency'] ?? 'USD') == 'INR' ? 'selected' : '' }}>INR - Indian Rupee</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-calendar me-2"></i>Date & Time Format
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="date_format" class="form-label">Date Format</label>
                                <select class="form-select" id="date_format" name="date_format">
                                    <option value="Y-m-d" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-DD</option>
                                    <option value="m/d/Y" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') == 'm/d/Y' ? 'selected' : '' }}>MM/DD/YYYY</option>
                                    <option value="d/m/Y" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') == 'd/m/Y' ? 'selected' : '' }}>DD/MM/YYYY</option>
                                    <option value="M d, Y" {{ old('date_format', $settings['date_format'] ?? 'Y-m-d') == 'M d, Y' ? 'selected' : '' }}>Jan 1, 2024</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="time_format" class="form-label">Time Format</label>
                                <select class="form-select" id="time_format" name="time_format">
                                    <option value="H:i:s" {{ old('time_format', $settings['time_format'] ?? 'H:i:s') == 'H:i:s' ? 'selected' : '' }}>24 Hour (HH:MM:SS)</option>
                                    <option value="h:i:s A" {{ old('time_format', $settings['time_format'] ?? 'H:i:s') == 'h:i:s A' ? 'selected' : '' }}>12 Hour (HH:MM:SS AM/PM)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-cog me-2"></i>System Settings
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="items_per_page" class="form-label">Items Per Page</label>
                                <input type="number" class="form-control" id="items_per_page" name="items_per_page" 
                                       value="{{ old('items_per_page', $settings['items_per_page'] ?? 10) }}" 
                                       min="5" max="100">
                            </div>
                            <div class="col-md-6">
                                <label for="cache_lifetime" class="form-label">Cache Lifetime (minutes)</label>
                                <input type="number" class="form-control" id="cache_lifetime" name="cache_lifetime" 
                                       value="{{ old('cache_lifetime', $settings['cache_lifetime'] ?? 60) }}" 
                                       min="1" max="1440">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="session_lifetime" class="form-label">Session Lifetime (minutes)</label>
                                <input type="number" class="form-control" id="session_lifetime" name="session_lifetime" 
                                       value="{{ old('session_lifetime', $settings['session_lifetime'] ?? 120) }}" 
                                       min="1" max="1440">
                            </div>
                            <div class="col-md-6">
                                <label for="cookie_prefix" class="form-label">Cookie Prefix</label>
                                <input type="text" class="form-control" id="cookie_prefix" name="cookie_prefix" 
                                       value="{{ old('cookie_prefix', $settings['cookie_prefix'] ?? 'ouptel_') }}" 
                                       placeholder="ouptel_">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-toggle-on me-2"></i>Feature Toggles
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="enable_https" name="enable_https" 
                                   value="1" {{ old('enable_https', $settings['enable_https'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="enable_https">
                                Enable HTTPS
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode" 
                                   value="1" {{ old('debug_mode', $settings['debug_mode'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="debug_mode">
                                Enable Debug Mode
                            </label>
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
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Information
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <strong>General Configuration</strong> contains the basic settings for your website.
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-globe text-primary me-2"></i>Site Information</li>
                        <li><i class="fas fa-language text-success me-2"></i>Localization</li>
                        <li><i class="fas fa-calendar text-info me-2"></i>Date & Time</li>
                        <li><i class="fas fa-cog text-warning me-2"></i>System Settings</li>
                    </ul>
                    <hr>
                    <p class="card-text">
                        <strong>Tips:</strong>
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-lightbulb text-warning me-2"></i>Use HTTPS in production</li>
                        <li><i class="fas fa-bug text-danger me-2"></i>Disable debug mode in production</li>
                        <li><i class="fas fa-clock text-info me-2"></i>Set appropriate cache lifetime</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection