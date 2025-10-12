#!/usr/bin/env php
<?php

/* DEBUGGING ONLY
define('FALLBACK_CRON_LOG', __DIR__ . '/cron-bootstrap.log');
function safelog($m){ @file_put_contents(FALLBACK_CRON_LOG, '['.date('c')."] $m\n", FILE_APPEND|LOCK_EX); }
*/

/**
 * GDPR-Compliant Log Anonymization Cronjob
 * 
 * @version     1.1.0
 * @date        2025-10-06 12:00:00 UTC
 * @repository  https://github.com/JoZapf/contact-form-abuse-prevention
 * @package     ContactFormAbusePrevention
 * @author      Jo Zapf
 * 
 * CHANGELOG v1.1.0 (2025-10-06):
 * - [FEATURE] Path configuration via .env.prod (CRON_PUBLIC_HTML, PROJECT_NAME)
 * - [FEATURE] Fallback to relative path detection if .env not configured
 * - [SECURITY] Fail-fast error handling with detailed diagnostics
 * - [LOGGING] Comprehensive execution logging with PID tracking
 * - [GDPR] Art. 5 (1) e - Storage limitation (14-day retention)
 * - [GDPR] Art. 17 - Right to erasure (anonymization = deletion)
 * 
 * 
 * DESCRIPTION:
 * Automatically anonymizes IP addresses in contact form submission logs
 * after the legal retention period (14 days). Runs daily via cronjob to
 * ensure GDPR compliance. All paths are configured in .env.prod for
 * maximum portability and GitHub-ready code.
 * 
 * USAGE:
 *   Via cron:   /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
 *   Manually:   php anonymize-logs.php
 *
 * CRONJOB CONFIGURATION:
 *   Daily at 3 AM:   0 3 * * * /usr/bin/php83 /path/to/anonymize-logs.php
 *   Twice daily:     0 3,15 * * * /usr/bin/php83 /path/to/anonymize-logs.php
 *   Every 6 hours:   0 *\/6 * * * /usr/bin/php83 /path/to/anonymize-logs.php
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
// CONFIGURATION: Initial Setup
// ============================================================================

// Current script directory (where this cronjob is located)
define('CRON_DIR', __DIR__);

// ============================================================================
// STEP 1: Load Environment Configuration
// ============================================================================

/**
 * Environment Configuration Loading
 * 
 * We need to load .env.prod FIRST to get the path configuration.
 * This creates a chicken-and-egg problem: we need to know where .env.prod
 * is, but .env.prod tells us where the project is.
 * 
 * SOLUTION:
 * We use a simple heuristic to find .env.prod:
 *   1. Try relative path from CRON_DIR (../../public_html/project/assets/php)
 *   2. If that fails, exit with clear error message
 * 
 * Once we have .env.prod loaded, we use CRON_PUBLIC_HTML and PROJECT_NAME
 * from the config to build all other paths.
 */

// Simple environment variable loader (defined early, before we need it)
function loadEnv(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    
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

/**
 * Find .env.prod File
 * 
 * Strategy:
 * 1. Try relative path (../../public_html/.../assets/php/.env.prod)
 * 2. If found, we're good
 * 3. If not found, exit with helpful error message
 */

// Attempt to find .env.prod via relative path
$possibleEnvPath = realpath(CRON_DIR . '/../../');
$envFile = null;

if ($possibleEnvPath !== false) {
    // Try to find .env.prod by exploring common structures
    $searchPaths = [
        // Direct structure: /cron/contactform/ -> ../../ -> /public_html/project/
        $possibleEnvPath . '/jozapf-de/assets/php/.env.prod',
        // Alternative: maybe public_html is in a subfolder
        $possibleEnvPath . '/public_html/jozapf-de/assets/php/.env.prod',
    ];
    
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            $envFile = $path;
            break;
        }
    }
}

// If we still haven't found it, error out with helpful message
if (!$envFile || !file_exists($envFile)) {
    fwrite(STDERR, "FATAL: Could not locate .env.prod file\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "Searched in:\n");
    if (isset($searchPaths)) {
        foreach ($searchPaths as $path) {
            fwrite(STDERR, "  - $path\n");
        }
    }
    fwrite(STDERR, "\n");
    fwrite(STDERR, "SOLUTION:\n");
    fwrite(STDERR, "Configure CRON_PUBLIC_HTML and PROJECT_NAME in .env.prod\n");
    fwrite(STDERR, "See: .env.prod.example.v3 for configuration instructions\n");
    fwrite(STDERR, "\n");
    exit(1);
}

// Load environment variables
$env = loadEnv($envFile);

// ============================================================================
// STEP 2: Path Resolution via .env.prod Configuration
// ============================================================================

/**
 * Path Configuration Strategy
 * 
 * NEW in v3.0.0:
 * All paths are now configured in .env.prod for maximum portability:
 * 
 *   CRON_PUBLIC_HTML=/usr/home/users/public_html  (your webroot)
 *   PROJECT_NAME=jozapf-de                         (your project folder)
 * 
 * BENEFITS:
 *   - Code is always GitHub-ready (no hardcoded paths)
 *   - Central configuration in one place
 *   - Easy deployment across different servers
 *   - Follows 12-Factor App principles
 * 
 * FALLBACK:
 * If not configured in .env.prod, we attempt automatic detection
 * using relative paths (backward compatible with v2.0.0)
 */

// Get PUBLIC_HTML from .env.prod
$PUBLIC_HTML = $env['CRON_PUBLIC_HTML'] ?? null;

// Get PROJECT_NAME from .env.prod  
$PROJECT_NAME = $env['PROJECT_NAME'] ?? null;

// ============================================================================
// STEP 3: Validate and Apply Configuration
// ============================================================================

// If .env.prod configuration is missing, try relative detection as fallback
if (!$PUBLIC_HTML || !$PROJECT_NAME) {
    fwrite(STDERR, "WARNING: Cronjob paths not configured in .env.prod\n");
    fwrite(STDERR, "Attempting automatic detection (not recommended)...\n\n");
    
    // Fallback: Try relative path detection
    $PUBLIC_HTML = realpath(CRON_DIR . '/../../');
    $PROJECT_NAME = 'jozapf-de'; // Hardcoded fallback
    
    if ($PUBLIC_HTML === false) {
        fwrite(STDERR, "FATAL: Automatic path detection failed\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "SOLUTION:\n");
        fwrite(STDERR, "Add these lines to .env.prod:\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  CRON_PUBLIC_HTML=/path/to/your/webroot\n");
        fwrite(STDERR, "  PROJECT_NAME=your-project-folder\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "See: .env.prod.example.v3 for detailed instructions\n");
        fwrite(STDERR, "\n");
        exit(1);
    }
    
    fwrite(STDERR, "INFO: Using fallback paths (please configure .env.prod properly):\n");
    fwrite(STDERR, "  PUBLIC_HTML: $PUBLIC_HTML\n");
    fwrite(STDERR, "  PROJECT_NAME: $PROJECT_NAME\n\n");
}

// Build project paths from configuration
define('PROJECT_ROOT', $PUBLIC_HTML . '/' . $PROJECT_NAME);
define('PHP_DIR',      PROJECT_ROOT . '/assets/php');
define('LOG_DIR',      PHP_DIR . '/logs');
define('CRON_LOG',     LOG_DIR . '/cron-anonymization.log');

// ============================================================================
// STEP 4: Validate Directory Structure
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
    fwrite(STDERR, "\n");
    fwrite(STDERR, "Expected structure:\n");
    fwrite(STDERR, "  $PUBLIC_HTML/$PROJECT_NAME/\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "TROUBLESHOOTING:\n");
    fwrite(STDERR, "1. Check if PUBLIC_HTML is correct:\n");
    fwrite(STDERR, "   ls -la $PUBLIC_HTML\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "2. Check if PROJECT_NAME is correct:\n");
    fwrite(STDERR, "   Current: PROJECT_NAME=$PROJECT_NAME\n");
    fwrite(STDERR, "   Update in .env.prod if wrong\n");
    fwrite(STDERR, "\n");
    exit(1);
}

// Verify PHP directory exists
if (!is_dir(PHP_DIR)) {
    fwrite(STDERR, "FATAL: PHP directory not found: " . PHP_DIR . "\n");
    fwrite(STDERR, "Expected: " . PROJECT_ROOT . "/assets/php/\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "This suggests the project structure is different than expected.\n");
    fwrite(STDERR, "Verify your project folder contains: assets/php/\n");
    fwrite(STDERR, "\n");
    exit(1);
}

// Verify log directory exists and is writable
if (!is_dir(LOG_DIR)) {
    fwrite(STDERR, "WARNING: Log directory does not exist: " . LOG_DIR . "\n");
    fwrite(STDERR, "Attempting to create it...\n");
    
    if (!mkdir(LOG_DIR, 0755, true)) {
        fwrite(STDERR, "FATAL: Could not create log directory\n");
        fwrite(STDERR, "Check permissions on: " . PHP_DIR . "\n");
        exit(1);
    }
    
    fwrite(STDERR, "SUCCESS: Log directory created\n\n");
}

if (!is_writable(LOG_DIR)) {
    fwrite(STDERR, "FATAL: Log directory is not writable: " . LOG_DIR . "\n");
    fwrite(STDERR, "Fix with: chmod 755 " . LOG_DIR . "\n");
    exit(1);
}

// ============================================================================
// STEP 5: Load Dependencies
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
    fwrite(STDERR, "Ensure your project includes this file.\n");
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
    $pid = getmypid();       // Process ID for tracking
    $logEntry = "[{$timestamp}] [{$level}] [PID:{$pid}] {$message}\n";
    
    // Ensure log directory exists
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    // Append to log file with exclusive lock
    file_put_contents(CRON_LOG, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log Errors
 * 
 * Logs to both file and STDERR (triggers email notifications in cron).
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
 * Calculates elapsed time since start.
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
 * Process:
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
logCronExecution("Version: 3.0.0");
logCronExecution("PHP Version: " . PHP_VERSION);
logCronExecution("User: " . get_current_user());
logCronExecution("Script: " . __FILE__);
logCronExecution("Configuration Source: .env.prod");
logCronExecution("Cron Directory: " . CRON_DIR);
logCronExecution("Public HTML: " . $PUBLIC_HTML);
logCronExecution("Project Name: " . $PROJECT_NAME);
logCronExecution("Project Root: " . PROJECT_ROOT);
logCronExecution("Log Directory: " . LOG_DIR);

try {
    // ========================================================================
    // Initialize ExtendedLogger
    // ========================================================================
    
    logCronExecution("Initializing ExtendedLogger...");
    $logger = new ExtendedLogger(LOG_DIR);
    
    // Get current retention settings
    $retentionDays = $logger->getRetentionDays();
    logCronExecution("Retention Period: {$retentionDays} days");
    
    // Optional: Override retention from .env.prod if configured
    if (isset($env['RETENTION_DAYS']) && is_numeric($env['RETENTION_DAYS'])) {
        $configuredRetention = (int)$env['RETENTION_DAYS'];
        if ($configuredRetention >= 1 && $configuredRetention <= 90) {
            $logger->setRetentionDays($configuredRetention);
            $retentionDays = $configuredRetention;
            logCronExecution("Retention Period overridden from .env.prod: {$retentionDays} days");
        }
    }
    
    // ========================================================================
    // Run Anonymization
    // ========================================================================
    
    /**
     * Anonymization Process
     * 
     * The anonymizeOldEntries() method:
     * 1. Scans detailed_submissions.log for entries > 14 days old
     * 2. Replaces full IPs with anonymized versions:
     *    - IPv4: 192.168.1.100 → 192.168.1.XXX
     *    - IPv6: 2001:db8::1 → 2001:db8::XXX
     * 3. Marks entries as anonymized with timestamp
     * 4. Logs each anonymization to audit trail
     * 5. Returns count of anonymized entries
     */
    
    logCronExecution("Scanning for entries older than {$retentionDays} days...");
    $anonymizedCount = $logger->anonymizeOldEntries();
    
    // ========================================================================
    // Log Results
    // ========================================================================
    
    if ($anonymizedCount > 0) {
        logCronExecution("✓ Anonymized {$anonymizedCount} entries", 'SUCCESS');
        
        // Get recent anonymization history for verification
        $recentAnonymizations = $logger->getAnonymizationHistory(5);
        
        if (!empty($recentAnonymizations)) {
            logCronExecution("Recent anonymizations logged in audit trail");
        }
    } else {
        logCronExecution("No entries requiring anonymization (all up to date)");
    }
    
    // ========================================================================
    // Generate Statistics
    // ========================================================================
    
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
    // Successful Completion
    // ========================================================================
    
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Completed Successfully in {$executionTime} ===");
    
    exit(0);
    
} catch (Exception $e) {
    // ========================================================================
    // Error Handling
    // ========================================================================
    
    logCronError("Exception occurred: " . $e->getMessage());
    logCronError("File: " . $e->getFile() . " (Line " . $e->getLine() . ")");
    logCronError("Stack Trace: " . $e->getTraceAsString());
    
    $executionTime = getExecutionTime($startTime);
    logCronExecution("=== Cronjob Failed after {$executionTime} ===", 'ERROR');
    
    exit(1);
}

// ============================================================================
// END OF SCRIPT
// ============================================================================
