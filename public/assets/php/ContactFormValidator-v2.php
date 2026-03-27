<?php
/**
 * Contact Form Validator with Extended Logging and Blocklist Support
 * V2.2 - Added Suspicious Prefix/TLD Detection + Disposable API Check
 * 
 * Features:
 * - All original validation rules
 * - Blocklist/Whitelist checking
 * - Extended GDPR-compliant logging
 * - Domain blacklist (email domains)
 * - NEW: Suspicious email prefix detection (spam@, test@, fake@ etc.)
 * - NEW: Suspicious TLD scoring (.tk, .ml, .cf etc.)
 * - NEW: Disposable email API check (DeBounce, optional)
 * 
 * @author JoZapf
 * @version 2.2.0
 * @date 2026-03-27
 *
 * Changelog v2.2.0 (2026-03-27):
 * - Schicht 1: checkSuspiciousPrefix() — Hard-Block (+30) und Soft-Flag (+10)
 * - Schicht 1: checkSuspiciousTLD() — Free-TLDs (.tk, .ml, .cf, .ga, .gq) → +15
 * - Schicht 2: Domain-Blacklist Score von 50 auf 60 erhöht (über blockThreshold)
 * - Schicht 3: checkDisposableAPI() — DeBounce Free API (optional, kein Key)
 * - Konzept: docs/contact-form-feature/KONZEPT-EMAIL-SPAM-VALIDIERUNG.md
 */

require_once __DIR__ . '/ExtendedLogger.php';
require_once __DIR__ . '/BlocklistManager.php';

class ContactFormValidator {
    private $config;
    private $extendedLogger;
    private $blocklistManager;
    private $useExtendedLogging;
    private $useBlocklist;
    private $domainBlacklist = [];
    
    public function __construct(array $config = [], bool $useExtendedLogging = true, bool $useBlocklist = true) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->useExtendedLogging = $useExtendedLogging;
        $this->useBlocklist = $useBlocklist;
        
        // Initialize Extended Logger
        if ($this->useExtendedLogging) {
            $logDir = $config['extendedLogDir'] ?? __DIR__ . '/logs';
            $this->extendedLogger = new ExtendedLogger($logDir);
        }
        
        // Initialize Blocklist Manager
        if ($this->useBlocklist) {
            $blocklistDir = $config['blocklistDir'] ?? __DIR__ . '/data';
            $this->blocklistManager = new BlocklistManager($blocklistDir);
        }
        
        // Load domain blacklist
        $this->loadDomainBlacklist();
    }
    
    private function getDefaultConfig(): array {
        return [
            'honeypotField' => 'website',
            'timestampField' => 'form_timestamp',
            'minSubmitTime' => 3,
            'maxSubmitTime' => 3600,
            'rateLimitWindow' => 3600,
            'rateLimitMax' => 5,
            'requiredFields' => ['email', 'name', 'message'],
            'spamKeywords' => [
                'viagra', 'cialis', 'casino', 'lottery', 'prize',
                'click here', 'buy now', 'limited time', 'act now',
                'congratulations', 'winner', 'claim', 'free money'
            ],
            'suspiciousPatterns' => [
                '/https?:\/\/[^\s]+\.(ru|cn|tk)/i',
                '/\b\d{13,16}\b/',
                '/<script/i',
                '/eval\(/i'
            ],
            'maxLinks' => 3,
            'maxEmailLength' => 254,
            'maxMessageLength' => 5000,
            'blockThreshold' => 30,
            'domainBlacklistFile' => 'domain-blacklist.txt',

            // v2.2.0: Schicht 1 — Verdächtige E-Mail-Prefixe (Localpart vor @)
            // Hard-Block: Offensichtlich nicht-persönliche Adressen → +30
            'suspiciousPrefixesHard' => [
                'spam', 'test', 'fake', 'bot', 'root', 'null',
                'noreply', 'no-reply', 'abuse', 'mailer-daemon',
                'nobody', 'devnull', 'tempmail', 'throwaway'
            ],
            // Soft-Flag: Bei Firmen üblich, aber in Kontaktformularen verdächtig → +10
            'suspiciousPrefixesSoft' => [
                'admin', 'info', 'contact', 'support', 'sales',
                'marketing', 'newsletter', 'office', 'hello',
                'postmaster', 'webmaster', 'hostmaster',
                'administrator', 'sysadmin', 'daemon'
            ],

            // v2.2.0: Schicht 1 — Verdächtige TLDs (Free-Domain-Registrare)
            'suspiciousTLDs' => ['.tk', '.ml', '.cf', '.ga', '.gq'],

            // v2.2.0: Schicht 3 — Disposable E-Mail API (optional)
            'useDisposableAPI' => true,
            'disposableAPIUrl' => 'https://disposable.debounce.io/',
            'disposableAPITimeout' => 2,
        ];
    }
    
    /**
     * Load domain blacklist from file
     */
    private function loadDomainBlacklist(): void {
        $blocklistDir = $this->config['blocklistDir'] ?? __DIR__ . '/data';
        $file = $blocklistDir . '/' . $this->config['domainBlacklistFile'];
        
        if (!file_exists($file)) {
            return;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $this->domainBlacklist[] = strtolower($line);
        }
    }
    
    /**
     * Main validation method
     */
    public function validate(array $formData): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $spamScore = 0;
        $reasons = [];
        $validationDetails = [];
        
        // PRIORITY CHECK: Blocklist
        if ($this->useBlocklist && $this->blocklistManager->isBlocked($ip)) {
            $blockDetails = $this->blocklistManager->getBlockDetails($ip);
            
            $result = [
                'valid' => false,
                'blocked' => true,
                'spamScore' => 100,
                'reason' => 'ip_blocklisted',
                'message' => 'Your IP address has been blocked.',
                'blockDetails' => $blockDetails
            ];
            
            if ($this->useExtendedLogging) {
                $this->extendedLogger->logSubmission([
                    'ip' => $ip,
                    'formData' => $this->sanitizeFormData($formData),
                    'spamScore' => 100,
                    'blocked' => true,
                    'reason' => 'ip_blocklisted',
                    'validationDetails' => ['blockDetails' => $blockDetails]
                ]);
            }
            
            return $result;
        }
        
        // Honeypot check
        if ($this->checkHoneypot($formData)) {
            $spamScore += 50;
            $reasons[] = 'honeypot_filled';
            $validationDetails['honeypot'] = 'triggered';
        }
        
        // Timestamp check
        $timestampCheck = $this->checkTimestamp($formData);
        if ($timestampCheck['score'] > 0) {
            $spamScore += $timestampCheck['score'];
            $reasons[] = $timestampCheck['reason'];
            $validationDetails['timestamp'] = $timestampCheck;
        }
        
        // Rate limiting
        $rateLimitCheck = $this->checkRateLimit($ip);
        if ($rateLimitCheck['exceeded']) {
            $spamScore += 30;
            $reasons[] = 'rate_limit_exceeded';
            $validationDetails['rateLimit'] = $rateLimitCheck;
        }
        
        // Required fields
        $missingFields = $this->checkRequiredFields($formData);
        if (!empty($missingFields)) {
            $spamScore += 20;
            $reasons[] = 'missing_required_fields';
            $validationDetails['missingFields'] = $missingFields;
        }
        
        // Email validation (including domain blacklist)
        $emailCheck = $this->validateEmail($formData['email'] ?? '');
        if (!$emailCheck['valid']) {
            $spamScore += $emailCheck['score'] ?? 25;
            $reasons[] = $emailCheck['reason'];
            $validationDetails['email'] = $emailCheck;
        }
        
        // ================================================================
        // v2.2.0: Schicht 1 — Suspicious Prefix + TLD Checks
        // ================================================================
        $email = $formData['email'] ?? '';
        
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Suspicious prefix check (spam@, test@, fake@ etc.)
            $prefixCheck = $this->checkSuspiciousPrefix($email);
            if ($prefixCheck['score'] > 0) {
                $spamScore += $prefixCheck['score'];
                $reasons[] = $prefixCheck['reason'];
                $validationDetails['suspiciousPrefix'] = $prefixCheck;
            }
            
            // Suspicious TLD check (.tk, .ml, .cf etc.)
            $tldCheck = $this->checkSuspiciousTLD($email);
            if ($tldCheck['score'] > 0) {
                $spamScore += $tldCheck['score'];
                $reasons[] = $tldCheck['reason'];
                $validationDetails['suspiciousTLD'] = $tldCheck;
            }
            
            // Schicht 3: Disposable API check (nur wenn Schicht 1+2 nicht anschlagen)
            if ($spamScore < $this->config['blockThreshold'] && $this->config['useDisposableAPI']) {
                $domain = strtolower(substr(strrchr($email, "@"), 1));
                $apiCheck = $this->checkDisposableAPI($domain);
                if ($apiCheck === true) {
                    $spamScore += 60;
                    $reasons[] = 'disposable_api_confirmed';
                    $validationDetails['disposableAPI'] = [
                        'disposable' => true,
                        'domain' => $domain
                    ];
                }
            }
        }
        
        // Content analysis
        $messageContent = $formData['message'] ?? '';
        
        // Spam keywords
        $keywordMatches = $this->checkSpamKeywords($messageContent);
        if (!empty($keywordMatches)) {
            $spamScore += count($keywordMatches) * 5;
            $reasons[] = 'spam_keywords';
            $validationDetails['spamKeywords'] = $keywordMatches;
        }
        
        // Suspicious patterns
        $patternMatches = $this->checkSuspiciousPatterns($messageContent);
        if (!empty($patternMatches)) {
            $spamScore += count($patternMatches) * 10;
            $reasons[] = 'suspicious_patterns';
            $validationDetails['suspiciousPatterns'] = $patternMatches;
        }
        
        // Link count
        $linkCount = $this->countLinks($messageContent);
        if ($linkCount > $this->config['maxLinks']) {
            $spamScore += ($linkCount - $this->config['maxLinks']) * 5;
            $reasons[] = 'excessive_links';
            $validationDetails['linkCount'] = $linkCount;
        }
        
        // Message length
        if (strlen($messageContent) > $this->config['maxMessageLength']) {
            $spamScore += 10;
            $reasons[] = 'message_too_long';
            $validationDetails['messageLength'] = strlen($messageContent);
        }
        
        // Determine if blocked
        $blocked = $spamScore >= $this->config['blockThreshold'];
        
        $result = [
            'valid' => !$blocked,
            'blocked' => $blocked,
            'spamScore' => $spamScore,
            'reasons' => $reasons,
            'validationDetails' => $validationDetails,
            'message' => $blocked ? 'Submission blocked due to spam indicators' : 'Validation passed'
        ];
        
        // Extended logging
        if ($this->useExtendedLogging) {
            $this->extendedLogger->logSubmission([
                'ip' => $ip,
                'formData' => $this->sanitizeFormData($formData),
                'spamScore' => $spamScore,
                'blocked' => $blocked,
                'reason' => $blocked ? implode(', ', $reasons) : null,
                'validationDetails' => $validationDetails
            ]);
        }
        
        return $result;
    }
    
    /**
     * Sanitize form data for logging
     */
    private function sanitizeFormData(array $formData): array {
        $sanitized = [];
        
        foreach ($formData as $key => $value) {
            if (in_array($key, ['email', 'name', 'subject'])) {
                $sanitized[$key] = $value;
            } elseif ($key === 'message') {
                $sanitized[$key] = strlen($value) > 200 
                    ? substr($value, 0, 200) . '...' 
                    : $value;
            }
        }
        
        return $sanitized;
    }
    
    private function checkHoneypot(array $formData): bool {
        $honeypotField = $this->config['honeypotField'];
        return !empty($formData[$honeypotField]);
    }
    
    private function checkTimestamp(array $formData): array {
        $timestampField = $this->config['timestampField'];
        
        if (empty($formData[$timestampField])) {
            return ['score' => 15, 'reason' => 'missing_timestamp'];
        }
        
        $submitTime = time() - (int)$formData[$timestampField];
        
        if ($submitTime < $this->config['minSubmitTime']) {
            return ['score' => 40, 'reason' => 'submitted_too_fast', 'time' => $submitTime];
        }
        
        if ($submitTime > $this->config['maxSubmitTime']) {
            return ['score' => 10, 'reason' => 'submitted_too_slow', 'time' => $submitTime];
        }
        
        return ['score' => 0, 'time' => $submitTime];
    }
    
    private function checkRateLimit(string $ip): array {
        if (!$this->useExtendedLogging) {
            return ['exceeded' => false];
        }
        
        $recentSubmissions = $this->extendedLogger->getSubmissionsByIP(
            $ip, 
            $this->config['rateLimitWindow'] / 86400
        );
        
        $count = count($recentSubmissions);
        $exceeded = $count >= $this->config['rateLimitMax'];
        
        return [
            'exceeded' => $exceeded,
            'count' => $count,
            'limit' => $this->config['rateLimitMax'],
            'window' => $this->config['rateLimitWindow']
        ];
    }
    
    private function checkRequiredFields(array $formData): array {
        $missing = [];
        
        foreach ($this->config['requiredFields'] as $field) {
            if (empty($formData[$field])) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Validate email with domain blacklist support
     * v2.2.0: Score von 50 auf 60 erhöht (sicheres Blocking über Threshold 30)
     */
    private function validateEmail(string $email): array {
        if (empty($email)) {
            return ['valid' => false, 'reason' => 'empty', 'score' => 25];
        }
        
        if (strlen($email) > $this->config['maxEmailLength']) {
            return ['valid' => false, 'reason' => 'too_long', 'score' => 25];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'reason' => 'invalid_format', 'score' => 25];
        }
        
        // Extract domain
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        // Check domain blacklist (Schicht 2)
        if (in_array($domain, $this->domainBlacklist)) {
            return [
                'valid' => false, 
                'reason' => 'blocked_domain', 
                'score' => 60,
                'domain' => $domain
            ];
        }
        
        return ['valid' => true];
    }
    
    // ====================================================================
    // v2.2.0: Neue Check-Methoden (Schicht 1 + 3)
    // ====================================================================
    
    /**
     * Schicht 1: Check for suspicious email prefixes (localpart before @)
     * 
     * Zwei Gruppen:
     * - Hard-Block (spam@, test@, fake@, bot@ etc.) → +30
     * - Soft-Flag  (admin@, info@, contact@ etc.)   → +10
     * 
     * @param string $email Full email address
     * @return array ['score' => int, 'reason' => string, 'prefix' => string, 'group' => string]
     */
    private function checkSuspiciousPrefix(string $email): array {
        $localpart = strtolower(strstr($email, '@', true));
        
        if ($localpart === false || $localpart === '') {
            return ['score' => 0];
        }
        
        // Hard-Block: Offensichtlich nicht-persönliche Adressen
        foreach ($this->config['suspiciousPrefixesHard'] as $prefix) {
            if ($localpart === strtolower($prefix)) {
                return [
                    'score' => 30,
                    'reason' => 'suspicious_prefix_hard',
                    'prefix' => $localpart,
                    'group' => 'hard'
                ];
            }
        }
        
        // Soft-Flag: Bei Firmen üblich, bei Kontaktformularen leicht verdächtig
        foreach ($this->config['suspiciousPrefixesSoft'] as $prefix) {
            if ($localpart === strtolower($prefix)) {
                return [
                    'score' => 10,
                    'reason' => 'suspicious_prefix_soft',
                    'prefix' => $localpart,
                    'group' => 'soft'
                ];
            }
        }
        
        return ['score' => 0];
    }
    
    /**
     * Schicht 1: Check for suspicious TLDs (free domain registrars)
     * 
     * .tk, .ml, .cf, .ga, .gq werden massenhaft für Wegwerf-Domains missbraucht.
     * Score: +15 (erhöht Gesamtscore, allein nicht blockierend)
     * 
     * @param string $email Full email address
     * @return array ['score' => int, 'reason' => string, 'tld' => string]
     */
    private function checkSuspiciousTLD(string $email): array {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        if (empty($domain)) {
            return ['score' => 0];
        }
        
        // Letzte TLD extrahieren
        $lastDot = strrpos($domain, '.');
        if ($lastDot === false) {
            return ['score' => 0];
        }
        $tld = substr($domain, $lastDot); // z.B. ".tk"
        
        if (in_array($tld, $this->config['suspiciousTLDs'])) {
            return [
                'score' => 15,
                'reason' => 'suspicious_tld',
                'tld' => $tld,
                'domain' => $domain
            ];
        }
        
        return ['score' => 0];
    }
    
    /**
     * Schicht 3: Check domain against DeBounce Disposable Email API
     * 
     * Kostenlose API, kein Key nötig, <50ms Latenz.
     * Wird nur aufgerufen wenn Schicht 1+2 nicht anschlagen.
     * Bei Timeout/Fehler: null → Fallback auf lokale Liste.
     * 
     * API: GET https://disposable.debounce.io/?email=check@domain
     * Response: {"disposable":"true"} oder {"disposable":"false"}
     * 
     * @param string $domain Email domain to check
     * @return bool|null true=disposable, false=not, null=API error/timeout
     */
    private function checkDisposableAPI(string $domain): ?bool {
        $apiUrl = $this->config['disposableAPIUrl'];
        $timeout = $this->config['disposableAPITimeout'];
        
        if (empty($apiUrl)) {
            return null;
        }
        
        $url = $apiUrl . '?email=check@' . urlencode($domain);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => "User-Agent: jozapf.de-contact-form/2.2\r\n" .
                            "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log('[ContactFormValidator] Disposable API unreachable: ' . $url);
            return null;
        }
        
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['disposable'])) {
            error_log('[ContactFormValidator] Disposable API invalid response: ' . $response);
            return null;
        }
        
        // DeBounce gibt "true"/"false" als String zurück
        return $data['disposable'] === 'true' || $data['disposable'] === true;
    }
    
    // ====================================================================
    // Bestehende Methoden (unverändert)
    // ====================================================================
    
    private function checkSpamKeywords(string $text): array {
        $matches = [];
        $lowerText = strtolower($text);
        
        foreach ($this->config['spamKeywords'] as $keyword) {
            if (stripos($lowerText, strtolower($keyword)) !== false) {
                $matches[] = $keyword;
            }
        }
        
        return $matches;
    }
    
    private function checkSuspiciousPatterns(string $text): array {
        $matches = [];
        
        foreach ($this->config['suspiciousPatterns'] as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches[] = $pattern;
            }
        }
        
        return $matches;
    }
    
    private function countLinks(string $text): int {
        return preg_match_all('/https?:\/\/[^\s]+/', $text);
    }
    
    /**
     * Get Extended Logger instance
     */
    public function getExtendedLogger(): ?ExtendedLogger {
        return $this->extendedLogger;
    }
    
    /**
     * Get Blocklist Manager instance
     */
    public function getBlocklistManager(): ?BlocklistManager {
        return $this->blocklistManager;
    }
    
    /**
     * Get loaded domain blacklist
     */
    public function getDomainBlacklist(): array {
        return $this->domainBlacklist;
    }
}
