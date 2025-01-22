<?php
session_start();
require_once 'check_install.php';
checkInstallation();
require_once 'config.php';

function generateQueryCode() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // 如果是查询请求
    if (isset($_POST['query_code'])) {
        $query_code = mysqli_real_escape_string($conn, $_POST['query_code']);
        // 获取反馈基本信息
        $sql = "SELECT * FROM feedback WHERE query_code = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $query_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $status_text = $row['status'] == 'pending' ? '未阅读' : '已阅读';
            $feedback_id = $row['id'];
            
            // 获取所有回复,按时间正序排列
            $replies_sql = "SELECT reply_content, created_at, is_admin 
                           FROM feedback_replies 
                           WHERE feedback_id = ? 
                           ORDER BY created_at ASC";
            $stmt = mysqli_prepare($conn, $replies_sql);
            mysqli_stmt_bind_param($stmt, "i", $feedback_id);
            mysqli_stmt_execute($stmt);
            $replies_result = mysqli_stmt_get_result($stmt);
            
            $replies = [];
            $reply_times = [];
            $is_admin_array = [];
            
            while ($reply = mysqli_fetch_assoc($replies_result)) {
                $replies[] = $reply['reply_content'];
                $reply_times[] = $reply['created_at'];
                $is_admin_array[] = $reply['is_admin'];
            }
            
            $query_result = [
                'status' => $row['status'],
                'status_text' => $status_text,
                'created_at' => date('Y-m-d H:i', strtotime($row['created_at'])),
                'content' => $row['content'],
                'replies' => $replies,
                'reply_times' => $reply_times,
                'is_admin_array' => $is_admin_array,
                'query_code' => $query_code
            ];
        } else {
            $query_error = "未找到相关反馈信息";
        }
    } 
    // 如果是提交反馈
    else {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        
        // 生成唯一查询码
        do {
            $query_code = generateQueryCode();
            $check = mysqli_query($conn, "SELECT id FROM feedback WHERE query_code = '$query_code'");
        } while (mysqli_num_rows($check) > 0);
        
        $sql = "INSERT INTO feedback (username, email, content, query_code) 
                VALUES ('$username', '$email', '$content', '$query_code')";
        
        if (mysqli_query($conn, $sql)) {
            $message = "您的反馈提交成功！反馈码为：<strong>$query_code</strong>";
            // 移除之前的 session 消息
            unset($_SESSION['error_message']);
            echo "<script>
                localStorage.removeItem('feedback_username');
                localStorage.removeItem('feedback_email');
                localStorage.removeItem('feedback_content');
            </script>";
        } else {
            $error = "提交失败，请重试。";
            $_SESSION['error_message'] = $error;
        }
    }
    mysqli_close($conn);
}

// 修改用户回复的处理逻辑
if (isset($_POST['add_user_reply']) && isset($_POST['query_code']) && isset($_POST['reply_content'])) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn) {
        die("连接数据库失败");
    }
    
    $query_code = mysqli_real_escape_string($conn, $_POST['query_code']);
    $reply_content = mysqli_real_escape_string($conn, $_POST['reply_content']);
    
    // 使用预处理语句
    $sql = "SELECT id FROM feedback WHERE query_code = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $query_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $feedback_id = $row['id'];
        
        mysqli_begin_transaction($conn);
        
        try {
            // 插入用户回复
            $sql = "INSERT INTO feedback_replies (feedback_id, reply_content, is_admin) 
                    VALUES (?, ?, 0)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "is", $feedback_id, $reply_content);
            mysqli_stmt_execute($stmt);
            
            // 更新反馈状态为待处理
            $sql = "UPDATE feedback SET status = 'pending' WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $feedback_id);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            
            // 设置成功消息
            $_SESSION['reply_success'] = true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['reply_error'] = "回复提交失败，请重试";
        }
    }
    
    mysqli_close($conn);
    // 修改跳转逻辑，使用 POST 方法重新提交查询
    echo '<form id="autoSubmitForm" action="' . $_SERVER['PHP_SELF'] . '" method="POST" style="display:none;">
            <input type="hidden" name="query_code" value="' . htmlspecialchars($query_code) . '">
          </form>
          <script>document.getElementById("autoSubmitForm").submit();</script>';
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>意见反馈中心</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --success-color: #059669;
            --error-color: #dc2626;
            --background-color: #f9fafb;
            --text-color: #1f2937;
            --border-color: #e5e7eb;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.7;
        }

        .container {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 24px;
        }

        .feedback-card {
            background: #fff;
            padding: 48px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h1, h2 {
            color: var(--text-color);
            font-weight: 600;
            letter-spacing: -0.025em;
        }

        h1 {
            font-size: 2.25rem;
            margin-bottom: 48px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        h2 {
            font-size: 1.875rem;
            margin-bottom: 24px;
        }

        .message, .error {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            transform: translateY(-10px);
            animation: slideDown 0.5s ease forwards;
        }

        .message {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .error {
            background: rgba(220, 38, 38, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .form-group {
            margin-bottom: 28px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s ease forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            color: var(--text-color);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: #fff;
        }

        textarea {
            min-height: 150px;
            line-height: 1.7;
        }

        button {
            width: 100%;
            padding: 16px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            transform: translateY(0);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1);
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px -2px rgba(79, 70, 229, 0.2);
        }

        .query-section {
            margin-bottom: 48px;
            padding-bottom: 36px;
            border-bottom: 2px solid var(--border-color);
        }

        .query-form {
            display: flex;
            gap: 12px;
        }

        .query-form input {
            flex: 1;
        }

        .query-form button {
            width: auto;
            padding: 14px 28px;
        }

        .query-result {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            margin-top: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 24px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .status-processed {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 24px auto;
            }

            .feedback-card {
                padding: 24px;
            }

            h1 {
                font-size: 1.75rem;
                margin-bottom: 36px;
            }

            .query-form {
                flex-direction: column;
            }

            .query-form button {
                width: 100%;
            }
        }

        /* 加载动画样式 */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 首次加载动画样式 */
        .initial-loading {
            display: none; /* 默认隐藏加载动画 */
        }

        .fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .wave-loading {
            display: flex;
            gap: 6px;
        }

        .wave-loading div {
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: wave 1.5s ease-in-out infinite;
        }

        .wave-loading div:nth-child(1) { animation-delay: 0s; }
        .wave-loading div:nth-child(2) { animation-delay: 0.1s; }
        .wave-loading div:nth-child(3) { animation-delay: 0.2s; }
        .wave-loading div:nth-child(4) { animation-delay: 0.3s; }
        .wave-loading div:nth-child(5) { animation-delay: 0.4s; }

        @keyframes wave {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        /* 防止内容闪烁 */
        .content-container {
            opacity: 1; /* 更改为默认可见 */
            transition: opacity 0.3s ease-out;
        }

        .content-visible {
            opacity: 1;
        }

        .replies-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .replies-section h3 {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 16px;
        }
        
        .reply-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            animation: fadeInUp 0.4s ease-out;
        }
        
        .reply-time {
            font-size: 0.813rem;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .reply-content {
            font-size: 0.938rem;
            line-height: 1.6;
        }

        .reply-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .reply-title {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reply-icon {
            width: 20px;
            height: 20px;
            color: var(--success-color);
        }

        .reply-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            animation: slideIn 0.4s ease-out;
        }

        .reply-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 0.875rem;
        }

        .reply-content {
            font-size: 0.938rem;
            line-height: 1.6;
            color: var(--text-color);
        }

        .admin-reply {
            margin-left: 20px;
            background: #f0f9ff;
            border-left: 4px solid var(--primary-color);
        }
        
        .user-reply {
            margin-right: 20px;
            background: #f7fee7;
            border-right: 4px solid #84cc16;
        }
        
        .reply-sender {
            font-weight: 500;
            margin-right: 10px;
        }
        
        .user-reply-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .user-reply-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 12px;
            resize: vertical;
        }
        
        .reply-button {
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .reply-button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        /* 添加返回首页按钮样式 */
        .home-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .home-button:hover {
            background: var(--primary-color);
            color: #fff;
            transform: translateY(-2px);
        }

        .home-button svg {
            width: 16px;
            height: 16px;
            margin-right: 8px;
        }

        .char-counter {
            text-align: right;
            color: #666;
            font-size: 0.8rem;
            margin-top: 5px;
            transition: color 0.3s ease;
        }

        .char-counter.warning {
            color: #dc2626;
        }

        .copy-tooltip {
            position: relative;
            display: inline-block;
        }

        .copy-tooltip::after {
            content: "点击复制";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .copy-tooltip:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* 自定义确认弹窗样式 */
        .confirm-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            max-width: 90%;
            width: 400px;
        }

        .confirm-dialog.active {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .confirm-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .confirm-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 16px;
        }

        .confirm-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .confirm-buttons button {
            flex: 1;
            padding: 12px;
        }

        .btn-cancel {
            background: #fff;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-cancel:hover {
            background: #f9fafb;
            transform: translateY(-2px);
        }
    </style>
    <script>
        // 重写加载处理逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const initialLoading = document.querySelector('.initial-loading');
            const contentContainer = document.querySelector('.content-container');
            const forms = document.querySelectorAll('form');
            const loadingOverlay = document.querySelector('.loading-overlay');
            
            // 立即显示内容
            setTimeout(() => {
                if (initialLoading) initialLoading.style.display = 'none';
                if (contentContainer) {
                    contentContainer.style.opacity = '1';
                    contentContainer.classList.add('content-visible');
                }
            }, 500);

            // 表单提交处理
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (this.checkValidity()) {
                        loadingOverlay.style.display = 'flex';
                    }
                });
            });

            // 页面加载完成后的处理
            window.addEventListener('load', function() {
                setTimeout(() => {
                    initialLoading.classList.add('fade-out');
                    contentContainer.classList.add('content-visible');
                }, 800);
            });

            // 原有的表单提交处理
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (this.checkValidity()) {
                        loadingOverlay.style.display = 'flex';
                        setTimeout(() => {
                            loadingOverlay.style.opacity = '1';
                        }, 10);
                    }
                });
            });

            // 表单内容自动保存
            const feedbackForm = document.querySelector('form:not(.query-form)');
            if (feedbackForm) {
                const formInputs = feedbackForm.querySelectorAll('input, textarea');
                formInputs.forEach(input => {
                    // 从 localStorage 恢复数据
                    const savedValue = localStorage.getItem(`feedback_${input.name}`);
                    if (savedValue) input.value = savedValue;

                    // 自动保存输入内容
                    input.addEventListener('input', function() {
                        localStorage.setItem(`feedback_${input.name}`, this.value);
                    });
                });

                // 提交成功后清除保存的数据
                feedbackForm.addEventListener('submit', function() {
                    if (confirm('确定要提交反馈吗？')) {
                        formInputs.forEach(input => {
                            localStorage.removeItem(`feedback_${input.name}`);
                        });
                        return true;
                    }
                    return false;
                });
            }

            // 字数统计功能
            const contentTextarea = document.querySelector('textarea[name="content"]');
            if (contentTextarea) {
                const counterDiv = document.createElement('div');
                counterDiv.style.cssText = 'text-align: right; color: #666; font-size: 0.8rem; margin-top: 5px;';
                contentTextarea.parentNode.appendChild(counterDiv);

                function updateCounter() {
                    const count = contentTextarea.value.length;
                    counterDiv.textContent = `${count} 字`;
                    if (count > 1000) {
                        counterDiv.style.color = '#dc2626';
                    } else {
                        counterDiv.style.color = '#666';
                    }
                }

                contentTextarea.addEventListener('input', updateCounter);
                updateCounter();
            }

            // 改进反馈码复制功能
            if (document.querySelector('.message')) {
                const feedbackCode = document.querySelector('.message strong');
                if (feedbackCode) {
                    feedbackCode.style.cursor = 'pointer';
                    feedbackCode.title = '点击复制反馈码';
                    feedbackCode.addEventListener('click', async function() {
                        try {
                            await navigator.clipboard.writeText(this.textContent);
                            const originalText = this.textContent;
                            this.textContent = '复制成功！';
                            setTimeout(() => {
                                this.textContent = originalText;
                            }, 1500);
                        } catch (err) {
                            console.error('复制失败:', err);
                        }
                    });
                }
            }

            // 改进加载状态显示
            const loadingOverlay = document.querySelector('.loading-overlay');
            if (loadingOverlay) {
                const loadingText = document.createElement('div');
                loadingText.style.cssText = 'margin-top: 15px; color: var(--primary-color); font-weight: 500;';
                loadingText.textContent = '正在处理您的请求...';
                loadingOverlay.appendChild(loadingText);

                let dots = '';
                setInterval(() => {
                    dots = dots.length >= 3 ? '' : dots + '.';
                    loadingText.textContent = '正在处理您的请求' + dots;
                }, 500);
            }

            // 添加确认弹窗功能
            const overlay = document.querySelector('.confirm-overlay');
            const dialog = document.querySelector('.confirm-dialog');
            const cancelBtn = dialog.querySelector('.btn-cancel');
            const confirmBtn = dialog.querySelector('.btn-confirm');
            let currentForm = null;

            // 修改回复表单的提交处理
            document.addEventListener('submit', function(e) {
                const form = e.target;
                if (form.querySelector('[name="add_user_reply"]')) {
                    e.preventDefault();
                    currentForm = form;
                    overlay.classList.add('active');
                    dialog.classList.add('active');
                }
            });

            // 取消按钮
            cancelBtn.addEventListener('click', function() {
                overlay.classList.remove('active');
                dialog.classList.remove('active');
                currentForm = null;
            });

            // 确认按钮
            confirmBtn.addEventListener('click', function() {
                if (currentForm) {
                    overlay.classList.remove('active');
                    dialog.classList.remove('active');
                    currentForm.submit();
                }
            });

            // 点击遮罩层关闭
            overlay.addEventListener('click', function() {
                overlay.classList.remove('active');
                dialog.classList.remove('active');
                currentForm = null;
            });
        });

        // 添加页面离开提醒
        window.addEventListener('beforeunload', function(e) {
            const form = document.querySelector('form:not(.query-form)');
            if (form) {
                const hasUnsavedChanges = Array.from(form.elements).some(element => 
                    element.type !== 'submit' && element.value !== ''
                );
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            }
        });
    </script>
</head>
<body>
    <!-- 首次加载动画 -->
    <div class="initial-loading">
        <div class="wave-loading">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>

    <!-- 包装现有内容 -->
    <div class="content-container">
        <!-- 原有的加载遮罩层 -->
        <div class="loading-overlay">
            <div class="loading-spinner"></div>
        </div>
        
        <div class="container">
            <div class="feedback-card">
                <!-- 添加查询部分 -->
                <div class="query-section">
                    <h2>寻觅足迹</h2>
                    <form method="POST" class="query-form">
                        <input type="text" 
                            name="query_code" 
                            required 
                            placeholder="输入反馈码，追寻您的声音"
                            pattern="[A-Z0-9]{8}"
                            title="请输入8位大写字母或数字的反馈码">
                        <button type="submit">追寻</button>
                    </form>
                    
                    <?php if (isset($query_result)): ?>
                        <div class="query-result">
                            <!-- 添加返回首页按钮 -->
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="home-button">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a 1 1 0 001-1v-4a1 1 0 011-1h2a 1 1 0 011 1v4a 1 1 0 001 1m-6 0h6" />
                                </svg>
                                返回首页
                            </a>
                            <div class="status-badge status-<?php echo $query_result['status']; ?>">
                                <?php echo $query_result['status_text']; ?>
                            </div>
                            <div class="query-meta">
                                提交时间：<?php echo $query_result['created_at']; ?>
                            </div>
                            <div class="query-content">
                                <?php echo nl2br(htmlspecialchars($query_result['content'])); ?>
                            </div>
                            <?php if (!empty($query_result['replies'])): ?>
                                <div class="replies-section">
                                    <h3>对话记录</h3>
                                    <?php foreach ($query_result['replies'] as $index => $reply): ?>
                                        <div class="reply-card <?php echo $query_result['is_admin_array'][$index] == '1' ? 'admin-reply' : 'user-reply'; ?>">
                                            <div class="reply-meta">
                                                <span class="reply-sender">
                                                    <?php echo $query_result['is_admin_array'][$index] == '1' ? '管理员回复' : '我的回复'; ?>
                                                </span>
                                                <span class="reply-time">
                                                    <?php echo date('Y-m-d H:i', strtotime($query_result['reply_times'][$index])); ?>
                                                </span>
                                            </div>
                                            <div class="reply-content">
                                                <?php echo nl2br(htmlspecialchars($reply)); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- 添加用户回复表单 -->
                            <div class="user-reply-form">
                                <h3>继续对话</h3>
                                <form method="POST">
                                    <input type="hidden" name="query_code" value="<?php echo $query_result['query_code']; ?>">
                                    <textarea name="reply_content" required 
                                              placeholder="写下您想说的话..."
                                              class="reply-textarea"></textarea>
                                    <button type="submit" name="add_user_reply" class="reply-button">
                                        发送回复
                                    </button>
                                </form>
                            </div>
                            
                            <?php if (isset($_SESSION['reply_success'])): ?>
                                <div class="message">回复已成功发送！</div>
                                <?php unset($_SESSION['reply_success']); ?>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['reply_error'])): ?>
                                <div class="error"><?php echo $_SESSION['reply_error']; ?></div>
                                <?php unset($_SESSION['reply_error']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($query_error)): ?>
                        <div class="error">似乎未能找到您的足迹，请检查反馈码是否正确</div>
                    <?php endif; ?>
                </div>

                <!-- 原有的反馈表单，添加条件判断 -->
                <?php if (!isset($query_result)): ?>
                    <h1>愿您的声音，成为我们前进的灯塔</h1>
                    <?php if (isset($message)): ?>
                        <div class="message">感谢您的倾诉！为了让我们能再次相遇，这是您的反馈码：<strong><?php echo $query_code; ?></strong><br>
                        请珍藏这串代码，它是连接我们的桥梁。</div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="error">提交失败，请稍后重试或联系管理员</div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>如何称呼这个美好的相遇</label>
                            <input type="text" name="username" required placeholder="留下您的名字，让相遇不再匆匆">
                        </div>
                        <div class="form-group">
                            <label>邮件驿站</label>
                            <input type="email" name="email" required placeholder="让我们能继续对话的邮箱">
                        </div>
                        <div class="form-group">
                            <label>倾诉之处</label>
                            <textarea name="content" required placeholder="在这里，诉说您的想法，每一个字都是璀璨的星光..."></textarea>
                        </div>
                        <button type="submit">传递心声</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="text-align: center; padding: 20px; color: #666; font-size: 0.9rem; margin-top: 20px;">
        <div id="hitokoto" style="font-style: italic; min-height: 60px;">『正在获取一言...』</div>
        <script>
            function fetchHitokoto() {
                fetch('https://v1.hitokoto.cn/?c=d&c=e&c=h&c=i&c=j&c=k&encode=json')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const hitokotoElement = document.getElementById('hitokoto');
                        if (hitokotoElement) {
                            hitokotoElement.innerHTML = 
                                `『${data.hitokoto}』<br><span style="font-size: 0.8rem; color: #888; margin-top: 8px; display: inline-block;">—— ${data.from ? data.from : '佚名'}</span>`;
                        }
                    })
                    .catch(err => {
                        console.error('获取一言失败:', err);
                        const hitokotoElement = document.getElementById('hitokoto');
                        if (hitokotoElement) {
                            hitokotoElement.innerHTML = '『生活明朗，万物可爱』';
                        }
                    });
            }

            // 页面加载完成后获取一言
            document.addEventListener('DOMContentLoaded', fetchHitokoto);

            // 如果加载失败，5秒后重试一次
            setTimeout(() => {
                const hitokotoElement = document.getElementById('hitokoto');
                if (hitokotoElement && hitokotoElement.textContent.includes('正在获取')) {
                    fetchHitokoto();
                }
            }, 5000);
        </script>
    </div>

    <!-- 在 body 末尾添加弹窗 HTML -->
    <div class="confirm-overlay"></div>
    <div class="confirm-dialog">
        <div class="confirm-title">确认发送</div>
        <div>确定要发送这条回复吗？</div>
        <div class="confirm-buttons">
            <button class="btn-cancel">取消</button>
            <button class="btn-confirm">确定</button>
        </div>
    </div>
</body>
</html>