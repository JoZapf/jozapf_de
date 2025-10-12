<?php
/**
 * Dashboard Frontend - Protected with HMAC token
 */

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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value.success { color: #27ae60; }
        .stat-value.danger { color: #e74c3c; }
        .stat-value.warning { color: #f39c12; }
        .stat-value.info { color: #3498db; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #2c3e50;
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
            border-bottom: 1px solid #ecf0f1;
        }
        
        th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
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
        
        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        
        .refresh-btn:hover {
            background: #2980b9;
        }
        
        .timestamp {
            color: #95a5a6;
            font-size: 0.85em;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Contact Form Dashboard</h1>
            <p class="subtitle">Real-time monitoring and analytics</p>
            <button class="refresh-btn" onclick="loadDashboard()">Refresh</button>
            <span class="timestamp" id="lastUpdate"></span>
        </header>
        
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
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
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
            
            <div class="card">
                <h2>Recent Submissions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Email</th>
                            <th>Spam Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="recentSubmissionsBody">
                        <tr><td colspan="4" class="loading">No data</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>Recent Blocks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Reason</th>
                            <th>Spam Score</th>
                        </tr>
                    </thead>
                    <tbody id="recentBlocksBody">
                        <tr><td colspan="4" class="loading">No data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        let trendChart = null;
        
        async function loadDashboard() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('dashboard').style.display = 'none';
            
            try {
                const response = await fetch('dashboard-api.php');
                const data = await response.json();
                
                if (data.status === 'ok') {
                    updateStats(data.today);
                    updateTrendChart(data.trend);
                    updateRecentSubmissions(data.recentSubmissions);
                    updateRecentBlocks(data.recentBlocks);
                    
                    document.getElementById('lastUpdate').textContent = 
                        'Last updated: ' + new Date(data.timestamp).toLocaleString();
                    
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
                            <td>${ip}</td>
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
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        function updateRecentSubmissions(submissions) {
            const tbody = document.getElementById('recentSubmissionsBody');
            
            if (submissions && submissions.length > 0) {
                tbody.innerHTML = '';
                submissions.forEach(sub => {
                    const time = new Date(sub.timestamp).toLocaleTimeString();
                    const status = sub.blocked ? 
                        '<span class="badge danger">Blocked</span>' : 
                        '<span class="badge success">Allowed</span>';
                    
                    tbody.innerHTML += `
                        <tr>
                            <td>${time}</td>
                            <td>${sub.email}</td>
                            <td>${sub.spamScore}</td>
                            <td>${status}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4">No submissions yet</td></tr>';
            }
        }
        
        function updateRecentBlocks(blocks) {
            const tbody = document.getElementById('recentBlocksBody');
            
            if (blocks && blocks.length > 0) {
                tbody.innerHTML = '';
                blocks.forEach(block => {
                    const time = new Date(block.timestamp).toLocaleTimeString();
                    const reasonText = block.reason.replace(/_/g, ' ');
                    
                    tbody.innerHTML += `
                        <tr>
                            <td>${time}</td>
                            <td>${block.ip}</td>
                            <td>${reasonText}</td>
                            <td><span class="badge danger">${block.spamScore}</span></td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4">No blocks today</td></tr>';
            }
        }
        
        setInterval(loadDashboard, 30000);
        loadDashboard();
    </script>
</body>
</html>