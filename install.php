<?php
session_start();

// 添加 PHP 版本检查
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('需要 PHP 7.4 或更高版本才能运行此程序。当前版本：' . PHP_VERSION);
}

function checkDatabaseConnection($host, $username, $password, $database) {
    $conn = @mysqli_connect($host, $username, $password);
    if (!$conn) {
        return false;
    }
    
    if (!mysqli_select_db($conn, $database)) {
        $sql = "CREATE DATABASE IF NOT EXISTS " . $database;
        if (!mysqli_query($conn, $sql)) {
            return false;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_username'];
    $db_pass = $_POST['db_password'];
    $db_name = $_POST['db_name'];
    $admin_user = $_POST['admin_username'];
    $admin_pass = $_POST['admin_password'];
    
    if (checkDatabaseConnection($db_host, $db_user, $db_pass, $db_name)) {
        // 创建配置文件
        $config_content = "<?php\n";
        $config_content .= "// 要求 PHP 7.4\n";
        $config_content .= "if (version_compare(PHP_VERSION, '7.4.0', '<')) { die('需要 PHP 7.4 或更高版本'); }\n\n";
        $config_content .= "define('DB_HOST', '$db_host');\n";
        $config_content .= "define('DB_USER', '$db_user');\n";
        $config_content .= "define('DB_PASS', '$db_pass');\n";
        $config_content .= "define('DB_NAME', '$db_name');\n";
        
        file_put_contents('config.php', $config_content);
        
        // 创建数据表
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        
        $sql = "CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            content TEXT NOT NULL,
            query_code VARCHAR(10) NOT NULL UNIQUE,
            status ENUM('pending', 'processed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        mysqli_query($conn, $sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL
        )";
        
        mysqli_query($conn, $sql);
        
        // 更新 feedback_replies 表结构
        $sql = "CREATE TABLE IF NOT EXISTS feedback_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT NOT NULL,
            reply_content TEXT NOT NULL,
            is_admin TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
        )";
        mysqli_query($conn, $sql);
        
        // 插入自定义管理员账号
        $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password) VALUES ('$admin_user', '$hashed_password')";
        mysqli_query($conn, $sql);
        
        header('Location: index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>安装向导 - 用户反馈管理系统</title>
    <meta charset="utf-8">
    <style>
        body { 
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f3ff 100%);
            min-height: 100vh;
            font-family: "Microsoft YaHei", system-ui, -apple-system, sans-serif;
        }
        
        .container { 
            max-width: 800px; 
            margin: 40px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px -8px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
        }

        h1 {
            color: #2563eb;
            text-align: center;
            font-size: 28px;
            margin-bottom: 30px;
            position: relative;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #60a5fa);
            border-radius: 2px;
        }

        .intro-section {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .requirement-list {
            padding-left: 20px;
        }

        .requirement-list li {
            margin: 8px 0;
            color: #475569;
        }

        .system-check {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin: 20px 0;
        }

        .check-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .check-item:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        button:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-1px);
        }

        .loading {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .version-badge {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 13px;
        }

        .version-badge.success {
            background: #dcfce7;
            color: #166534;
        }

        .version-badge.error {
            background: #fee2e2;
            color: #991b1b;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = document.querySelectorAll('input');
            
            inputs.forEach(input => {
                const helpText = document.createElement('div');
                helpText.className = 'help-text';
                
                switch(input.name) {
                    case 'db_host':
                        helpText.textContent = '数据库服务器地址，默认使用 localhost';
                        break;
                    case 'db_username':
                        helpText.textContent = '具有数据库创建权限的数据库账号';
                        break;
                    case 'db_password':
                        helpText.textContent = '数据库账号对应的访问密码';
                        break;
                    case 'db_name':
                        helpText.textContent = '系统将要使用的数据库名称，如已存在将直接使用';
                        break;
                    case 'admin_username':
                        helpText.textContent = '系统管理员账号，建议使用易记且安全的名称';
                        break;
                    case 'admin_password':
                        helpText.textContent = '建议使用字母、数字和符号的组合作为密码';
                        break;
                }
                
                input.parentNode.appendChild(helpText);
            });

            form.addEventListener('submit', function(e) {
                if (form.checkValidity()) {
                    document.querySelector('.loading').style.display = 'flex';
                }
            });
        });
    </script>
</head>
<body>
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>
    <div class="container">
        <h1>用户反馈管理系统 - 安装向导</h1>
        
        <div class="intro-section">
            <h2>开始使用</h2>
            <p>欢迎使用用户反馈管理系统。在开始安装之前，我们需要收集一些信息以完成系统配置。请确保您的服务器环境满足以下要求：</p>
            <ul class="requirement-list">
                <li>服务器环境：PHP 7.4 或更高版本</li>
                <li>数据库服务：MySQL 5.7+ 或 MariaDB 10.2+</li>
                <li>必要扩展：PDO 和 MySQLi 支持</li>
                <li>存储空间：建议预留 50MB 以上可用空间</li>
            </ul>
        </div>

        <div class="system-check">
            <h2>环境配置检测</h2>
            <div class="check-item">
                <span>运行环境检测</span>
                <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                    <span class="version-badge success">✓ PHP <?php echo PHP_VERSION; ?> 已满足要求</span>
                <?php else: ?>
                    <span class="version-badge error">✗ 需要 PHP 7.4 或更高版本运行环境</span>
                <?php endif; ?>
            </div>
            <div class="check-item">
                <span>PDO 数据库支持</span>
                <?php if (extension_loaded('pdo')): ?>
                    <span class="version-badge success">✓ 支持</span>
                <?php else: ?>
                    <span class="version-badge error">✗ 未检测到支持</span>
                <?php endif; ?>
            </div>
            <div class="check-item">
                <span>MySQLi 扩展支持</span>
                <?php if (extension_loaded('mysqli')): ?>
                    <span class="version-badge success">✓ 支持</span>
                <?php else: ?>
                    <span class="version-badge error">✗ 未检测到支持</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>数据库服务器地址</label>
                <input type="text" name="db_host" value="localhost" required placeholder="请输入数据库服务器地址">
            </div>
            <div class="form-group">
                <label>数据库用户名</label>
                <input type="text" name="db_username" required placeholder="请输入数据库访问用户名">
            </div>
            <div class="form-group">
                <label>数据库访问密码</label>
                <input type="password" name="db_password" placeholder="请输入数据库访问密码">
            </div>
            <div class="form-group">
                <label>数据库名称</label>
                <input type="text" name="db_name" value="feedback_system" required placeholder="请输入要使用的数据库名称">
            </div>
            <div class="form-group">
                <label>管理员账号</label>
                <input type="text" name="admin_username" required placeholder="请设置系统管理员账号">
            </div>
            <div class="form-group">
                <label>管理员密码</label>
                <input type="password" name="admin_password" required placeholder="请设置系统管理员密码">
            </div>
            <button type="submit">开始安装</button>
        </form>
    </div>
</body>
</html>