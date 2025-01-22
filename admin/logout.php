<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>退出登录</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(120deg, #e0c3fc 0%, #8ec5fc 100%);
            height: 100vh;
            margin: 0;
        }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            title: '👋 再见，朋友',
            html: `
                <div style="font-size: 1.1em; color: #666;">
                    您已安全退出系统 🔒<br>
                    感谢您的使用，祝您有愉快的一天！✨<br>
                    期待我们的下次相遇 💫
                </div>
            `,
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            backdrop: `rgba(0,0,123,0.4)`,
            customClass: {
                popup: 'swal2-show',
                title: 'custom-title'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        }).then(() => {
            window.location.href = 'login.php';
        });
    </script>
</body>
</html>
<?php
session_destroy();
?>