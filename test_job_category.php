<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\JobCategory;

try {
    echo "Testing updated JobCategory model...\n\n";
    
    // Test with a few job categories
    $categories = JobCategory::limit(5)->get();
    foreach ($categories as $cat) {
        echo "ID: " . $cat->id . ", Lang Key: " . $cat->lang_key . ", Name: " . $cat->name . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}


