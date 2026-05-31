<?php
// إظهار كافة الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 بدء اختبار النظام المعزول</h1>";

$host = 'localhost';
$dbname = 'chem_sales_sys';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div style='color:green; font-weight:bold;'>✅ الاتصال بقاعدة البيانات نجح!</div>";
    
    // اختبار استعلام بسيط
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $count = $stmt->fetchColumn();
    echo "<div>عدد المنتجات: $count</div>";

} catch(PDOException $e) {
    echo "<div style='color:red; font-weight:bold;'>❌ فشل الاتصال: " . $e->getMessage() . "</div>";
}
?>