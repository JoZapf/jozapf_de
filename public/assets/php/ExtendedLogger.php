<?php
/**
 * Extended Logger with GDPR-compliant Auto-Anonymization
 * 
 * Features:
 * - Logs full IP addresses for 14 days
 * - Auto-anonymizes IPs after 14 days (192.168.1.XXX)
 * - Detailed submission tracking
 * - User-Agent and fingerprint logging
 * 
 * GDPR Compliance:
 * - Legal basis: Art. 6 (1) f GDPR (legitimate interest)
 * - Purpose: Spam protection and abuse prevention
 * - Retention: 14 days for full IPs, anonymized thereafter
 * - Deletion: Automatic anonymization process
 * 
 * @author JoZapf
 * @version 1.0.0
 */

class ExtendedLogger {
    private $logDir;
    private $retentionDays = 14;
    
    // Log files
    private $detailedLog = 'detailed_submissions.log';
    private $anonymizationLog = 'anonymization_history.log';
    
    public function __construct(string $logDir) {
        $this->logDir = rtrim($logDir, '/');
        
        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Run anonymization on init (lazy cleanup)
        $this->anonymizeOldEntries();
    }
    
    /**
     * Log a detailed submission with full metadata
     */
    public function logSubmission(array $data): void {
        $entry = [
            'timestamp' => date('c'),
            'ip' => $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'fingerprint' => $this->generateFingerprint(),
            'formData' => [
                'email' => $data['formData']['email'] ?? null,
                'subject' => $data['formData']['subject'] ?? null,
                'hasAttachment' => !empty($data['formData']['attachment'])
            ],
            'spamScore' => $data['spamScore'] ?? 0,
            'blocked' => $data['blocked'] ?? false,
            'blockReason' => $data['reason'] ?? null,
            'validationDetails' => $data['validationDetails'] ?? [],
            'anonymized' => false
        ];
        
        $this->writeLog($this->detailedLog, $entry);
    }
    
    /**
     * Generate browser/device fingerprint (non-invasive)
     */
    private function generateFingerprint(): string {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
        
        return substr(hash('sha256', implode('|', $components)), 0, 16);
    }
    
    /**
     * Anonymize IPs older than retention period (14 days)
     */
    public function anonymizeOldEntries(): int {
        $logFile = $this->logDir . '/' . $this->detailedLog;
        
        if (!file_exists($logFile)) {
            return 0;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $updated = [];
        $anonymizedCount = 0;
        $cutoffDate = strtotime("-{$this->retentionDays} days");
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['timestamp'])) {
                $updated[] = $line; // Keep malformed entries as-is
                continue;
            }
            
            $entryTime = strtotime($entry['timestamp']);
            
            // Anonymize if older than retention period and not already anonymized
            if ($entryTime < $cutoffDate && !($entry['anonymized'] ?? false)) {
                $originalIp = $entry['ip'];
                $entry['ip'] = $this->anonymizeIP($entry['ip']);
                $entry['anonymized'] = true;
                $entry['anonymizedAt'] = date('c');
                
                // Log anonymization action
                $this->logAnonymization($originalIp, $entry['ip'], $entry['timestamp']);
                $anonymizedCount++;
            }
            
            $updated[] = json_encode($entry, JSON_UNESCAPED_UNICODE);
        }
        
        // Write updated log
        if ($anonymizedCount > 0) {
            file_put_contents($logFile, implode("\n", $updated) . "\n");
        }
        
        return $anonymizedCount;
    }
    
    /**
     * Anonymize IP address by removing last octet
     * 192.168.1.100 -> 192.168.1.XXX
     * 2001:db8::1 -> 2001:db8::XXX
     */
    private function anonymizeIP(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Remove last octet
            $parts = explode('.', $ip);
            $parts[3] = 'XXX';
            return implode('.', $parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Remove last segment
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = 'XXX';
            return implode(':', $parts);
        }
        
        return 'XXX.XXX.XXX.XXX'; // Unknown format
    }
    
    /**
     * Log anonymization action (audit trail)
     */
    private function logAnonymization(string $originalIP, string $anonymizedIP, string $originalTimestamp): void {
        $entry = [
            'timestamp' => date('c'),
            'originalTimestamp' => $originalTimestamp,
            'originalIP' => hash('sha256', $originalIP), // Hash for audit, not store plain
            'anonymizedIP' => $anonymizedIP,
            'action' => 'auto_anonymization',
            'retentionDays' => $this->retentionDays
        ];
        
        $this->writeLog($this->anonymizationLog, $entry);
    }
    
    /**
     * Get recent submissions (non-anonymized only, for blocklist UI)
     */
    public function getRecentSubmissions(int $limit = 50, bool $onlyNonAnonymized = true): array {
        $logFile = $this->logDir . '/' . $this->detailedLog;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $submissions = [];
        
        // Read in reverse (newest first)
        foreach (array_reverse($lines) as $line) {
            if (count($submissions) >= $limit) {
                break;
            }
            
            $entry = json_decode($line, true);
            
            if (!$entry) {
                continue;
            }
            
            // Skip anonymized entries if requested
            if ($onlyNonAnonymized && ($entry['anonymized'] ?? false)) {
                continue;
            }
            
            $submissions[] = $entry;
        }
        
        return $submissions;
    }
    
    /**
     * Get submissions by IP address
     */
    public function getSubmissionsByIP(string $ip, int $days = 30): array {
        $logFile = $this->logDir . '/' . $this->detailedLog;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $submissions = [];
        $cutoffDate = strtotime("-{$days} days");
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['ip'], $entry['timestamp'])) {
                continue;
            }
            
            // Check IP match and date range
            if ($entry['ip'] === $ip && strtotime($entry['timestamp']) >= $cutoffDate) {
                $submissions[] = $entry;
            }
        }
        
        return $submissions;
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStatistics(int $days = 7): array {
        $logFile = $this->logDir . '/' . $this->detailedLog;
        
        if (!file_exists($logFile)) {
            return [
                'total' => 0,
                'blocked' => 0,
                'allowed' => 0,
                'avgSpamScore' => 0,
                'uniqueIPs' => 0,
                'topBlockReasons' => []
            ];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $cutoffDate = strtotime("-{$days} days");
        
        $stats = [
            'total' => 0,
            'blocked' => 0,
            'allowed' => 0,
            'spamScores' => [],
            'ips' => [],
            'blockReasons' => []
        ];
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['timestamp'])) {
                continue;
            }
            
            // Only count entries within date range
            if (strtotime($entry['timestamp']) < $cutoffDate) {
                continue;
            }
            
            $stats['total']++;
            
            if ($entry['blocked'] ?? false) {
                $stats['blocked']++;
                $reason = $entry['blockReason'] ?? 'unknown';
                $stats['blockReasons'][$reason] = ($stats['blockReasons'][$reason] ?? 0) + 1;
            } else {
                $stats['allowed']++;
            }
            
            if (isset($entry['spamScore'])) {
                $stats['spamScores'][] = $entry['spamScore'];
            }
            
            if (isset($entry['ip']) && !($entry['anonymized'] ?? false)) {
                $stats['ips'][$entry['ip']] = ($stats['ips'][$entry['ip']] ?? 0) + 1;
            }
        }
        
        // Calculate averages
        $avgSpamScore = !empty($stats['spamScores']) 
            ? array_sum($stats['spamScores']) / count($stats['spamScores']) 
            : 0;
        
        // Sort block reasons by frequency
        arsort($stats['blockReasons']);
        
        // Sort IPs by frequency
        arsort($stats['ips']);
        
        return [
            'total' => $stats['total'],
            'blocked' => $stats['blocked'],
            'allowed' => $stats['allowed'],
            'avgSpamScore' => round($avgSpamScore, 2),
            'uniqueIPs' => count($stats['ips']),
            'topBlockReasons' => array_slice($stats['blockReasons'], 0, 10, true),
            'topIPs' => array_slice($stats['ips'], 0, 10, true)
        ];
    }
    
    /**
     * Write log entry to file
     */
    private function writeLog(string $filename, array $entry): void {
        $logFile = $this->logDir . '/' . $filename;
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get retention period
     */
    public function getRetentionDays(): int {
        return $this->retentionDays;
    }
    
    /**
     * Set retention period (for testing or configuration)
     */
    public function setRetentionDays(int $days): void {
        if ($days < 1 || $days > 90) {
            throw new InvalidArgumentException('Retention days must be between 1 and 90');
        }
        $this->retentionDays = $days;
    }
    
    /**
     * Get anonymization history (audit trail)
     */
    public function getAnonymizationHistory(int $limit = 100): array {
        $logFile = $this->logDir . '/' . $this->anonymizationLog;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $history = [];
        
        foreach (array_reverse($lines) as $line) {
            if (count($history) >= $limit) {
                break;
            }
            
            $entry = json_decode($line, true);
            if ($entry) {
                $history[] = $entry;
            }
        }
        
        return $history;
    }
    
    /**
     * Manual cleanup: Delete all anonymized entries older than X days
     * This is optional - for compliance or disk space management
     */
    public function purgeAnonymizedEntries(int $olderThanDays = 365): int {
        $logFile = $this->logDir . '/' . $this->detailedLog;
        
        if (!file_exists($logFile)) {
            return 0;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $kept = [];
        $deletedCount = 0;
        $cutoffDate = strtotime("-{$olderThanDays} days");
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['timestamp'])) {
                $kept[] = $line;
                continue;
            }
            
            $entryTime = strtotime($entry['timestamp']);
            $isAnonymized = $entry['anonymized'] ?? false;
            
            // Delete if anonymized AND older than threshold
            if ($isAnonymized && $entryTime < $cutoffDate) {
                $deletedCount++;
                continue; // Don't keep this entry
            }
            
            $kept[] = $line;
        }
        
        if ($deletedCount > 0) {
            file_put_contents($logFile, implode("\n", $kept) . "\n");
        }
        
        return $deletedCount;
    }
}
