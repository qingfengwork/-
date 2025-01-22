<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 在 PHP 处理部分添加回复处理
if (isset($_POST['add_reply'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $reply_content = mysqli_real_escape_string($conn, $_POST['reply_content']);
    
    $sql = "INSERT INTO feedback_replies (feedback_id, reply_content) VALUES ($feedback_id, '$reply_content')";
    mysqli_query($conn, $sql);
    
    // 更新反馈状态为已处理
    $sql = "UPDATE feedback SET status = 'processed' WHERE id = $feedback_id";
    mysqli_query($conn, $sql);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 处理删除反馈
if (isset($_POST['delete_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    
    // 首先删除所有相关的回复
    $sql = "DELETE FROM feedback_replies WHERE feedback_id = $feedback_id";
    mysqli_query($conn, $sql);
    
    // 然后删除反馈
    $sql = "DELETE FROM feedback WHERE id = $feedback_id";
    mysqli_query($conn, $sql);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 获取统计数据
$stats = array();

// 总反馈数
$sql = "SELECT COUNT(*) as total FROM feedback";
$result_total = mysqli_query($conn, $sql);
$stats['total'] = mysqli_fetch_assoc($result_total)['total'];

// 待处理反馈数
$sql = "SELECT COUNT(*) as pending FROM feedback WHERE status = 'pending'";
$result_pending = mysqli_query($conn, $sql);
$stats['pending'] = mysqli_fetch_assoc($result_pending)['pending'];

// 已处理反馈数
$sql = "SELECT COUNT(*) as processed FROM feedback WHERE status = 'processed'";
$result_processed = mysqli_query($conn, $sql);
$stats['processed'] = mysqli_fetch_assoc($result_processed)['processed'];

// 总回复数
$sql = "SELECT COUNT(*) as total_replies FROM feedback_replies";
$result_replies = mysqli_query($conn, $sql);
$stats['total_replies'] = mysqli_fetch_assoc($result_replies)['total_replies'];

// 修改查询 SQL，加入回复内容
$sql = "SELECT f.*, 
               GROUP_CONCAT(fr.reply_content ORDER BY fr.created_at DESC) as replies,
               GROUP_CONCAT(fr.created_at ORDER BY fr.created_at DESC) as reply_times,
               GROUP_CONCAT(fr.is_admin ORDER BY fr.created_at DESC) as is_admin_array
        FROM feedback f 
        LEFT JOIN feedback_replies fr ON f.id = fr.feedback_id 
        GROUP BY f.id 
        ORDER BY f.created_at DESC";
$result = mysqli_query($conn, $sql);

// 处理状态更新
if (isset($_POST['update_status'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $new_status = $_POST['status'] == 'pending' ? 'processed' : 'pending';
    
    $sql = "UPDATE feedback SET status = '$new_status' WHERE id = $feedback_id";
    mysqli_query($conn, $sql);
    
    // 刷新页面
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 在统计数据之前添加服务器信息获取函数
function getServerInfo() {
    return array(
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'mysql_version' => mysqli_get_server_info($GLOBALS['conn']),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
        'server_time' => date('Y-m-d H:i:s'),
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? '未知',
        'max_upload' => ini_get('upload_max_filesize')
    );
}

$server_info = getServerInfo();
?>

<!DOCTYPE html>
<html>
<head>
    <title>反馈管理</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ffffff;
            --primary-gradient: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
            --success-gradient: linear-gradient(135deg, #2ed573 0%, #1e8449 100%);
            --pending-gradient: linear-gradient(135deg, #ffa502 0%, #e67e22 100%);
            --background-color: #f8f9fa;
            --card-background: rgba(255, 255, 255, 0.98);
            --danger-color: #e74c3c;
            --text-color: #333333;
            --border-color: #edf2f7;
            --hover-color: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body { 
            font-family: 'Inter', "Microsoft YaHei", sans-serif;
            background: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container { 
            max-width: 1200px; 
            margin: 40px auto;
            padding: 0 20px;
            animation: fadeIn 0.8s ease-out;
        }

        .header {
            background: var(--card-background);
            padding: 24px 32px;
            border-radius: 20px;
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.6s ease-out;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 32px;
            height: 32px;
            background: #333333;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .logout {
            text-decoration: none;
            color: var(--danger-color);
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            background: #fff;
            border: 2px solid var(--danger-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout:hover {
            background: var(--danger-color);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.2);
        }

        .table-container {
            margin-top: 32px;
            background: var(--card-background);
            border-radius: 20px;
            box-shadow: 
                0 20px 40px -12px rgba(0, 0, 0, 0.1),
                0 8px 16px -8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
            animation: fadeIn 0.8s ease-out 0.2s both;
        }

        .table-header {
            padding: 16px 24px;
            display: flex;
            justify-content: flex-end;
            border-bottom: 1px solid var(--border-color);
        }

        .refresh-button {
            padding: 8px 16px;
            background: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-color);
            font-size: 0.875rem;
        }

        .refresh-button:hover {
            background: var(--hover-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .refresh-button.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .refresh-button.loading svg {
            animation: spin 1s linear infinite;
        }

        table { 
            border-collapse: separate;
            border-spacing: 0;
            width: 100%; 
        }

        th { 
            padding: 20px 24px;
            background: #333333;
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            text-transform: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td { 
            padding: 16px 24px;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        tr {
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out;
            animation-fill-mode: both;
        }

        tr:hover td {
            background: rgba(0, 0, 0, 0.02);
            transform: translateX(6px) scale(1.01);
        }

        .feedback-content {
            position: relative;
            padding: 16px;
            background: #fff;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .feedback-text {
            position: relative;
            max-height: 100px;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding-bottom: 40px; /* 为按钮留出空间 */
        }

        .feedback-text.expanded {
            max-height: none;
        }

        .feedback-toggle {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(transparent, white 40%);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .feedback-text.expanded .feedback-toggle {
            background: none;
            opacity: 1;
        }

        .reply-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .reply-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .reply-list {
            margin-bottom: 16px;
            position: relative;
            max-height: 150px;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding-bottom: 40px; /* 为按钮留出空间 */
        }

        .reply-list.expanded {
            max-height: none;
        }

        .reply-list-toggle {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(transparent, white 40%);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reply-list.expanded .reply-list-toggle {
            background: none;
            opacity: 1;
        }

        .reply-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .reply-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .reply-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-height: 80px;
            margin-bottom: 8px;
            resize: vertical;
            font-size: 0.875rem;
        }

        .reply-button {
            padding: 8px 16px;
            background: var(--success-gradient);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reply-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-background);
            border-radius: 24px;
            box-shadow: 
                0 20px 40px -12px rgba(0, 0, 0, 0.1),
                0 8px 16px -8px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
        }

        .empty-state h2 {
            color: var(--text-color);
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #64748b;
        }

        .timestamp {
            color: #64748b;
            font-size: 0.813rem;
        }

        /* 添加图标 */
        .icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .header {
                padding: 15px;
                flex-direction: column;
                gap: 15px;
                position: static;
            }

            .table-container {
                border-radius: 8px;
            }

            table {
                display: block;
                overflow-x: auto;
            }

            th, td {
                min-width: 120px;
                font-size: 0.813rem;
            }

            .feedback-content {
                max-width: 200px;
            }

            .logout {
                width: 100%;
                justify-content: center;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }
        
            .action-button {
                width: 100%;
                justify-content: center;
            }
        }

        .status-toggle {
            background: none;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: auto;
        }
        
        .status-pending {
            background: var(--pending-gradient);
            color: white;
            opacity: 0.9;
        }
        
        .status-processed {
            background: var(--success-gradient);
            color: white;
            opacity: 0.9;
        }
        
        .status-toggle:hover {
            opacity: 1;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        
        .query-code {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #475569;
        }

        .welcome-message {
            background: var(--card-background);
            padding: 20px 32px;
            border-radius: 20px;
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
            animation: fadeIn 0.8s ease-out;
        }

        .welcome-message .icon {
            width: 32px;
            height: 32px;
            color: #4CAF50;
        }

        .welcome-text {
            flex: 1;
        }

        .welcome-text h3 {
            color: var(--text-color);
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .welcome-text p {
            color: #64748b;
        }

        .action-button {
            text-decoration: none;
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-button:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .reply-list {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var(--border-color);
            position: relative;
            max-height: 200px; /* 增加初始高度 */
            overflow: hidden;
            transition: max-height 0.5s ease; /* 增加过渡时间 */
            padding-bottom: 40px; /* 为按钮留出空间 */
        }

        .reply-list.expanded {
            max-height: 2000px; /* 增加展开后的最大高度 */
        }

        .reply-list-toggle {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px 0 5px; /* 增加渐变区域 */
            background: linear-gradient(transparent, white 40%);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reply-list.expanded .reply-list-toggle {
            background: none;
            padding: 5px 0;
        }

        .reply-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .reply-time {
            color: #64748b;
            font-size: 0.75rem;
            margin-bottom: 4px;
        }
        
        .reply-form {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var (--border-color);
        }
        
        .reply-form textarea {
            width: 100%;
            min-height: 80px;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 0.875rem;
            resize: vertical;
        }
        
        .reply-button {
            background: var(--primary-color);
            color: #333;
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .reply-button:hover {
            background: var(--hover-color);
            transform: translateY(-1px);
        }

        .admin-reply {
            background: #f0f9ff;
            margin-left: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .user-reply {
            background: #f7fee7;
            margin-right: 20px;
            border-right: 4px solid #84cc16;
        }
        
        .reply-sender {
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .admin-reply .reply-sender {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .user-reply .reply-sender {
            background: #ecfccb;
            color: #3f6212;
        }

        /* 更新折叠按钮样式 */
        .toggle-button {
            padding: 6px 16px;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            font-size: 0.85rem;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 2px 4px rgba(0, 0, 0, 0.05),
                0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .toggle-button:hover {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            transform: translateY(-1px);
            box-shadow: 
                0 4px 6px rgba(0, 0, 0, 0.05),
                0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .toggle-button:active {
            transform: translateY(0);
            box-shadow: 
                 0 1px 2px rgba(0, 0, 0, 0.05),
                0 1px 1px rgba(0, 0, 0, 0.1);
        }

        .toggle-button svg {
            width: 14px;
            height: 14px;
            stroke-width: 2.5;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .toggle-button:hover svg {
            stroke: #374151;
        }

        .expanded .toggle-button {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            color: #fff;
            border-color: transparent;
        }

        .expanded .toggle-button:hover {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
        }

        .expanded .toggle-button svg {
            stroke: #fff;
            transform: rotate(180deg);
        }

        .feedback-toggle, .reply-list-toggle {
            background: linear-gradient(transparent, #ffffff 40%);
            padding: 20px 0 0;
        }

        .expanded .feedback-toggle, 
        .expanded .reply-list-toggle {
            background: none;
            padding: 10px 0;
        }

        /* 更新回复列表相关样式 */
        .reply-list {
            position: relative;
            max-height: 200px;
            overflow: hidden;
            transition: all 0.5s ease;
            padding-bottom: 40px;
            margin-bottom: 16px;
        }

        .reply-list-content {
            padding-bottom: 20px;
        }

        .reply-list.expanded {
            max-height: none;
        }

        .reply-list-toggle {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            display: none; /* 默认隐藏，通过JS控制显示 */
            align-items: center;
            justify-content: center;
            background: linear-gradient(transparent, white 40%);
            z-index: 2;
        }

        .reply-list.has-overflow .reply-list-toggle {
            display: flex;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease-out;
        }
        
        .stat-card {
            background: var(--card-background);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .stat-title {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .stat-trend {
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .trend-up {
            color: #22c55e;
        }
        
        .trend-down {
            color: #ef4444;
        }

        .tip-container {
            margin-top: 12px;
            padding: 12px 16px;
            background: rgba(59, 130, 246, 0.05);
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
        }

        .tip-header {
            color: #1e40af;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .tip-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tip-list li {
            color: #475569;
            font-size: 0.875rem;
            line-height: 1.6;
            margin-bottom: 4px;
            padding-left: 20px;
            position: relative;
        }

        .tip-list li:before {
            content: "•";
            position: absolute;
            left: 8px;
            color: #3b82f6;
        }

        .tip-list li:last-child {
            margin-bottom: 0;
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .clock-widget {
            text-align: right;
            padding: 8px 12px;
            background: rgba(59, 130, 246, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }

        .clock-time {
            font-size: 1.125rem;
            font-weight: 600;
            color: #3b82f6;
            font-family: 'Monaco', 'Consolas', monospace;
        }

        .clock-date {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 2px;
        }

        @media (max-width: 768px) {
            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .clock-widget {
                width: 100%;
                text-align: center;
            }
        }

        .rest-reminder {
            margin-top: 16px;
            padding: 12px 16px;
            background: rgba(236, 72, 153, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(236, 72, 153, 0.1);
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .rest-reminder.show {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rest-icon {
            width: 24px;
            height: 24px;
            color: #ec4899;
            flex-shrink: 0;
        }

        .rest-content {
            flex: 1;
        }

        .rest-title {
            color: #ec4899;
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 4px;
        }

        .rest-text {
            color: #64748b;
            font-size: 0.813rem;
            line-height: 1.5;
        }

        .rest-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .rest-button {
            padding: 4px 12px;
            border: 1px solid #ec4899;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: #ec4899;
        }

        .rest-button:hover {
            background: #ec4899;
            color: white;
        }

        .rest-button.dismiss {
            border-color: #64748b;
            color: #64748b;
        }

        .rest-button.dismiss:hover {
            background: #64748b;
            color: white;
        }

        .delete-button {
            padding: 6px 12px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.813rem;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .delete-button:hover {
            opacity: 1;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.2);
        }

        .delete-button svg {
            width: 14px;
            height: 14px;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .server-info {
            background: var(--card-background);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            position: relative;
        }

        .server-info-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .server-item {
            padding: 12px;
            background: rgba(59, 130, 246, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }

        .server-item-label {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 4px;
        }

        .server-item-value {
            color: #1e40af;
            font-weight: 500;
            font-size: 0.938rem;
            font-family: 'Monaco', 'Consolas', monospace;
            transition: all 0.3s ease;
        }

        .server-item-value.updating {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .server-refresh {
            position: absolute;
            right: 24px;
            top: 24px;
            padding: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .server-refresh:hover {
            color: #3b82f6;
            transform: rotate(180deg);
        }

        .server-refresh.loading {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                <div class="logo">墨</div>
                <h1>心语流芳</h1>
            </div>
            <div class="header-actions">
                <a href="change_password.php" class="action-button">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                    </svg>
                    修改密码
                </a>
                <a href="logout.php" class="logout">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                    归隐
                </a>
            </div>
        </div>
        
        <div class="welcome-message">
            <svg class="icon" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M12,20C7.58,20 4,16.42 4,12C4,7.58 7.58,4 12,4C16.42,4 20,7.58 20,12C20,16.42 16.42,20 12,20M15.5,11C16.33,11 17,10.33 17,9.5C17,8.67 16.33,8 15.5,8C14.67,8 14,8.67 14,9.5C14,10.33 14.67,11 15.5,11M8.5,11C9.33,11 10,10.33 10,9.5C10,8.67 9.33,8 8.5,8C7.67,8 7,8.67 7,9.5C7,10.33 7.67,11 8.5,11M12,17.5C14.33,17.5 16.31,16.04 17.11,14H6.89C7.69,16.04 9.67,17.5 12,17.5Z"/>
            </svg>
            <div class="welcome-text">
                <div class="welcome-header">
                    <h3>
                        <?php 
                        $hour = date('H');
                        if($hour >= 5 && $hour < 12) {
                            echo "早安，";
                        } elseif($hour >= 12 && $hour < 18) {
                            echo "下午好，";
                        } else {
                            echo "晚安，";
                        }
                        ?>
                        欢迎您管理员
                    </h3>
                    <div class="clock-widget">
                        <div class="clock-time" id="clock-time"></div>
                        <div class="clock-date" id="clock-date"></div>
                    </div>
                </div>
                <p>每一份心声，都值得倾听；每一句回应，都是温暖的希望。</p>
                <div class="tip-container">
                    <div class="tip-header">温馨提示：</div>
                    <ul class="tip-list">
                        <li>请及时回应用户的反馈，让他们感受到被重视和关心</li>
                        <li>回复时请保持友善和耐心，给予理解和支持</li>
                        <li>对于敏感问题，建议建议谨慎处理并保护用户隐私</li>
                    </ul>
                </div>
                <div id="hitokoto" style="margin-top: 12px; font-style: italic; color: #64748b;">『加载中...』</div>
                <script>
                    fetch('https://v1.hitokoto.cn')
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('hitokoto').innerHTML = 
                                `『${data.hitokoto}』<br><span style="font-size: 0.8rem;">—— ${data.from}</span>`;
                        })
                        .catch(err => console.error(err));
                </script>
                <div class="rest-reminder" id="restReminder">
                    <svg class="rest-icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12,2C6.48,2 2,6.48 2,12s4.48,10 10,10 10-4.48 10-10S17.52,2 12,2zm0,18c-4.41,0-8-3.59-8-8s3.59-8 8-8 8,3.59 8,8-3.59,8-8,8zm-.97-4.21v-5.34L16.31,12l-5.28,3.79zM13,6c-3.31,0-6,2.69-6,6s2.69,6 6,6 6-2.69 6-6-2.69-6-6-6zm-1,10V8l4.06,4L12,16z"/>
                    </svg>
                    <div class="rest-content">
                        <div class="rest-title">温馨提醒</div>
                        <div class="rest-text">已连续工作一段时间了，建议您稍作休息，活动一下，喝杯温水~</div>
                        <div class="rest-actions">
                            <button class="rest-button" onclick="startBreak()">开始休息</button>
                            <button class="rest-button dismiss" onclick="dismissReminder()">稍后提醒</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加统计数据展示 -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M16,14H8V6H16M16,18H8V16H16M16,4H8C6.89,4 6,4.89 6,6V18C6,19.11 6.89,20 8,20H16C17.11,20 18,19.11 18,18V6C18,4.89 17.11,4 16,4Z" />
                    </svg>
                    总反馈数
                </div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12,2C6.47,2 2,6.47 2,12C2,17.53 6.47,22 12,22C17.53,22 22,17.53 22,12C22,6.47 17.53,2 12,2M12,20C7.58,20 4,16.42 4,12C4,7.58 7.58,4 12,4C16.42,4 20,7.58 20,12C20,16.42 16.42,20 12,20M15.5,11C16.33,11 17,10.33 17,9.5C17,8.67 16.33,8 15.5,8C14.67,8 14,8.67 14,9.5C14,10.33 14.67,11 15.5,11M8.5,11C9.33,11 10,10.33 10,9.5C10,8.67 9.33,8 8.5,8C7.67,8 7,8.67 7,9.5C7,10.33 7.67,11 8.5,11M12,17.5C14.33,17.5 16.31,16.04 17.11,14H6.89C7.69,16.04 9.67,17.5 12,17.5Z" />
                    </svg>
                    待处理
                </div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M9,7V9H13V15H11V17H15V7H9M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4Z" />
                    </svg>
                    已处理
                </div>
                <div class="stat-value"><?php echo $stats['processed']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M20,2H4A2,2 0 0,0 2,4V22L6,18H20A2,2 0 0,0 22,16V4A2,2 0 0,0 20,2M20,16H5.17L4,17.17V4H20v12z"/>
                </svg>
                    总回复数
                </div>
                <div class="stat-value"><?php echo $stats['total_replies']; ?></div>
            </div>
        </div>

        <!-- 添加服务器信息展示模块 -->
        <div class="server-info">
            <div class="server-info-title">
                <svg class="icon" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M4,1H20A1,1 0 0,1 21,2V6A1,1 0 0,1 20,7H4A1,1 0 0,1 3,6V2A1,1 0 0,1 4,1M4,9H20A1,1 0 0,1 21,10V14A1,1 0 0,1 20,15H4A1,1 0 0,1 3,14V10A1,1 0 0,1 4,9M4,17H20A1,1 0 0,1 21,18V22A1,1 0 0,1 20,23H4A1,1 0 0,1 3,22V18A1,1 0 0,1 4,17M9,5H10V3H9V5M9,13H10V11H9V13M9,21H10V19H9V21M5,3V5H7V3H5M5,11V13H7V11H5M5,19V21H7V19H5Z"/>
                </svg>
                服务器状态
                <button class="server-refresh" onclick="refreshServerInfo(true)" title="刷新服务器信息">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/>
                    </svg>
                </button>
            </div>
            <div class="server-grid" id="serverGrid">
                <!-- 服务器信息将通过JavaScript动态更新 -->
            </div>
        </div>

        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="table-container">
                <table id="feedbackTable">
                    <tr>
                        <th>ID</th>
                        <th>查询码</th>
                        <th>用户名</th>
                        <th>邮箱</th>
                        <th>反馈内容</th>
                        <th>提交时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>#<?php echo $row['id']; ?></td>
                        <td><span class="query-code"><?php echo $row['query_code']; ?></span></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td class="feedback-content">
                            <div class="feedback-text">
                                <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                                <div class="feedback-toggle">
                                    <button type="button" class="toggle-button" onclick="toggleFeedback(this)">
                                        <span class="toggle-text">展开内容</span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="reply-section">
                                <div class="reply-header">
                                    <span class="reply-label">回复记录</span>
                                </div>
                                
                                <?php if ($row['replies']): ?>
                                    <div class="reply-list">
                                        <div class="reply-list-content">
                                            <?php 
                                            $replies = explode(',', $row['replies']);
                                            $reply_times = explode(',', $row['reply_times']);
                                            $is_admin_array = explode(',', $row['is_admin_array']);
                                            foreach ($replies as $index => $reply): ?>
                                                <div class="reply-item <?php echo $is_admin_array[$index] == '1' ? 'admin-reply' : 'user-reply'; ?>">
                                                    <div class="reply-meta">
                                                        <span class="reply-sender"><?php echo $is_admin_array[$index] == '1' ? '管理员' : '用户'; ?></span>
                                                        <span class="reply-time">
                                                            <?php echo date('Y-m-d H:i', strtotime($reply_times[$index])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="reply-content">
                                                        <?php echo nl2br(htmlspecialchars($reply)); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="reply-list-toggle">
                                            <button type="button" class="toggle-button" onclick="toggleReplies(this)">
                                                <span class="toggle-text">展开回复</span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form class="reply-form" method="POST" onsubmit="return confirm('确认发送这条回复？');">
                                    <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                                    <textarea name="reply_content" required 
                                              placeholder="写下您温暖的回应..."
                                              onfocus="this.parentElement.parentElement.parentElement.classList.add('expanded')"></textarea>
                                    <button type="submit" name="add_reply" class="reply-button">
                                        <svg class="icon" style="width: 16px; height: 16px; margin-right: 4px;" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                        </svg>
                                        发送回复
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td class="timestamp">
                            <?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?>
                        </td>
                        <td>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $row['status']; ?>">
                                <button type="submit" 
                                        name="update_status" 
                                        class="status-toggle status-<?php echo $row['status']; ?>">
                                    <?php echo $row['status'] == 'pending' ? '等待回应' : '已得圆满'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="actions-cell">
                            <form method="POST" style="margin:0" onsubmit="return confirm('确定要删除这条反馈吗？此操作不可撤销。');">
                                <input type="hidden" name="feedback_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="delete_feedback" class="delete-button">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" 
                                              stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    删除
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg class="icon" style="width: 48px; height: 48px; color: #64748b; margin-bottom: 20px;" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4H20v12z"/>
                </svg>
                <h2>温暖的等待</h2>
                <p>此刻安静，静待温暖的相遇...</p>
            </div>
        <?php endif; ?>
    </div>
    <script>
        // 更新折叠功能的JavaScript
        function toggleFeedback(button) {
            const textContainer = button.closest('.feedback-text');
            const toggleText = button.querySelector('.toggle-text');
            
            textContainer.classList.toggle('expanded');
            if (textContainer.classList.contains('expanded')) {
                toggleText.textContent = '收起';
            } else {
                toggleText.textContent = '展开';
                textContainer.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function toggleReplies(button) {
            const replyList = button.closest('.reply-list');
            const toggleText = button.querySelector('.toggle-text');
            const contentWrapper = replyList.querySelector('.reply-list-content');
            
            if (replyList.classList.contains('expanded')) {
                replyList.style.maxHeight = '200px';
                replyList.classList.remove('expanded');
                toggleText.textContent = '展开回复';
                replyList.scrollIntoView({ behavior: 'smooth' });
            } else {
                replyList.style.maxHeight = contentWrapper.scrollHeight + 60 + 'px';
                replyList.classList.add('expanded');
                toggleText.textContent = '收起回复';
            }
        }

        // 修改初始化逻辑
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化反馈内容折叠
            document.querySelectorAll('.feedback-text').forEach(container => {
                const content = container.firstElementChild;
                const toggle = container.querySelector('.feedback-toggle');
                
                if (content.scrollHeight <= 140) {
                    toggle.style.display = 'none';
                } else {
                    toggle.style.display = 'flex';
                }
            });

            // 初始化回复列表折叠
            document.querySelectorAll('.reply-list').forEach(container => {
                const contentWrapper = container.querySelector('.reply-list-content');
                const toggle = container.querySelector('.reply-list-toggle');
                
                // 检查内容是否需要折叠
                if (contentWrapper.scrollHeight > 160) {
                    container.classList.add('has-overflow');
                    container.style.maxHeight = '200px';
                } else {
                    container.style.maxHeight = 'none';
                    container.style.paddingBottom = '0';
                }

                // 监听窗口大小变化，重新检查是否需要显示折叠按钮
                window.addEventListener('resize', () => {
                    if (contentWrapper.scrollHeight > 160) {
                        container.classList.add('has-overflow');
                    } else {
                        container.classList.remove('has-overflow');
                        container.style.maxHeight = 'none';
                    }
                });
            });
        });

        function updateClock() {
            const now = new Date();
            const timeElement = document.getElementById('clock-time');
            const dateElement = document.getElementById('clock-date');
            
            // 格式化时间
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            timeElement.textContent = `${hours}:${minutes}:${seconds}`;
            
            // 格式化日期
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const date = String(now.getDate()).padStart(2, '0');
            const weekDays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
            const weekDay = weekDays[now.getDay()];
            dateElement.textContent = `${year}年${month}月${date}日 ${weekDay}`;
        }

        // 初始化并每秒更新时钟
        updateClock();
        setInterval(updateClock, 1000);

        // 休息提醒相关功能
        let workStartTime = localStorage.getItem('workStartTime') || Date.now();
        const WORK_DURATION = 45 * 60 * 1000; // 45分钟后提醒

        function checkWorkDuration() {
            const currentTime = Date.now();
            const workDuration = currentTime - workStartTime;
            
            if (workDuration >= WORK_DURATION) {
                document.getElementById('restReminder').classList.add('show');
            }
        }

        function startBreak() {
            // 打开休息页面或执行休息相关操作
            if (confirm('建议您休息5分钟，是否开始休息？')) {
                document.getElementById('restReminder').classList.remove('show');
                workStartTime = Date.now();
                localStorage.setItem('workStartTime', workStartTime);
                
                // 可以在这里添加更多休息时的操作，比如播放轻音乐等
                alert('请起身活动，让眼睛休息一下~');
            }
        }

        function dismissReminder() {
            document.getElementById('restReminder').classList.remove('show');
            // 15分钟后再次提醒
            workStartTime = Date.now() - (WORK_DURATION - 15 * 60 * 1000);
            localStorage.setItem('workStartTime', workStartTime);
        }

        // 每分钟检查一次工作时长
        setInterval(checkWorkDuration, 60000);
        // 页面加载时也检查一次
        checkWorkDuration();

        // 添加服务器信息动态更新功能
        let serverInfoTimer = null;
        
        async function refreshServerInfo(manual = false) {
            const refreshBtn = document.querySelector('.server-refresh');
            const serverGrid = document.getElementById('serverGrid');
            
            if (manual) {
                refreshBtn.classList.add('loading');
            }
            
            try {
                const response = await fetch('get_server_info.php');
                const data = await response.json();
                
                // 更新服务器信息显示
                serverGrid.innerHTML = Object.entries(data).map(([key, value]) => `
                    <div class="server-item">
                        <div class="server-item-label">${formatLabel(key)}</div>
                        <div class="server-item-value" data-key="${key}">${formatValue(key, value)}</div>
                    </div>
                `).join('');
                
            } catch (error) {
                console.error('获取服务器信息失败:', error);
            } finally {
                if (manual) {
                    refreshBtn.classList.remove('loading');
                }
            }
        }
        
        function formatLabel(key) {
            const labels = {
                'os': '操作系统',
                'php_version': 'PHP版本',
                'mysql_version': 'MySQL版本',
                'memory_usage': '内存使用',
                'memory_peak': '内存峰值',
                'disk_free': '磁盘剩余',
                'disk_total': '磁盘总量',
                'disk_used_percent': '磁盘使用率',
                'server_time': '服务器时间',
                'server_ip': '服务器IP',
                'max_upload': '最大上传限制',
                'cpu_usage': 'CPU负载',
                'uptime': '运行时间'
            };
            return labels[key] || key;
        }
        
        function formatValue(key, value) {
            switch(key) {
                case 'memory_usage':
                case 'memory_peak':
                    return `${value} MB`;
                case 'disk_free':
                case 'disk_total':
                    return `${value} GB`;
                case 'disk_used_percent':
                    return `${value}%`;
                default:
                    return value;
            }
        }
        
        // 初始化服务器信息
        refreshServerInfo();
        // 每30秒自动更新一次
        serverInfoTimer = setInterval(refreshServerInfo, 30000);
        
        // 页面切换时清除定时器
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(serverInfoTimer);
            } else {
                refreshServerInfo();
                serverInfoTimer = setInterval(refreshServerInfo, 30000);
            }
        });
    </script>
</body>
</html>