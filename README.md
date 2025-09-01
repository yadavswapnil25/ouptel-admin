# Ouptel Admin Panel

A modern, feature-rich admin panel for the Ouptel social networking platform built with Laravel and Filament.

## 🚀 Features

- **Modern UI**: Built with Filament 3.x for a beautiful, responsive interface
- **User Management**: Complete user lifecycle management with roles and permissions
- **Dashboard Analytics**: Real-time statistics and insights
- **Secure Authentication**: Admin-only access with secure login
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile
- **Custom Branding**: Ouptel-specific styling and branding

## 📋 Requirements

- PHP 8.1 or higher
- Composer
- MySQL 8.0 or higher
- Node.js 16+ (for asset compilation)

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd ouptel-admin
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database configuration**
   - Create a MySQL database named `ouptel_admin`
   - Update `.env` file with your database credentials

5. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed --class=AdminUserSeeder
   ```

6. **Start the development server**
   ```bash
   php artisan serve
   ```

## 🔐 Default Admin Credentials

- **Email**: admin@ouptel.com
- **Password**: admin123

## 📁 Project Structure

```
ouptel-admin/
├── app/
│   ├── Filament/
│   │   ├── Pages/          # Admin pages
│   │   ├── Resources/      # Resource definitions
│   │   └── Widgets/        # Dashboard widgets
│   ├── Models/             # Eloquent models
│   └── Providers/          # Service providers
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   └── css/               # Custom styles
└── config/                # Configuration files
```

## 🎨 Customization

### Branding
- Update logo in `app/Providers/Filament/AdminPanelProvider.php`
- Modify colors in `resources/css/filament.css`
- Change app name in `.env` file

### Adding New Resources
```bash
php artisan make:filament-resource ResourceName
```

### Adding New Widgets
```bash
php artisan make:filament-widget WidgetName
```

## 🔧 Development

### Running Tests
```bash
php artisan test
```

### Code Style
```bash
./vendor/bin/pint
```

### Asset Compilation
```bash
npm install
npm run dev
```

## 📊 Features Overview

### User Management
- View all users with search and filtering
- Create, edit, and delete users
- Manage user roles and permissions
- User status management (active, inactive, banned)
- Avatar upload and management

### Dashboard
- Real-time user statistics
- System overview widgets
- Quick access to common actions
- Responsive design for all devices

### Security
- Admin-only access
- Secure authentication
- CSRF protection
- Input validation and sanitization

## 🚀 Deployment

1. **Production environment setup**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. **Web server configuration**
   - Point document root to `public/` directory
   - Configure SSL certificate
   - Set up proper file permissions

3. **Database optimization**
   - Enable query caching
   - Optimize database indexes
   - Set up regular backups

## 📝 License

This project is licensed under the MIT License.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📞 Support

For support and questions, please contact the development team.

---

**Ouptel Admin Panel** - Built with ❤️ using Laravel and Filament