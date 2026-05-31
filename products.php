<?php
ob_start();
include 'includes/header.php';
// =========================================================
// إصلاح: كود تغيير الحالة (Toggle Status)
// =========================================================
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    // جلب الحالة الحالية وعكسها (إذا 1 تصبح 0 والعكس)
    $stmt = $pdo->prepare("UPDATE products SET status = NOT status WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: products.php");
    exit;
}
// =========================================================
// 1. الإحصائيات (تطهير: جلب الأرقام في استعلام واحد بدلاً من array_filter)
// =========================================================
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity < 5 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active
    FROM products
")->fetch();

// =========================================================
// 2. نظام التقسيم (Pagination) - ضروري جداً لعدم انهيار الصفحة
// =========================================================
$limit = 15; // عرض 15 منتج فقط في الصفحة
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// =========================================================
// 3. بناء استعلام البحث والفلترة (محسن)
// =========================================================
$search = $_GET['search'] ?? '';
$category_id = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($category_id)) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
}
if ($stock_filter == 'low') $where[] = "p.stock_quantity < 5 AND p.stock_quantity > 0";
elseif ($stock_filter == 'out') $where[] = "p.stock_quantity = 0";

$where_sql = implode(" AND ", $where);

// حساب عدد النتائج الكلي للتقسيم
$total_results = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where_sql");
$total_results->execute($params);
$total_rows = $total_results->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// جلب المنتجات للصفحة الحالية فقط
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $where_sql 
        ORDER BY p.id DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// =========================================================
// 4. العمليات (حذف / تعطيل) - كما هي مع تحسين بسيط
// =========================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // التحقق من وجود الطلبات قبل الحذف
    $check = $pdo->prepare("SELECT id FROM order_items WHERE product_id = ? LIMIT 1");
    $check->execute([$id]);
    if ($check->fetch()) {
        echo "<script>alert('⚠️ لا يمكن الحذف؛ المنتج مرتبط بطلبات. تم تعطيله بدلاً من ذلك.');</script>";
        $pdo->prepare("UPDATE products SET status = 0 WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    }
    header("Location: products.php"); exit;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold m-0"><i class="bi bi-box-seam text-primary"></i> المخزن والمنتجات</h2>
        <a href="product_add.php" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="bi bi-plus-lg"></i> إضافة صنف</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-success h-100">
                <small class="text-muted fw-bold">متوفر</small>
                <h3 class="fw-bold mb-0"><?php echo $stats['in_stock'] ?? 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-warning h-100">
                <small class="text-muted fw-bold">منخفض</small>
                <h3 class="fw-bold mb-0 text-warning"><?php echo $stats['low_stock'] ?? 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-danger h-100">
                <small class="text-muted fw-bold">نفد</small>
                <h3 class="fw-bold mb-0 text-danger"><?php echo $stats['out_of_stock'] ?? 0; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold">النشطة</small>
                <h3 class="fw-bold mb-0 text-primary"><?php echo $stats['active'] ?? 0; ?></h3>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="ابحث عن منتج..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="stock" class="form-select">
                        <option value="">كل الحالات</option>
                        <option value="low" <?php echo $stock_filter=='low'?'selected':''; ?>>مخزون منخفض</option>
                        <option value="out" <?php echo $stock_filter=='out'?'selected':''; ?>>المنتهي</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100">تصفية</button>
                </div>
                <div class="col-md-2">
                    <a href="products.php" class="btn btn-light border w-100">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">المنتج</th>
                        <th>التصنيف</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stmt->fetch()): 
                        $qty = (int)$row['stock_quantity'];
                        $badge = ($qty == 0) ? 'danger' : (($qty < 5) ? 'warning' : 'success');
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center">
                                <img src="../assets/uploads/<?php echo $row['image']; ?>" class="rounded me-3 border" width="45" height="45" style="object-fit:cover" onerror="this.src='../assets/images/no-img.png'">
                                <div>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="text-muted" style="font-size:0.7rem">ID: #<?php echo $row['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td class="fw-bold">$<?php echo number_format($row['price_usd'], 2); ?></td>
                        <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $qty; ?></span></td>
                        <td>
                            <a href="?toggle_status=<?php echo $row['id']; ?>" class="badge bg-<?php echo $row['status']?'success':'secondary'; ?> text-decoration-none">
                                <?php echo $row['status']?'نشط':'معطل'; ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="product_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                <button onclick="if(confirm('حذف؟')) window.location.href='?delete=<?php echo $row['id']; ?>'" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
            <li class="page-item <?php echo $page==$i?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ob_end_flush(); ?>