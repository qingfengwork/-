<?php
function checkInstallation() {
    // 检查配置文件是否存在
    if (!file_exists(dirname(__FILE__) . '/config.php')) {
        header('Location: install.php');
        exit;
    }
}
?>