<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. الاتصال والقواعد
require_once __DIR__ . '/../includes/db.php';

// التحقق من أن المستخدم مدير
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 2. معالجة إضافة وحدة جديدة
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_unit'])) {
    $unit_name = trim($_POST['unit_name']);
    
    if (!empty($unit_name)) {
        try {
            // التحقق من عدم التكرار
            $stmt = $pdo->prepare("SELECT id FROM units WHERE name = ?");
            $stmt->execute([$unit_name]);
            if ($stmt->rowCount() > 0) {
                $msg = '<div class="alert alert-warning">هذه الوحدة موجودة مسبقاً!</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO units (name) VALUES (?)");
                $stmt->execute([$unit_name]);
                $msg = '<div class="alert alert-success">تمت إضافة الوحدة بنجاح ✅</div>';
            }
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger">خطأ: ' . $e->getMessage() . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">الرجاء كتابة اسم الوحدة</div>';
    }
}

// 3. معالجة حذف وحدة
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // يمكن إضافة شرط هنا لمنع حذف وحدة مستخدمة بالفعل في منتجات
        $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: units.php?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $msg = '<div class="alert alert-danger">لا يمكن حذف هذه الوحدة لأنها مرتبطة بمنتجات</div>';
    }
}

// 4. جلب كافة الوحدات للعرض
$units = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
            <h3 class="fw-bold text-primary"><i class="bi bi-rulers me-2"></i> إدارة الوحدات</h3>
            <a href="product_add.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-right"></i> الذهاب لإضافة منتج
            </a>
        </div>

        <div class="col-12 mb-3">
            <?php 
            echo $msg; 
            if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
                echo '<div class="alert alert-info alert-dismissible fade show">تم حذف الوحدة بنجاح <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
            ?>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold m-0 text-success">إضافة وحدة جديدة</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">اسم الوحدة</label>
                            <input type="text" name="unit_name" class="form-control rounded-3" placeholder="مثال: كرتونة، جرام..." required>
                        </div>
                        <button type="submit" name="add_unit" class="btn btn-success w-100 rounded-pill fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> حفظ الوحدة
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold m-0 text-dark">قائمة الوحدات المتوفرة (<?php echo count($units); ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">اسم الوحدة</th>
                                    <th class="px-4 py-3 text-end">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($units) > 0): ?>
                                    <?php foreach ($units as $u): ?>
                                    <tr>
                                        <td class="px-4"><?php echo $u['id']; ?></td>
                                        <td class="px-4 fw-bold text-primary"><?php echo htmlspecialchars($u['name']); ?></td>
                                        <td class="px-4 text-end">
                                            <a href="units.php?delete=<?php echo $u['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-pill"
                                               onclick="return confirm('هل أنت متأكد من حذف هذه الوحدة؟');">
                                                <i class="bi bi-trash"></i> حذف
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">لا توجد وحدات مضافة بعد.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ob_end_flush(); ?>