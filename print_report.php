<!DOCTYPE html>
<?php
// استدعاء الهيدر ضروري للاتصال بقاعدة البيانات، لكن سنخفي محتواه بالـ CSS
include 'includes/header.php'; 

$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');

// استعلام الإجماليات
$sql_summary = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount_yer), 0) as total_revenue
                FROM orders 
                WHERE status != 'cancelled' 
                AND DATE(order_date) BETWEEN ? AND ?";
$stmt = $pdo->prepare($sql_summary);
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch();

// استعلام التفاصيل
$sql_details = "SELECT o.id, o.order_date, u.full_name, o.total_amount_yer, o.status 
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.status != 'cancelled'
                AND DATE(o.order_date) BETWEEN ? AND ?
                ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql_details);
$stmt->execute([$start_date, $end_date]);
$details = $stmt->fetchAll();
?>

<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المبيعات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        /* === تصميم الصفحة الأساسي === */
        body {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background: #f4f6fa;
            margin: 0; 
            padding: 0;
        }

        /* حاوية التقرير (الورقة البيضاء) */
        .report-container {
            max-width: 950px;
            margin: 40px auto;
            background: #fff;
            box-shadow: 0 6px 30px rgba(0,0,0,0.10);
            border-radius: 14px;
            padding: 30px 32px;
            position: relative;
            z-index: 1050; /* لتظهر فوق أي عنصر آخر */
        }

        /* === إعدادات الطباعة الصارمة (الحل للمشكلة) === */
        @media print {
            /* 1. إخفاء كل شيء في الصفحة مبدئياً */
            body * {
                visibility: hidden;
            }

            /* 2. إخفاء القوائم الجانبية والعلوية بشكل قاطع */
            .sidebar, nav, header, footer, .navbar, .admin-panel-elements {
                display: none !important;
            }

            /* 3. إظهار فقط حاوية التقرير ومحتوياتها */
            .report-container, .report-container * {
                visibility: visible;
            }

            /* 4. ضبط موقع التقرير ليملأ الورقة بالكامل */
            .report-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0 !important;
                padding: 20px !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            /* إخفاء الأزرار */
            .no-print {
                display: none !important;
            }
            
            /* إجبار الخلفيات والألوان على الظهور */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* بقية التنسيقات (الجدول، الترويسة، الخ) */
        .top-header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .header-title {
            font-size: 2.1rem;
            color: #2155CD;
            font-weight: bold;
            text-align: center;
        }
        .header-text-block { text-align: center; color: #333; line-height: 1.6; font-weight: bold; }
        .header-date { font-size: 1.1rem; color: #666; margin-top: 5px; text-align: center; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 20px; }
        thead th { background: #2155CD; color: #fff; padding: 12px; border-bottom: 3px solid #1a44a5; text-align: center; }
        tbody td { padding: 10px; border-bottom: 1px solid #e3e3e8; text-align: center; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tfoot th { padding: 15px; background: #f1f6fd; color: #333; text-align: center; }
        
        .action-btn {
            background: #2155CD; color: #fff; border: none; padding: 10px 25px; 
            border-radius: 5px; cursor: pointer; font-family: 'Cairo'; margin-bottom: 20px;
        }
        .search-box { background: #eee; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .search-box input { padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-family: 'Cairo'; }
    </style>
</head>
<body>

    <div class="report-container">
        
        <div class="no-print">
            <div class="search-box">
                <form method="GET">
                    <label>من:</label> <input type="date" name="start" value="<?php echo $start_date; ?>">
                    <label>إلى:</label> <input type="date" name="end" value="<?php echo $end_date; ?>">
                    <button type="submit" style="background:#2c3e50; color:#fff; border:none; padding:8px 15px; border-radius:4px; font-family:'Cairo'; cursor:pointer;">تحديث</button>
                </form>
            </div>
            <button class="action-btn" onclick="window.print()">🖨️ طباعة التقرير</button>
            <a href="reports.php" style="text-decoration:none; color:#666; margin-right:15px;">رجوع</a>
        </div>

        <div class="top-header-container">
            <div><img src="../assets/images/logo.png" width="80" alt="logo" onerror="this.style.visibility='hidden'"></div>
            <div class="header-text-block">
                الجمهورية اليمنية<br>
                نظام إدارة المبيعات<br>
                التقارير المالية
            </div>
            <div><img src="../assets/images/logo.png" width="80" style="visibility:hidden"></div>
        </div>

        <div class="header-title">تقرير المبيعات التفصيلي</div>
        <div class="header-date">
            للفترة من: <b><?php echo $start_date; ?></b> &nbsp; إلى: <b><?php echo $end_date; ?></b>
            <br>
            <span style="font-size:0.8em; color:#555">عدد العمليات: <?php echo $summary['total_orders']; ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>رقم الطلب</th>
                    <th>اسم العميل</th>
                    <th>المبلغ (ر.ي)</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($details) > 0): ?>
                    <?php $x = 1; foreach ($details as $row): ?>
                    <tr>
                        <td><?php echo $x++; ?></td>
                        <td style="font-weight:bold;">#<?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name'] ?? 'عميل عام'); ?></td>
                        <td style="font-weight:bold; color:#2155CD;"><?php echo number_format($row['total_amount_yer']); ?></td>
                        <td><?php echo $row['status']; ?></td>
                        <td><?php echo date('Y/m/d', strtotime($row['order_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">لا توجد مبيعات في هذه الفترة</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" style="color:#0B9444; font-size:1.1rem; font-weight:bold;">
                        الإجمالي الكلي للإيرادات: <?php echo number_format($summary['total_revenue']); ?> ريال
                    </th>
                </tr>
                <tr>
                    <th colspan="6" style="text-align:left; padding-left:50px; padding-top:40px;">
                        توقيع المسؤول المالي / ..............................
                    </th>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top:30px; border-top:1px dashed #ddd; padding-top:10px; text-align:center; color:#777; font-size:0.8rem;">
            تم استخراج هذا التقرير من النظام آلياً | <?php echo date('Y'); ?> &copy;
        </div>

    </div>

</body>
</html>