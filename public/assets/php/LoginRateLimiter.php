<?php
/**
 * Login Rate Limiter — dateibasiert, kein DB nötig.
 * Schützt Dashboard-Login vor Brute-Force-Angriffen (HF-02 FIX).
 * 
 * Funktionsweise:
 * - Speichert fehlgeschlagene Login-Versuche pro IP in einer JSON-Datei
 * - Nach 5 Fehlversuchen innerhalb von 15 Minuten wird die IP gesperrt
 * - Alte Einträge werden bei jedem Speichern automatisch bereinigt
 * 
 * @version 1.0.0
 * @date 2026-03-24
 */

class LoginRateLimiter {
    private string $dataFile;
    private int $maxAttempts;
    private int $windowSeconds;

    /**
     * @param string $dataDir  Verzeichnis für die Sperrdatei (z.B. __DIR__ . '/data')
     * @param int $maxAttempts  Max. erlaubte Fehlversuche im Zeitfenster (Default: 5)
     * @param int $windowSeconds  Zeitfenster in Sekunden (Default: 900 = 15 Minuten)
     */
    public function __construct(string $dataDir, int $maxAttempts = 5, int $windowSeconds = 900) {
        $this->dataFile = rtrim($dataDir, '/') . '/login_attempts.json';
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Prüft ob eine IP gesperrt ist (zu viele Fehlversuche im Zeitfenster).
     */
    public function isLocked(string $ip): bool {
        return count($this->getRecentAttempts($ip)) >= $this->maxAttempts;
    }

    /**
     * Zeichnet einen fehlgeschlagenen Login-Versuch auf.
     */
    public function recordFailedAttempt(string $ip): void {
        $data = $this->loadData();
        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }
        $data[$ip][] = time();
        $this->saveData($data);
    }

    /**
     * Gibt die verbleibende Sperrzeit in Sekunden zurück.
     * 0 = nicht gesperrt.
     */
    public function getRemainingLockTime(string $ip): int {
        $attempts = $this->getRecentAttempts($ip);
        if (count($attempts) < $this->maxAttempts) return 0;
        
        $oldestRelevant = min($attempts);
        return max(0, ($oldestRelevant + $this->windowSeconds) - time());
    }

    /**
     * Gibt die Anzahl der aktuellen Fehlversuche im Zeitfenster zurück.
     */
    public function getAttemptCount(string $ip): int {
        return count($this->getRecentAttempts($ip));
    }

    /**
     * Fehlversuche für eine IP manuell zurücksetzen (z.B. nach erfolgreichem Login).
     */
    public function resetAttempts(string $ip): void {
        $data = $this->loadData();
        unset($data[$ip]);
        $this->saveData($data);
    }

    /**
     * Gibt nur die Fehlversuche innerhalb des aktiven Zeitfensters zurück.
     */
    private function getRecentAttempts(string $ip): array {
        $data = $this->loadData();
        $cutoff = time() - $this->windowSeconds;
        return array_values(array_filter($data[$ip] ?? [], fn($t) => $t > $cutoff));
    }

    private function loadData(): array {
        if (!file_exists($this->dataFile)) return [];
        $content = @file_get_contents($this->dataFile);
        if ($content === false) return [];
        return json_decode($content, true) ?? [];
    }

    /**
     * Speichert und bereinigt gleichzeitig — alte Einträge werden entfernt.
     */
    private function saveData(array $data): void {
        $cutoff = time() - $this->windowSeconds;
        foreach ($data as $ip => &$timestamps) {
            $timestamps = array_values(array_filter($timestamps, fn($t) => $t > $cutoff));
            if (empty($timestamps)) unset($data[$ip]);
        }
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
