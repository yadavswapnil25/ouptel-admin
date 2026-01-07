<?php
/**
 * Comprehensive intl diagnostic tool
 * Access via: http://127.0.0.1:8000/diagnose-intl.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>intl Extension Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PHP intl Extension Diagnostic</h1>
        
        <div class="section">
            <h2>1. Extension Status</h2>
            <?php if (extension_loaded('intl')): ?>
                <p class="success">✓ intl extension is LOADED</p>
                <?php if (defined('INTL_ICU_VERSION')): ?>
                    <p>ICU Version: <strong><?= INTL_ICU_VERSION ?></strong></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="error">✗ intl extension is NOT LOADED</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>2. PHP Configuration</h2>
            <p><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
            <p><strong>php.ini Location:</strong> <code><?= php_ini_loaded_file() ?></code></p>
            <p><strong>Additional ini files:</strong> <?= php_ini_scanned_files() ?: 'None' ?></p>
        </div>

        <div class="section">
            <h2>3. Extension File Check</h2>
            <?php
            $extPath = ini_get('extension_dir');
            $intlDll = $extPath . DIRECTORY_SEPARATOR . 'php_intl.dll';
            ?>
            <p><strong>Extension Directory:</strong> <code><?= $extPath ?></code></p>
            <p><strong>intl DLL Path:</strong> <code><?= $intlDll ?></code></p>
            <?php if (file_exists($intlDll)): ?>
                <p class="success">✓ php_intl.dll file EXISTS</p>
            <?php else: ?>
                <p class="error">✗ php_intl.dll file NOT FOUND</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>4. Test Formatting</h2>
            <?php if (extension_loaded('intl')): ?>
                <?php
                try {
                    $date = new DateTime();
                    $formatter = new IntlDateFormatter('en_US', IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
                    $formatted = $formatter->format($date);
                    echo '<p class="success">✓ Formatting test: <strong>' . htmlspecialchars($formatted) . '</strong></p>';
                } catch (Exception $e) {
                    echo '<p class="error">✗ Formatting test failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            <?php else: ?>
                <p class="error">✗ Cannot test formatting - extension not loaded</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>5. Solution</h2>
            <?php if (!extension_loaded('intl')): ?>
                <div class="warning">
                    <h3>To Fix This Issue:</h3>
                    <ol>
                        <li><strong>Open XAMPP Control Panel</strong></li>
                        <li><strong>Stop Apache</strong> (click "Stop" button)</li>
                        <li><strong>Wait 5 seconds</strong></li>
                        <li><strong>Start Apache</strong> (click "Start" button)</li>
                        <li><strong>Refresh this page</strong> to verify</li>
                    </ol>
                    <p><strong>OR</strong> if using <code>php artisan serve</code>:</p>
                    <ol>
                        <li>Stop the server (Ctrl+C)</li>
                        <li>Run: <code>php artisan serve</code> again</li>
                    </ol>
                </div>
            <?php else: ?>
                <p class="success">✓ Everything looks good! intl is loaded and working.</p>
                <p>If you're still seeing errors, try:</p>
                <ul>
                    <li>Clear browser cache</li>
                    <li>Clear Laravel cache: <code>php artisan cache:clear</code></li>
                    <li>Clear config cache: <code>php artisan config:clear</code></li>
                </ul>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>6. All Loaded Extensions</h2>
            <pre><?php
            $extensions = get_loaded_extensions();
            sort($extensions);
            echo implode("\n", $extensions);
            ?></pre>
        </div>
    </div>
</body>
</html>

