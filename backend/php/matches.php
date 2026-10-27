
<?php
require_once 'db.php';

header('Content-Type: application/json; charset=UTF-8');

function sendMatchResponse(
    int $status,
    string $message,
    mixed $data = null
): void {
    http_response_code($status);

    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        // Keep this endpoint safe until your actual
        // database schema and existing query are confirmed.
        sendMatchResponse(
            200,
            'Matches endpoint is working.',
            []
        );
    }

    if ($method === 'POST') {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            sendMatchResponse(
                400,
                'Invalid JSON request.'
            );
        }

        sendMatchResponse(
            501,
            'Match creation must be connected to your project database.'
        );
    }

    sendMatchResponse(
        405,
        'Method not allowed.'
    );

} catch (Throwable $e) {
    error_log('Matches API error: ' . $e->getMessage());

    sendMatchResponse(
        500,
        'An internal server error occurred.'
    );
}
