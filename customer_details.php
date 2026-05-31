<?php
ob_start();
include 'includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit;
}

$user_id = (int)$_GET['id'];

// 1. جلب بيانات العميل
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) { echo "<div class='alert alert-danger m-4'>العميل غير موجود</div>"; include 'includes/footer.php'; exit; }

// 2. جلب طلبات العميل
$stmt_orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt_orders->execute([$user_id]);
$orders = $stmt_orders->fetchAll();

// إحصائيات سريعة
$total_spent = 0;
foreach($orders as $o) { if($o['status']=='delivered') $total_spent += $o['total_amount_yer']; }
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="customers.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="bi bi-arrow-right"></i> عودة للقائمة</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 text-center h-100">
                <div class="card-body p-4">
                    <div class="mb-3 mx-auto bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-2 fw-bold" style="width: 80px; height: 80px;">
                        <?php echo mb_substr($user['full_name'], 0, 1, 'UTF-8'); ?>
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                    <span class="badge bg-light text-muted border mb-3">ID: #<?php echo $user['id']; ?></span>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <?php if($user['status'] == 'active'): ?>
                            <span class="badge bg-success-soft text-success"><i class="bi bi-check-circle"></i> حساب نشط</span>
                        <?php else: ?>
                            <span class="badge bg-danger-soft text-danger"><i class="bi bi-slash-circle"></i> محظور</span>
                        <?php endif; ?>
                    </div>

                    <div class="text-start bg-light p-3 rounded-3">
                        <div class="mb-2"><i class="bi bi-envelope text-primary me-2"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="mb-2"><i class="bi bi-telephone text-primary me-2"></i> <?php echo htmlspecialchars($user['phone'] ?: 'غير متوفر'); ?></div>
                        <div class="mb-2"><i class="bi bi-calendar text-primary me-2"></i> سجل في: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></div>
                        <?php if($user['google_id']): ?>
                        <div class="mt-2 text-center border-top pt-2">
                            <small class="text-muted"><i class="bi bi-google"></i> مسجل بواسطة جوجل</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3">
                    <div class="row text-center">
                        <div class="col">
                            <h6 class="mb-0 fw-bold text-dark"><?php echo count($orders); ?></h6>
                            <small class="text-muted">طلب</small>
                        </div>
                        <div class="col border-start">
                            <h6 class="mb-0 fw-bold text-success"><?php echo number_format($total_spent); ?></h6>
                            <small class="text-muted">مشتريات (ر.ي)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">📦 سجل الطلبات</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">رقم الطلب</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($orders) > 0): ?>
                                    <?php foreach($orders as $order): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">#<?php echo $order['id']; ?></td>
                                        <td class="fw-bold"><?php echo number_format($order['total_amount_yer']); ?> ر.ي</td>
                                        <td>
                                            <?php 
                                            $st_colors = ['pending'=>'warning', 'delivered'=>'success', 'cancelled'=>'danger', 'shipped'=>'primary'];
                                            $st_text = ['pending'=>'قيد الانتظار', 'delivered'=>'مكتمل', 'cancelled'=>'ملغي', 'shipped'=>'تم الشحن'];
                                            $c = $st_colors[$order['status']] ?? 'secondary';
                                            $t = $st_text[$order['status']] ?? $order['status'];
                                            ?>
                                            <span class="badge bg-<?php echo $c; ?> bg-opacity-10 text-<?php echo $c; ?> px-2"><?php echo $t; ?></span>
                                        </td>
                                        <td class="text-muted small"><?php echo date('Y/m/d H:i', strtotime($order['order_date'])); ?></td>
                                        <td class="text-end pe-3">
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-dark">عرض</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">لا يوجد طلبات لهذا العميل حتى الآن.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
ob_end_flush();
include 'includes/footer.php'; 
?>