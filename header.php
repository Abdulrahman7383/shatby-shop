<?php
// =======================================
// ADMIN HEADER - نسخة محسّنة للهواتف
// =======================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحديد المسار بناءً على موقع الملف
$current_dir = __DIR__; // مجلد admin
$root = dirname($current_dir); // المستوى الأعلى

// المسارات المحتملة لملف db.php
$db_paths = [
    $root . '/includes/db.php',
    dirname($current_dir, 2) . '/includes/db.php',
    'includes/db.php',
    '../includes/db.php'
];

// تحميل ملف الاتصال بقاعدة البيانات
foreach ($db_paths as $db_path) {
    if (file_exists($db_path)) {
        require_once $db_path;
        break;
    }
}

// حراسة الدخول
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $login_paths = ['../login.php', 'login.php', '../../login.php'];
    foreach ($login_paths as $path) {
        if (file_exists($path)) {
            header("Location: " . $path);
            exit;
        }
    }
    die("ليس لديك صلاحية للوصول إلى هذه الصفحة. <a href='login.php'>سجل الدخول</a>");
}

// إعدادات الصفحة
$script_name = $_SERVER['SCRIPT_NAME'];
$active_page = pathinfo($script_name, PATHINFO_FILENAME);
$admin_name  = htmlspecialchars($_SESSION['admin_name'] ?? 'المدير', ENT_QUOTES, 'UTF-8');

// عدادات
$pending_count = 0;
$unread_msg_count = 0;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'جديد'");
        $stmt->execute();
        $pending_count = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("خطأ في عد الطلبات: " . $e->getMessage());
        $pending_count = 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0");
        $stmt->execute();
        $unread_msg_count = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("خطأ في عد الرسائل: " . $e->getMessage());
        $unread_msg_count = 0;
    }
}

// عناوين الصفحات
$titles = [
    'index' => 'لوحة التحكم',
    'products' => 'إدارة المنتجات',
    'categories' => 'إدارة الفئات',
    'units' => 'إدارة الوحدات',
    'orders' => 'إدارة الطلبات',
    'customers' => 'إدارة العملاء',
    'messages' => 'رسائل الزبائن',
    'reports' => 'التقارير المالية',
    'inventory_report' => 'حركة المخزون',
    'print_report' => 'طباعة التقرير',
    'settings' => 'الإعدادات العامة',
    'offers' => 'العروض الترويجية',
    'users' => 'إدارة الموظفين',
    'order_details' => 'تفاصيل الطلب',
    'product_add' => 'إضافة منتج',
    'product_edit' => 'تعديل منتج',
    'offer_add' => 'إضافة عرض',
    'offer_edit' => 'تعديل عرض',
    'customer_details' => 'تفاصيل العميل',
];

$page_title = $titles[$active_page] ?? "نظام الشطبي";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> | لوحة التحكم</title>
    
    <link rel="icon" type="image/png" href="../images/favicon.png">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <meta name="description" content="لوحة تحكم نظام الشطبي للتجهيزات المخبرية والكيميائية">
    <meta name="keywords" content="لوحة تحكم, إدارة, منتجات, طلبات, عملاء">
    
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0b2f5f;
            --secondary-color: #0a3a70;
            --accent-color: #4dabf7;
            --danger-color: #fa5252;
            --warning-color: #ffc107;
            --success-color: #40c057;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 70px;
            --header-height: 60px;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--dark-color);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* تحسينات اللمس */
        button, a, .nav-link, .sidebar-toggle, .store-link, .logout-btn, .btn {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        /* ========== LAYOUT ========== */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* ========== SIDEBAR ========== */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 1100;
            transition: transform 0.3s ease-in-out, width 0.3s ease;
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        /* وضع الجوال - مخفي افتراضياً */
        @media (max-width: 991.98px) {
            .admin-sidebar {
                transform: translateX(100%);
                width: 85%;
                max-width: 320px;
                box-shadow: -5px 0 25px rgba(0, 0, 0, 0.2);
            }
            .admin-sidebar.show {
                transform: translateX(0);
            }
        }

        /* وضع التصغير (سطح المكتب) */
        .admin-sidebar.compact {
            width: var(--sidebar-width-collapsed);
        }

        /* عند التصغير، نخفي النصوص */
        .admin-sidebar.compact .nav-text,
        .admin-sidebar.compact .logo-text,
        .admin-sidebar.compact .logo-subtext,
        .admin-sidebar.compact .logout-btn span {
            display: none;
        }

        .admin-sidebar.compact .nav-link {
            justify-content: center;
            padding: 14px 8px;
        }

        .admin-sidebar.compact .nav-icon {
            margin: 0;
            font-size: 1.5rem;
        }

        .admin-sidebar.compact .logout-btn {
            justify-content: center;
            padding: 12px 8px;
        }

        .admin-sidebar.compact .logout-btn i {
            font-size: 1.3rem;
            margin: 0;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
        }

        .logo-img:hover {
            transform: scale(1.05);
            border-color: var(--accent-color);
        }

        .logo-text {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(45deg, #ffffff, var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-subtext {
            font-size: 0.85rem;
            opacity: 0.8;
            font-weight: 300;
        }

        /* ========== NAVIGATION ========== */
        .sidebar-nav {
            padding: 20px 15px;
            flex: 1;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 14px 16px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            font-weight: 500;
            min-height: 44px; /* تحسين اللمس */
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 3px;
            height: 100%;
            background: var(--accent-color);
            transform: translateX(100%);
            transition: var(--transition);
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-right: 20px;
            transform: translateX(-5px);
        }

        .nav-link:hover::before {
            transform: translateX(0);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--accent-color), #339af0);
            color: white;
            box-shadow: 0 5px 15px rgba(77, 171, 247, 0.3);
        }

        .nav-link.active::before {
            transform: translateX(0);
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-text {
            flex: 1;
            transition: opacity 0.2s;
        }

        .nav-badge {
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
            min-width: 22px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-badge.warning {
            background: var(--warning-color);
            color: var(--dark-color);
        }

        /* ========== LOGOUT SECTION ========== */
        .logout-section {
            padding: 20px 15px;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: var(--border-radius);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            font-weight: 600;
            min-height: 44px;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* ========== MAIN CONTENT ========== */
        .admin-main {
            flex: 1;
            margin-right: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-right 0.3s ease;
            position: relative;
            width: 100%;
        }

        .admin-main.expanded {
            margin-right: 0;
        }

        .admin-main.compact {
            margin-right: var(--sidebar-width-collapsed);
        }

        /* ========== TOPBAR ========== */
        .admin-topbar {
            background: white;
            height: var(--header-height);
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #e9ecef;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
        }

        .sidebar-toggle:hover {
            background: var(--light-color);
            transform: rotate(180deg);
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .store-link {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
        }

        .store-link:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 47, 95, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-color), #339af0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            border: 2px solid white;
            box-shadow: 0 0 10px rgba(77, 171, 247, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray-color);
        }

        /* ========== CONTENT AREA ========== */
        .content-wrapper {
            padding: 25px;
            min-height: calc(100vh - var(--header-height));
        }

        /* ========== OVERLAY ========== */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1099;
            display: none;
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* ========== RESPONSIVE DESIGN - محسّن للهاتف ========== */
        @media (max-width: 1199.98px) {
            .admin-sidebar {
                width: 240px;
            }
            .admin-main {
                margin-right: 240px;
            }
            .admin-main.compact {
                margin-right: 70px;
            }
        }

        @media (max-width: 991.98px) {
            .admin-main {
                margin-right: 0 !important;
            }
            .admin-main.compact,
            .admin-main.expanded {
                margin-right: 0 !important;
            }
            
            .topbar-right .user-details {
                display: none;
            }
            
            .store-link span {
                display: none;
            }
            
            .store-link {
                padding: 8px 12px;
            }
            
            /* تحسين أزرار اللمس */
            .sidebar-toggle {
                width: 44px;
                height: 44px;
                font-size: 1.5rem;
            }
            
            .user-avatar {
                width: 44px;
                height: 44px;
            }
            
            .nav-link {
                min-height: 50px;
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            
            .page-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 767.98px) {
            .content-wrapper {
                padding: 15px;
            }
            
            .admin-topbar {
                padding: 0 15px;
                height: 56px;
            }
            
            .admin-sidebar {
                width: 85%;
                max-width: 320px;
            }
            
            .logo-text {
                font-size: 1.2rem;
            }
            
            .nav-link {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            
            .nav-icon {
                font-size: 1.3rem;
                width: 28px;
            }
        }

        @media (max-width: 575.98px) {
            :root {
                --header-height: 56px;
            }
            
            .content-wrapper {
                padding: 12px;
            }
            
            .admin-topbar {
                padding: 0 12px;
            }
            
            .sidebar-toggle {
                width: 44px;
                height: 44px;
                font-size: 1.5rem;
            }
            
            .user-avatar {
                width: 44px;
                height: 44px;
            }
            
            .store-link {
                padding: 6px 10px;
                font-size: 0.85rem;
                min-height: 44px;
            }
            
            .logo-img {
                width: 60px;
                height: 60px;
            }
            
            .nav-link {
                padding: 12px 12px;
                font-size: 0.9rem;
            }
            
            .page-title {
                font-size: 1rem;
            }
            
            .topbar-left {
                gap: 12px;
            }
            
            .topbar-right {
                gap: 12px;
            }
        }

        @media (max-width: 374.98px) {
            .topbar-left {
                gap: 8px;
            }
            
            .topbar-right {
                gap: 8px;
            }
            
            .page-title {
                font-size: 0.95rem;
            }
            
            .store-link {
                padding: 5px 8px;
            }
        }

        /* ========== PRINT STYLES ========== */
        @media print {
            .admin-sidebar,
            .admin-topbar,
            .sidebar-overlay,
            .sidebar-toggle,
            .store-link,
            .user-info,
            .no-print {
                display: none !important;
            }
            
            .admin-main {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            body {
                background: white !important;
            }
            
            .content-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }
        }

        /* ========== UTILITY CLASSES ========== */
        .mobile-only {
            display: none !important;
        }
        
        .desktop-only {
            display: block !important;
        }
        
        @media (max-width: 991.98px) {
            .mobile-only {
                display: flex !important;
            }
            
            .desktop-only {
                display: none !important;
            }
        }

        /* ========== SCROLLBAR STYLING ========== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.3);
        }
        
        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        /* ========== ANIMATIONS ========== */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .slide-in {
            animation: slideInRight 0.3s ease forwards;
        }

        .slide-out {
            animation: slideOutRight 0.3s ease forwards;
        }

        /* ========== TOUCH FRIENDLY ========== */
        @media (hover: none) and (pointer: coarse) {
            .nav-link,
            .sidebar-toggle,
            .store-link,
            .logout-btn,
            .btn {
                min-height: 44px;
            }
            
            .nav-icon {
                font-size: 1.3rem;
            }
            
            .sidebar-toggle {
                min-width: 44px;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="../images/logo.png" alt="الشطبي للكيماويات" class="logo-img" 
                     onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><rect width=\'100\' height=\'100\' fill=\'%230b2f5f\'/><text x=\'50\' y=\'50\' font-family=\'Arial\' font-size=\'40\' fill=\'white\' text-anchor=\'middle\' dy=\'.3em\'>S</text></svg>';">
                <div class="logo-text">مختبر الشطبي</div>
                <div class="logo-subtext">لوحة الإدارة</div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link <?php echo $active_page=='index'?'active':''; ?>">
                    <i class="nav-icon bi bi-speedometer2"></i>
                    <span class="nav-text">الرئيسية</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="products.php" class="nav-link <?php echo strpos($active_page,'product')!==false?'active':''; ?>">
                    <i class="nav-icon bi bi-box-seam"></i>
                    <span class="nav-text">المنتجات</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="categories.php" class="nav-link <?php echo $active_page=='categories'?'active':''; ?>">
                    <i class="nav-icon bi bi-tags"></i>
                    <span class="nav-text">الأصناف</span>
                </a>
            </div>
            
            <!-- ✅ عنصر الوحدات تم دمجه بشكل صحيح -->
            <div class="nav-item">
                <a href="units.php" class="nav-link <?php echo $active_page=='units'?'active':''; ?>">
                    <i class="nav-icon bi bi-rulers"></i>
                    <span class="nav-text">الوحدات</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="orders.php" class="nav-link <?php echo strpos($active_page,'order')!==false?'active':''; ?>">
                    <i class="nav-icon bi bi-cart-check"></i>
                    <span class="nav-text">الطلبات</span>
                    <?php if($pending_count > 0): ?>
                        <span class="nav-badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="customers.php" class="nav-link <?php echo strpos($active_page,'customer')!==false?'active':''; ?>">
                    <i class="nav-icon bi bi-people"></i>
                    <span class="nav-text">العملاء</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="messages.php" class="nav-link <?php echo $active_page=='messages'?'active':''; ?>">
                    <i class="nav-icon bi bi-envelope-paper"></i>
                    <span class="nav-text">الرسائل</span>
                    <?php if($unread_msg_count > 0): ?>
                        <span class="nav-badge warning"><?php echo $unread_msg_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo in_array($active_page,['reports','print_report','inventory_report'])?'active':''; ?>">
                    <i class="nav-icon bi bi-bar-chart"></i>
                    <span class="nav-text">التقارير</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="offers.php" class="nav-link <?php echo strpos($active_page,'offer')!==false?'active':''; ?>">
                    <i class="nav-icon bi bi-tag"></i>
                    <span class="nav-text">العروض</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $active_page=='settings'?'active':''; ?>">
                    <i class="nav-icon bi bi-gear"></i>
                    <span class="nav-text">الإعدادات</span>
                </a>
            </div>
        </nav>
        
        <div class="logout-section">
            <a href="../logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </aside>
    
    <main class="admin-main" id="adminMain">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title"><?php echo $page_title; ?></h1>
            </div>
            
            <div class="topbar-right">
                <a href="../index.php" target="_blank" class="store-link">
                    <i class="bi bi-shop"></i>
                    <span class="desktop-only">المتجر</span>
                </a>
                
                <div class="user-info">
                    <div class="user-details desktop-only">
                        <div class="user-name"><?php echo $admin_name; ?></div>
                        <div class="user-role">مدير النظام</div>
                    </div>
                    <div class="user-avatar">
                        <?php echo mb_substr($admin_name, 0, 1, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="content-wrapper">