<?php
/**
 * Simple intl check - Access via browser
 * http://127.0.0.1:8000/check-intl-simple.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== PHP intl Extension Check ===\n\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "php.ini file: " . php_ini_loaded_file() . "\n\n";

if (extension_loaded('intl')) {
    echo "✓ intl extension is LOADED\n";
    if (defined('INTL_ICU_VERSION')) {
        echo "ICU Version: " . INTL_ICU_VERSION . "\n";
    }
    echo "\n✓ Your web server PHP has intl enabled!\n";
} else {
    echo "✗ intl extension is NOT LOADED\n";
    echo "\nTo fix:\n";
    echo "1. Open: E:\\xampp8.2\\php\\php.ini\n";
    echo "2. Find: ;extension=intl\n";
    echo "3. Change to: extension=intl\n";
    echo "4. RESTART Apache in XAMPP Control Panel\n";
    echo "\nCurrent php.ini: " . php_ini_loaded_file() . "\n";
}


