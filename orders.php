<?php 
ob_start();
include 'includes/header.php'; 

// 1. إعدادات التقسيم (Pagination)
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 2. حساب إجمالي عدد الطلبات
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // 3. جلب الطلبات
    $sql = "SELECT o.id, o.order_date, o.status, o.total_amount_yer, u.full_name, o.user_id
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            ORDER BY o.id DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // ✅ تعديل هام: نجلب البيانات في مصفوفة لنستخدمها مرتين (للموبايل والكمبيوتر)
    $orders_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات.</div>';
    $orders_data = [];
}

// 4. خريطة الحالات
$status_map = [
    'جديد'        => ['bg' => 'warning', 'text' => 'جديد'],
    'قيد التجهيز' => ['bg' => 'info',    'text' => 'قيد التجهيز'],
    'تم الشحن'    => ['bg' => 'primary', 'text' => 'تم الشحن'],
    'تم التسليم'  => ['bg' => 'success', 'text' => 'مكتمل'],
    'ملغي'        => ['bg' => 'danger',  'text' => 'ملغي']
];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-cart-check text-primary"></i> إدارة الطلبات</h3>
        <span class="badge bg-white text-dark border shadow-sm px-3 py-2">
            العدد: <?php echo $total_orders; ?>
        </span>
    </div>

    <div class="card shadow-sm border-0 d-none d-md-block overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">رقم الطلب</th>
                        <th>اسم العميل</th>
                        <th class="text-end">الإجمالي (ر.ي)</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center pe-4">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders_data as $order): 
                        $st = $status_map[$order['status']] ?? ['bg' => 'secondary', 'text' => $order['status']];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary">#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                        <td class="fw-bold text-end"><?php echo number_format($order['total_amount_yer']); ?></td>
                        <td>
                            <span class="badge rounded-pill bg-<?php echo $st['bg']; ?>-subtle text-<?php echo $st['bg']; ?> border border-<?php echo $st['bg']; ?>-subtle px-3 py-2">
                                <?php echo $st['text']; ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?php echo date('Y/m/d', strtotime($order['order_date'])); ?></td>
                        <td class="text-center pe-4">
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-eye"></i> التفاصيل
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-md-none">
        <?php foreach ($orders_data as $order): 
            $st = $status_map[$order['status']] ?? ['bg' => 'secondary', 'text' => $order['status']];
        ?>
        <div class="card shadow-sm border-0 mb-3 rounded-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-primary">#<?php echo $order['id']; ?></span>
                    <span class="badge bg-<?php echo $st['bg']; ?>-subtle text-<?php echo $st['bg']; ?>">
                        <?php echo $st['text']; ?>
                    </span>
                </div>
                
                <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($order['full_name']); ?></h6>
                <div class="small text-muted mb-3">
                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('Y/m/d h:i A', strtotime($order['order_date'])); ?>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-3">
                    <div class="fw-bold text-dark fs-5">
                        <?php echo number_format($order['total_amount_yer']); ?> <small class="fs-6 text-muted">ر.ي</small>
                    </div>
                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm rounded-pill px-4">
                        التفاصيل
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders_data)): ?>
        <div class="text-center py-5 bg-white rounded shadow-sm">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="mt-2 text-muted">لا توجد طلبات لعرضها حالياً.</p>
        </div>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center shadow-sm">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>">السابق</a>
            </li>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>">التالي</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<?php 
include 'includes/footer.php'; 
ob_end_flush();
?>