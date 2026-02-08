<?php

/**
 * Forum Module Verification Script
 * Run: php verify_forum_module.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "Forum Module Verification\n";
echo "========================================\n\n";

$errors = [];
$success = [];

// 1. Check if ForumResource exists
echo "1. Checking ForumResource...\n";
if (class_exists(\App\Filament\Admin\Resources\ForumResource::class)) {
    echo "   ✅ ForumResource class exists\n";
    $success[] = "ForumResource";
} else {
    echo "   ❌ ForumResource class NOT found\n";
    $errors[] = "ForumResource class missing";
}

// 2. Check if all pages exist
echo "\n2. Checking Forum Pages...\n";
$pages = [
    'ListForums' => \App\Filament\Admin\Resources\ForumResource\Pages\ListForums::class,
    'CreateForum' => \App\Filament\Admin\Resources\ForumResource\Pages\CreateForum::class,
    'EditForum' => \App\Filament\Admin\Resources\ForumResource\Pages\EditForum::class,
    'ViewForum' => \App\Filament\Admin\Resources\ForumResource\Pages\ViewForum::class,
];

foreach ($pages as $name => $class) {
    if (class_exists($class)) {
        echo "   ✅ {$name} class exists\n";
        $success[] = $name;
    } else {
        echo "   ❌ {$name} class NOT found\n";
        $errors[] = "{$name} class missing";
    }
}

// 3. Check if widget exists
echo "\n3. Checking Forum Widget...\n";
if (class_exists(\App\Filament\Admin\Resources\ForumResource\Widgets\ForumStatsWidget::class)) {
    echo "   ✅ ForumStatsWidget class exists\n";
    $success[] = "ForumStatsWidget";
} else {
    echo "   ❌ ForumStatsWidget class NOT found\n";
    $errors[] = "ForumStatsWidget class missing";
}

// 4. Check Forum model
echo "\n4. Checking Forum Model...\n";
if (class_exists(\App\Models\Forum::class)) {
    echo "   ✅ Forum model exists\n";
    $model = new \App\Models\Forum();
    $fillable = $model->getFillable();
    
    $requiredFields = ['name', 'description', 'sections', 'posts', 'last_post'];
    $missingFields = array_diff($requiredFields, $fillable);
    
    if (empty($missingFields)) {
        echo "   ✅ All required fields in fillable array\n";
        $success[] = "Forum model fillable fields";
    } else {
        echo "   ⚠️  Missing fields in fillable: " . implode(', ', $missingFields) . "\n";
        $errors[] = "Forum model missing fillable fields: " . implode(', ', $missingFields);
    }
} else {
    echo "   ❌ Forum model NOT found\n";
    $errors[] = "Forum model missing";
}

// 5. Check routes
echo "\n5. Checking Routes...\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$forumRoutes = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (str_contains($uri, 'admin/forums')) {
        $forumRoutes[] = $route->methods()[0] . ' ' . $uri;
    }
}

if (count($forumRoutes) >= 4) {
    echo "   ✅ Found " . count($forumRoutes) . " forum routes:\n";
    foreach ($forumRoutes as $route) {
        echo "      - {$route}\n";
    }
    $success[] = "Forum routes registered";
} else {
    echo "   ❌ Expected at least 4 routes, found " . count($forumRoutes) . "\n";
    $errors[] = "Insufficient forum routes";
}

// 6. Check database table
echo "\n6. Checking Database Table...\n";
try {
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('Wo_Forums');
    if ($tableExists) {
        echo "   ✅ Wo_Forums table exists\n";
        
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('Wo_Forums');
        $requiredColumns = ['id', 'name', 'description', 'sections', 'posts', 'last_post'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (empty($missingColumns)) {
            echo "   ✅ All required columns exist\n";
            $success[] = "Database table structure";
        } else {
            echo "   ⚠️  Missing columns: " . implode(', ', $missingColumns) . "\n";
            $errors[] = "Missing database columns: " . implode(', ', $missingColumns);
        }
        
        // Check if there are any forums
        $forumCount = \App\Models\Forum::count();
        echo "   ℹ️  Current forums in database: {$forumCount}\n";
    } else {
        echo "   ❌ Wo_Forums table does NOT exist\n";
        $errors[] = "Wo_Forums table missing";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Could not check database: " . $e->getMessage() . "\n";
}

// 7. Check file structure
echo "\n7. Checking File Structure...\n";
$files = [
    'app/Filament/Admin/Resources/ForumResource.php',
    'app/Filament/Admin/Resources/ForumResource/Pages/ListForums.php',
    'app/Filament/Admin/Resources/ForumResource/Pages/CreateForum.php',
    'app/Filament/Admin/Resources/ForumResource/Pages/EditForum.php',
    'app/Filament/Admin/Resources/ForumResource/Pages/ViewForum.php',
    'app/Filament/Admin/Resources/ForumResource/Widgets/ForumStatsWidget.php',
];

$allFilesExist = true;
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "   ✅ {$file}\n";
    } else {
        echo "   ❌ {$file} NOT found\n";
        $allFilesExist = false;
        $errors[] = "File missing: {$file}";
    }
}

if ($allFilesExist) {
    $success[] = "All files exist";
}

// Summary
echo "\n========================================\n";
echo "Verification Summary\n";
echo "========================================\n";
echo "✅ Successful checks: " . count($success) . "\n";
echo "❌ Errors found: " . count($errors) . "\n\n";

if (empty($errors)) {
    echo "🎉 All checks passed! Forum module is ready.\n";
    echo "\nNext steps:\n";
    echo "1. Open browser: http://127.0.0.1:8000/admin\n";
    echo "2. Login with: admin@ouptel.com / admin123\n";
    echo "3. Navigate to: Manage Features > Forums\n";
    echo "4. Test CRUD operations\n";
    exit(0);
} else {
    echo "⚠️  Issues found:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    exit(1);
}

