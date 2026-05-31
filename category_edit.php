<?php
// تفعيل التخزين المؤقت لمنع أخطاء الهيدر
ob_start();

include 'includes/header.php';

// 1. التحقق من وجود المعرف ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: categories.php");
    exit;
}

$id = (int)$_GET['id'];
$msg = "";

// 2. جلب البيانات الحالية أولاً (قبل التحديث) لاستخدامها في التحقق
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if (!$category) {
        echo "<div class='container py-5 text-center'><div class='alert alert-danger'>عذراً، هذا التصنيف غير موجود. <a href='categories.php'>عودة</a></div></div>";
        include 'includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// 3. معالجة التحديث عند الضغط على زر الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $icon_name = $category['icon']; // القيمة الافتراضية هي الأيقونة القديمة

    if (!empty($name)) {
        try {
            // معالجة رفع أيقونة جديدة إذا تم اختيار ملف
            if (!empty($_FILES['icon']['name'])) {
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                $file_ext = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));
                $file_size = $_FILES['icon']['size'];

                if (in_array($file_ext, $allowed_exts) && $file_size < 1 * 1024 * 1024) {
                    $new_icon_name = 'cat_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_dir = "../assets/category_icons/";

                    if (move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $new_icon_name)) {
                        // حذف الأيقونة القديمة من السيرفر إذا لم تكن الافتراضية
                        if ($category['icon'] !== 'default-cat.png' && file_exists($upload_dir . $category['icon'])) {
                            unlink($upload_dir . $category['icon']);
                        }
                        $icon_name = $new_icon_name;
                    }
                } else {
                    $msg = '<div class="alert alert-warning shadow-sm border-0">⚠️ صيغة الملف غير مدعومة أو الحجم كبير جداً.</div>';
                }
            }

            // تحديث البيانات في قاعدة البيانات
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $icon_name, $id])) {
                $msg = '<div class="alert alert-success shadow-sm border-0">
                            <i class="bi bi-check-circle-fill"></i> تم تحديث بيانات التصنيف بنجاح!
                            <meta http-equiv="refresh" content="2;url=categories.php">
                        </div>';
                // تحديث بيانات المتغير لعرضها في النموذج فوراً
                $category['name'] = $name;
                $category['description'] = $description;
                $category['icon'] = $icon_name;
            }
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger shadow-sm border-0">خطأ في قاعدة البيانات: ' . $e->getMessage() . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning shadow-sm border-0">يرجى كتابة اسم التصنيف.</div>';
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark mb-0">
                    <i class="bi bi-pencil-square text-primary"></i> تعديل التصنيف
                </h3>
                <a href="categories.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-arrow-right"></i> عودة للقائمة
                </a>
            </div>

            <?php echo $msg; ?>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <h5 class="mb-0 fw-normal">تعديل: <strong><?php echo htmlspecialchars($category['name']); ?></strong></h5>
                </div>
                
                <div class="card-body p-4 bg-white">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">اسم التصنيف <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-tag text-primary"></i></span>
                                <input type="text" name="name" 
                                       class="form-control bg-light border-0 py-2" 
                                       value="<?php echo htmlspecialchars($category['name']); ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-8">
                                <label class="form-label fw-bold text-secondary">أيقونة القسم الجديدة <small class="text-muted">(اختياري)</small></label>
                                <input type="file" name="icon" class="form-control bg-light border-0" accept="image/*">
                                <div class="form-text">يفضل استخدام صور PNG شفافة أو SVG بحجم صغير.</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <label class="form-label fw-bold text-secondary d-block">الأيقونة الحالية</label>
                                <?php 
                                    $current_icon = !empty($category['icon']) ? "../assets/category_icons/" . $category['icon'] : "../assets/images/default-cat.png";
                                ?>
                                <img src="<?php echo $current_icon; ?>" class="img-thumbnail p-2 bg-light" style="width: 80px; height: 80px; object-fit: contain;">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">الوصف (اختياري)</label>
                            <textarea name="description" 
                                      class="form-control bg-light border-0 py-2" 
                                      rows="4"><?php echo htmlspecialchars($category['description']); ?></textarea>
                        </div>

                        <hr class="my-4 opacity-10">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="categories.php" class="btn btn-light rounded-pill px-4 border">إلغاء</a>
                            <button type="submit" name="update_category" class="btn btn-primary rounded-pill px-5 shadow-sm">
                                <i class="bi bi-save me-2"></i> حفظ التعديلات
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4 text-muted small">
                <i class="bi bi-info-circle me-1"></i> معرف التصنيف (ID): #<?php echo $category['id']; ?>
            </div>

        </div>
    </div>
</div>

<?php 
// إنهاء التخزين المؤقت وإرسال المخرجات
ob_end_flush();
include 'includes/footer.php'; 
?>