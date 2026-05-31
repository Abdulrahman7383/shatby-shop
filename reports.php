<?php
ob_start();
require_once '../includes/db.php'; 

// استقبال التواريخ
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-d');

// 1. استعلام الإحصائيات
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount_yer) as total_revenue,
        SUM(CASE WHEN status = 'تم التسليم' THEN total_amount_yer ELSE 0 END) as collected_cash
    FROM orders 
    WHERE status != 'ملغي' AND DATE(order_date) BETWEEN ? AND ?
");
$stats->execute([$start, $end]);
$summary = $stats->fetch();

// 2. استعلام البيانات التفصيلية
$stmt = $pdo->prepare("
    SELECT o.id, o.order_date, o.status, o.total_amount_yer, u.full_name 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status != 'ملغي' AND DATE(o.order_date) BETWEEN ? AND ?
    ORDER BY o.id ASC
");
$stmt->execute([$start, $end]);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المبيعات المطور</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #f0f7ff;
            --secondary-color: #10b981;
            --gray-100: #f3f4f6;
            --gray-700: #374151;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background: #f8fafc;
            font-size: 13px;
        }
        
        .report-header { 
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-right: 5px solid var(--primary-color);
            margin-bottom: 25px;
        }

        .filter-box { 
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }

        .table { border-radius: 12px; overflow: hidden; background: white; }
        .table thead { background: var(--primary-color); color: white; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-delivered { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0; }
            .report-header { box-shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    
    <div class="no-print mb-4">
        <div class="filter-box shadow-sm">
            <form id="filterForm" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light fw-bold">من</span>
                        <input type="date" name="start" onchange="this.form.submit()" class="form-control" value="<?php echo $start; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light fw-bold">إلى</span>
                        <input type="date" name="end" onchange="this.form.submit()" class="form-control" value="<?php echo $end; ?>">
                    </div>
                </div>
                <div class="col-md-4 text-start">
                    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm px-4 shadow-sm">
                        <i class="bi bi-printer-fill me-1"></i> طباعة التقرير
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm ms-2">إغلاق</a>
                </div>
            </form>
        </div>
        <div class="mt-2 small text-muted">
            <i class="bi bi-info-circle me-1"></i> يتم تحديث البيانات تلقائياً بمجرد تغيير التاريخ.
        </div>
    </div>

    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="fw-bold text-primary mb-1">مختبر الشطبي للكيماويات</h3>
                <h5 class="text-muted">تقرير المبيعات وحركة الصندوق</h5>
                <div class="mt-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 px-3 border border-primary border-opacity-25 rounded-pill">
                        <i class="bi bi-calendar3 me-1"></i> 
                        الفترة: <?php echo $start; ?> &nbsp; ⮕ &nbsp; <?php echo $end; ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-start mt-3 mt-md-0">
                <div class="p-3 bg-light rounded-3 small">
                    <strong>تاريخ الاستخراج:</strong><br>
                    <span class="text-secondary"><?php echo date('Y-m-d H:i'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 text-center">
        <div class="col-4">
            <div class="stat-card">
                <small class="text-muted d-block mb-1">عدد الطلبات</small>
                <h4 class="fw-bold mb-0 text-dark"><?php echo $summary['total_orders']; ?></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card" style="border-right: 4px solid #7c3aed;">
                <small class="text-muted d-block mb-1">إجمالي المبيعات</small>
                <h4 class="fw-bold mb-0" style="color: #7c3aed;"><?php echo number_format($summary['total_revenue']); ?> <small class="fs-6">ر.ي</small></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card" style="border-right: 4px solid var(--secondary-color);">
                <small class="text-muted d-block mb-1">النقد المستلم</small>
                <h4 class="fw-bold mb-0 text-success"><?php echo number_format($summary['collected_cash']); ?> <small class="fs-6">ر.ي</small></h4>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle shadow-sm">
            <thead>
                <tr class="text-center">
                    <th style="width: 50px;">#</th>
                    <th>رقم الطلب</th>
                    <th>العميل</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th class="text-end">القيمة (ر.ي)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                $data_found = false;
                while($row = $stmt->fetch()): 
                    $data_found = true;
                    $s_class = ($row['status'] == 'تم التسليم') ? 'status-delivered' : (($row['status'] == 'ملغي') ? 'status-cancelled' : 'status-pending');
                ?>
                <tr>
                    <td class="text-center text-muted"><?php echo $count++; ?></td>
                    <td class="text-center fw-bold text-primary">#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['full_name'] ?? 'عميل عام'); ?></td>
                    <td class="text-center"><?php echo date('Y-m-d', strtotime($row['order_date'])); ?></td>
                    <td class="text-center">
                        <span class="status-badge <?php echo $s_class; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td class="text-end fw-bold"><?php echo number_format($row['total_amount_yer']); ?></td>
                </tr>
                <?php endwhile; ?>
                
                <?php if (!$data_found): ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        لا توجد عمليات مبيعات في هذه الفترة.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-bold py-3">الإجمالي النهائي للتقرير:</td>
                    <td class="text-end fw-bold text-primary py-3 fs-5"><?php echo number_format($summary['total_revenue']); ?> ر.ي</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="row mt-5 text-center g-4">
        <div class="col-4">
            <div class="p-3 border-top border-2">توقيع المحاسب</div>
        </div>
        <div class="col-4">
            <div class="p-3 border-top border-2">ختم المنشأة</div>
        </div>
        <div class="col-4">
            <div class="p-3 border-top border-2">توقيع المدير العام</div>
        </div>
    </div>
</div>

</body>
</html>