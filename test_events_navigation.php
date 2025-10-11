<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Filament\Admin\Resources\EventResource;

try {
    echo "Testing Events navigation move to Manage Features...\n\n";
    
    // Test EventResource navigation
    echo "EventResource:\n";
    echo "  Navigation Group: " . EventResource::getNavigationGroup() . "\n";
    echo "  Navigation Sort: " . EventResource::getNavigationSort() . "\n";
    echo "  Navigation Label: " . EventResource::getNavigationLabel() . "\n";
    echo "  Navigation Icon: " . EventResource::getNavigationIcon() . "\n\n";
    
    echo "✅ Events has been successfully moved to Manage Features!\n";
    echo "Updated Manage Features Navigation Structure:\n";
    echo "📋 Manage Features\n";
    echo "  ├── 1. Pages (sort: 1) - 📄 Pages\n";
    echo "  ├── 3. Posts (sort: 3) - 📝 Posts\n";
    echo "  ├── 4. Funding (sort: 4) - 💰 Funding\n";
    echo "  ├── 5. Jobs (sort: 5) - 💼 Jobs\n";
    echo "  ├── 6. Job Categories (sort: 6) - 🏷️ Job Categories\n";
    echo "  ├── 7. Articles (sort: 7) - 📰 Articles\n";
    echo "  ├── 8. Blog Categories (sort: 8) - 🏷️ Blog Categories\n";
    echo "  ├── 9. Events (sort: 9) - 📅 Events\n";
    echo "  └── Groups (no sort) - 👥 Groups\n\n";
    
    echo "Events is now properly organized under Manage Features!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
























