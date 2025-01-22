<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

function getServerInfo() {
    global $conn;
    
    // 添加错误处理的服务器信息获取
    $mysql_version = @mysqli_get_server_info($conn) ?: '未知';
    $server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '未知';
    
    return array(
        'php_version' => 'PHP ' . PHP_VERSION,
        'mysql_version' => 'MySQL ' . $mysql_version,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => '时区: ' . date_default_timezone_get(),
        'max_upload' => '上传限制: ' . ini_get('upload_max_filesize'),
        'http_host' => $_SERVER['HTTP_HOST'] ?? '未知',
    );
}

header('Content-Type: application/json');
echo json_encode(getServerInfo());
