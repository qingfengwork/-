# 轻风意见反馈系统 ![GitHub License](https://img.shields.io/github/license/yourname/feedback-system?color=blue) ![PHP Version](https://img.shields.io/badge/PHP-7.0%2B-777BB4?logo=php)

一个现代化的意见反馈管理系统，支持用户反馈提交和跟踪功能，基于MIT协议开源。

![系统截图](https://www.helloimg.com/i/2025/01/22/67908c23d974b.png)
![系统截图](https://www.helloimg.com/i/2025/01/22/67908c23dd7fb.png)
## ✨ 核心功能

### 💡 用户反馈
- 🎯 匿名提交支持
- 🔐 自动生成唯一反馈码（8位数字字母组合）
- 📱 响应式设计（适配移动设备）
- 🎨 平滑交互动画
- 💾 数据持久化（自动保存未提交内容）

### 🔍 反馈追踪
- 🔎 反馈码实时查询
- 📈 状态可视化展示（未阅读/已阅读）
- 💬 多轮对话支持（用户与管理员）
- 📅 时间轴显示处理进度
- 📧 邮件通知提醒（可选）

### 🛠️ 管理功能
- 👮 管理员后台
- 🔑 基于会话的权限管理
- 📊 数据统计看板
- 📤 数据导出功能（CSV格式）

## 🛠️ 技术栈

| 分类       | 技术/工具                |
|------------|-------------------------|
| **前端**   | HTML5, CSS3, JavaScript |
| **后端**   | PHP 7.4+, MySQL 8.0     |
| **服务器** | Nginx 1.18+             |
| **构建**   | Composer                |
| **依赖**   | [Hitokoto API](https://hitokoto.cn/) |

## 🚀 快速开始

### 前置要求
- PHP 7.4+（需开启PDO扩展）
- MySQL 5.7+ 
- Web服务器（Nginx/Apache）
- Git（可选）

### 🛠️ 安装步骤
1.下载文件
2.放置服务器
3.访问安装
4.填写数据库信息
5.点击安装
6.完成后点击返回首页

## 🔧 项目结构
```
project/
├── index.php          # 主程序文件
├── config.php         # 配置文件
├── check_install.php  # 安装检查
├── install            # 安装文件
├── admin/             # 后台管理目录
│   ├── index.php      # 后台管理主程序
│   ├── login.php      # 后台登录页面
│   ├── logout.php     # 后台退出登录
│   └── change_password.php     # 修改密码
└── README.md          # 说明文档
```

### 开发建议
1. 使用PHP 7.4+的新特性（类型声明、预加载等）
2. 遵循PSR-4自动加载规范
3. 数据库操作统一使用预处理语句
4. 前端资源使用Webpack打包（可选）

## 🤝 参与贡献

欢迎通过以下方式参与贡献：
- 报告问题（[Issue模板](.github/ISSUE_TEMPLATE.md)）
- 提交功能请求
- 改进代码质量
- 完善文档

贡献流程：
1. Fork项目仓库
2. 创建特性分支（`git checkout -b feature/新功能`）
3. 提交变更（`git commit -m '添加精彩功能'`）
4. 推送分支（`git push origin feature/新功能`）
5. 发起Pull Request
---

> 「倾听用户声音，驱动产品进化」  
> —— 轻风反馈系统开发团队

📮 联系方式：qingfeng@qingfengnb.cn  
📢 社区支持：[支持页面](https://support.qingfengnb.cn/)
