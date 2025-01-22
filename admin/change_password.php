<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = '两次输入的新密码不同，烦请重新输入';
        $messageType = 'error';
    } else {
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            $message = "数据库暂时无法连接，请稍后再试";
            $messageType = 'error';
        } else {
            // 验证当前密码
            $admin_id = $_SESSION['admin_id'];
            
            // 使用预处理语句查询当前密码
            $stmt = mysqli_prepare($conn, "SELECT password FROM admins WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && $admin = mysqli_fetch_assoc($result)) {
                if (password_verify($current_password, $admin['password'])) {
                    // 更新新密码
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // 使用预处理语句更新密码
                    $update_stmt = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $admin_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $message = '密码修改成功！3秒后将自动跳转至登录页面...';
                        $messageType = 'success';
                        session_destroy(); // 销毁当前会话
                        // 不再直接使用header跳转
                    } else {
                        $message = '密码更新遇到了一些问题，请稍后再试';
                        $messageType = 'error';
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $message = '当前密码有误，请仔细核对后重试';
                    $messageType = 'error';
                }
            } else {
                $message = '获取用户信息时遇到了问题，请重新登录';
                $messageType = 'error';
            }
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>心语密匙 - 管理后台</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --hover-color: #f1f5f9;
            --error-color: #ef4444;
            --success-color: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', "Microsoft YaHei", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        .card {
            background: var(--card-background);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.5);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 30px -5px rgba(0,0,0,0.15), 0 15px 15px -5px rgba(0,0,0,0.08);
        }

        .card-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
        }

        .card-header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .card-header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--hover-color);
        }

        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px rgba(99, 102, 241, 0.3);
        }

        .button:active {
            transform: translateY(0);
        }

        .message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 0.975rem;
            position: relative;
            overflow: hidden;
        }

        .message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .message.error {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fee2e2;
        }

        .message.error::before {
            background: var(--error-color);
        }

        .message.success {
            background: #ecfdf5;
            color: var (--success-color);
            border: 1px solid #d1fae5;
        }

        .message.success::before {
            background: var(--success-color);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.975rem;
            transition: all 0.2s ease;
            padding: 8px 16px;
            border-radius: 8px;
        }

        .back-link:hover {
            color: var(--primary-color);
            background: var(--hover-color);
        }

        @media (max-width: 640px) {
            .card {
                padding: 24px;
                border-radius: 16px;
            }
            
            .card-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>心语密匙</h1>
                <p>更新密匙，守护心语</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="current_password">原有密匙</label>
                    <input type="password" id="current_password" name="current_password" 
                           placeholder="请输入当前使用的密匙" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">新的密匙</label>
                    <input type="password" id="new_password" name="new_password" 
                           placeholder="请设置新的密匙" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密匙</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="请再次输入新的密匙" required>
                </div>
                
                <button type="submit" class="button">更新密匙</button>
            </form>
            
            <a href="index.php" class="back-link">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                返回管理面板
            </a>
        </div>
    </div>
    <?php if ($message && $messageType == 'success'): ?>
    <script>
        let seconds = 3;
        const countdownElement = document.querySelector('.message.success');
        const countdown = setInterval(() => {
            seconds--;
            if (seconds > 0) {
                countdownElement.innerHTML = `密码修改成功！${seconds}秒后将自动跳转至登录页面...`;
            } else {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
