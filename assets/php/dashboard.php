<?php
/**
 * Unified Dashboard - Statistics + Blocklist Management
 * Protected with HMAC token
 * @version 2025.10.12
 */

// Token verification & robust env loader
function env($key, $default = null) {
    // 1) prefer process environment (getenv / $_ENV)
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];

    // 2) fallback candidates (support .app.env in webroot, assets/php, legacy .env.prod)
    $candidates = [
        __DIR__ . '/.env.prod',
        __DIR__ . '/.app.env',
        __DIR__ . '/app.env',
        dirname(__DIR__) . '/.app.env',       // project webroot
        dirname(__DIR__) . '/app.env',
        dirname(__DIR__) . '/.env',
    ];

    foreach ($candidates as $envFile) {
        if (!file_exists($envFile)) continue;
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k, $val] = explode('=', $line, 2);
            if (trim($k) === $key) return trim($val, " \t\n\r\0\x0B\"'");
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

// Get data
$recentSubmissions = $logger->getRecentSubmissions(50, true);
$blockedIPs = $blocklist->getBlocklist();
$whitelistedIPs = $blocklist->getWhitelist();
$blockStats = $blocklist->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Dashboard</title>
    <script src="../js/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0d1117;
            color: #e9ecef;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        header {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid rgba(48, 54, 61, 0.8);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        h1 { color: #fff; font-size: 2em; margin-bottom: 10px; }
        .subtitle { color: #8b949e; font-size: 0.9em; }
        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-bottom: 2px solid rgba(48, 54, 61, 0.8);
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
            font-size: 0.95em;
        }
        
        .nav-tab:hover { color: #c9d1d9; }
        .nav-tab.active { color: #3498db; border-bottom-color: #3498db; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid rgba(48, 54, 61, 0.8);
            padding: 25px;
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #8b949e;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value.success { color: #27ae60; }
        .stat-value.danger { color: #e74c3c; }
        .stat-value.warning { color: #f39c12; }
        .stat-value.info { color: #3498db; }
        
        .card {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid rgba(48, 54, 61, 0.8);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(48, 54, 61, 0.8);
        }
        
        th {
            background: rgba(255,255,255,0.05);
            color: #8b949e;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        tr:hover { background: rgba(255,255,255,0.03); }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-small { padding: 5px 10px; font-size: 0.85em; }
        
        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
            float: right;
        }
        
        .refresh-btn:hover { background: #2980b9; }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #8b949e;
        }
        
        .spinner {
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .timestamp { color: #8b949e; font-size: 0.85em; }
        .ip-address { font-family: monospace; color: #3498db; }
        
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
        
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #8b949e;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            background: rgba(13, 17, 23, 0.8);
            border: 1px solid rgba(48, 54, 61, 0.8);
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
            background: rgba(22, 27, 34, 0.95);
            border: 1px solid rgba(48, 54, 61, 0.8);
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
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .chart-container { height: 250px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Contact Form Dashboard</h1>
            <p class="subtitle">Analytics, Extended Logging & IP Management</p>
            
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchTab('overview')">Overview</button>
                <button class="nav-tab" onclick="switchTab('submissions')">Recent Submissions</button>
                <button class="nav-tab" onclick="switchTab('blocklist')">Blocklist</button>
                <button class="nav-tab" onclick="switchTab('whitelist')">Whitelist</button>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="message message-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content active">
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Loading dashboard data...</p>
            </div>
            
            <div id="dashboard" style="display: none;">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Submissions</div>
                        <div class="stat-value info" id="totalSubmissions">-</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Allowed</div>
                        <div class="stat-value success" id="allowedSubmissions">-</div>
                        <div class="timestamp" id="allowedPercent"></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Blocked</div>
                        <div class="stat-value danger" id="blockedSubmissions">-</div>
                        <div class="timestamp" id="blockedPercent"></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Average Spam Score</div>
                        <div class="stat-value warning" id="avgSpamScore">-</div>
                    </div>
                </div>
                
                <div class="card">
                    <h2>7-Day Trend</h2>
                    <button class="refresh-btn" onclick="loadDashboard()">Refresh</button>
                    <div style="clear: both;"></div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="card">
                        <h2>Top IP Addresses</h2>
                        <table id="topIPsTable">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>IP Address</th>
                                    <th>Requests</th>
                                </tr>
                            </thead>
                            <tbody id="topIPsBody">
                                <tr><td colspan="3" class="loading">No data</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card">
                        <h2>Block Reasons</h2>
                        <table id="blockReasonsTable">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Count</th>
                                </tr>
                            </thead>
                            <tbody id="blockReasonsBody">
                                <tr><td colspan="2" class="loading">No data</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Submissions Tab -->
        <div id="tab-submissions" class="tab-content">
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
                
                <div class="stats-grid" style="margin-bottom: 20px;">
                    <div class="stat-card">
                        <div class="stat-label">Active Blocks</div>
                        <div class="stat-value danger"><?= $blockStats['activeBlocks'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Permanent Blocks</div>
                        <div class="stat-value warning"><?= $blockStats['permanentBlocks'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Expired Blocks</div>
                        <div class="stat-value"><?= $blockStats['expiredBlocks'] ?></div>
                    </div>
                </div>
                
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
        let trendChart = null;
        
        async function loadDashboard() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('dashboard').style.display = 'none';
            
            try {
                const response = await fetch('dashboard-api.php', { credentials: 'same-origin' });
                // If the server redirected to HTML (login page) or returned non-JSON,
                // handle gracefully and surface a helpful error instead of throwing
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status + ' ' + response.statusText);
                }
                const ct = response.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    const text = await response.text();
                    // likely an HTML login redirect — show first 200 chars safely
                    throw new Error('Unexpected non-JSON response: ' + text.slice(0, 200));
                }
                const data = await response.json();
                
                if (data.status === 'ok') {
                    updateStats(data.today);
                    updateTrendChart(data.trend);
                    
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('dashboard').style.display = 'block';
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
                document.getElementById('loading').innerHTML = 
                    '<p style="color: #e74c3c;">Error loading dashboard: ' + error.message + '</p>';
            }
        }
        
        function updateStats(report) {
            document.getElementById('totalSubmissions').textContent = report.total;
            document.getElementById('allowedSubmissions').textContent = report.allowed;
            document.getElementById('blockedSubmissions').textContent = report.blocked;
            document.getElementById('avgSpamScore').textContent = report.avgSpamScore.toFixed(1);
            
            const allowedPercent = report.total > 0 ? 
                ((report.allowed / report.total) * 100).toFixed(1) : 0;
            const blockedPercent = report.total > 0 ? 
                ((report.blocked / report.total) * 100).toFixed(1) : 0;
            
            document.getElementById('allowedPercent').textContent = allowedPercent + '%';
            document.getElementById('blockedPercent').textContent = blockedPercent + '%';
            
            const topIPsBody = document.getElementById('topIPsBody');
            if (report.topIPs && Object.keys(report.topIPs).length > 0) {
                topIPsBody.innerHTML = '';
                let rank = 1;
                for (const [ip, count] of Object.entries(report.topIPs)) {
                    topIPsBody.innerHTML += `
                        <tr>
                            <td>${rank++}</td>
                            <td class="ip-address">${ip}</td>
                            <td>${count}</td>
                        </tr>
                    `;
                }
            }
            
            const blockReasonsBody = document.getElementById('blockReasonsBody');
            if (report.byReason && Object.keys(report.byReason).length > 0) {
                blockReasonsBody.innerHTML = '';
                for (const [reason, count] of Object.entries(report.byReason)) {
                    const reasonText = reason.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    blockReasonsBody.innerHTML += `
                        <tr>
                            <td>${reasonText}</td>
                            <td>${count}</td>
                        </tr>
                    `;
                }
            } else {
                blockReasonsBody.innerHTML = '<tr><td colspan="2">No blocks today</td></tr>';
            }
        }
        
        function updateTrendChart(trend) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            
            if (trendChart) {
                trendChart.destroy();
            }
            
            trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trend.map(d => d.date),
                    datasets: [
                        {
                            label: 'Total',
                            data: trend.map(d => d.total),
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Allowed',
                            data: trend.map(d => d.allowed),
                            borderColor: '#27ae60',
                            backgroundColor: 'rgba(39, 174, 96, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Blocked',
                            data: trend.map(d => d.blocked),
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: '#8b949e' },
                            grid: { color: 'rgba(48, 54, 61, 0.5)' }
                        },
                        x: {
                            ticks: { color: '#8b949e' },
                            grid: { color: 'rgba(48, 54, 61, 0.5)' }
                        }
                    }
                }
            });
        }
        
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
            
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
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-refresh every 30 seconds (only Overview tab)
        setInterval(() => {
            if (document.getElementById('tab-overview').classList.contains('active')) {
                loadDashboard();
            }
        }, 30000);
        
        // Initial load
        loadDashboard();
    </script>
</body>
</html>
