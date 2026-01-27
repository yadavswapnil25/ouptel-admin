<?php
/**
 * Legacy API Handler - Routes old WoWonder API requests to new Laravel API endpoints
 * 
 * This file handles legacy API calls like: requests.php?hash=...&f=posts&s=delete_post&post_id=...
 * and routes them to the corresponding Laravel API endpoints.
 */

// Bootstrap Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Get request parameters
$f = $_GET['f'] ?? $_POST['f'] ?? null; // Feature (e.g., 'posts')
$s = $_GET['s'] ?? $_POST['s'] ?? null; // Sub-action (e.g., 'delete_post')
$hash = $_GET['hash'] ?? $_POST['hash'] ?? null; // Session hash
$postId = $_GET['post_id'] ?? $_POST['post_id'] ?? null;

// Handle posts delete_post action
if ($f === 'posts' && $s === 'delete_post') {
    // Validate hash and get user_id from session
    if (empty($hash)) {
        header('Content-Type: application/json');
        echo json_encode([
            'api_status' => 400,
            'api_text' => 'failed',
            'errors' => [
                'error_id' => 1,
                'error_text' => 'Hash is required'
            ]
        ]);
        exit;
    }

    if (empty($postId)) {
        header('Content-Type: application/json');
        echo json_encode([
            'api_status' => 400,
            'api_text' => 'failed',
            'errors' => [
                'error_id' => 5,
                'error_text' => 'No post id sent.'
            ]
        ]);
        exit;
    }

    // Get user_id from hash (assuming hash is session_id)
    // Use Laravel's DB facade
    try {
        $userId = Illuminate\Support\Facades\DB::table('Wo_AppsSessions')
            ->where('session_id', $hash)
            ->value('user_id');
        
        if (!$userId) {
            header('Content-Type: application/json');
            echo json_encode([
                'api_status' => 400,
                'api_text' => 'failed',
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid hash - Session not found'
                ]
            ]);
            exit;
        }
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'api_status' => 400,
            'api_text' => 'failed',
            'errors' => [
                'error_id' => 2,
                'error_text' => 'Invalid hash - Session not found'
            ]
        ]);
        exit;
    }

    // Create Laravel request and forward to API endpoint
    $request = Illuminate\Http\Request::create(
        '/api/v1/posts/delete',
        'POST',
        ['post_id' => $postId],
        [],
        [],
        [
            'HTTP_Authorization' => 'Bearer ' . $hash,
            'HTTP_Accept' => 'application/json',
        ]
    );

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    
    $response->send();
    exit;
}

// Default response for unhandled requests
header('Content-Type: application/json');
echo json_encode([
    'api_status' => 400,
    'api_text' => 'failed',
    'errors' => [
        'error_id' => 1,
        'error_text' => 'Bad request, feature not supported or not specified.'
    ]
]);
exit;

