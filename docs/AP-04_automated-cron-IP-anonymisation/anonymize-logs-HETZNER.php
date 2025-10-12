#!/usr/bin/env php
<?php
/**
 * GDPR-Compliant Log Anonymization Cronjob
 * 
 * Purpose: Automatically anonymize IP addresses in contact form logs
 *          after the legal retention period (14 days)
 * 
 * LOCATION: /usr/home/jozapf/cron/contactform/anonymize-logs.php
 * 
 * Usage:
 *   - Via cron: php /usr/home/jozapf/cron/contactform/anonymize-logs.php
 *   - Manually: php anonymize-logs.php
 * 
 * GDPR Compliance:
 *   - Art. 6 (1) f GDPR: Legitimate interest (spam protection)
 *   - Art. 5 (1) e GDPR: Storage limitation
 *   - Art. 17 GDPR: Right to erasure (via anonymization)
 * 
 * Hetzner Cronjob Setup:
 *   Minute Hour Day Month Weekday Interpreter Script
 *   0      3    *   *     *       php8.3     /usr/home/jozapf/cron/contactform/anonymize-logs.php
 * 
 * Recommended Schedule:
 *   - Daily at 3:00 AM: 0 3 * * *
 *   - Twice daily: 0 3,15 * * *
 *   - Every 6 hours: 0 */6 * * *
 * 
 * @author JoZapf
 * @version 2.0.0 - Adapted for Hetzner cron directory
 * @since 2025-10-06
 */

// ============================================================================
// CONFIGURATION - HETZNER STRUCTURE
// ============================================================================

// Hetzner cron directory structure (outside public_html)
define('CRON_DIR', __DIR__);
define('USER_HOME', '/usr/home/jozapf');
define('PROJECT_ROOT', USER_HOME . '/site/jozapf-de/ContactFormForGithub');
define('PHP_DIR', PROJECT_ROOT . '/assets/php');
define('LOG_DIR', PHP_DIR . '/logs');

// Cronjob log file (separate from submission logs)
define('CRON_LOG', LOG_DIR . '/cron-anonymization.log');

// Verify paths
if (!is_dir(PROJECT_ROOT)) {
    fwrite(STDERR, "ERROR: Project root not found: " . PROJECT_ROOT . "\n");
    exit(1);
}

// Load environment configuration
$envFile = PHP_DIR . '/.env.prod';
if (!file_exists($envFile)) {
    fwrite(STDERR, "FATAL: .env.prod not found at: {$envFile}\n");
    exit(1);
}

// Simple env loader (no dependencies)
function loadEnv(string $file): array {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $env[$key] = $value;
        }
    }
    
    return $env;
}

$env = loadEnv($envFile);

// ============================================================================
// REQUIRE DEPENDENCIES
// ============================================================================

// Load ExtendedLogger class
require_once PHP_DIR . '/ExtendedLogger.php';

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Log cronjob execution details
 */
function logCronExecution(string $message, string $level = 'INFO'): void {
    $timestamp = date('c');
    $pid = getmypid();
    $logEntry = "[{$timestamp}] [{$level}] [PID:{$pid}] {$message}\n";
    
    // Ensure log directory exists
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    file_put_contents(CRON_LOG, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log errors (also to stderr for cron email notifications)
 */
function logCronError(string $message): void {
    logCronExecution($message, 'ERROR');
    fwrite(STDERR, "[ERROR] {$message}\n");
}

/**
 * Get script execution time
 */
function getExecutionTime(float $start): string {
    $duration = microtime(true) - $start;
    return number_format($duration, 3) . 's';
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

$startTime = microtime(true);
logCronExecution("=== Anonymization Cronjob Started ===");
logCronExecution("PHP Version: " . PHP_VERSION);
logCronExecution("User: " . get_current_user());
logCronExecution("Cron Directory: " . CRON_DIR);
logCronExecution("Project Root: " . PROJECT_ROOT);
logCronExecution("Log Directory: " . LOG_DIR);

try {
    // Validate log directory exists and is writable
    if (!is_dir(LOG_DIR)) {
        logCronError("Log directory does not exist: " . LOG_DIR);
        exit(1);
    }
    
    if (!is_writable(LOG_DIR)) {
        logCronError("Log directory is not writable: " . LOG_DIR);
        exit(1);
    }
    
    // Initialize ExtendedLogger
    logCronExecution("Initializing ExtendedLogger...");
    $logger = new ExtendedLogger(LOG_DIR);
    
    // Get current retention settings
    $retentionDays = $logger->getRetentionDays();
    logCronExecution("Retention Period: {$retentionDays} days");
    
    // Run anonymization
    logCronExecution("Scanning for entries older than {$retentionDays} days...");
    $anonymizedCount = $logger->anonymizeOldEntries();
    
    // Log results
    if ($anonymizedCount > 0) {
        logCronExecution("✓ Anonymized {$anonymizedCount} entries", 'SUCCESS');
        
        // Get anonymization history for verification
        $recentAnonymizations = $logger->getAnonymizationHistory(5);
        logCronExecution("Recent anonymizations: " . json_encode($recentAnonymizations, JSON_UNESCAPED_UNICODE));
    } else {
        logCronExecution("No entries requiring anonymization (all up to date)");
    }
    
    // Get statistics
    $stats = $logger->getStatistics(30); // Last 30 days
    logCronExecution("Log Statistics (30 days):");
    logCronExecution("  - Total submissions: {$stats['total']}");
    logCronExecution("  - Blocked: {$stats['blocked']}");
    logCronExecution("  - Allowed: {$stats['allowed']}");
    logCronExecution("  - Avg Spam Score: {$stats['avgSpamScore']}");
    
    // Execution summary
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Completed Successfully in {$executionTime} ===");
    
    exit(0);
    
} catch (Exception $e) {
    logCronError("Exception: " . $e->getMessage());
    logCronError("Stack Trace: " . $e->getTraceAsString());
    
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Failed after {$executionTime} ===", 'ERROR');
    
    exit(1);
}

// ============================================================================
// END OF SCRIPT
// ============================================================================
