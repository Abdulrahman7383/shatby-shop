<?php
// 1. إدارة المخرجات والجلسة بأمان
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. استدعاء الهيدر (الذي قمنا بتطهيره سابقاً)
include 'includes/header.php';

// 3. التحقق من المعرف (تطهير المدخلات)
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// 4. معالجة تحديث حالة الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = htmlspecialchars(strip_tags($_POST['status']));
    $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($updateStmt->execute([$new_status, $order_id])) {
        echo "<div class='alert alert-success m-3 shadow-sm no-print'>✅ تم تحديث الحالة بنجاح.</div>";
    }
}

// 5. جلب بيانات الطلب والعميل (استعلام مدمج وبسيط)
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.phone as user_phone, u.address as user_address
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? LIMIT 1
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("<div class='container py-5'><div class='alert alert-danger'>عذراً، الطلب غير موجود.</div></div>");
}

// 6. تجهيز استعلام المنتجات (استخدمنا fetch داخل الحلقة لاحقاً لتوفير الذاكرة)
$stmt_items = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="fw-bold mb-0">📦 تفاصيل الطلب #<?php echo $order['id']; ?></h3>
            <small class="text-muted">تاريخ الطلب: <?php echo date('Y/m/d H:i', strtotime($order['order_date'])); ?></small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark px-4 rounded-pill shadow-sm"><i class="bi bi-printer me-2"></i> طباعة</button>
            <a href="orders.php" class="btn btn-primary px-4 rounded-pill shadow-sm">عودة</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-cart3 me-2 text-primary"></i> المواد والكميات</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th class="ps-4">المنتج</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-end pe-4">الإجمالي (ر.ي)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // استخدام fetch بدلاً من fetchAll لمنع استهلاك الـ Stack Size
                            while ($item = $stmt_items->fetch()): 
                                $price_yer = $item['price_at_order'] * $order['exchange_rate_at_order'];
                                $line_total = $price_yer * $item['quantity'];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($item['product_name'] ?? 'منتج محذوف'); ?></div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold"><?php echo $item['quantity']; ?></td>
                                <td class="text-end pe-4 fw-bold text-primary"><?php echo number_format($line_total); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="bg-light border-top-0">
                            <tr>
                                <td colspan="2" class="text-end fw-bold py-3">الإجمالي النهائي للطلب:</td>
                                <td class="text-end pe-4 fw-bold text-primary fs-5 py-3"><?php echo number_format($order['total_amount_yer']); ?> <small>ر.ي</small></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">حالة الطلب</h6>
                    <form method="POST">
                        <select name="status" class="form-select mb-3 rounded-3">
                            <?php 
                            $st_list = ['جديد', 'قيد التجهيز', 'تم الشحن', 'تم التسليم', 'ملغي'];
                            foreach($st_list as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($order['status'] == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-success w-100 rounded-pill">تحديث</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white py-3 border-0 text-center">
                    <h6 class="mb-0 small fw-bold">بيانات المستلم</h6>
                </div>
                <div class="card-body small">
                    <div class="mb-3">
                        <label class="text-muted d-block small">الاسم:</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['full_name']); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted d-block small">الهاتف:</label>
                        <div class="fw-bold" dir="ltr">
                            <?php $p = $order['customer_phone'] ?? $order['user_phone']; echo $p; ?>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted d-block small">العنوان:</label>
                        <div class="p-2 bg-light border rounded mt-1"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* تنسيقات الطباعة الصارمة والذكية */
    @media print {
        nav, .sidebar, .no-print, .btn, .admin-sidebar, header { display: none !important; }
        body { background: white !important; margin: 0; padding: 0; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .card { border: 1px solid #eee !important; box-shadow: none !important; border-radius: 0 !important; }
        .container-fluid { padding: 0 !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<?php 
include 'includes/footer.php'; 
ob_end_flush(); 
?>