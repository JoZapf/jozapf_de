<?php
/**
 * Enhanced Contact Form Abuse Logger
 * 
 * Features:
 * - Strukturiertes JSON-Logging für Analyse
 * - IP-Tracking & Rate-Limiting Detection
 * - Fingerprinting (User-Agent, Accept-Language, etc.)
 * - Spam-Score Calculation
 * - Verdächtiges Verhalten erkennen
 * - Logrotation Support
 * - DSGVO-konform (Anonymisierung möglich)
 * 
 * @author JoZapf
 * @version 1.0.0
 * @date 2025-10-02
 */

class AbuseLogger
{
    private string $logDir;
    private string $securityLog;
    private string $submissionsLog;
    private string $blockedLog;
    private string $blocklistFile;
    private bool $anonymizeIP;
    
    // Rate-Limiting Configuration
    private int $maxSubmissionsPerHour = 5;
    private int $maxSubmissionsPerDay = 20;
    
    // Spam Detection Thresholds
    private int $spamScoreThreshold = 70; // Block if score >= 70
    
    // Spam Keywords
    private array $spamKeywords = [
        'viagra', 'cialis', 'pharmacy', 'casino', 'poker',
        'loan', 'mortgage', 'crypto', 'bitcoin', 'investment',
        'weight loss', 'penis', 'sex', 'porn', 'xxx'
    ];
    
    // Temp Email Domains
    private array $tempEmailDomains = [
        'temp-mail.org', 'guerrillamail.com', '10minutemail.com',
        'throwaway.email', 'mailinator.com', 'trashmail.com',
        'yopmail.com', 'getnada.com', 'tempmail.de'
    ];
    
    public function __construct(
        string $logDir, 
        bool $anonymizeIP = false
    ) {
        $this->logDir = rtrim($logDir, '/');
        $this->anonymizeIP = $anonymizeIP;
        
        // Separate Log-Dateien für verschiedene Zwecke
        $this->securityLog = $this->logDir . '/security.log';
        $this->submissionsLog = $this->logDir . '/submissions.log';
        $this->blockedLog = $this->logDir . '/blocked.log';
        $this->blocklistFile = $this->logDir . '/blocklist.txt';
        
        // Log-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Archive-Verzeichnis für rotierte Logs
        if (!is_dir($this->logDir . '/archive')) {
            mkdir($this->logDir . '/archive', 0755, true);
        }
    }
    
    /**
     * Hauptmethode: Prüft und loggt eine Formular-Submission
     * 
     * @param array $formData Die Formulardaten
     * @return array ['allowed' => bool, 'spamScore' => int, 'reason' => string|null]
     */
    public function checkAndLog(array $formData): array
    {
        $ip = $this->getClientIP();
        $fingerprint = $this->getFingerprint();
        
        // 1. Prüfe Blocklist
        if ($this->isIPBlocked($ip)) {
            $this->logBlocked($ip, $formData, 'IP on blocklist', 100);
            return [
                'allowed' => false,
                'spamScore' => 100,
                'reason' => 'Your IP address has been blocked'
            ];
        }
        
        // 2. Prüfe Rate Limiting
        $rateLimit = $this->checkRateLimit($ip);
        if (!$rateLimit['allowed']) {
            $this->logBlocked($ip, $formData, $rateLimit['reason'], 90);
            return [
                'allowed' => false,
                'spamScore' => 90,
                'reason' => $rateLimit['reason']
            ];
        }
        
        // 3. Berechne Spam-Score
        $spamAnalysis = $this->calculateSpamScore($formData);
        
        // 4. Entscheide ob blockieren
        $allowed = $spamAnalysis['score'] < $this->spamScoreThreshold;
        
        // 5. Logge Submission
        $logEntry = [
            'timestamp' => date('c'),
            'ip' => $this->anonymizeIP ? $this->anonymizeIPAddress($ip) : $ip,
            'userAgent' => $fingerprint['userAgent'],
            'acceptLanguage' => $fingerprint['acceptLanguage'],
            'referer' => $fingerprint['referer'],
            'formData' => [
                'name' => $formData['name'] ?? '',
                'email' => $formData['email'] ?? '',
                'phone' => $formData['phone'] ?? '',
                'subject' => $formData['subject'] ?? '',
                'message' => substr($formData['message'] ?? '', 0, 200) . '...' // Gekürzt
            ],
            'spamScore' => $spamAnalysis['score'],
            'spamReasons' => $spamAnalysis['reasons'],
            'ipSubmissions' => $rateLimit['counts'],
            'blocked' => !$allowed,
            'blockReason' => !$allowed ? 'Spam score too high' : null
        ];
        
        // In entsprechende Log-Datei schreiben
        if ($allowed) {
            $this->appendLog($this->submissionsLog, $logEntry);
        } else {
            $this->appendLog($this->blockedLog, $logEntry);
            $this->logSecurityEvent("Blocked submission from {$ip} (Score: {$spamAnalysis['score']})");
        }
        
        return [
            'allowed' => $allowed,
            'spamScore' => $spamAnalysis['score'],
            'reason' => !$allowed ? 'Your submission appears to be spam' : null
        ];
    }
    
    /**
     * Berechnet Spam-Score basierend auf verschiedenen Kriterien
     */
    private function calculateSpamScore(array $formData): array
    {
        $score = 0;
        $reasons = [];
        
        $message = strtolower($formData['message'] ?? '');
        $email = strtolower($formData['email'] ?? '');
        $name = $formData['name'] ?? '';
        
        // 1. Verdächtige Keywords (20 Punkte)
        foreach ($this->spamKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $score += 20;
                $reasons[] = 'suspicious_keywords';
                break;
            }
        }
        
        // 2. Zu viele Links (15 Punkte)
        $linkCount = substr_count($message, 'http://') + substr_count($message, 'https://');
        if ($linkCount > 3) {
            $score += 15;
            $reasons[] = 'excessive_links';
        }
        
        // 3. Großbuchstaben-Missbrauch (15 Punkte)
        $capsRatio = $this->calculateCapsRatio($formData['message'] ?? '');
        if ($capsRatio > 0.5) {
            $score += 15;
            $reasons[] = 'excessive_caps';
        }
        
        // 4. Temp Email (25 Punkte)
        $emailDomain = substr(strrchr($email, '@'), 1);
        if (in_array($emailDomain, $this->tempEmailDomains)) {
            $score += 25;
            $reasons[] = 'temp_email';
        }
        
        // 5. Name = Email (10 Punkte)
        if (stripos($email, str_replace(' ', '', $name)) !== false) {
            $score += 10;
            $reasons[] = 'name_equals_email';
        }
        
        // 6. Nachrichtenlänge (15-20 Punkte)
        $msgLength = strlen($message);
        if ($msgLength < 10) {
            $score += 20;
            $reasons[] = 'message_too_short';
        } elseif ($msgLength > 5000) {
            $score += 15;
            $reasons[] = 'message_too_long';
        }
        
        // 7. Nur Sonderzeichen (20 Punkte)
        $alphanumRatio = $this->calculateAlphanumRatio($message);
        if ($alphanumRatio < 0.3) {
            $score += 20;
            $reasons[] = 'non_alphanumeric';
        }
        
        // 8. Bot-Timing wird in contact-php-handler geprüft
        
        // 9. Fehlender/falscher Referer (10 Punkte)
        if (empty($_SERVER['HTTP_REFERER']) || 
            strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
            $score += 10;
            $reasons[] = 'invalid_referer';
        }
        
        return [
            'score' => min($score, 100), // Max 100
            'reasons' => $reasons
        ];
    }
    
    /**
     * Prüft Rate-Limiting für eine IP
     */
    private function checkRateLimit(string $ip): array
    {
        $now = time();
        $oneHourAgo = $now - 3600;
        $oneDayAgo = $now - 86400;
        
        // Lies submissions.log und zähle
        $hourCount = 0;
        $dayCount = 0;
        
        if (file_exists($this->submissionsLog)) {
            $lines = file($this->submissionsLog, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;
                
                $entryIP = $entry['ip'] ?? '';
                $entryTime = strtotime($entry['timestamp'] ?? '');
                
                // IP-Match (auch anonymisierte IPs berücksichtigen)
                $ipMatch = ($entryIP === $ip) || 
                           ($this->anonymizeIP && $this->anonymizeIPAddress($ip) === $entryIP);
                
                if ($ipMatch) {
                    if ($entryTime >= $oneHourAgo) $hourCount++;
                    if ($entryTime >= $oneDayAgo) $dayCount++;
                }
            }
        }
        
        if ($hourCount >= $this->maxSubmissionsPerHour) {
            return [
                'allowed' => false,
                'reason' => 'Too many submissions in the last hour. Please try again later.',
                'counts' => ['lastHour' => $hourCount, 'lastDay' => $dayCount]
            ];
        }
        
        if ($dayCount >= $this->maxSubmissionsPerDay) {
            return [
                'allowed' => false,
                'reason' => 'Too many submissions today. Please try again tomorrow.',
                'counts' => ['lastHour' => $hourCount, 'lastDay' => $dayCount]
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => null,
            'counts' => ['lastHour' => $hourCount, 'lastDay' => $dayCount]
        ];
    }
    
    /**
     * Prüft ob IP auf Blocklist steht
     */
    private function isIPBlocked(string $ip): bool
    {
        if (!file_exists($this->blocklistFile)) {
            return false;
        }
        
        $blocked = file($this->blocklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return in_array($ip, $blocked);
    }
    
    /**
     * Holt Client-IP (auch hinter Proxies)
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Bei X-Forwarded-For kann es mehrere IPs geben
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Sammelt Browser-Fingerprint
     */
    private function getFingerprint(): array
    {
        return [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'acceptLanguage' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'acceptEncoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
        ];
    }
    
    /**
     * Anonymisiert IP-Adresse (DSGVO)
     */
    private function anonymizeIPAddress(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Letztes Oktett auf 0 setzen
            return preg_replace('/\d+$/', '0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Letzten 80 Bits auf 0 setzen
            return substr($ip, 0, strpos($ip, ':', strpos($ip, ':') + 1)) . '::';
        }
        return $ip;
    }
    
    /**
     * Berechnet Großbuchstaben-Ratio
     */
    private function calculateCapsRatio(string $text): float
    {
        $text = preg_replace('/[^a-zA-Z]/', '', $text);
        if (strlen($text) === 0) return 0;
        
        $upper = strlen(preg_replace('/[^A-Z]/', '', $text));
        return $upper / strlen($text);
    }
    
    /**
     * Berechnet alphanumerische Ratio
     */
    private function calculateAlphanumRatio(string $text): float
    {
        if (strlen($text) === 0) return 0;
        
        $alphanum = strlen(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        return $alphanum / strlen($text);
    }
    
    /**
     * Schreibt in Log-Datei (JSON-Lines Format)
     */
    private function appendLog(string $logFile, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($logFile, $json, FILE_APPEND | LOCK_EX);
        
        // Rotiere Log bei Bedarf (>10MB)
        $this->rotateLogIfNeeded($logFile);
    }
    
    /**
     * Loggt blockierte Submission
     */
    private function logBlocked(string $ip, array $formData, string $reason, int $score): void
    {
        $entry = [
            'timestamp' => date('c'),
            'ip' => $this->anonymizeIP ? $this->anonymizeIPAddress($ip) : $ip,
            'reason' => $reason,
            'spamScore' => $score,
            'formData' => [
                'email' => $formData['email'] ?? '',
                'name' => $formData['name'] ?? ''
            ]
        ];
        
        $this->appendLog($this->blockedLog, $entry);
        $this->logSecurityEvent("Blocked: {$reason} from {$ip}");
    }
    
    /**
     * Loggt Sicherheits-Event
     */
    private function logSecurityEvent(string $message): void
    {
        $log = sprintf(
            "[%s] %s - IP: %s - UA: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            $this->getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );
        
        file_put_contents($this->securityLog, $log, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotiert Log-Dateien wenn sie zu groß werden
     */
    private function rotateLogIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) return;
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        if (filesize($logFile) > $maxSize) {
            $archiveName = basename($logFile, '.log') . '-' . date('Ymd-His') . '.log';
            $archivePath = $this->logDir . '/archive/' . $archiveName;
            rename($logFile, $archivePath);
            
            // Optional: Komprimieren
            if (function_exists('gzopen')) {
                $gz = gzopen($archivePath . '.gz', 'w9');
                gzwrite($gz, file_get_contents($archivePath));
                gzclose($gz);
                unlink($archivePath);
            }
        }
    }
    
    /**
     * Generiert täglichen Report
     */
    public function generateDailyReport(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $report = [
            'date' => $date,
            'total' => 0,
            'allowed' => 0,
            'blocked' => 0,
            'avgSpamScore' => 0,
            'byReason' => [],
            'topIPs' => [],
            'highRiskSubmissions' => []
        ];
        
        $scores = [];
        $ipCounts = [];
        
        // Analysiere submissions.log
        if (file_exists($this->submissionsLog)) {
            $lines = file($this->submissionsLog, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;
                
                $entryDate = substr($entry['timestamp'], 0, 10);
                if ($entryDate !== $date) continue;
                
                $report['total']++;
                $report['allowed']++;
                $scores[] = $entry['spamScore'] ?? 0;
                
                $ip = $entry['ip'] ?? 'Unknown';
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
                
                if (($entry['spamScore'] ?? 0) >= 50) {
                    $report['highRiskSubmissions'][] = [
                        'timestamp' => $entry['timestamp'],
                        'ip' => $ip,
                        'email' => $entry['formData']['email'] ?? '',
                        'spamScore' => $entry['spamScore']
                    ];
                }
            }
        }
        
        // Analysiere blocked.log
        if (file_exists($this->blockedLog)) {
            $lines = file($this->blockedLog, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry) continue;
                
                $entryDate = substr($entry['timestamp'], 0, 10);
                if ($entryDate !== $date) continue;
                
                $report['total']++;
                $report['blocked']++;
                $scores[] = $entry['spamScore'] ?? 0;
                
                $reason = $entry['reason'] ?? 'Unknown';
                $report['byReason'][$reason] = ($report['byReason'][$reason] ?? 0) + 1;
                
                $ip = $entry['ip'] ?? 'Unknown';
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
            }
        }
        
        // Berechne Durchschnitts-Score
        if (!empty($scores)) {
            $report['avgSpamScore'] = round(array_sum($scores) / count($scores), 2);
        }
        
        // Top 10 IPs
        arsort($ipCounts);
        $report['topIPs'] = array_slice($ipCounts, 0, 10, true);
        
        return $report;
    }
}
