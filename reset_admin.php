<?php
include '../includes/db.php';

$email = 'admin@gmail.com';
$password = '123456';

// 1. توليد تشفير جديد وصحيح لكلمة السر باستخدام سيرفرك الحالي
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 2. حذف الحساب القديم (إن وجد) لتجنب تكرار البيانات
$pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

// 3. إنشاء الحساب من جديد بالبيانات الصحيحة
$sql = "INSERT INTO users (full_name, email, password, phone, role, is_verified) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

if ($stmt->execute(['المدير العام', $email, $hashed_password, '777000000', 'admin', 1])) {
    echo "<h1>✅ تم إعادة ضبط حساب المدير بنجاح!</h1>";
    echo "<p><strong>الإيميل:</strong> $email</p>";
    echo "<p><strong>كلمة المرور:</strong> $password</p>";
    echo "<br><a href='login.php'>اضغط هنا لتسجيل الدخول</a>";
} else {
    echo "❌ حدث خطأ، لم يتم إنشاء الحساب.";
}
?>