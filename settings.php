<?php
ob_start();
include 'includes/header.php';

$msg = "";
$admin_id = $_SESSION['admin_id'];

// ==============================
// 1. معالجة تحديث سعر الصرف
// ==============================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_general'])) {
    $rate = (float)$_POST['currency_rate'];
    $site_name = trim($_POST['site_name']);
    
    if ($rate > 0) {
        $stmt = $pdo->prepare("UPDATE settings SET currency_rate = ?, site_name = ? WHERE id = 1");
        if ($stmt->execute([$rate, $site_name])) {
            $msg = '<div class="alert alert-success shadow-sm">✅ تم تحديث الإعدادات وسعر الصرف بنجاح.</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning shadow-sm">⚠️ سعر الصرف يجب أن يكون أكبر من صفر.</div>';
    }
}

// ==============================
// 2. معالجة تغيير كلمة المرور
// ==============================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // جلب بيانات المدير الحالي
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $current_user = $stmt->fetch();

    if (password_verify($current_pass, $current_user['password'])) {
        if ($new_pass === $confirm_pass) {
            if (strlen($new_pass) >= 6) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($upd->execute([$new_hash, $admin_id])) {
                    $msg = '<div class="alert alert-success shadow-sm">🔐 تم تغيير كلمة المرور بنجاح!</div>';
                }
            } else {
                $msg = '<div class="alert alert-warning shadow-sm">⚠️ كلمة المرور يجب أن تكون 6 أحرف على الأقل.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger shadow-sm">❌ كلمة المرور الجديدة غير متطابقة.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger shadow-sm">❌ كلمة المرور الحالية غير صحيحة.</div>';
    }
}

// جلب الإعدادات الحالية للعرض
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-gear-fill text-primary"></i> الإعدادات العامة</h3>
        <p class="text-muted">التحكم في تسعير النظام وأمان الحساب</p>
    </div>

    <?php echo $msg; ?>

    <div class="row g-4">
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-primary mb-0">⚙️ إعدادات النظام</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">اسم المتجر</label>
                            <input type="text" name="site_name" class="form-control bg-light border-0" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-success">سعر صرف الدولار (1$ = كم ريال يمني؟)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white border-0"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" step="0.01" name="currency_rate" class="form-control bg-light border-0 fw-bold text-success fs-5" value="<?php echo $settings['currency_rate']; ?>">
                                <span class="input-group-text bg-light border-0 fw-bold">R.Y</span>
                            </div>
                            <div class="form-text text-muted">
                                <i class="bi bi-info-circle"></i> تنبيه: تغيير هذا الرقم سيقوم بتحديث أسعار جميع المنتجات في المتجر فوراً.
                            </div>
                        </div>

                        <button type="submit" name="update_general" class="btn btn-primary w-100 rounded-pill shadow-sm">
                            <i class="bi bi-save"></i> حفظ الإعدادات
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-danger mb-0">🔐 أمان الحساب</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control bg-light border-0" required>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                                <input type="password" name="new_password" class="form-control bg-light border-0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">تأكيد الجديدة</label>
                                <input type="password" name="confirm_password" class="form-control bg-light border-0" required>
                            </div>
                        </div>

                        <button type="submit" name="update_password" class="btn btn-outline-danger w-100 rounded-pill">
                            <i class="bi bi-shield-lock"></i> تغيير كلمة المرور
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center rounded-3">
                <i class="bi bi-info-circle-fill fs-3 me-3"></i>
                <div>
                    <strong>هل تعلم؟</strong>
                    <p class="mb-0 small">عند تغيير سعر الصرف هنا، يتم حساب الأسعار في واجهة المستخدم تلقائياً كالتالي: (السعر بالدولار × سعر الصرف الجديد).</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
ob_end_flush();
include 'includes/footer.php'; 
?>