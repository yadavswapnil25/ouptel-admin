<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouptel Admin - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .login-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }
        .login-header h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }
        .login-header p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }
        .form-floating {
            margin-bottom: 1.5rem;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 10;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        .form-check-label {
            color: #666;
            font-size: 0.9rem;
        }
        .alert {
            border-radius: 12px;
            border: none;
        }
        .input-group {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>Ouptel Admin</h1>
            <p>Welcome back! Please sign in to your account</p>
        </div>
        
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="{{ route('admin.login') }}">
            @csrf
            
            <div class="input-group mb-3">
                <span class="input-icon">
                    <i class="fas fa-envelope"></i>
                </span>
                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" value="{{ old('email') }}" required autofocus>
            </div>
            
            <div class="input-group mb-4">
                <span class="input-icon">
                    <i class="fas fa-lock"></i>
                </span>
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            </div>
            
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">
                    Remember me
                </label>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In to Dashboard
                </button>
            </div>
        </form>
        
        <div class="text-center mt-4">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>
                Secure admin access
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>