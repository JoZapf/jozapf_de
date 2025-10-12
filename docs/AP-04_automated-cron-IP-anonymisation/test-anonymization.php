#!/usr/bin/env php
<?php
/**
 * Test Script for Anonymization Cronjob
 * 
 * Purpose: Verify the anonymization script works correctly before setting up cronjob
 * 
 * Usage:
 *   php test-anonymization.php
 * 
 * This script will:
 *   1. Check if all required files exist
 *   2. Verify directory permissions
 *   3. Test ExtendedLogger initialization
 *   4. Show current log statistics
 *   5. Perform a dry-run of anonymization
 * 
 * @author JoZapf
 * @version 1.0.0
 * @since 2025-10-06
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

define('CRON_DIR', __DIR__);
define('PROJECT_ROOT', dirname(CRON_DIR));
define('PHP_DIR', PROJECT_ROOT . '/assets/php');
define('LOG_DIR', PHP_DIR . '/logs');

// Colors for terminal output
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_BLUE', "\033[0;34m");
define('COLOR_RESET', "\033[0m");

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function printHeader(string $text): void {
    echo "\n" . COLOR_BLUE . str_repeat('=', 70) . COLOR_RESET . "\n";
    echo COLOR_BLUE . "  " . $text . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat('=', 70) . COLOR_RESET . "\n\n";
}

function printSuccess(string $text): void {
    echo COLOR_GREEN . "✓ " . $text . COLOR_RESET . "\n";
}

function printError(string $text): void {
    echo COLOR_RED . "✗ " . $text . COLOR_RESET . "\n";
}

function printWarning(string $text): void {
    echo COLOR_YELLOW . "⚠ " . $text . COLOR_RESET . "\n";
}

function printInfo(string $text): void {
    echo "  " . $text . "\n";
}

// ============================================================================
// TEST FUNCTIONS
// ============================================================================

function testFileExists(string $path, string $description): bool {
    if (file_exists($path)) {
        printSuccess("{$description} exists: {$path}");
        return true;
    } else {
        printError("{$description} NOT FOUND: {$path}");
        return false;
    }
}

function testDirectoryWritable(string $path, string $description): bool {
    if (!is_dir($path)) {
        printError("{$description} does not exist: {$path}");
        return false;
    }
    
    if (is_writable($path)) {
        printSuccess("{$description} is writable: {$path}");
        return true;
    } else {
        printError("{$description} is NOT writable: {$path}");
        return false;
    }
}

// ============================================================================
// MAIN TEST EXECUTION
// ============================================================================

printHeader("ContactForm GDPR Anonymization - Test Script");

echo "PHP Version: " . COLOR_GREEN . PHP_VERSION . COLOR_RESET . "\n";
echo "User: " . COLOR_GREEN . get_current_user() . COLOR_RESET . "\n";
echo "Script: " . COLOR_GREEN . __FILE__ . COLOR_RESET . "\n\n";

$allTestsPassed = true;

// ============================================================================
// TEST 1: File Existence
// ============================================================================

printHeader("TEST 1: Checking Required Files");

$requiredFiles = [
    [PHP_DIR . '/.env.prod', '.env.prod configuration'],
    [PHP_DIR . '/ExtendedLogger.php', 'ExtendedLogger class'],
    [CRON_DIR . '/anonymize-logs.php', 'Anonymization cronjob script'],
];

foreach ($requiredFiles as [$path, $desc]) {
    if (!testFileExists($path, $desc)) {
        $allTestsPassed = false;
    }
}

// ============================================================================
// TEST 2: Directory Permissions
// ============================================================================

printHeader("TEST 2: Checking Directory Permissions");

$requiredDirs = [
    [LOG_DIR, 'Log directory'],
    [PHP_DIR . '/data', 'Data directory'],
];

foreach ($requiredDirs as [$path, $desc]) {
    if (!testDirectoryWritable($path, $desc)) {
        $allTestsPassed = false;
    }
}

// ============================================================================
// TEST 3: Environment Configuration
// ============================================================================

printHeader("TEST 3: Checking Environment Configuration");

if (file_exists(PHP_DIR . '/.env.prod')) {
    $envContent = file_get_contents(PHP_DIR . '/.env.prod');
    
    // Check for required variables
    $requiredVars = ['SMTP_HOST', 'SMTP_USER', 'RECIPIENT_EMAIL'];
    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var) !== false) {
            printSuccess("Environment variable '{$var}' found");
        } else {
            printWarning("Environment variable '{$var}' not found");
        }
    }
} else {
    printError(".env.prod not found - cannot check configuration");
    $allTestsPassed = false;
}

// ============================================================================
// TEST 4: ExtendedLogger Initialization
// ============================================================================

printHeader("TEST 4: Testing ExtendedLogger");

try {
    require_once PHP_DIR . '/ExtendedLogger.php';
    printSuccess("ExtendedLogger class loaded successfully");
    
    $logger = new ExtendedLogger(LOG_DIR);
    printSuccess("ExtendedLogger initialized successfully");
    
    $retentionDays = $logger->getRetentionDays();
    printInfo("Retention Period: {$retentionDays} days");
    
} catch (Exception $e) {
    printError("Failed to initialize ExtendedLogger: " . $e->getMessage());
    $allTestsPassed = false;
    exit(1);
}

// ============================================================================
// TEST 5: Log Statistics
// ============================================================================

printHeader("TEST 5: Current Log Statistics");

try {
    $stats7d = $logger->getStatistics(7);
    $stats30d = $logger->getStatistics(30);
    
    echo "\n" . COLOR_YELLOW . "Last 7 Days:" . COLOR_RESET . "\n";
    printInfo("Total Submissions: {$stats7d['total']}");
    printInfo("Blocked: {$stats7d['blocked']}");
    printInfo("Allowed: {$stats7d['allowed']}");
    printInfo("Avg Spam Score: {$stats7d['avgSpamScore']}");
    printInfo("Unique IPs: {$stats7d['uniqueIPs']}");
    
    echo "\n" . COLOR_YELLOW . "Last 30 Days:" . COLOR_RESET . "\n";
    printInfo("Total Submissions: {$stats30d['total']}");
    printInfo("Blocked: {$stats30d['blocked']}");
    printInfo("Allowed: {$stats30d['allowed']}");
    printInfo("Avg Spam Score: {$stats30d['avgSpamScore']}");
    printInfo("Unique IPs: {$stats30d['uniqueIPs']}");
    
    if ($stats30d['total'] === 0) {
        printWarning("No submissions found in logs - this is normal for a fresh installation");
    }
    
} catch (Exception $e) {
    printError("Failed to get statistics: " . $e->getMessage());
    $allTestsPassed = false;
}

// ============================================================================
// TEST 6: Anonymization Check
// ============================================================================

printHeader("TEST 6: Checking Entries Requiring Anonymization");

try {
    $logFile = LOG_DIR . '/detailed_submissions.log';
    
    if (!file_exists($logFile)) {
        printWarning("No submission log file found yet - this is normal for a fresh installation");
    } else {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $cutoffDate = strtotime("-{$retentionDays} days");
        
        $totalEntries = 0;
        $needsAnonymization = 0;
        $alreadyAnonymized = 0;
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['timestamp'])) {
                continue;
            }
            
            $totalEntries++;
            $entryTime = strtotime($entry['timestamp']);
            $isAnonymized = $entry['anonymized'] ?? false;
            
            if ($entryTime < $cutoffDate) {
                if ($isAnonymized) {
                    $alreadyAnonymized++;
                } else {
                    $needsAnonymization++;
                }
            }
        }
        
        printInfo("Total log entries: {$totalEntries}");
        printInfo("Entries older than {$retentionDays} days:");
        printInfo("  - Already anonymized: {$alreadyAnonymized}");
        printInfo("  - Need anonymization: " . COLOR_YELLOW . $needsAnonymization . COLOR_RESET);
        
        if ($needsAnonymization > 0) {
            printWarning("Found {$needsAnonymization} entries that need anonymization");
            printInfo("Run the cronjob to anonymize these entries");
        } else {
            printSuccess("All entries are up to date!");
        }
    }
    
} catch (Exception $e) {
    printError("Failed to check anonymization status: " . $e->getMessage());
    $allTestsPassed = false;
}

// ============================================================================
// TEST 7: Anonymization History
// ============================================================================

printHeader("TEST 7: Recent Anonymization History");

try {
    $history = $logger->getAnonymizationHistory(10);
    
    if (empty($history)) {
        printInfo("No anonymization history yet - this is normal for a fresh installation");
    } else {
        printSuccess("Found " . count($history) . " recent anonymization entries");
        
        echo "\n" . COLOR_YELLOW . "Most Recent Anonymizations:" . COLOR_RESET . "\n";
        foreach (array_slice($history, 0, 5) as $entry) {
            $timestamp = $entry['timestamp'] ?? 'unknown';
            $originalTs = $entry['originalTimestamp'] ?? 'unknown';
            $anonymizedIP = $entry['anonymizedIP'] ?? 'unknown';
            printInfo("  {$timestamp}: Anonymized entry from {$originalTs} → {$anonymizedIP}");
        }
    }
    
} catch (Exception $e) {
    printError("Failed to get anonymization history: " . $e->getMessage());
    $allTestsPassed = false;
}

// ============================================================================
// TEST 8: Cronjob Log Check
// ============================================================================

printHeader("TEST 8: Checking Cronjob Log");

$cronLog = LOG_DIR . '/cron-anonymization.log';
if (file_exists($cronLog)) {
    printSuccess("Cronjob log exists: {$cronLog}");
    
    $logLines = file($cronLog, FILE_IGNORE_NEW_LINES);
    $recentLines = array_slice($logLines, -10);
    
    echo "\n" . COLOR_YELLOW . "Last 10 Cronjob Log Entries:" . COLOR_RESET . "\n";
    foreach ($recentLines as $line) {
        if (strpos($line, '[ERROR]') !== false) {
            echo COLOR_RED . $line . COLOR_RESET . "\n";
        } elseif (strpos($line, '[SUCCESS]') !== false) {
            echo COLOR_GREEN . $line . COLOR_RESET . "\n";
        } else {
            echo "  " . $line . "\n";
        }
    }
} else {
    printInfo("Cronjob log does not exist yet - will be created on first run");
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================

printHeader("Test Summary");

if ($allTestsPassed) {
    printSuccess("ALL TESTS PASSED!");
    echo "\n" . COLOR_GREEN . "✓ The anonymization cronjob is ready to be set up." . COLOR_RESET . "\n";
    echo "\n" . COLOR_YELLOW . "Next Steps:" . COLOR_RESET . "\n";
    printInfo("1. Test manual execution: php " . CRON_DIR . "/anonymize-logs.php");
    printInfo("2. Set up cronjob in Hetzner Console (see README.md)");
    printInfo("3. Monitor cronjob execution in: {$cronLog}");
    echo "\n";
    exit(0);
} else {
    printError("SOME TESTS FAILED!");
    echo "\n" . COLOR_RED . "✗ Please fix the issues above before setting up the cronjob." . COLOR_RESET . "\n";
    echo "\n" . COLOR_YELLOW . "Troubleshooting:" . COLOR_RESET . "\n";
    printInfo("1. Check file permissions: chmod 755 for directories, 644 for files");
    printInfo("2. Ensure .env.prod exists with correct configuration");
    printInfo("3. Verify log directory is writable: chmod 755 logs/");
    printInfo("4. Check README.md for detailed setup instructions");
    echo "\n";
    exit(1);
}
