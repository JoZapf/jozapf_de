<?php
/**
 * Dashboard Backend API - FIXED VERSION
 * 
 * Provides JSON data for the dashboard frontend
 * 
 * @author JoZapf
 * @version 1.1.0
 */

// CRITICAL: Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1);     // Log errors instead

// Start output buffering to catch any unwanted output
ob_start();

// Set headers FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Clear any previous output
    ob_clean();
    
    // Check if AbuseLogger exists
    $abuseLoggerPath = __DIR__ . '/AbuseLogger.php';
    if (!file_exists($abuseLoggerPath)) {
        throw new Exception('AbuseLogger.php not found at: ' . $abuseLoggerPath);
    }
    
    require_once $abuseLoggerPath;
    
    // Check if logs directory exists
    $logsPath = __DIR__ . '/logs';
    if (!is_dir($logsPath)) {
        throw new Exception('Logs directory not found at: ' . $logsPath);
    }
    
    $logger = new AbuseLogger($logsPath, false);
    
    // Get date from query parameter (default: today)
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Generate report
    $report = $logger->generateDailyReport($date);
    
    // Additional statistics for dashboard
    $stats = [
        'today' => $report,
        'status' => 'ok',
        'timestamp' => date('c'),
        'server_time' => time()
    ];
    
    // Recent submissions (last 10)
    $recentSubmissions = [];
    $submissionsFile = $logsPath . '/submissions.log';
    if (file_exists($submissionsFile) && is_readable($submissionsFile)) {
        $lines = file($submissionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $recentLines = array_slice(array_reverse($lines), 0, 10);
            
            foreach ($recentLines as $line) {
                $entry = json_decode($line, true);
                if ($entry && is_array($entry)) {
                    $recentSubmissions[] = [
                        'timestamp' => $entry['timestamp'] ?? '',
                        'email' => isset($entry['formData']['email']) ? $entry['formData']['email'] : 'N/A',
                        'spamScore' => isset($entry['spamScore']) ? (int)$entry['spamScore'] : 0,
                        'blocked' => isset($entry['blocked']) ? (bool)$entry['blocked'] : false
                    ];
                }
            }
        }
    }
    $stats['recentSubmissions'] = $recentSubmissions;
    
    // Recent blocks (last 10)
    $recentBlocks = [];
    $blockedFile = $logsPath . '/blocked.log';
    if (file_exists($blockedFile) && is_readable($blockedFile)) {
        $lines = file($blockedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $recentLines = array_slice(array_reverse($lines), 0, 10);
            
            foreach ($recentLines as $line) {
                $entry = json_decode($line, true);
                if ($entry && is_array($entry)) {
                    $recentBlocks[] = [
                        'timestamp' => $entry['timestamp'] ?? '',
                        'ip' => $entry['ip'] ?? 'N/A',
                        'reason' => $entry['reason'] ?? 'N/A',
                        'spamScore' => isset($entry['spamScore']) ? (int)$entry['spamScore'] : 0
                    ];
                }
            }
        }
    }
    $stats['recentBlocks'] = $recentBlocks;
    
    // Trend data (last 7 days)
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $trendDate = date('Y-m-d', strtotime("-$i days"));
        $trendReport = $logger->generateDailyReport($trendDate);
        $trend[] = [
            'date' => $trendDate,
            'total' => (int)$trendReport['total'],
            'allowed' => (int)$trendReport['allowed'],
            'blocked' => (int)$trendReport['blocked'],
            'avgSpamScore' => (float)$trendReport['avgSpamScore']
        ];
    }
    $stats['trend'] = $trend;
    
    // Clear output buffer and output JSON
    ob_clean();
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

// End output buffering and flush
ob_end_flush();
