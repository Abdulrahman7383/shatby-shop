<?php
ob_start();
include 'includes/header.php';

// تعريف دالة التنظيف
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// جلب الفئات والوحدات
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$units = $pdo->query("SELECT id, name FROM units ORDER BY id ASC")->fetchAll();

// معالجة الحفظ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    
    // تنظيف بيانات المنتج الرئيسي
    $name = sanitize($_POST['name']);
    $cat_id = (int)$_POST['category_id'];
    $desc = sanitize($_POST['description']);
    $keywords = sanitize($_POST['keywords'] ?? '');
    $unit_id = (int)$_POST['unit_id'];

    $main_image_name = ''; // لتخزين الصورة الرئيسية (الغلاف)
    $uploaded_images = []; // مصفوفة لتخزين كل الصور المرفوعة
    $msds_name = null;

    // رفع الصور المتعددة
    if (!empty($_FILES['images']['name'][0])) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $upload_dir = "../assets/uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // المرور على كل صورة تم اختيارها
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            $img_ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
            $file_size = $_FILES['images']['size'][$key];

            // التحقق من الامتداد والحجم (أقل من 2MB)
            if (in_array($img_ext, $allowed_exts) && $file_size < 2 * 1024 * 1024) {
                $new_name = time() . '_' . uniqid() . '.' . $img_ext;
                
                if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                    $uploaded_images[] = $new_name;
                    // تعيين أول صورة كصورة رئيسية للمنتج لتجنب كسر التصميم القديم
                    if (empty($main_image_name)) {
                        $main_image_name = $new_name;
                    }
                }
            }
        }
    }

    // رفع MSDS
    if (!empty($_FILES['msds_file']['name'])) {
        $msds_ext = strtolower(pathinfo($_FILES['msds_file']['name'], PATHINFO_EXTENSION));
        if ($msds_ext == 'pdf' && $_FILES['msds_file']['size'] < 5 * 1024 * 1024) {
            $msds_name = "MSDS_" . time() . '.' . $msds_ext;
            if (!is_dir("../assets/msds")) mkdir("../assets/msds", 0777, true);
            move_uploaded_file($_FILES['msds_file']['tmp_name'], "../assets/msds/" . $msds_name);
        }
    }

    // بدء المعاملة (transaction)
    try {
        $pdo->beginTransaction();

        // 1. إدراج المنتج الرئيسي
        $sql = "INSERT INTO products (category_id, name, description, keywords, unit_id, image, msds_file, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cat_id, $name, $desc, $keywords, $unit_id, $main_image_name, $msds_name]);
        
        $product_id = $pdo->lastInsertId();

        // 2. إدراج معرض الصور في الجدول الجديد
        if (!empty($uploaded_images)) {
            $img_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            foreach ($uploaded_images as $img_path) {
                $img_stmt->execute([$product_id, $img_path]);
            }
        }

        // 3. إدراج المقاسات
        $sizes = $_POST['size_name'] ?? [];
        $prices = $_POST['size_price'] ?? [];
        $qtys = $_POST['size_qty'] ?? [];

        $variant_stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price_usd, stock_quantity) VALUES (?, ?, ?, ?)");
        for ($i = 0; $i < count($sizes); $i++) {
            if (!empty($sizes[$i]) && is_numeric($prices[$i]) && is_numeric($qtys[$i])) {
                $variant_stmt->execute([$product_id, $sizes[$i], (float)$prices[$i], (int)$qtys[$i]]);
            }
        }

        $pdo->commit();
        echo "<script>alert('✅ تم إضافة المنتج مع معرض الصور والمقاسات بنجاح'); window.location.href='products.php';</script>";
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        echo "<div class='container mt-3'><div class='alert alert-danger'>خطأ في الإضافة: " . $e->getMessage() . "</div></div>";
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold m-0 text-primary"><i class="bi bi-plus-circle-fill me-2"></i> إضافة منتج جديد</h4>
                    <a href="products.php" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-arrow-right"></i> رجوع</a>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small">اسم المنتج <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">الفئة <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select rounded-3" required>
                                    <option value="">اختر الفئة...</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                   <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small">الوحدة الأساسية <span class="text-danger">*</span></label>
                                <select name="unit_id" class="form-select rounded-3" required>
                                    <option value="">اختر...</option>
                                    <?php foreach($units as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small">وصف المنتج</label>
                                <textarea name="description" class="form-control rounded-3" rows="4"></textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small">الكلمات المفتاحية (SEO)</label>
                                <input type="text" name="keywords" class="form-control rounded-3" placeholder="مثال: حمض, تنظيف, مواد خام">
                            </div>

                            <div class="col-12 mt-3">
                                <label class="form-label fw-bold small">المقاسات (الأحجام)</label>
                                <div id="variants-container">
                                    <div class="row g-2 mb-2 variant-row">
                                        <div class="col-md-4">
                                            <input type="text" name="size_name[]" class="form-control" placeholder="الحجم (مثال: 100ml)" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" step="0.01" name="size_price[]" class="form-control" placeholder="السعر ($)" required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" name="size_qty[]" class="form-control" placeholder="الكمية" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger remove-variant w-100">حذف</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-variant" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-plus-circle"></i> إضافة مقاس آخر
                                </button>
                            </div>

                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small">صور المنتج <span class="text-danger">*</span></label>
                                <p class="text-muted mb-2" style="font-size: 0.8rem;">يمكنك تحديد أكثر من صورة معاً. (أول صورة ستكون هي الغلاف الأساسي للمنتج).</p>
                                <input type="file" name="images[]" id="imageInput" class="form-control rounded-3" accept="image/*" multiple required>
                                
                                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mt-3"></div>
                            </div>

                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small">ملف MSDS <small class="text-muted">(PDF - Max 5MB)</small></label>
                                <input type="file" name="msds_file" class="form-control rounded-3" accept=".pdf">
                            </div>

                            <div class="col-12 mt-4 text-center">
                                <button type="submit" name="add_product" class="btn btn-primary px-5 py-2 rounded-pill fw-bold shadow-sm">
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> حفظ المنتج والمقاسات
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. نظام المقاسات
    const container = document.getElementById('variants-container');
    const addBtn = document.getElementById('add-variant');

    addBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-2 variant-row';
        newRow.innerHTML = `
            <div class="col-md-4">
                <input type="text" name="size_name[]" class="form-control" placeholder="الحجم (مثال: 100ml)" required>
            </div>
            <div class="col-md-3">
                <input type="number" step="0.01" name="size_price[]" class="form-control" placeholder="السعر ($)" required>
            </div>
            <div class="col-md-3">
                <input type="number" name="size_qty[]" class="form-control" placeholder="الكمية" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger remove-variant w-100">حذف</button>
            </div>
        `;
        container.appendChild(newRow);
        attachRemoveEvent(newRow);
    });

    document.querySelectorAll('.remove-variant').forEach(btn => {
        attachRemoveEvent(btn.closest('.variant-row'));
    });

    function attachRemoveEvent(row) {
        const removeBtn = row.querySelector('.remove-variant');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                if (document.querySelectorAll('.variant-row').length > 1) {
                    row.remove();
                } else {
                    alert('لا يمكن حذف المقاس الوحيد، اتركه فارغاً إذا لم ترد إضافة مقاسات');
                }
            });
        }
    }

    // 2. نظام معاينة الصور المتعددة
    const imageInput = document.getElementById('imageInput');
    const previewContainer = document.getElementById('imagePreviewContainer');

    if (imageInput) {
        imageInput.addEventListener('change', function() {
            previewContainer.innerHTML = ''; // تفريغ المعاينة القديمة
            Array.from(this.files).forEach((file, index) => {
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'position-relative';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        // تمييز أول صورة بحد أزرق لتوضيح أنها الغلاف
                        img.className = index === 0 ? 'img-thumbnail border-primary border-2 shadow-sm' : 'img-thumbnail shadow-sm';
                        img.style.width = '90px';
                        img.style.height = '90px';
                        img.style.objectFit = 'cover';
                        
                        wrapper.appendChild(img);
                        
                        if(index === 0) {
                            const badge = document.createElement('span');
                            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary';
                            badge.innerText = 'الغلاف';
                            wrapper.appendChild(badge);
                        }
                        
                        previewContainer.appendChild(wrapper);
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ob_end_flush(); ?>