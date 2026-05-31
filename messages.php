<?php
ob_start();
include 'includes/header.php';

$msg = "";
$view_message = null; // لتخزين الرسالة المراد عرضها

// 1. معالجة الحذف
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: messages.php?msg=deleted");
    exit;
}

// 2. معالجة عرض الرسالة (فتحها + تحديث الحالة إلى مقروءة)
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    
    // تحديث الحالة إلى "مقرؤة"
    $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$id]);
    
    // جلب تفاصيل الرسالة لعرضها في النافذة المنبثقة
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $view_message = $stmt->fetch();
}

// 3. جلب جميع الرسائل (الأحدث أولاً)
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();

// 4. إحصائيات سريعة
$unread_count = 0;
foreach ($messages as $m) { if ($m['is_read'] == 0) $unread_count++; }
?>

<div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-envelope-paper text-primary"></i> صندوق الوارد</h3>
            <p class="text-muted mb-0">رسائل واستفسارات الزبائن من نموذج التواصل</p>
        </div>
        <div>
            <?php if($unread_count > 0): ?>
            <span class="badge bg-danger rounded-pill px-3 py-2 fs-6 shadow-sm">
                <i class="bi bi-bell-fill me-1"></i> <?php echo $unread_count; ?> رسائل جديدة
            </span>
            <?php else: ?>
            <span class="badge bg-success rounded-pill px-3 py-2 fs-6 shadow-sm">
                <i class="bi bi-check-all me-1"></i> كل الرسائل مقروءة
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success shadow-sm rounded-3">تم حذف الرسالة بنجاح.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">المرسل</th>
                            <th>الموضوع</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th class="text-end pe-4">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $row): ?>
                            <tr class="<?php echo $row['is_read'] == 0 ? 'bg-primary bg-opacity-10 fw-bold' : ''; ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-white text-primary border d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 40px; height: 40px;">
                                            <?php echo mb_substr($row['name'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="text-dark"><?php echo htmlspecialchars($row['name']); ?></div>
                                            <div class="small text-muted fw-normal"><?php echo htmlspecialchars($row['phone']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark"><?php echo htmlspecialchars($row['subject']); ?></span>
                                    <div class="small text-muted text-truncate fw-normal" style="max-width: 250px;">
                                        <?php echo htmlspecialchars($row['message']); ?>
                                    </div>
                                </td>
                                <td class="small text-muted">
                                    <?php 
                                    $date = date('Y/m/d', strtotime($row['created_at']));
                                    $time = date('h:i A', strtotime($row['created_at']));
                                    echo "<div>$date</div><div>$time</div>";
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['is_read']): ?>
                                        <span class="badge bg-light text-secondary border">مقرؤة</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">جديدة</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="messages.php?view=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                        <i class="bi bi-eye"></i> قراءة
                                    </a>
                                    <a href="messages.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle ms-1" onclick="return confirm('حذف هذه الرسالة؟')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                    لا توجد رسائل واردة حتى الآن.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($view_message): ?>
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">تفاصيل الرسالة #<?php echo $view_message['id']; ?></h5>
                <a href="messages.php" class="btn-close"></a>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted fw-bold">المرسل:</small>
                        <div class="fs-5"><?php echo htmlspecialchars($view_message['name']); ?></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted fw-bold">التاريخ:</small>
                        <div><?php echo date('d/m/Y - h:i A', strtotime($view_message['created_at'])); ?></div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="bg-light p-2 rounded">
                            <i class="bi bi-envelope me-2 text-primary"></i> 
                            <a href="mailto:<?php echo $view_message['email']; ?>" class="text-decoration-none text-dark"><?php echo $view_message['email']; ?></a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-2 rounded">
                            <i class="bi bi-telephone me-2 text-primary"></i> 
                            <a href="tel:<?php echo $view_message['phone']; ?>" class="text-decoration-none text-dark"><?php echo $view_message['phone'] ?: 'غير متوفر'; ?></a>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <small class="text-muted fw-bold d-block mb-2">الموضوع:</small>
                        <h6 class="fw-bold border-bottom pb-2"><?php echo htmlspecialchars($view_message['subject']); ?></h6>
                    </div>
                    
                    <div class="col-12">
                        <small class="text-muted fw-bold d-block mb-2">نص الرسالة:</small>
                        <div class="bg-light p-3 rounded-3 border" style="min-height: 150px; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($view_message['message'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <a href="mailto:<?php echo $view_message['email']; ?>" class="btn btn-primary rounded-pill">
                    <i class="bi bi-reply-fill"></i> رد عبر الإيميل
                </a>
                <?php if($view_message['phone']): ?>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $view_message['phone']); ?>" target="_blank" class="btn btn-success rounded-pill">
                    <i class="bi bi-whatsapp"></i> رد عبر واتساب
                </a>
                <?php endif; ?>
                <a href="messages.php" class="btn btn-secondary rounded-pill">إغلاق</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('messageModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php 
ob_end_flush();
include 'includes/footer.php'; 
?>