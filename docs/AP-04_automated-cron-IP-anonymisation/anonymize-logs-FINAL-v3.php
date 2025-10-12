#!/usr/bin/env php
<?php
/**
 * GDPR-Compliant Log Anonymization Cronjob
 * 
 * @version     3.0.0
 * @date        2025-10-06 12:00:00 UTC
 * @repository  https://github.com/JoZapf/contact-form-abuse-prevention
 * @package     ContactFormAbusePrevention
 * @author      Jo Zapf
 * 
 * CHANGELOG v3.0.0 (2025-10-06):
 * - [FEATURE] Relative path resolution for portability (tested in production)
 * - [FEATURE] Automatic PUBLIC_HTML detection via __DIR__
 * - [FEATURE] Fallback to absolute paths if relative resolution fails
 * - [SECURITY] Fail-fast error handling with STDERR output
 * - [LOGGING] Comprehensive execution logging with PID tracking
 * - [LOGGING] Anonymization audit trail with SHA256 hash
 * - [GDPR] Art. 5 (1) e - Storage limitation (14-day retention)
 * - [GDPR] Art. 17 - Right to erasure (anonymization = deletion)
 * 
 * Previous version: v2.0.0 (Initial cronjob implementation)
 * 
 * DESCRIPTION:
 * Automatically anonymizes IP addresses in contact form submission logs
 * after the legal retention period (14 days). Runs daily via cronjob to
 * ensure GDPR compliance. Uses the ExtendedLogger class for anonymization
 * and creates detailed audit trails for compliance verification.
 * 
 * USAGE:
 *   Via cron:   /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
 *   Manually:   php anonymize-logs.php
 * 
 * CRONJOB CONFIGURATION:
 *   Daily at 3 AM:   0 3 * * * /usr/bin/php83 /path/to/anonymize-logs.php
 *   Twice daily:     0 3,15 * * * /usr/bin/php83 /path/to/anonymize-logs.php
 *   Every 6 hours:   0 */6 * * * /usr/bin/php83 /path/to/anonymize-logs.php
 * 
 * GDPR COMPLIANCE:
 *   Art. 6 (1) f GDPR - Legitimate interest (spam protection)
 *   Art. 5 (1) e GDPR - Storage limitation (automatic deletion)
 *   Art. 17 GDPR      - Right to erasure (anonymization)
 * 
 * LOGS:
 *   Execution:    /path/to/project/assets/php/logs/cron-anonymization.log
 *   Audit trail:  /path/to/project/assets/php/logs/anonymization_history.log
 * 
 * EXIT CODES:
 *   0 - Success (anonymization completed)
 *   1 - Fatal error (configuration, paths, or execution failure)
 */

// ============================================================================
// CONFIGURATION: Path Resolution
// ============================================================================

/**
 * Path Resolution Strategy
 * 
 * This script supports two path resolution methods:
 * 
 * 1. RELATIVE PATHS (Recommended - Portable across servers)
 *    - Automatically detects webroot based on script location
 *    - Works on any server without modification
 *    - Tested successfully in production
 * 
 * 2. ABSOLUTE PATHS (Fallback - When relative detection fails)
 *    - Uses hardcoded paths specific to your server
 *    - Requires manual configuration
 *    - Use only if relative method fails
 * 
 * The script will attempt relative resolution first. If that fails,
 * it will use the absolute fallback (if configured), or exit with error.
 */

// Current script directory
define('CRON_DIR', __DIR__);

// ============================================================================
// METHOD 1: RELATIVE PATH RESOLUTION (Recommended - Tested in Production)
// ============================================================================

/**
 * Relative Path Detection
 * 
 * Directory Structure:
 *   /usr/home/users/cron/contactform/          <- Script location (CRON_DIR)
 *                       ../../                  <- Navigate 2 levels up
 *   /usr/home/users/                            <- User home
 *   /usr/home/users/public_html/                <- Webroot (PUBLIC_HTML)
 *   /usr/home/users/public_html/jozapf-de/      <- Project (PROJECT_ROOT)
 * 
 * The realpath() function resolves symbolic links and relative paths,
 * returning the absolute canonical path. It returns FALSE if the path
 * doesn't exist, allowing us to detect configuration issues early.
 */

// Attempt to resolve webroot via relative path (2 levels up from CRON_DIR)
$PUBLIC_HTML = realpath(CRON_DIR . '/../../');

if ($PUBLIC_HTML === false) {
    // Relative resolution failed - log detailed error
    fwrite(STDERR, "ERROR: Could not resolve PUBLIC_HTML via relative path\n");
    fwrite(STDERR, "Attempted: " . CRON_DIR . "/../../\n");
    fwrite(STDERR, "This usually means the directory structure doesn't match expectations.\n\n");
    
    // ========================================================================
    // METHOD 2: ABSOLUTE PATH FALLBACK (Use if relative method fails)
    // ========================================================================
    
    /**
     * Absolute Path Configuration
     * 
     * If relative path detection fails, uncomment and configure these lines
     * with your server's actual absolute paths.
     * 
     * To find your paths:
     *   pwd                     # Current directory
     *   realpath .              # Absolute path
     *   find ~ -name "*.php"    # Find PHP files in home directory
     * 
     * Common structures:
     *   Shared hosting: /home/username/public_html/
     *   VPS/Dedicated:  /var/www/html/ or /usr/www/
     *   Hetzner:        /usr/www/users/username/
     */
    
    // UNCOMMENT AND CONFIGURE THESE IF RELATIVE METHOD FAILS:
    // $PUBLIC_HTML = '/usr/home/users/public_html';  // Your webroot
    // $PROJECT_NAME = 'jozapf-de';                    // Your project folder
    
    // Check if absolute fallback is configured
    if (!isset($PUBLIC_HTML) || $PUBLIC_HTML === false) {
        fwrite(STDERR, "FATAL: No fallback absolute path configured\n");
        fwrite(STDERR, "Please configure absolute paths in the script (see METHOD 2).\n");
        exit(1);
    }
    
    fwrite(STDERR, "INFO: Using absolute path fallback: $PUBLIC_HTML\n\n");
}

// Define project root
// CUSTOMIZE THIS: Change 'jozapf-de' to your actual project folder name
define('PROJECT_ROOT', $PUBLIC_HTML . '/jozapf-de');

// Define PHP and log directories (standard structure)
define('PHP_DIR',      PROJECT_ROOT . '/assets/php');
define('LOG_DIR',      PHP_DIR . '/logs');
define('CRON_LOG',     LOG_DIR . '/cron-anonymization.log');

// ============================================================================
// VALIDATION: Verify Directory Structure
// ============================================================================

/**
 * Path Validation
 * 
 * Verify all required directories exist before proceeding.
 * This fail-fast approach prevents silent failures and provides
 * clear error messages for troubleshooting.
 */

// Verify project root exists
if (!is_dir(PROJECT_ROOT)) {
    fwrite(STDERR, "FATAL: Project root not found: " . PROJECT_ROOT . "\n");
    fwrite(STDERR, "Expected structure: PUBLIC_HTML/jozapf-de/\n");
    fwrite(STDERR, "Check if 'jozapf-de' is the correct folder name.\n");
    exit(1);
}

// Verify PHP directory exists
if (!is_dir(PHP_DIR)) {
    fwrite(STDERR, "FATAL: PHP directory not found: " . PHP_DIR . "\n");
    fwrite(STDERR, "Expected: " . PROJECT_ROOT . "/assets/php/\n");
    exit(1);
}

// Verify log directory exists and is writable
if (!is_dir(LOG_DIR)) {
    fwrite(STDERR, "ERROR: Log directory does not exist: " . LOG_DIR . "\n");
    fwrite(STDERR, "Attempting to create it...\n");
    
    if (!mkdir(LOG_DIR, 0755, true)) {
        fwrite(STDERR, "FATAL: Could not create log directory\n");
        exit(1);
    }
    
    fwrite(STDERR, "SUCCESS: Log directory created\n");
}

if (!is_writable(LOG_DIR)) {
    fwrite(STDERR, "FATAL: Log directory is not writable: " . LOG_DIR . "\n");
    fwrite(STDERR, "Fix: chmod 755 " . LOG_DIR . "\n");
    exit(1);
}

// ============================================================================
// CONFIGURATION: Environment Variables
// ============================================================================

/**
 * Load Environment Configuration
 * 
 * The .env.prod file contains sensitive configuration like SMTP credentials
 * and dashboard secrets. This simple loader reads KEY=VALUE pairs and
 * makes them available via the env() helper function.
 */

$envFile = PHP_DIR . '/.env.prod';

if (!file_exists($envFile)) {
    fwrite(STDERR, "FATAL: Configuration file not found: {$envFile}\n");
    fwrite(STDERR, "This file should contain SMTP and dashboard settings.\n");
    exit(1);
}

/**
 * Simple Environment Variable Loader
 * 
 * Parses .env files in KEY=VALUE format.
 * Supports:
 *   - Comments (lines starting with #)
 *   - Empty lines (ignored)
 *   - Quoted values (quotes are stripped)
 *   - Whitespace trimming
 * 
 * @param string $file Path to .env file
 * @return array Associative array of environment variables
 */
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
            // Remove quotes and whitespace from value
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $env[$key] = $value;
        }
    }
    
    return $env;
}

// Load environment variables into memory
$env = loadEnv($envFile);

// ============================================================================
// DEPENDENCIES: Load Required Classes
// ============================================================================

/**
 * Load ExtendedLogger Class
 * 
 * The ExtendedLogger class handles:
 *   - Detailed submission logging
 *   - Automatic IP anonymization after 14 days
 *   - Anonymization audit trail
 *   - GDPR-compliant data retention
 */

$extendedLoggerPath = PHP_DIR . '/ExtendedLogger.php';

if (!file_exists($extendedLoggerPath)) {
    fwrite(STDERR, "FATAL: ExtendedLogger.php not found: {$extendedLoggerPath}\n");
    fwrite(STDERR, "This class is required for anonymization.\n");
    exit(1);
}

require_once $extendedLoggerPath;

// ============================================================================
// HELPER FUNCTIONS: Logging
// ============================================================================

/**
 * Log Cronjob Execution Details
 * 
 * Writes timestamped log entries to the cronjob log file.
 * Format: [timestamp] [level] [PID:process_id] message
 * 
 * @param string $message Log message
 * @param string $level   Log level (INFO, SUCCESS, ERROR)
 * @return void
 */
function logCronExecution(string $message, string $level = 'INFO'): void {
    $timestamp = date('c');  // ISO 8601 format
    $pid = getmypid();       // Process ID for tracking parallel executions
    $logEntry = "[{$timestamp}] [{$level}] [PID:{$pid}] {$message}\n";
    
    // Ensure log directory exists
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    // Append to log file with exclusive lock to prevent corruption
    file_put_contents(CRON_LOG, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log Errors
 * 
 * Logs to both the cronjob log file and STDERR.
 * STDERR output triggers email notifications in most cron implementations.
 * 
 * @param string $message Error message
 * @return void
 */
function logCronError(string $message): void {
    logCronExecution($message, 'ERROR');
    fwrite(STDERR, "[ERROR] {$message}\n");
}

/**
 * Get Execution Time
 * 
 * Calculates elapsed time since start timestamp.
 * 
 * @param float $start Start time from microtime(true)
 * @return string Formatted execution time (e.g., "0.123s")
 */
function getExecutionTime(float $start): string {
    $duration = microtime(true) - $start;
    return number_format($duration, 3) . 's';
}

// ============================================================================
// MAIN EXECUTION: Anonymization Process
// ============================================================================

/**
 * Main Execution Block
 * 
 * This is the core anonymization process:
 * 1. Initialize logging
 * 2. Load ExtendedLogger class
 * 3. Run anonymization on old entries
 * 4. Generate statistics
 * 5. Log results
 */

// Start execution timer
$startTime = microtime(true);

// Log execution start with environment details
logCronExecution("=== Anonymization Cronjob Started ===");
logCronExecution("PHP Version: " . PHP_VERSION);
logCronExecution("User: " . get_current_user());
logCronExecution("Script: " . __FILE__);
logCronExecution("Cron Directory: " . CRON_DIR);
logCronExecution("Public HTML: " . $PUBLIC_HTML);
logCronExecution("Project Root: " . PROJECT_ROOT);
logCronExecution("PHP Directory: " . PHP_DIR);
logCronExecution("Log Directory: " . LOG_DIR);

try {
    // ========================================================================
    // STEP 1: Initialize ExtendedLogger
    // ========================================================================
    
    logCronExecution("Initializing ExtendedLogger...");
    $logger = new ExtendedLogger(LOG_DIR);
    
    // Get current retention settings
    $retentionDays = $logger->getRetentionDays();
    logCronExecution("Retention Period: {$retentionDays} days");
    
    // ========================================================================
    // STEP 2: Run Anonymization
    // ========================================================================
    
    /**
     * Anonymization Process
     * 
     * The anonymizeOldEntries() method:
     * 1. Scans detailed_submissions.log for entries older than 14 days
     * 2. Replaces full IP addresses with anonymized versions:
     *    - IPv4: 192.168.1.100 → 192.168.1.XXX
     *    - IPv6: 2001:db8::1 → 2001:db8::XXX
     * 3. Marks entries as anonymized with timestamp
     * 4. Logs each anonymization to anonymization_history.log
     * 5. Returns count of anonymized entries
     */
    
    logCronExecution("Scanning for entries older than {$retentionDays} days...");
    $anonymizedCount = $logger->anonymizeOldEntries();
    
    // ========================================================================
    // STEP 3: Log Results
    // ========================================================================
    
    if ($anonymizedCount > 0) {
        logCronExecution("✓ Anonymized {$anonymizedCount} entries", 'SUCCESS');
        
        // Get recent anonymization history for verification
        $recentAnonymizations = $logger->getAnonymizationHistory(5);
        
        if (!empty($recentAnonymizations)) {
            logCronExecution("Recent anonymizations: " . json_encode($recentAnonymizations, JSON_UNESCAPED_UNICODE));
        }
    } else {
        logCronExecution("No entries requiring anonymization (all up to date)");
    }
    
    // ========================================================================
    // STEP 4: Generate Statistics (Last 30 Days)
    // ========================================================================
    
    /**
     * Statistics Collection
     * 
     * Provides insights into contact form activity:
     * - Total submissions
     * - Blocked vs. Allowed submissions
     * - Average spam score
     * - Unique IP addresses
     * 
     * Useful for monitoring and compliance reporting.
     */
    
    $stats = $logger->getStatistics(30);
    
    logCronExecution("Log Statistics (30 days):");
    logCronExecution("  - Total submissions: {$stats['total']}");
    logCronExecution("  - Blocked: {$stats['blocked']}");
    logCronExecution("  - Allowed: {$stats['allowed']}");
    logCronExecution("  - Avg Spam Score: {$stats['avgSpamScore']}");
    
    if ($stats['uniqueIPs'] > 0) {
        logCronExecution("  - Unique IPs: {$stats['uniqueIPs']}");
    }
    
    // ========================================================================
    // STEP 5: Successful Completion
    // ========================================================================
    
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Completed Successfully in {$executionTime} ===");
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    // ========================================================================
    // ERROR HANDLING: Catch and Log All Exceptions
    // ========================================================================
    
    /**
     * Exception Handling
     * 
     * Any unhandled exception will be caught here, logged with full
     * stack trace, and result in exit code 1 (failure).
     * 
     * This triggers email notifications in cron implementations that
     * monitor exit codes.
     */
    
    logCronError("Exception occurred: " . $e->getMessage());
    logCronError("File: " . $e->getFile() . " (Line " . $e->getLine() . ")");
    logCronError("Stack Trace: " . $e->getTraceAsString());
    
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Failed after {$executionTime} ===", 'ERROR');
    
    // Exit with error code
    exit(1);
}

// ============================================================================
// END OF SCRIPT
// ============================================================================
