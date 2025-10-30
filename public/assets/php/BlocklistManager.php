<?php
/**
 * Blocklist Manager
 * 
 * Manages IP blocklist and whitelist with expiration dates and notes.
 * Integrates with ExtendedLogger for quick blocking from dashboard.
 * 
 * Features:
 * - Add/remove IPs from blocklist
 * - Whitelist for trusted IPs
 * - Expiration dates for temporary blocks
 * - Notes/reasons for each block
 * - Subnet blocking support (192.168.1.0/24)
 * 
 * @author JoZapf
 * @version 1.0.0
 */

class BlocklistManager {
    private $dataDir;
    private $blocklistFile = 'blocklist.json';
    private $whitelistFile = 'whitelist.json';
    
    public function __construct(string $dataDir) {
        $this->dataDir = rtrim($dataDir, '/');
        
        // Ensure data directory exists
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        // Initialize files if they don't exist
        $this->initializeFiles();
        
        // Clean expired blocks on init
        $this->cleanExpiredBlocks();
    }
    
    /**
     * Initialize blocklist and whitelist files
     */
    private function initializeFiles(): void {
        $blocklistPath = $this->dataDir . '/' . $this->blocklistFile;
        $whitelistPath = $this->dataDir . '/' . $this->whitelistFile;
        
        if (!file_exists($blocklistPath)) {
            file_put_contents($blocklistPath, json_encode([], JSON_PRETTY_PRINT));
        }
        
        if (!file_exists($whitelistPath)) {
            file_put_contents($whitelistPath, json_encode([], JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Check if an IP is blocked
     */
    public function isBlocked(string $ip): bool {
        // Whitelist takes precedence
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        $blocklist = $this->getBlocklist();
        
        foreach ($blocklist as $entry) {
            // Skip expired blocks
            if (isset($entry['expiresAt']) && strtotime($entry['expiresAt']) < time()) {
                continue;
            }
            
            // Exact IP match
            if ($entry['ip'] === $ip) {
                return true;
            }
            
            // Subnet match (if CIDR notation)
            if (strpos($entry['ip'], '/') !== false) {
                if ($this->ipInSubnet($ip, $entry['ip'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if an IP is whitelisted
     */
    public function isWhitelisted(string $ip): bool {
        $whitelist = $this->getWhitelist();
        
        foreach ($whitelist as $entry) {
            if ($entry['ip'] === $ip) {
                return true;
            }
            
            // Subnet match
            if (strpos($entry['ip'], '/') !== false) {
                if ($this->ipInSubnet($ip, $entry['ip'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add IP to blocklist
     * 
     * @param string $ip IP address or CIDR subnet (192.168.1.0/24)
     * @param string|null $reason Reason for blocking
     * @param string|null $expiresAt ISO 8601 date (null = permanent)
     * @param array $metadata Additional metadata (e.g., spam score, user agent)
     */
    public function addToBlocklist(
        string $ip, 
        ?string $reason = null, 
        ?string $expiresAt = null,
        array $metadata = []
    ): bool {
        // Validate IP
        if (!$this->validateIP($ip)) {
            throw new InvalidArgumentException("Invalid IP address or CIDR: $ip");
        }
        
        // Don't block if whitelisted
        if ($this->isWhitelisted($ip)) {
            return false;
        }
        
        $blocklist = $this->getBlocklist();
        
        // Check if already blocked
        foreach ($blocklist as $entry) {
            if ($entry['ip'] === $ip) {
                return false; // Already blocked
            }
        }
        
        // Add to blocklist
        $blocklist[] = [
            'ip' => $ip,
            'reason' => $reason,
            'addedAt' => date('c'),
            'expiresAt' => $expiresAt,
            'metadata' => $metadata
        ];
        
        return $this->saveBlocklist($blocklist);
    }
    
    /**
     * Remove IP from blocklist
     */
    public function removeFromBlocklist(string $ip): bool {
        $blocklist = $this->getBlocklist();
        $newBlocklist = array_filter($blocklist, fn($entry) => $entry['ip'] !== $ip);
        
        if (count($newBlocklist) === count($blocklist)) {
            return false; // IP not found
        }
        
        return $this->saveBlocklist(array_values($newBlocklist));
    }
    
    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip, ?string $note = null): bool {
        if (!$this->validateIP($ip)) {
            throw new InvalidArgumentException("Invalid IP address or CIDR: $ip");
        }
        
        $whitelist = $this->getWhitelist();
        
        // Check if already whitelisted
        foreach ($whitelist as $entry) {
            if ($entry['ip'] === $ip) {
                return false;
            }
        }
        
        $whitelist[] = [
            'ip' => $ip,
            'note' => $note,
            'addedAt' => date('c')
        ];
        
        return $this->saveWhitelist($whitelist);
    }
    
    /**
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(string $ip): bool {
        $whitelist = $this->getWhitelist();
        $newWhitelist = array_filter($whitelist, fn($entry) => $entry['ip'] !== $ip);
        
        if (count($newWhitelist) === count($whitelist)) {
            return false;
        }
        
        return $this->saveWhitelist(array_values($newWhitelist));
    }
    
    /**
     * Get all blocklist entries
     */
    public function getBlocklist(): array {
        $file = $this->dataDir . '/' . $this->blocklistFile;
        $content = file_get_contents($file);
        return json_decode($content, true) ?? [];
    }
    
    /**
     * Get all whitelist entries
     */
    public function getWhitelist(): array {
        $file = $this->dataDir . '/' . $this->whitelistFile;
        $content = file_get_contents($file);
        return json_decode($content, true) ?? [];
    }
    
    /**
     * Get blocklist entry details for specific IP
     */
    public function getBlockDetails(string $ip): ?array {
        $blocklist = $this->getBlocklist();
        
        foreach ($blocklist as $entry) {
            if ($entry['ip'] === $ip) {
                return $entry;
            }
        }
        
        return null;
    }
    
    /**
     * Clean expired blocks
     */
    public function cleanExpiredBlocks(): int {
        $blocklist = $this->getBlocklist();
        $cleaned = 0;
        
        $newBlocklist = array_filter($blocklist, function($entry) use (&$cleaned) {
            if (isset($entry['expiresAt']) && strtotime($entry['expiresAt']) < time()) {
                $cleaned++;
                return false; // Remove expired
            }
            return true; // Keep
        });
        
        if ($cleaned > 0) {
            $this->saveBlocklist(array_values($newBlocklist));
        }
        
        return $cleaned;
    }
    
    /**
     * Save blocklist to file
     */
    private function saveBlocklist(array $blocklist): bool {
        $file = $this->dataDir . '/' . $this->blocklistFile;
        $json = json_encode($blocklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $json, LOCK_EX) !== false;
    }
    
    /**
     * Save whitelist to file
     */
    private function saveWhitelist(array $whitelist): bool {
        $file = $this->dataDir . '/' . $this->whitelistFile;
        $json = json_encode($whitelist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $json, LOCK_EX) !== false;
    }
    
    /**
     * Validate IP address or CIDR notation
     */
    private function validateIP(string $ip): bool {
        // Check for CIDR notation
        if (strpos($ip, '/') !== false) {
            [$address, $mask] = explode('/', $ip, 2);
            
            // Validate address
            if (!filter_var($address, FILTER_VALIDATE_IP)) {
                return false;
            }
            
            // Validate mask
            $mask = (int)$mask;
            if ($mask < 0 || $mask > 32) {
                return false;
            }
            
            return true;
        }
        
        // Simple IP validation
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Check if IP is in subnet (CIDR notation)
     * Example: ipInSubnet('192.168.1.100', '192.168.1.0/24') -> true
     */
    private function ipInSubnet(string $ip, string $subnet): bool {
        if (strpos($subnet, '/') === false) {
            return $ip === $subnet;
        }
        
        [$subnetIP, $mask] = explode('/', $subnet, 2);
        $mask = (int)$mask;
        
        // Convert IPs to long
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnetIP);
        
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        
        // Calculate subnet mask
        $maskLong = -1 << (32 - $mask);
        
        // Check if IP is in subnet
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
    
    /**
     * Get statistics
     */
    public function getStats(): array {
        $blocklist = $this->getBlocklist();
        $whitelist = $this->getWhitelist();
        
        $activeBlocks = 0;
        $expiredBlocks = 0;
        $permanentBlocks = 0;
        
        foreach ($blocklist as $entry) {
            if (!isset($entry['expiresAt'])) {
                $permanentBlocks++;
                $activeBlocks++;
            } elseif (strtotime($entry['expiresAt']) < time()) {
                $expiredBlocks++;
            } else {
                $activeBlocks++;
            }
        }
        
        return [
            'totalBlocked' => count($blocklist),
            'activeBlocks' => $activeBlocks,
            'expiredBlocks' => $expiredBlocks,
            'permanentBlocks' => $permanentBlocks,
            'whitelisted' => count($whitelist)
        ];
    }
    
    /**
     * Import IPs from array (bulk add)
     */
    public function bulkAddToBlocklist(array $ips, ?string $reason = null): int {
        $added = 0;
        
        foreach ($ips as $ip) {
            try {
                if ($this->addToBlocklist($ip, $reason)) {
                    $added++;
                }
            } catch (InvalidArgumentException $e) {
                // Skip invalid IPs
                continue;
            }
        }
        
        return $added;
    }
    
    /**
     * Export blocklist for backup or migration
     */
    public function exportBlocklist(): string {
        return json_encode([
            'blocklist' => $this->getBlocklist(),
            'whitelist' => $this->getWhitelist(),
            'exportedAt' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Import blocklist from backup
     */
    public function importBlocklist(string $json): bool {
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['blocklist'], $data['whitelist'])) {
            return false;
        }
        
        $this->saveBlocklist($data['blocklist']);
        $this->saveWhitelist($data['whitelist']);
        
        return true;
    }
}
