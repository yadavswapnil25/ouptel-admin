@extends('admin.settings.layout')

@section('settings-title', 'Website Mode Settings')

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

<form method="POST" action="{{ route('admin.settings.update', 'website_mode') }}">
    @csrf
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-globe me-2"></i>Website Mode Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-tools me-2"></i>Maintenance Mode
                        </h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                   value="1" {{ old('maintenance_mode', $settings['maintenance_mode'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="maintenance_mode">
                                Enable Maintenance Mode
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            When enabled, only administrators can access the website
                        </small>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-user-plus me-2"></i>Registration Settings
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled" 
                                   value="1" {{ old('registration_enabled', $settings['registration_enabled'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="registration_enabled">
                                Allow User Registration
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="email_verification" name="email_verification" 
                                   value="1" {{ old('email_verification', $settings['email_verification'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="email_verification">
                                Require Email Verification
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="admin_approval" name="admin_approval" 
                                   value="1" {{ old('admin_approval', $settings['admin_approval'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="admin_approval">
                                Require Admin Approval
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Settings
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="login_enabled" name="login_enabled" 
                                   value="1" {{ old('login_enabled', $settings['login_enabled'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="login_enabled">
                                Allow User Login
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guest_access" name="guest_access" 
                                   value="1" {{ old('guest_access', $settings['guest_access'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="guest_access">
                                Allow Guest Access
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-shield-alt me-2"></i>Security Settings
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="two_factor_enabled" name="two_factor_enabled" 
                                   value="1" {{ old('two_factor_enabled', $settings['two_factor_enabled'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="two_factor_enabled">
                                Enable Two-Factor Authentication
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="recaptcha_enabled" name="recaptcha_enabled" 
                                   value="1" {{ old('recaptcha_enabled', $settings['recaptcha_enabled'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="recaptcha_enabled">
                                Enable Google reCAPTCHA
                            </label>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="recaptcha_site_key" class="form-label">reCAPTCHA Site Key</label>
                                <input type="text" class="form-control" id="recaptcha_site_key" name="recaptcha_site_key" 
                                       value="{{ old('recaptcha_site_key', $settings['recaptcha_site_key'] ?? '') }}" 
                                       placeholder="Enter site key">
                            </div>
                            <div class="col-md-6">
                                <label for="recaptcha_secret_key" class="form-label">reCAPTCHA Secret Key</label>
                                <input type="password" class="form-control" id="recaptcha_secret_key" name="recaptcha_secret_key" 
                                       value="{{ old('recaptcha_secret_key', $settings['recaptcha_secret_key'] ?? '') }}" 
                                       placeholder="Enter secret key">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-comment me-2"></i>Maintenance Message
                        </h6>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="4" 
                                  placeholder="Enter maintenance mode message...">{{ old('maintenance_message', $settings['maintenance_message'] ?? '') }}</textarea>
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
                        <strong>Website Mode</strong> controls the overall status and accessibility of your website.
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Active: Normal operation</li>
                        <li><i class="fas fa-wrench text-warning me-2"></i>Maintenance: Limited access</li>
                    </ul>
                    <hr>
                    <p class="card-text">
                        <strong>Security Features:</strong>
                    </p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-shield-alt text-primary me-2"></i>Two-Factor Authentication</li>
                        <li><i class="fas fa-robot text-info me-2"></i>reCAPTCHA Protection</li>
                        <li><i class="fas fa-envelope text-success me-2"></i>Email Verification</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection