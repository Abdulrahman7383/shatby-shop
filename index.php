<?php
ob_start();
include 'includes/header.php';

// =========================================================
// 1. منطق التجهيز (إعداد المتغيرات والاتصال)
// =========================================================

// التأكد من وجود المتغير $pdo
if (!isset($pdo)) {
    // محاولة اتصال طوارئ إذا فشل الهيدر
    require_once '../includes/db.php';
}

// تهيئة المتغيرات لتجنب أخطاء Undefined Variable
$q_orders = 0; $q_revenue = 0; $q_users = 0; $q_stock = 0; $q_pending = 0;
$chart_months = []; $chart_totals = [];
$latest_orders_stmt = null;
$top_products_stmt = null;

try {
    // أ) فحص هل عمود quantity موجود؟ (لمنع الأخطاء)
    $hasQty = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'quantity'");
        $hasQty = (bool)$checkCol->fetch();
    } catch (Exception $e) { $hasQty = false; }

    // ب) جلب الإحصائيات العامة (استعلام واحد سريع)
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE status != 'ملغي') as total_orders,
            (SELECT COALESCE(SUM(total_amount_yer),0) FROM orders WHERE status = 'تم التسليم') as total_revenue,
            (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
            (SELECT COUNT(*) FROM products WHERE stock_quantity <= 5) as low_stock,
            (SELECT COUNT(*) FROM orders WHERE status = 'جديد') as pending_orders
    ")->fetch(PDO::FETCH_ASSOC);

    $q_orders  = (int)($stats['total_orders'] ?? 0);
    $q_revenue = (float)($stats['total_revenue'] ?? 0);
    $q_users   = (int)($stats['total_customers'] ?? 0);
    $q_stock   = (int)($stats['low_stock'] ?? 0);
    $q_pending = (int)($stats['pending_orders'] ?? 0);

    // ج) جلب آخر 8 طلبات
    $latest_orders_stmt = $pdo->prepare("
        SELECT o.id, o.status, o.total_amount_yer, u.full_name, o.order_date 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC LIMIT 8
    ");
    $latest_orders_stmt->execute();

    // د) جلب أفضل المنتجات مبيعاً
    // إذا وجدنا عمود الكمية نستخدم SUM، وإلا نستخدم COUNT
    $aggregator = $hasQty ? "SUM(oi.quantity)" : "COUNT(*)";
    $top_products_stmt = $pdo->prepare("
        SELECT p.name, p.id, COALESCE($aggregator, 0) as sales_count
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN orders o ON o.id = oi.order_id
        WHERE o.status = 'تم التسليم'
        GROUP BY oi.product_id, p.name, p.id
        ORDER BY sales_count DESC LIMIT 6
    ");
    $top_products_stmt->execute();

    // هـ) تجهيز بيانات الرسم البياني (آخر 6 أشهر)
    // 1. نجلب البيانات الخام من الداتا بيس
    $raw_sales = $pdo->query("
        SELECT DATE_FORMAT(order_date, '%Y-%m') as m, SUM(total_amount_yer) as total
        FROM orders WHERE status = 'تم التسليم' 
        AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY m
    ")->fetchAll(PDO::FETCH_KEY_PAIR); // مصفوفة بصيغة [شهر => قيمة]

    // 2. نملأ الأشهر الناقصة بـ 0
    for ($i = 5; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime("-$i months")); // 2024-02
        $monthLabel = date('M', strtotime("-$i months")); // Feb
        
        $chart_months[] = $monthLabel;
        $chart_totals[] = (float)($raw_sales[$monthKey] ?? 0);
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>خطأ في قاعدة البيانات: " . $e->getMessage() . "</div>";
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid p-0">
    
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-success h-100 hover-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="text-muted fw-bold">إجمالي المبيعات</small>
                        <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($q_revenue); ?> <small class="fs-6">ر.ي</small></h4>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                        <i class="bi bi-cash-stack text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-primary h-100 hover-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="text-muted fw-bold">إجمالي الطلبات</small>
                        <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo $q_orders; ?></h4>
                        <?php if($q_pending > 0): ?>
                            <small class="text-primary fw-bold" style="font-size: 0.8rem;">
                                <i class="bi bi-bell-fill"></i> <?php echo $q_pending; ?> طلب جديد
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                        <i class="bi bi-cart-check text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-info h-100 hover-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="text-muted fw-bold">العملاء المسجلين</small>
                        <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo $q_users; ?></h4>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                        <i class="bi bi-people text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-danger h-100 hover-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="text-muted fw-bold">نواقص المخزون</small>
                        <h4 class="fw-bold mb-0 text-danger mt-1"><?php echo $q_stock; ?></h4>
                        <small class="text-danger" style="font-size: 0.8rem;">تحتاج لتعبئة</small>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-3 rounded-circle d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>تحليل المبيعات (آخر 6 أشهر)</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>الأكثر مبيعاً</h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php if ($top_products_stmt && $top_products_stmt->rowCount() > 0): ?>
                        <?php $rank = 1; while($prod = $top_products_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="list-group-item border-0 d-flex align-items-center px-4 py-3">
                                <span class="badge bg-light text-dark rounded-circle me-3 border" style="width:25px;height:25px;display:flex;align-items:center;justify-content:center;">
                                    <?php echo $rank++; ?>
                                </span>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($prod['name']); ?></h6>
                                    <small class="text-muted"><?php echo $prod['sales_count']; ?> مبيعة</small>
                                </div>
                                <a href="../product_details.php?id=<?php echo $prod['id']; ?>" target="_blank" class="text-primary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-basket fs-1 opacity-50"></i>
                            <p class="mt-2 mb-0">لا توجد مبيعات مكتملة بعد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>آخر الطلبات الواردة</h6>
            <a href="orders.php" class="btn btn-sm btn-primary px-3 rounded-pill">عرض الكل</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">رقم الطلب</th>
                        <th>العميل</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th class="text-end pe-4">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($latest_orders_stmt && $latest_orders_stmt->rowCount() > 0): ?>
                        <?php while($ord = $latest_orders_stmt->fetch(PDO::FETCH_ASSOC)): 
                            $badgeClass = 'secondary';
                            if ($ord['status'] == 'جديد') $badgeClass = 'primary';
                            if ($ord['status'] == 'قيد التنفيذ') $badgeClass = 'info';
                            if ($ord['status'] == 'تم التسليم') $badgeClass = 'success';
                            if ($ord['status'] == 'ملغي') $badgeClass = 'danger';
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo $ord['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-1 me-2"><i class="bi bi-person text-secondary"></i></div>
                                    <span><?php echo htmlspecialchars($ord['full_name'] ?? 'زائر'); ?></span>
                                </div>
                            </td>
                            <td class="small text-muted"><?php echo date('Y-m-d', strtotime($ord['order_date'])); ?></td>
                            <td><span class="badge bg-<?php echo $badgeClass; ?> rounded-pill px-3"><?php echo $ord['status']; ?></span></td>
                            <td class="text-end pe-4 fw-bold"><?php echo number_format($ord['total_amount_yer']); ?> ر.ي</td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">لا توجد طلبات حتى الآن</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
    .hover-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .hover-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart');
    if(ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_months); ?>,
                datasets: [{
                    label: 'المبيعات (ر.ي)',
                    data: <?php echo json_encode($chart_totals); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0d6efd',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' ر.ي';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4], color: '#f0f0f0' },
                        ticks: { font: { family: 'Cairo' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Cairo' } }
                    }
                }
            }
        });
    }
});
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>