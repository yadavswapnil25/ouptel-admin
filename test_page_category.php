<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Page;

try {
    echo "Testing Page model category_name accessor...\n\n";
    
    // Test creating a Page instance with different category values
    $testCategories = ['business', 'entertainment', 'education', 'health', 'technology', 'sports', 'news', 'other', null];
    
    foreach ($testCategories as $category) {
        $page = new Page(['page_category' => $category]);
        echo "Category: " . ($category ?? 'null') . " -> Display Name: " . $page->category_name . "\n";
    }
    
    echo "\nTesting with a sample page from database...\n";
    
    // Try to get a real page from the database
    $realPage = Page::first();
    if ($realPage) {
        echo "Real page found:\n";
        echo "Page ID: " . $realPage->page_id . "\n";
        echo "Page Name: " . $realPage->page_name . "\n";
        echo "Raw Category: " . $realPage->page_category . "\n";
        echo "Category Name: " . $realPage->category_name . "\n";
    } else {
        echo "No pages found in database.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
