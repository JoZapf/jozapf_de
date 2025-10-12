#!/usr/bin/env php
<?php
/**
 * GDPR-Compliant Log Anonymization Cronjob
 * 
 * Purpose: Automatically anonymize IP addresses in contact form logs
 *          after the legal retention period (14 days)
 * 
 * Features:
 *   - Relative path resolution (portable across servers)
 *   - GDPR-compliant auto-anonymization (Art. 5 (1) e)
 *   - Comprehensive logging with audit trail
 *   - Email notifications on errors
 *   - Safe fail-fast error handling
 * 
 * Usage:
 *   Via cron:   /usr/bin/php /path/to/cron/contactform/anonymize-logs.php
 *   Manually:   php anonymize-logs.php
 * 
 * Cronjob Configuration (customize paths):
 *   0 3 * * * /usr/bin/php /path/to/cron/contactform/anonymize-logs.php
 * 
 * Recommended Schedules:
 *   Daily at 3:00 AM:    0 3 * * *      (recommended)
 *   Twice daily:         0 3,15 * * *
 *   Every 6 hours:       0 */6 * * *
 *   Weekly (Sundays):    0 2 * * 0
 * 
 * GDPR Compliance:
 *   Art. 6 (1) f GDPR - Legitimate interest (spam protection)
 *   Art. 5 (1) e GDPR - Storage limitation (14-day retention)
 *   Art. 17 GDPR      - Right to erasure (anonymization = deletion)
 * 
 * Path Resolution:
 *   Uses relative paths for portability. Automatically detects webroot.
 *   CRON_DIR (__DIR__) → ../../ → PUBLIC_HTML → /your-project → PROJECT_ROOT
 *   
 *   Customize PROJECT_ROOT below to match your project folder name.
 * 
 * Logs:
 *   Execution log:        /path/to/project/assets/php/logs/cron-anonymization.log
 *   Anonymization audit:  /path/to/project/assets/php/logs/anonymization_history.log
 * 
 * @package    ContactForm-GDPR-Cronjob
 * @author     Contact Form Project Contributors
 * @copyright  2025
 * @license    MIT
 * @version    3.0.0
 * @since      2025-10-06
 * 
 * @changelog
 *   3.0.0 (2025-10-06) - Relative path resolution, portable solution
 *   2.0.0 (2025-10-06) - Initial cronjob implementation
 */

// ============================================================================
// CONFIGURATION - DIRECTORY STRUCTURE RESOLUTION
// ============================================================================

// Automatic webroot detection via relative paths
define('CRON_DIR', __DIR__);
$PUBLIC_HTML = realpath(CRON_DIR . '/../../'); // Adjust ../../ based on your structure
if ($PUBLIC_HTML === false) {
    // Early fatal without dependencies
    fwrite(STDERR, "FATAL: Could not resolve PUBLIC_HTML via " . (CRON_DIR . '/../../') . PHP_EOL);
    fwrite(STDERR, "Adjust the relative path in the script to match your directory structure." . PHP_EOL);
    exit(1);
}

// ⚠️ CUSTOMIZE THIS: Set your project folder name
define('PROJECT_ROOT', $PUBLIC_HTML . '/your-project-name');  // ← CHANGE THIS!

define('PHP_DIR',      PROJECT_ROOT . '/assets/php');
define('LOG_DIR',      PHP_DIR . '/logs');
define('CRON_LOG',     LOG_DIR . '/cron-anonymization.log');

// Common structures:
// - Shared hosting:  $PUBLIC_HTML . '/public_html/your-site'
// - VPS/Dedicated:   $PUBLIC_HTML . '/var/www/your-domain.com'
// - Direct webroot:  $PUBLIC_HTML (no subfolder)
