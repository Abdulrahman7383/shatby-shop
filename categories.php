<?php
ob_start();
include 'includes/header.php';

$msg = "";

// ===============================
// معالجة إضافة فئة جديدة مع الأيقونة
// ===============================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $icon_name = 'default-cat.png'; // الأيقونة الافتراضية

    if (!empty($name)) {
        // معالجة رفع الأيقونة
        if (!empty($_FILES['icon']['name'])) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $file_ext = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['icon']['size'];
            
            // التحقق من الامتداد والحجم (أقل من 1 ميجابايت للأيقونات)
            if (in_array($file_ext, $allowed_exts) && $file_size < 1 * 1024 * 1024) {
                $icon_name = 'cat_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_dir = "../assets/category_icons/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon_name);
            } else {
                $msg = '<div class="alert alert-warning shadow-sm">⚠️ صيغة الأيقونة غير مدعومة أو حجمها كبير جداً.</div>';
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $icon_name]);
            $msg = '<div class="alert alert-success shadow-sm">✅ تم إضافة القسم بنجاح!</div>';
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger shadow-sm">❌ خطأ: ' . $e->getMessage() . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning shadow-sm">⚠️ يرجى كتابة اسم القسم.</div>';
    }
}

// ===============================
// معالجة الحذف
// ===============================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // 1. التأكد من عدم وجود منتجات
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $check->execute([$id]);
    
    if ($check->fetchColumn() > 0) {
        $msg = '<div class="alert alert-danger shadow-sm">⛔ لا يمكن حذف هذا القسم لأنه يحتوي على منتجات.</div>';
    } else {
        // 2. حذف الأيقونة من السيرفر إذا لم تكن الافتراضية
        $get_icon = $pdo->prepare("SELECT icon FROM categories WHERE id = ?");
        $get_icon->execute([$id]);
        $icon_to_delete = $get_icon->fetchColumn();
        
        if ($icon_to_delete && $icon_to_delete !== 'default-cat.png') {
            $icon_path = "../assets/category_icons/" . $icon_to_delete;
            if (file_exists($icon_path)) {
                unlink($icon_path);
            }
        }

        // 3. حذف القسم من القاعدة
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: categories.php"); 
        exit;
    }
}

// ===============================
// عرض البيانات
// ===============================
$sql = "SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.id DESC";
$categories = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-tags text-primary"></i> إدارة الأقسام</h3>
        <span class="badge bg-white text-primary border p-2 shadow-sm">الإجمالي: <?php echo count($categories); ?></span>
    </div>

    <?php echo $msg; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold text-primary mb-0">إضافة قسم جديد</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">اسم التصنيف <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control bg-light border-0" placeholder="مثلاً: زجاجيات" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">أيقونة القسم <small class="text-muted">(اختياري - PNG, SVG)</small></label>
                            <input type="file" name="icon" class="form-control bg-light border-0" accept="image/png, image/jpeg, image/svg+xml, image/webp">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">الوصف</label>
                            <textarea name="description" class="form-control bg-light border-0" rows="3" placeholder="وصف يظهر للعملاء..."></textarea>
                        </div>
                        
                        <button type="submit" name="add_category" class="btn btn-primary w-100 rounded-pill shadow-sm">
                            <i class="bi bi-plus-lg"></i> حفظ التصنيف
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3">القسم</th>
                                    <th>الوصف</th>
                                    <th class="text-center">المنتجات</th>
                                    <th class="text-end pe-4">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): 
                                        $icon_path = !empty($cat['icon']) ? "../assets/category_icons/" . $cat['icon'] : "../assets/images/default-cat.png";
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="icon" class="me-3 p-1 border rounded bg-white" style="width: 40px; height: 40px; object-fit: contain;">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo mb_substr($cat['description'], 0, 40); ?>...
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border rounded-pill px-3">
                                                <?php echo $cat['product_count']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="category_edit.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-light text-primary border rounded-circle mx-1" title="تعديل"><i class="bi bi-pencil"></i></a>
                                            <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="btn btn-sm btn-light text-danger border rounded-circle mx-1" onclick="return confirm('حذف هذا القسم؟')" title="حذف"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">لا توجد أقسام مضافة بعد.</td></tr>
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