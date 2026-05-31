<?php
// =========================================================
// ملف معالجة تعديل المنتج (AJAX Backend)
// =========================================================

// 1. إعدادات الهيدر (ضروري جداً ليفهم المتصفح الرد)
header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. معالجة الأخطاء الصامتة (لمنع ظهور نصوص PHP وسط الـ JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0); // نخفي الأخطاء عن الشاشة
ini_set('log_errors', 1);     // ونسجلها في الملفات فقط

try {
    // 3. الاتصال بقاعدة البيانات
    // تأكد أن المسار صحيح بالنسبة لمكان هذا الملف
    $root_path = __DIR__ . '/../includes/db.php';
    if (!file_exists($root_path)) {
        throw new Exception("ملف الاتصال بقاعدة البيانات غير موجود");
    }
    require_once $root_path;

    // 4. التحقق من الصلاحيات (Admin Only)
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        throw new Exception("غير مصرح لك بالقيام بهذا الإجراء");
    }

    // 5. التحقق من نوع الطلب (POST Only)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("طريقة الطلب غير صحيحة");
    }

    // 6. التحقق من وجود ID المنتج
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("رقم المنتج مفقود أو غير صحيح");
    }
    $id = (int)$_GET['id'];

    // 7. استلام البيانات وتنظيفها
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price_usd'] ?? 0);
    $qty = (int)($_POST['stock_quantity'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    // تحقق بسيط
    if (empty($name)) {
        throw new Exception("اسم المنتج مطلوب");
    }

    // 8. تنفيذ التحديث
    $stmt = $pdo->prepare("
        UPDATE products SET
            name = ?,
            category_id = ?,
            price_usd = ?,
            stock_quantity = ?,
            description = ?,
            status = ?
        WHERE id = ?
    ");

    $result = $stmt->execute([$name, $category_id, $price, $qty, $desc, $status, $id]);

    if ($result) {
        // الرد بنجاح
        echo json_encode(['success' => true, 'message' => 'تم الحفظ بنجاح']);
    } else {
        throw new Exception("فشل تحديث قاعدة البيانات");
    }

} catch (Exception $e) {
    // في حال حدوث أي خطأ، نرسله كـ JSON
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>