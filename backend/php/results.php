
<?php
require_once 'db.php';

header('Content-Type: application/json; charset=UTF-8');

function sendResultResponse(
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
        // The real results query depends on your database schema.
        sendResultResponse(
            200,
            'Results endpoint is working.',
            []
        );
    }

    if ($method === 'POST') {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            sendResultResponse(
                400,
                'Invalid JSON request.'
            );
        }

        $matchId = filter_var(
            $input['match_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $team1Score = filter_var(
            $input['team1_score'] ?? null,
            FILTER_VALIDATE_INT
        );

        $team2Score = filter_var(
            $input['team2_score'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $matchId === false || $matchId === null ||
            $team1Score === false || $team1Score === null ||
            $team2Score === false || $team2Score === null ||
            $matchId <= 0 ||
            $team1Score < 0 ||
            $team2Score < 0
        ) {
            sendResultResponse(
                400,
                'Please provide a valid match ID and non-negative scores.'
            );
        }

        sendResultResponse(
            501,
            'Result saving must be connected to your project database.'
        );
    }

    sendResultResponse(
        405,
        'Method not allowed.'
    );

} catch (Throwable $e) {
    error_log('Results API error: ' . $e->getMessage());

    sendResultResponse(
        500,
        'An internal server error occurred.'
    );
}
