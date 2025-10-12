<?php
/**
 * Dashboard Backend API - Returns JSON data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/AbuseLogger.php';

try {
    $logger = new AbuseLogger(__DIR__ . '/logs', false);
    $date = $_GET['date'] ?? date('Y-m-d');
    $report = $logger->generateDailyReport($date);
    
    $stats = [
        'today' => $report,
        'status' => 'ok',
        'timestamp' => date('c')
    ];
    
    // Recent submissions
    $recentSubmissions = [];
    if (file_exists(__DIR__ . '/logs/submissions.log')) {
        $lines = file(__DIR__ . '/logs/submissions.log', FILE_IGNORE_NEW_LINES);
        $recentLines = array_slice(array_reverse($lines), 0, 10);
        
        foreach ($recentLines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $recentSubmissions[] = [
                    'timestamp' => $entry['timestamp'],
                    'email' => $entry['formData']['email'] ?? 'N/A',
                    'spamScore' => $entry['spamScore'] ?? 0,
                    'blocked' => $entry['blocked'] ?? false
                ];
            }
        }
    }
    $stats['recentSubmissions'] = $recentSubmissions;
    
    // Recent blocks
    $recentBlocks = [];
    if (file_exists(__DIR__ . '/logs/blocked.log')) {
        $lines = file(__DIR__ . '/logs/blocked.log', FILE_IGNORE_NEW_LINES);
        $recentLines = array_slice(array_reverse($lines), 0, 10);
        
        foreach ($recentLines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                $recentBlocks[] = [
                    'timestamp' => $entry['timestamp'],
                    'ip' => $entry['ip'],
                    'reason' => $entry['reason'] ?? 'N/A',
                    'spamScore' => $entry['spamScore'] ?? 0
                ];
            }
        }
    }
    $stats['recentBlocks'] = $recentBlocks;
    
    // 7-day trend
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $trendDate = date('Y-m-d', strtotime("-$i days"));
        $trendReport = $logger->generateDailyReport($trendDate);
        $trend[] = [
            'date' => $trendDate,
            'total' => $trendReport['total'],
            'allowed' => $trendReport['allowed'],
            'blocked' => $trendReport['blocked'],
            'avgSpamScore' => $trendReport['avgSpamScore']
        ];
    }
    $stats['trend'] = $trend;
    
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}