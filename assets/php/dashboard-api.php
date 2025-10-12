<?php
/**
 * Dashboard Backend API - Returns JSON data
 * Updated for Extended Logging System
 * @version 2025.10.12
 */

header('Content-Type: application/json');

// Harden: the API must validate dashboard tokens on every request.
// Accept token via Cookie `dashboard_token` or `Authorization: Bearer <token>` header.
require_once __DIR__ . '/ExtendedLogger.php';

// Lightweight env() helper (prefer process env, then asset files)
function env($key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];

    $candidates = [
        __DIR__ . '/.env.prod',
        __DIR__ . '/.app.env',
        __DIR__ . '/app.env',
        dirname(__DIR__) . '/.app.env',
        dirname(__DIR__) . '/app.env',
        dirname(__DIR__) . '/.env',
    ];
    foreach ($candidates as $envFile) {
        if (!file_exists($envFile)) continue;
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $val] = explode('=', $line, 2);
            if (trim($k) === $key) return trim($val, " \t\n\r\0\x0B\"'");
        }
    }
    return $default;
}

function verifyToken($token, $secret) {
    if (empty($token) || strpos($token, '.') === false) return false;
    [$payload, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) return false;
    $data = json_decode(base64_decode($payload), true);
    return $data && isset($data['exp']) && $data['exp'] >= time();
}

try {
    $secret = env('DASHBOARD_SECRET');
    if (empty($secret)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server misconfiguration: DASHBOARD_SECRET not set']);
        exit;
    }

    // get token from Authorization header (Bearer) or cookie
    $token = null;
    $headers = getallheaders();
    if (!empty($headers['Authorization'])) {
        if (preg_match('/^Bearer\s+(.*)$/i', $headers['Authorization'], $m)) {
            $token = trim($m[1]);
        }
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    }
    if (!$token && !empty($_COOKIE['dashboard_token'])) {
        $token = $_COOKIE['dashboard_token'];
    }

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['status' => 'unauthorized', 'message' => 'Missing dashboard token']);
        exit;
    }

    if (!verifyToken($token, $secret)) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden', 'message' => 'Invalid or expired token']);
        exit;
    }

    // token is valid — proceed to build the response
    $logger = new ExtendedLogger(__DIR__ . '/logs');
    $date = $_GET['date'] ?? date('Y-m-d');

    // Get statistics from ExtendedLogger
    $stats = $logger->getStatistics(1); // Today only
    $recentSubmissions = $logger->getRecentSubmissions(10, false); // Include anonymized

    // Calculate trend (last 7 days)
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $trendDate = date('Y-m-d', strtotime("-$i days"));

        // Count submissions for specific date
        $daySubmissions = array_filter($logger->getRecentSubmissions(1000, false), function($sub) use ($trendDate) {
            return date('Y-m-d', strtotime($sub['timestamp'])) === $trendDate;
        });

        $dayBlocked = array_filter($daySubmissions, fn($s) => $s['blocked'] ?? false);
        $dayAllowed = array_filter($daySubmissions, fn($s) => !($s['blocked'] ?? false));

        $spamScores = array_map(fn($s) => $s['spamScore'] ?? 0, $daySubmissions);
        $avgSpamScore = !empty($spamScores) ? array_sum($spamScores) / count($spamScores) : 0;

        $trend[] = [
            'date' => $trendDate,
            'total' => count($daySubmissions),
            'allowed' => count($dayAllowed),
            'blocked' => count($dayBlocked),
            'avgSpamScore' => round($avgSpamScore, 2)
        ];
    }

    // Build response
    $response = [
        'today' => [
            'total' => $stats['total'],
            'allowed' => $stats['allowed'],
            'blocked' => $stats['blocked'],
            'avgSpamScore' => $stats['avgSpamScore'],
            'topIPs' => $stats['topIPs'] ?? [],
            'byReason' => $stats['topBlockReasons'] ?? []
        ],
        'status' => 'ok',
        'timestamp' => date('c'),
        'recentSubmissions' => array_map(function($sub) {
            return [
                'timestamp' => $sub['timestamp'],
                'email' => $sub['formData']['email'] ?? 'N/A',
                'spamScore' => $sub['spamScore'] ?? 0,
                'blocked' => $sub['blocked'] ?? false
            ];
        }, $recentSubmissions),
        'recentBlocks' => array_filter($recentSubmissions, fn($s) => $s['blocked'] ?? false),
        'trend' => $trend
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
