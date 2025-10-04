<?php
/**
 * Dashboard Backend API - Returns JSON data
 * Updated for Extended Logging System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
