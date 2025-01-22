<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 使用预处理语句来防止 SQL 注入
    $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $_POST['username']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($_POST['password'], $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            header('Location: index.php');
            exit;
        }
    }
    
    $error = "用户名或密码错误";
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理员登录</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5b7fb9;
            --primary-gradient: linear-gradient(135deg, #5b7fb9 0%, #2c3e50 100%);
            --background-gradient: linear-gradient(135deg, #eef2f7 0%, #e4ecf7 100%);
            --text-color: #2c3e50;
            --text-secondary: #64748b;
            --border-color: #e1e8f0;
            --shadow-color: rgba(91, 127, 185, 0.2);
            --card-background: rgba(255, 255, 255, 0.95);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', "Microsoft YaHei", sans-serif;
            background: var(--background-gradient);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(20px);
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 420px;
            margin: 20px;
            animation: fadeIn 0.8s ease-out;
        }

        .login-card {
            background: var(--card-background);
            padding: 48px;
            border-radius: 28px;
            box-shadow: 
                0 20px 40px -12px var(--shadow-color),
                0 8px 16px -8px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            width: 100%;
            max-width: 440px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.8) 0%, transparent 60%);
            opacity: 0.6;
            pointer-events: none;
            z-index: 0;
            animation: shimmer 15s infinite linear;
        }

        .logo {
            width: 88px;
            height: 88px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 28px;
            animation: logoAnimation 1s ease-out;
            box-shadow: 0 8px 16px -4px rgba(74, 144, 226, 0.3);
            transform-style: preserve-3d;
            transition: transform 0.5s ease;
            perspective: 1000px;
        }

        .logo::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: var(--primary-gradient);
            filter: blur(8px);
            opacity: 0.5;
        }

        .logo:hover {
            transform: translateY(-5px) rotateY(10deg);
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }

        h1 {
            color: var(--text-color);
            font-size: 1.85rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        .subtitle {
            color: var(--text-accent);
            font-size: 1rem;
            margin-top: 8px;
            letter-spacing: 0.03em;
            line-height: 1.8;
        }

        .poetic-text {
            text-align: center;
            color: var(--text-accent);
            font-size: 0.9rem;
            margin-top: 24px;
            font-style: italic;
            opacity: 0.85;
            animation: floatAnimation 3s ease-in-out infinite;
        }

        .form-group {
            margin-bottom: 24px;
            animation: slideIn 0.6s ease-out;
            animation-fill-mode: both;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 1.05rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-color);
            letter-spacing: 0.01em;
            backdrop-filter: blur(12px);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        input[type="text"]:hover,
        input[type="password"]:hover {
            border-color: var(--primary-color);
            background: white;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary-color);
            background: white;
            box-shadow: 
                0 0 0 4px var(--shadow-color),
                inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            box-shadow: 
                0 8px 16px -4px var(--shadow-color),
                0 4px 8px -4px rgba(0, 0, 0, 0.1);
        }

        input::placeholder {
            color: var(--text-secondary);
            opacity: 0.8;
        }

        button {
            width: 100%;
            padding: 18px 24px;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeIn 0.8s ease-out 0.3s both;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            position: relative;
            overflow: hidden;
        }

        button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        button:hover::after {
            opacity: 1;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 20px -6px var(--shadow-color),
                0 8px 16px -8px rgba(0, 0, 0, 0.2);
        }

        .error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            border: 1px solid rgba(231, 76, 60, 0.2);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes logoAnimation {
            from {
                transform: scale(0.5);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        @keyframes floatAnimation {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">欢迎，请登录</div>
                <h1>轻风反馈管理系统</h1>
                <div class="subtitle">一念执守，静待花开</div>
            </div>
            <?php if (isset($error)): ?>
                <div class="error">春江水暖，重寻门径</div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" required placeholder="请输入用户名">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required placeholder="请输入密码">
                </div>
                <button type="submit">拂衣入门</button>
            </form>
            <div class="poetic-text">
                明月照临，花影成双
            </div>
        </div>
    </div>
</body>
</html>