<?php
// db.php
// Author: Divyanshu
// Description: Creates and returns a PDO database connection.
//              Every other PHP file includes this to talk to the database.

require_once 'config.php';

function getConnection() {
    // Build the DSN (Data Source Name) string
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    // PDO options — best practice settings
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw errors as exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return rows as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
    ];

    try {
        // Create and return the PDO connection
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;

    } catch (PDOException $e) {
        // Return a JSON error instead of crashing — safe for API use
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}
