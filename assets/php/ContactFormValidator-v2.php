<?php
/**
 * Contact Form Validator with Extended Logging and Blocklist Support
 * 
 * Features:
 * - All original validation rules
 * - Blocklist/Whitelist checking
 * - Extended GDPR-compliant logging
 * - Integration with ExtendedLogger and BlocklistManager
 * 
 * @author JoZapf
 * @version 2.0.0
 */

require_once __DIR__ . '/ExtendedLogger.php';
require_once __DIR__ . '/BlocklistManager.php';

class ContactFormValidator {
    private $config;
    private $extendedLogger;
    private $blocklistManager;
    private $useExtendedLogging;
    private $useBlocklist;
    
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
                '/https?:\/\/[^\s]+\.(ru|cn|tk)/i', // Suspicious TLDs
                '/\b\d{13,16}\b/', // Credit card numbers
                '/<script/i', // Script tags
                '/eval\(/i' // Eval functions
            ],
            'maxLinks' => 3,
            'maxEmailLength' => 254,
            'maxMessageLength' => 5000,
            'blockThreshold' => 30
        ];
    }
    
    /**
     * Main validation method
     */
    public function validate(array $formData): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $spamScore = 0;
        $reasons = [];
        $validationDetails = [];
        
        // PRIORITY CHECK: Blocklist (before any other validation)
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
            
            // Log blocked attempt
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
        
        // Email validation
        $emailCheck = $this->validateEmail($formData['email'] ?? '');
        if (!$emailCheck['valid']) {
            $spamScore += 25;
            $reasons[] = 'invalid_email';
            $validationDetails['email'] = $emailCheck;
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
     * Sanitize form data for logging (remove sensitive info)
     */
    private function sanitizeFormData(array $formData): array {
        $sanitized = [];
        
        foreach ($formData as $key => $value) {
            // Keep non-sensitive fields
            if (in_array($key, ['email', 'name', 'subject'])) {
                $sanitized[$key] = $value;
            } elseif ($key === 'message') {
                // Truncate long messages
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
        // Use ExtendedLogger to get recent submissions from this IP
        if (!$this->useExtendedLogging) {
            return ['exceeded' => false];
        }
        
        $recentSubmissions = $this->extendedLogger->getSubmissionsByIP(
            $ip, 
            $this->config['rateLimitWindow'] / 86400 // Convert seconds to days
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
    
    private function validateEmail(string $email): array {
        if (empty($email)) {
            return ['valid' => false, 'reason' => 'empty'];
        }
        
        if (strlen($email) > $this->config['maxEmailLength']) {
            return ['valid' => false, 'reason' => 'too_long'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'reason' => 'invalid_format'];
        }
        
        // Check for disposable email domains (basic check)
        $disposableDomains = ['tempmail.com', 'guerrillamail.com', '10minutemail.com'];
        $domain = substr(strrchr($email, "@"), 1);
        
        if (in_array($domain, $disposableDomains)) {
            return ['valid' => false, 'reason' => 'disposable_email'];
        }
        
        return ['valid' => true];
    }
    
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
}
