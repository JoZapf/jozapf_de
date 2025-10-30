<?php
/**
 * Dashboard Blocklist Management
 * Protected with HMAC token
 */

// Token verification
function env($key, $default = null) {
    $envFile = __DIR__ . '/.env.prod';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                [$k, $v] = explode('=', trim($line), 2);
                if (trim($k) === $key) return trim($v, '"\'');
            }
        }
    }
    return $default;
}

function verifyToken($token, $secret) {
    if (empty($token) || strpos($token, '.') === false) return false;
    [$payload, $signature] = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) return false;
    $data = json_decode(base64_decode($payload), true);
    return $data && isset($data['exp']) && $data['exp'] >= time();
}

$secret = env('DASHBOARD_SECRET');
$token = $_COOKIE['dashboard_token'] ?? '';

if (!verifyToken($token, $secret)) {
    header('Location: dashboard-login.php');
    exit;
}

// Load managers
require_once __DIR__ . '/ExtendedLogger.php';
require_once __DIR__ . '/BlocklistManager.php';

$logger = new ExtendedLogger(__DIR__ . '/logs');
$blocklist = new BlocklistManager(__DIR__ . '/data');

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'block_ip':
                $ip = $_POST['ip'] ?? '';
                $reason = $_POST['reason'] ?? null;
                $duration = $_POST['duration'] ?? 'permanent';
                
                $expiresAt = null;
                if ($duration !== 'permanent') {
                    $days = (int)$duration;
                    $expiresAt = date('c', strtotime("+{$days} days"));
                }
                
                $metadata = [
                    'blockedBy' => 'dashboard',
                    'userAgent' => $_POST['userAgent'] ?? null
                ];
                
                if ($blocklist->addToBlocklist($ip, $reason, $expiresAt, $metadata)) {
                    $message = "IP {$ip} successfully blocked";
                    $messageType = 'success';
                } else {
                    $message = "IP {$ip} is already blocked or whitelisted";
                    $messageType = 'warning';
                }
                break;
                
            case 'unblock_ip':
                $ip = $_POST['ip'] ?? '';
                if ($blocklist->removeFromBlocklist($ip)) {
                    $message = "IP {$ip} successfully unblocked";
                    $messageType = 'success';
                } else {
                    $message = "IP {$ip} not found in blocklist";
                    $messageType = 'warning';
                }
                break;
                
            case 'whitelist_ip':
                $ip = $_POST['ip'] ?? '';
                $note = $_POST['note'] ?? null;
                if ($blocklist->addToWhitelist($ip, $note)) {
                    $message = "IP {$ip} successfully whitelisted";
                    $messageType = 'success';
                } else {
                    $message = "IP {$ip} is already whitelisted";
                    $messageType = 'warning';
                }
                break;
                
            case 'remove_whitelist':
                $ip = $_POST['ip'] ?? '';
                if ($blocklist->removeFromWhitelist($ip)) {
                    $message = "IP {$ip} removed from whitelist";
                    $messageType = 'success';
                } else {
                    $message = "IP {$ip} not found in whitelist";
                    $messageType = 'warning';
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get data for display
$recentSubmissions = $logger->getRecentSubmissions(50, true); // Only non-anonymized
$blockedIPs = $blocklist->getBlocklist();
$whitelistedIPs = $blocklist->getWhitelist();
$stats = $blocklist->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocklist Management</title>
    <link rel="stylesheet" href="../css/contact-form.css">
    <style>
        body {
            background: #0d1117;
            color: #e9ecef;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        header {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        h1 { margin: 0 0 10px; color: #fff; }
        .subtitle { color: #8b949e; }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-bottom: 2px solid var(--cf-card-border);
        }
        
        .nav-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            color: #8b949e;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        
        .nav-tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            padding: 20px;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #8b949e;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .card {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            margin: 0 0 20px;
            color: #fff;
            font-size: 1.3em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--cf-card-border);
        }
        
        th {
            background: rgba(255,255,255,0.05);
            color: #8b949e;
            font-weight: 600;
            font-size: 0.85em;
            text-transform: uppercase;
        }
        
        tr:hover { background: rgba(255,255,255,0.03); }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-small { padding: 5px 10px; font-size: 0.85em; }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-danger { background: #e74c3c; color: white; }
        .badge-success { background: #27ae60; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .message-error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        
        .message-warning {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid #f39c12;
            color: #f39c12;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #8b949e;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            background: var(--cf-input-bg);
            border: 1px solid var(--cf-input-border);
            border-radius: 6px;
            color: #fff;
            box-sizing: border-box;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 12px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #8b949e;
            font-size: 1.5em;
            cursor: pointer;
        }
        
        .ip-address { font-family: monospace; color: #3498db; }
        .timestamp { color: #8b949e; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Blocklist Management</h1>
            <p class="subtitle">Manage IP blocklist and whitelist • GDPR-compliant with 14-day retention</p>
            
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchTab('recent')">Recent Submissions</button>
                <button class="nav-tab" onclick="switchTab('blocklist')">Blocklist</button>
                <button class="nav-tab" onclick="switchTab('whitelist')">Whitelist</button>
                <button class="nav-tab" onclick="switchTab('stats')">Statistics</button>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="message message-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Submissions Tab -->
        <div id="tab-recent" class="tab-content active">
            <div class="card">
                <h2>Recent Submissions (Last 50, Non-Anonymized)</h2>
                <p style="color: #8b949e; margin-bottom: 20px;">
                    IPs are automatically anonymized after 14 days per GDPR requirements
                </p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Email</th>
                            <th>Spam Score</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSubmissions as $sub): ?>
                            <tr>
                                <td class="timestamp"><?= date('Y-m-d H:i:s', strtotime($sub['timestamp'])) ?></td>
                                <td class="ip-address"><?= htmlspecialchars($sub['ip']) ?></td>
                                <td><?= htmlspecialchars($sub['formData']['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $sub['spamScore'] >= 30 ? 'badge-danger' : ($sub['spamScore'] >= 15 ? 'badge-warning' : 'badge-success') ?>">
                                        <?= $sub['spamScore'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sub['blocked']): ?>
                                        <span class="badge badge-danger">Blocked</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Allowed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$blocklist->isBlocked($sub['ip'])): ?>
                                        <button class="btn btn-danger btn-small" onclick="showBlockModal('<?= htmlspecialchars($sub['ip']) ?>', '<?= htmlspecialchars($sub['userAgent'] ?? '') ?>')">
                                            Block IP
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #8b949e;">Blocked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentSubmissions)): ?>
                            <tr><td colspan="6" style="text-align: center; color: #8b949e;">No recent submissions</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Blocklist Tab -->
        <div id="tab-blocklist" class="tab-content">
            <div class="card">
                <h2>Blocked IP Addresses (<?= count($blockedIPs) ?>)</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Reason</th>
                            <th>Added</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blockedIPs as $entry): ?>
                            <?php 
                            $expired = isset($entry['expiresAt']) && strtotime($entry['expiresAt']) < time();
                            ?>
                            <tr style="<?= $expired ? 'opacity: 0.5;' : '' ?>">
                                <td class="ip-address"><?= htmlspecialchars($entry['ip']) ?></td>
                                <td><?= htmlspecialchars($entry['reason'] ?? 'No reason specified') ?></td>
                                <td class="timestamp"><?= date('Y-m-d H:i', strtotime($entry['addedAt'])) ?></td>
                                <td>
                                    <?php if (isset($entry['expiresAt'])): ?>
                                        <?php if ($expired): ?>
                                            <span class="badge badge-warning">Expired</span>
                                        <?php else: ?>
                                            <?= date('Y-m-d H:i', strtotime($entry['expiresAt'])) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Permanent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="unblock_ip">
                                        <input type="hidden" name="ip" value="<?= htmlspecialchars($entry['ip']) ?>">
                                        <button type="submit" class="btn btn-success btn-small" onclick="return confirm('Unblock this IP?')">
                                            Unblock
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($blockedIPs)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #8b949e;">No blocked IPs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Whitelist Tab -->
        <div id="tab-whitelist" class="tab-content">
            <div class="card">
                <h2>Whitelisted IP Addresses (<?= count($whitelistedIPs) ?>)</h2>
                
                <button class="btn btn-primary" onclick="showWhitelistModal()" style="margin-bottom: 20px;">
                    Add to Whitelist
                </button>
                
                <table>
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Note</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($whitelistedIPs as $entry): ?>
                            <tr>
                                <td class="ip-address"><?= htmlspecialchars($entry['ip']) ?></td>
                                <td><?= htmlspecialchars($entry['note'] ?? 'No note') ?></td>
                                <td class="timestamp"><?= date('Y-m-d H:i', strtotime($entry['addedAt'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_whitelist">
                                        <input type="hidden" name="ip" value="<?= htmlspecialchars($entry['ip']) ?>">
                                        <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Remove from whitelist?')">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($whitelistedIPs)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #8b949e;">No whitelisted IPs</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Statistics Tab -->
        <div id="tab-stats" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Blocked</div>
                    <div class="stat-value"><?= $stats['totalBlocked'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Blocks</div>
                    <div class="stat-value"><?= $stats['activeBlocks'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Permanent Blocks</div>
                    <div class="stat-value"><?= $stats['permanentBlocks'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Whitelisted</div>
                    <div class="stat-value"><?= $stats['whitelisted'] ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Block IP Modal -->
    <div id="blockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Block IP Address</h2>
                <button class="modal-close" onclick="hideBlockModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="block_ip">
                <input type="hidden" name="ip" id="blockIP">
                <input type="hidden" name="userAgent" id="blockUserAgent">
                
                <div class="form-group">
                    <label>IP Address</label>
                    <input type="text" id="blockIPDisplay" readonly>
                </div>
                
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="3" placeholder="Why is this IP being blocked?"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Duration</label>
                    <select name="duration">
                        <option value="1">1 day</option>
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                        <option value="permanent">Permanent</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-danger" style="width: 100%;">Block IP</button>
            </form>
        </div>
    </div>
    
    <!-- Whitelist Modal -->
    <div id="whitelistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add to Whitelist</h2>
                <button class="modal-close" onclick="hideWhitelistModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="whitelist_ip">
                
                <div class="form-group">
                    <label>IP Address or CIDR</label>
                    <input type="text" name="ip" placeholder="192.168.1.100 or 192.168.1.0/24" required>
                </div>
                
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note" rows="2" placeholder="Why is this IP trusted?"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%;">Add to Whitelist</button>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
        }
        
        function showBlockModal(ip, userAgent) {
            document.getElementById('blockIP').value = ip;
            document.getElementById('blockIPDisplay').value = ip;
            document.getElementById('blockUserAgent').value = userAgent;
            document.getElementById('blockModal').style.display = 'block';
        }
        
        function hideBlockModal() {
            document.getElementById('blockModal').style.display = 'none';
        }
        
        function showWhitelistModal() {
            document.getElementById('whitelistModal').style.display = 'block';
        }
        
        function hideWhitelistModal() {
            document.getElementById('whitelistModal').style.display = 'none';
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
