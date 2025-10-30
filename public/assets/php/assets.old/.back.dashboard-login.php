<?php
session_start();

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

$DASHBOARD_PASSWORD = env('DASHBOARD_PASSWORD', 'admin123');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === $DASHBOARD_PASSWORD) {
        $_SESSION['dashboard_authenticated'] = true;
        header('Location: dashboard.html');
        exit;
    }
    $error = 'Invalid password';
}

if (isset($_SESSION['dashboard_authenticated'])) {
    header('Location: dashboard.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Login</title>
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/contact-form.css">
    <style>
        body {
            background: #0d1117;
            color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            background: var(--cf-card-bg);
            border: 1px solid var(--cf-card-border);
            padding: 40px;
            border-radius: 12px;
            max-width: 400px;
        }
        input {
            width: 100%;
            padding: 12px;
            background: var(--cf-input-bg);
            border: 1px solid var(--cf-input-border);
            color: #fff;
            border-radius: 6px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            margin-top: 15px;
        }
        .error { color: var(--cf-error-text-dark); margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>🔒 Dashboard Login</h1>
        <?php if($error) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Password" autofocus autocomplete="current-password">
            <button>Access Dashboard</button>
        </form>
    </div>
</body>
</html>