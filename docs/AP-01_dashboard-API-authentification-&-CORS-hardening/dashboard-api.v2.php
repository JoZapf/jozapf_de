<?php
/**
 * Dashboard Backend API - Returns JSON data
 * 
 * @version     2.0.0
 * @date        2025-10-05 18:30:00 UTC
 * @repository  https://github.com/JoZapf/contact-form-abuse-prevention
 * @package     ContactFormAbusePrevention
 * @author      Jo Zapf
 * 
 * CHANGELOG v2.0.0 (2025-10-05):
 * - [SECURITY] Added token-based authentication (AP-01)
 * - [SECURITY] Restricted CORS to own origin (AP-01)
 * - [PRIVACY] Minimized PII exposure - partial email masking (AP-01)
 * - [SECURITY] Added Cache-Control headers
 * - [SECURITY] Unauthorized access returns HTTP 401
 * 
 * Updated for Extended Logging System
 */

// ============================================================================
// SECURITY: Token Authentication (AP-01)
// ============================================================================

/**
 * Load environment variable from .env.prod
 */
function env($key, $default = null) {
    $envFile = __DIR__ . '/.env.prod';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                [$k, $v] = explode('=', trim($line), 2);
                if (trim($k) === $key) {
                    return trim($v, '"\'');
                }
            }
        }
    }
    return $default;
}

/**
 * Verify HMAC token from cookie
 * 
 * @param string $token The token to verify
 * @param string $secret The secret key for HMAC
 * @return bool True if valid, false otherwise
 */
function verifyToken($token, $secret) {
    if (empty($token) || strpos($token, '.') === false) {
        return false;
    }
    
    [$payload, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, $secret);
    
    if (!hash_equals($expected, $signature)) {
        return false;
    }
    
    $data = json_decode(base64_decode($payload), true);
    return $data && isset($data['exp']) && $data['exp'] >= time();
}

/**
 * Mask email address for privacy
 * Example: user@example.com -> u***@example.com
 * 
 * @param string $email The email to mask
 * @return string Masked email
 */
function maskEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'N/A';
    }
    
    [$local, $domain] = explode('@', $email);
    $maskedLocal = substr($local, 0, 1) . str_repeat('*', min(strlen($local) - 1, 3));
    
    return $maskedLocal . '@' . $domain;
}

// ============================================================================
// AUTHENTICATION CHECK
// ============================================================================

$DASHBOARD_SECRET = env('DASHBOARD_SECRET');

if (!$DASHBOARD_SECRET) {
    error_log('CRITICAL: DASHBOARD_SECRET not set in .env.prod');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Server configuration error'
    ]);
    exit;
}

$token = $_COOKIE['dashboard_token'] ?? '';

if (!verifyToken($token, $DASHBOARD_SECRET)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized - Valid authentication required'
    ]);
    exit;
}

// ============================================================================
// SECURITY HEADERS (AP-01)
// ============================================================================

// Restrict CORS to own origin
// REQUIRED: Must be set in .env.prod!
$allowedOrigin = env('ALLOWED_ORIGIN');
if (!$allowedOrigin) {
    error_log('CRITICAL: ALLOWED_ORIGIN not configured in .env.prod');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Server configuration error - ALLOWED_ORIGIN not set'
    ]);
    exit;
}
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Vary: Origin');

// Prevent caching of sensitive data
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

// Standard headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ============================================================================
// API LOGIC
// ============================================================================

require_once __DIR__ . '/ExtendedLogger.php';

try {
    $logger = new ExtendedLogger(__DIR__ . '/logs');
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get statistics from ExtendedLogger
    $stats = $logger->getStatistics(1); // Today only
    $recentSubmissions = $logger->getRecentSubmissions(10, false); // Include anonymized
    
    // Calculate trend (last 7 days)
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $trendDate = date('Y-m-d', strtotime("-$i days"));
        $trendStats = $logger->getStatistics(1); // This needs improvement - currently always today
        
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
    
    // Build response with PRIVACY-ENHANCED data (AP-01)
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
                // PRIVACY: Mask email addresses (AP-01)
                'email' => maskEmail($sub['formData']['email'] ?? ''),
                'spamScore' => $sub['spamScore'] ?? 0,
                'blocked' => $sub['blocked'] ?? false
            ];
        }, $recentSubmissions),
        'recentBlocks' => array_map(function($sub) {
            return [
                'timestamp' => $sub['timestamp'],
                // PRIVACY: Mask email addresses (AP-01)
                'email' => maskEmail($sub['formData']['email'] ?? ''),
                'spamScore' => $sub['spamScore'] ?? 0,
                'blocked' => true,
                'reason' => $sub['blockReasons'][0] ?? 'unknown'
            ];
        }, array_filter($recentSubmissions, fn($s) => $s['blocked'] ?? false)),
        'trend' => $trend
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Dashboard API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}
