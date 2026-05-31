<?php
ob_start();
include 'includes/header.php';

// =========================================================
// ✅ 1. تعريف دالة التنظيف (لحل مشكلة Fatal Error)
// =========================================================
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// 2. إعدادات التقسيم (Pagination)
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 3. بناء استعلام البحث
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$where = "role = 'customer'";
$params = [];

if (!empty($search)) {
    // تم إضافة الأقواس لضمان ترتيب الشروط بشكل صحيح
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 4. جلب البيانات
$customers = []; // مصفوفة فارغة لتخزين النتائج
try {
    // أ) حساب الإجمالي للتقسيم
    $count_sql = "SELECT COUNT(*) FROM users WHERE $where";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_customers = $stmt_count->fetchColumn();
    $total_pages = ceil($total_customers / $limit);

    // ب) جلب البيانات الفعلية
    $sql = "SELECT id, full_name, email, phone, status, created_at 
            FROM users 
            WHERE $where 
            ORDER BY id DESC 
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // ✅ جلبنا البيانات مرة واحدة هنا لنستخدمها في الجدول وفي الموبايل
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-people text-primary"></i> إدارة العملاء</h3>
            <p class="text-muted small mb-0">إجمالي النتائج: <?php echo $total_customers; ?></p>
        </div>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm rounded-pill" placeholder="بحث باسم أو هاتف..." value="<?php echo $search; ?>">
            <button type="submit" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden d-none d-md-block">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">العميل</th>
                        <th>التواصل</th>
                        <th>الحالة</th>
                        <th>تاريخ التسجيل</th>
                        <th class="text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                <small class="text-muted">ID: #<?php echo $customer['id']; ?></small>
                            </td>
                            <td>
                                <div class="small"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer['email']); ?></div>
                                <div class="small text-primary"><i class="bi bi-phone me-1"></i> <?php echo $customer['phone']; ?></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill bg-<?php echo $customer['status']=='active'?'success':'secondary'; ?> bg-opacity-10 text-<?php echo $customer['status']=='active'?'success':'secondary'; ?> border">
                                    <?php echo $customer['status']=='active'?'نشط':'غير نشط'; ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?php echo date('Y/m/d', strtotime($customer['created_at'])); ?></td>
                            <td class="text-center">
                                <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">لا توجد نتائج مطابقة للبحث.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-md-none">
        <?php if (count($customers) > 0): ?>
            <?php foreach ($customers as $customer): ?>
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between">
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h6>
                        <span class="small text-muted">#<?php echo $customer['id']; ?></span>
                    </div>
                    <div class="small text-muted mb-2"><?php echo $customer['email']; ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-primary fw-bold small"><?php echo $customer['phone']; ?></div>
                        <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3">عرض</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center text-muted py-5">لا توجد نتائج.</div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link shadow-none" href="?page=<?php echo $page-1; ?>&search=<?php echo $search; ?>">«</a>
            </li>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link shadow-none" href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link shadow-none" href="?page=<?php echo $page+1; ?>&search=<?php echo $search; ?>">»</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ob_end_flush(); ?>