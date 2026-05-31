<?php
ob_start();
require_once '../includes/db.php'; // اتصال مباشر للأداء العالي

// 1. استلام الفلاتر وتنظيفها
$start_date  = sanitize($_GET['start'] ?? date('Y-m-01'));
$end_date    = sanitize($_GET['end']   ?? date('Y-m-d'));
$filter_type = sanitize($_GET['type']  ?? 'all');

// 2. بناء الاستعلام الذكي (جلب الأعمدة الضرورية فقط)
$sql = "SELECT l.id, l.type, l.quantity, l.reason, l.created_at, 
               p.name as product_name, u.full_name 
        FROM inventory_logs l 
        LEFT JOIN products p ON l.product_id = p.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE DATE(l.created_at) BETWEEN ? AND ?";

$params = [$start_date, $end_date];

if ($filter_type != 'all') {
    $sql .= " AND l.type = ?";
    $params[] = $filter_type;
}

$sql .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المخزون | <?php echo $start_date; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #fff; font-size: 13px; }
        .report-header { border-bottom: 2px solid #2155CD; padding-bottom: 15px; margin-bottom: 25px; }
        .badge-in { background-color: #d1e7dd; color: #0f5132; }
        .badge-out { background-color: #f8d7da; color: #842029; }
        .badge-adj { background-color: #fff3cd; color: #664d03; }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .container { max-width: 100% !important; width: 100% !important; }
            .card { border: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="no-print d-flex justify-content-between mb-4 bg-light p-3 rounded shadow-sm">
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary fw-bold">🖨️ طباعة التقرير</button>
            <form method="GET" class="d-flex gap-2 ms-3">
                <input type="hidden" name="start" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end" value="<?php echo $end_date; ?>">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_type=='all'?'selected':''; ?>>كل الحركات</option>
                    <option value="in" <?php echo $filter_type=='in'?'selected':''; ?>>وارد (مشتريات)</option>
                    <option value="out" <?php echo $filter_type=='out'?'selected':''; ?>>صادر (مبيعات)</option>
                </select>
            </form>
        </div>
        <button onclick="window.close()" class="btn btn-outline-secondary">إغلاق</button>
    </div>

    <div class="report-header text-center">
        <h2 class="fw-bold text-primary">نظام الشطبي لإدارة الكيماويات</h2>
        <h5 class="text-dark">سجل حركة المخزون التفصيلي</h5>
        <div class="mt-2 text-muted">
            الفترة: من <strong><?php echo $start_date; ?></strong> إلى <strong><?php echo $end_date; ?></strong>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th width="50">#</th>
                    <th>اسم الصنف</th>
                    <th class="text-center">النوع</th>
                    <th class="text-center">الكمية</th>
                    <th>التاريخ والوقت</th>
                    <th>البيان / السبب</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                while($row = $stmt->fetch()): 
                    $type_label = ($row['type'] == 'in') ? 'وارد (+)' : (($row['type'] == 'out') ? 'صادر (-)' : 'تسوية');
                    $badge_class = ($row['type'] == 'in') ? 'badge-in' : (($row['type'] == 'out') ? 'badge-out' : 'badge-adj');
                ?>
                <tr>
                    <td><?php echo $count++; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td class="text-center">
                        <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill border">
                            <?php echo $type_label; ?>
                        </span>
                    </td>
                    <td class="text-center fw-bold fs-6"><?php echo abs($row['quantity']); ?></td>
                    <td class="small"><?php echo date('Y/m/d h:i A', strtotime($row['created_at'])); ?></td>
                    <td class="text-muted small"><?php echo htmlspecialchars($row['reason'] ?? $row['full_name']); ?></td>
                </tr>
                <?php endwhile; ?>

                <?php if($count == 1): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">لا توجد سجلات لهذه الفترة</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-5 row text-center fw-bold">
        <div class="col-4">أمين المستودع<br><br>...................</div>
        <div class="col-4">المراجع المالي<br><br>...................</div>
        <div class="col-4">مدير المختبر<br><br>...................</div>
    </div>

    <div class="text-center mt-5 pt-4 border-top text-muted small">
        تم استخراج التقرير آلياً بواسطة نظام الشطبي | <?php echo date('Y/m/d H:i'); ?>
    </div>
</div>

</body>
</html>