<?php
include 'includes/header.php';

// كود حذف العرض مع تأكيد محسن
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM offers WHERE id = ?")->execute([$id]);
    
    // إضافة رسالة نجاح باستخدام session
    $_SESSION['success_message'] = 'تم حذف العرض بنجاح';
    echo "<script>window.location.href='offers.php';</script>";
    exit();
}

// كود تفعيل/تعطيل العرض مع رسالة تأكيد
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    
    // الحصول على حالة العرض الحالية
    $stmt = $pdo->prepare("SELECT is_active FROM offers WHERE id = ?");
    $stmt->execute([$id]);
    $current_status = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("UPDATE offers SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success_message'] = $current_status ? 'تم تعطيل العرض بنجاح' : 'تم تفعيل العرض بنجاح';
    echo "<script>window.location.href='offers.php';</script>";
    exit();
}

// عرض رسائل النجاح
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['success_message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success_message']);
}

// فلترة البحث
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// بناء الاستعلام مع الفلترة
$sql = "SELECT o.*, p.name as product_name, p.image as product_image 
        FROM offers o 
        JOIN products p ON o.product_id = p.id 
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR o.discount_percentage LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $sql .= " AND o.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND o.is_active = 0";
    } elseif ($status_filter === 'expired') {
        $sql .= " AND o.end_date < CURDATE()";
    } elseif ($status_filter === 'upcoming') {
        $sql .= " AND o.start_date > CURDATE()";
    }
}

$sql .= " ORDER BY o.id DESC";

// تنفيذ الاستعلام
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$offers = $stmt->fetchAll();

// إحصائيات
$active_count = $pdo->query("SELECT COUNT(*) FROM offers WHERE is_active = 1")->fetchColumn();
$expiring_count = $pdo->query("SELECT COUNT(*) FROM offers WHERE end_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND end_date >= CURDATE()")->fetchColumn();
$avg_discount = $pdo->query("SELECT AVG(discount_percentage) FROM offers WHERE is_active = 1")->fetchColumn();
$total_offers = $pdo->query("SELECT COUNT(*) FROM offers")->fetchColumn();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-tags fa-2x text-primary"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0">🎯 إدارة العروض الترويجية</h1>
                    <p class="text-muted mb-0">إدارة وتتبع جميع العروض الحالية والمستقبلية</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a href="offer_add.php" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>إضافة عرض جديد
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-hover border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-2">إجمالي العروض</h6>
                            <h3 class="card-title mb-0"><?php echo $total_offers; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-tags fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card card-hover border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-2">العروض النشطة</h6>
                            <h3 class="card-title mb-0 text-success"><?php echo $active_count; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-bolt fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card card-hover border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-2">عروض قاربت على الانتهاء</h6>
                            <h3 class="card-title mb-0 text-warning"><?php echo $expiring_count; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card card-hover border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-2">متوسط نسبة الخصم</h6>
                            <h3 class="card-title mb-0 text-info"><?php echo number_format($avg_discount, 1); ?>%</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-percent fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0" 
                               name="search" 
                               placeholder="ابحث عن منتج أو نسبة خصم..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>جميع الحالات</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>العروض النشطة</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>العروض المعطلة</option>
                        <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>العروض المنتهية</option>
                        <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>العروض القادمة</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>تصفية
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">قائمة العروض</h5>
                <span class="badge bg-light text-dark">
                    <?php echo count($offers); ?> عرض
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th>المنتج</th>
                            <th>نسبة الخصم</th>
                            <th>الفترة الزمنية</th>
                            <th>الحالة</th>
                            <th>الأيام المتبقية</th>
                            <th width="150">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($offers) > 0): ?>
                            <?php foreach ($offers as $offer): 
                                $today = new DateTime();
                                $start_date = new DateTime($offer['start_date']);
                                $end_date = new DateTime($offer['end_date']);
                                $days_left = $today->diff($end_date)->days;
                                $is_active = $offer['is_active'];
                                $is_expired = $end_date < $today;
                                $is_upcoming = $start_date > $today;
                                
                                // تحديد لون الحالة
                                $status_color = 'secondary';
                                $status_text = 'معطل';
                                
                                if ($is_expired) {
                                    $status_color = 'dark';
                                    $status_text = 'منتهي';
                                } elseif ($is_upcoming) {
                                    $status_color = 'info';
                                    $status_text = 'قادم';
                                } elseif ($is_active) {
                                    $status_color = 'success';
                                    $status_text = 'نشط';
                                }
                            ?>
                                <tr class="<?php echo $is_expired ? 'table-light' : ''; ?>">
                                    <td>
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <span class="text-muted"><?php echo $offer['id']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($offer['product_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($offer['product_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($offer['product_name']); ?>"
                                                     class="rounded me-3" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-box text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($offer['product_name']); ?></h6>
                                                <small class="text-muted">رقم المنتج: <?php echo $offer['product_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger bg-gradient fs-6 py-2 px-3 rounded-pill">
                                            <i class="fas fa-percent me-1"></i>
                                            <?php echo $offer['discount_percentage']; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-nowrap">
                                            <div>
                                                <small class="text-muted">من:</small>
                                                <strong><?php echo $offer['start_date']; ?></strong>
                                            </div>
                                            <div>
                                                <small class="text-muted">إلى:</small>
                                                <strong><?php echo $offer['end_date']; ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!$is_expired): ?>
                                            <a href="offers.php?toggle=<?php echo $offer['id']; ?>" 
                                               class="btn btn-sm btn-<?php echo $status_color; ?> btn-hover"
                                               onclick="return confirm('هل تريد تغيير حالة هذا العرض؟')">
                                                <i class="fas fa-<?php echo $is_active ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                <?php echo $status_text; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-<?php echo $status_color; ?> py-2 px-3">
                                                <?php echo $status_text; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_expired): ?>
                                            <span class="badge bg-dark py-2">
                                                <i class="fas fa-ban me-1"></i>منتهي
                                            </span>
                                        <?php elseif ($is_upcoming): ?>
                                            <span class="badge bg-info py-2">
                                                يبدأ بعد <?php echo $today->diff($start_date)->days; ?> يوم
                                            </span>
                                        <?php elseif ($days_left <= 3): ?>
                                            <span class="badge bg-warning py-2 pulse-animation">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <?php echo $days_left; ?> يوم
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success py-2">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $days_left; ?> يوم
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="offer_edit.php?id=<?php echo $offer['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               data-bs-toggle="tooltip"
                                               title="تعديل العرض">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger delete-offer"
                                                    data-id="<?php echo $offer['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($offer['product_name']); ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="حذف العرض">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                            <a href="../product/<?php echo $offer['product_id']; ?>" 
                                               class="btn btn-outline-secondary"
                                               target="_blank"
                                               data-bs-toggle="tooltip"
                                               title="عرض المنتج">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-tags fa-3x opacity-25"></i>
                                    </div>
                                    <h5 class="text-muted">لا توجد عروض</h5>
                                    <p class="text-muted mb-4">لم يتم إضافة أي عروض حتى الآن</p>
                                    <a href="offer_add.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>إضافة أول عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (count($offers) > 0): ?>
        <div class="card-footer bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">عرض <?php echo count($offers); ?> من <?php echo $total_offers; ?> عرض</small>
                </div>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>طباعة
                    </button>
                    <button class="btn btn-outline-success btn-sm ms-2">
                        <i class="fas fa-file-export me-1"></i>تصدير
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>تأكيد الحذف
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من حذف العرض <strong id="offerName"></strong>؟</p>
                <p class="text-muted small">هذا الإجراء لا يمكن التراجع عنه.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">حذف العرض</a>
            </div>
        </div>
    </div>
</div>

<style>
.card-hover:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.btn-hover:hover {
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.table > :not(:first-child) {
    border-top: 1px solid #eef2f7;
}

.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.pulse-animation {
    animation: pulse 2s infinite;
}

.badge.bg-gradient {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.input-group-text {
    border-right: 1px solid #dee2e6;
    border-left: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة أدوات التلميح
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // التعامل مع حذف العروض
    document.querySelectorAll('.delete-offer').forEach(button => {
        button.addEventListener('click', function() {
            const offerId = this.getAttribute('data-id');
            const offerName = this.getAttribute('data-name');
            
            document.getElementById('offerName').textContent = offerName;
            document.getElementById('confirmDelete').href = 'offers.php?delete=' + offerId;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    });
    
    // فلترة تلقائية عند تغيير الحالة
    document.querySelector('select[name="status"]').addEventListener('change', function() {
        this.form.submit();
    });
    
    // تأثيرات للبطاقات
    const cards = document.querySelectorAll('.card-hover');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });
    });
    
    // إشعارات الوقت الفعلي
    function updateTimeRemaining() {
        document.querySelectorAll('.badge').forEach(badge => {
            const text = badge.textContent;
            if (text.includes('يوم') && !text.includes('منتهي') && !text.includes('يبدأ')) {
                // يمكن إضافة تحديث للوقت هنا إذا لزم الأمر
            }
        });
    }
    
    // تحديث كل 5 دقائق
    setInterval(updateTimeRemaining, 300000);
});
</script>

<?php include 'includes/footer.php'; ?>