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
 *   Via cron:   /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
 *   Manually:   php anonymize-logs.php
 * 
 * Cronjob Configuration:
 *   0 3 * * * /usr/bin/php83 /usr/home/users/cron/contactform/anonymize-logs.php
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
 *   Uses relative paths for portability:
 *   CRON_DIR (__DIR__) → ../../ → PUBLIC_HTML → /jozapf-de → PROJECT_ROOT
 * 
 * Logs:
 *   Execution log:        /path/to/project/assets/php/logs/cron-anonymization.log
 *   Anonymization audit:  /path/to/project/assets/php/logs/anonymization_history.log
 * 
 * @package    ContactForm-GDPR-Cronjob
 * @author     JoZapf
 * @copyright  2025 JoZapf
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

// Hetzner directory structure (customize PROJECT_ROOT if needed)
define('CRON_DIR', __DIR__);
$PUBLIC_HTML = realpath(CRON_DIR . '/../../'); // -> /public_html
if ($PUBLIC_HTML === false) {
    // Early fatal without dependencies
    fwrite(STDERR, "FATAL: Could not resolve PUBLIC_HTML via " . (CRON_DIR . '/../../') . PHP_EOL);
    exit(1);
}
define('PROJECT_ROOT', $PUBLIC_HTML . '/jozapf-de');  // ← Customize this for your project
define('PHP_DIR',      PROJECT_ROOT . '/assets/php');
define('LOG_DIR',      PHP_DIR . '/logs');
define('CRON_LOG',     LOG_DIR . '/cron-anonymization.log');
