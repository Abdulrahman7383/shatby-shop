<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك']);
        exit;
    }
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("رقم المنتج غير صحيح");
$id = (int)$_GET['id'];

// معالجة POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $status = isset($_POST['status']) ? 1 : 0;

        // 1. تحديث المنتج الرئيسي
        $stmt = $pdo->prepare("
            UPDATE products SET
                name = ?, category_id = ?, price_usd = ?, stock_quantity = ?,
                unit_id = ?, description = ?, keywords = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['name']),
            (int)$_POST['category_id'],
            (float)$_POST['price_usd'],
            (int)$_POST['stock_quantity'],
            (int)$_POST['unit_id'],
            trim($_POST['description']),
            trim($_POST['keywords'] ?? ''),
            $status,
            $id
        ]);

        // 2. معالجة حذف الصور المحددة
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
                $img_stmt->execute([(int)$img_id, $id]);
                if ($img = $img_stmt->fetch()) {
                    $file_path = "../assets/uploads/" . $img['image_path'];
                    if (file_exists($file_path)) unlink($file_path); // حذف الملف الفعلي من السيرفر
                    $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([(int)$img_id]);
                }
            }
        }

        // 3. رفع صور جديدة للمعرض
        if (!empty($_FILES['images']['name'][0])) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $upload_dir = "../assets/uploads/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $img_ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                $file_size = $_FILES['images']['size'][$key];

                if (in_array($img_ext, $allowed_exts) && $file_size < 2 * 1024 * 1024) {
                    $new_name = time() . '_' . uniqid() . '.' . $img_ext;
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                        $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)")->execute([$id, $new_name]);
                    }
                }
            }
        }

        // 4. المزامنة الذكية: تحديث صورة الغلاف (الرئيسية) لتكون هي أول صورة متوفرة في المعرض
        $first_img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1");
        $first_img_stmt->execute([$id]);
        $cover_image = $first_img_stmt->fetchColumn() ?: '';
        $pdo->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$cover_image, $id]);

        // 5. معالجة المقاسات
        if (isset($_POST['delete_variants']) && is_array($_POST['delete_variants'])) {
            $del_ids = array_map('intval', $_POST['delete_variants']);
            $placeholders = implode(',', array_fill(0, count($del_ids), '?'));
            $del_stmt = $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders) AND product_id = ?");
            $del_stmt->execute(array_merge($del_ids, [$id]));
        }

        $variant_ids = $_POST['variant_id'] ?? [];
        $size_names = $_POST['size_name'] ?? [];
        $prices = $_POST['size_price'] ?? [];
        $qtys = $_POST['size_qty'] ?? [];

        for ($i = 0; $i < count($size_names); $i++) {
            $v_id = (int)($variant_ids[$i] ?? 0);
            $size = trim($size_names[$i]);
            $price = (float)$prices[$i];
            $qty = (int)$qtys[$i];
            if (empty($size)) continue;

            if ($v_id > 0) {
                $up = $pdo->prepare("UPDATE product_variants SET size_name=?, price_usd=?, stock_quantity=? WHERE id=? AND product_id=?");
                $up->execute([$size, $price, $qty, $v_id, $id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price_usd, stock_quantity) VALUES (?, ?, ?, ?)");
                $ins->execute([$id, $size, $price, $qty]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'تم حفظ التعديلات بنجاح']);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// جلب بيانات المنتج
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) die("المنتج غير موجود");

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$units = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();

// جلب المقاسات الحالية
$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY size_name");
$variants->execute([$id]);
$variants = $variants->fetchAll();

// جلب الصور الحالية للمنتج
$images_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id ASC");
$images_stmt->execute([$id]);
$product_images = $images_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0 text-primary"><i class="bi bi-pencil-square me-2"></i> تعديل المنتج: <?php echo htmlspecialchars($product['name']); ?></h5>
                    <a href="products.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-right"></i> عودة</a>
                </div>
                <div class="card-body p-4">
                    <form id="editProductForm" onsubmit="saveProduct(event, <?php echo $id; ?>)" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">اسم المنتج</label>
                                <input type="text" name="name" class="form-control rounded-3" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">القسم</label>
                                <select name="category_id" class="form-select rounded-3">
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php if ($c['id'] == $product['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch p-3 bg-light rounded-3 w-100">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="status" id="statusSwitch" <?php if ($product['status']) echo 'checked'; ?>>
                                    <label class="form-check-label fw-bold small" for="statusSwitch">عرض المنتج في الموقع</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">السعر ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">$</span>
                                    <input type="number" step="0.01" name="price_usd" class="form-control border-start-0 rounded-end-3" value="<?php echo $product['price_usd']; ?>" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">الكمية المتوفرة</label>
                                <input type="number" name="stock_quantity" class="form-control rounded-3" value="<?php echo $product['stock_quantity']; ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">الوحدة</label>
                                <select name="unit_id" class="form-select rounded-3" required>
                                    <option value="">اختر...</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php if ($u['id'] == $product['unit_id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">وصف المنتج</label>
                                <textarea name="description" class="form-control rounded-3" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">الكلمات المفتاحية (SEO)</label>
                                <input type="text" name="keywords" class="form-control rounded-3" value="<?php echo htmlspecialchars($product['keywords'] ?? ''); ?>" placeholder="مثال: حمض, تنظيف, مواد خام">
                            </div>

                            <div class="col-12 mt-4 p-3 bg-light border rounded-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-images me-2"></i> معرض الصور</h6>
                                
                                <?php if (!empty($product_images)): ?>
                                    <label class="form-label fw-bold small text-muted">الصور الحالية (حدد التي تريد حذفها)</label>
                                    <div class="d-flex flex-wrap gap-3 mb-4">
                                        <?php foreach ($product_images as $index => $img): ?>
                                            <div class="position-relative border p-2 bg-white rounded text-center">
                                                <img src="../assets/uploads/<?php echo htmlspecialchars($img['image_path']); ?>" class="rounded mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                                                <?php if($index === 0): ?>
                                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size:0.65rem;">الغلاف</span>
                                                <?php endif; ?>
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input class="form-check-input me-1" type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>" id="del_img_<?php echo $img['id']; ?>">
                                                    <label class="form-check-label text-danger small fw-bold" for="del_img_<?php echo $img['id']; ?>">حذف</label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <label class="form-label fw-bold small text-muted">إضافة صور جديدة <small>(JPG, PNG - Max 2MB)</small></label>
                                <input type="file" name="images[]" class="form-control rounded-3" accept="image/*" multiple>
                            </div>
                            <div class="col-12 mt-3">
                                <label class="form-label fw-bold small text-muted">المقاسات (الأحجام)</label>
                                <div id="variants-container">
                                    <?php if (empty($variants)): ?>
                                        <div class="row g-2 mb-2 variant-row">
                                            <div class="col-md-4"><input type="text" name="size_name[]" class="form-control" placeholder="الحجم (مثال: 100ml)"></div>
                                            <div class="col-md-3"><input type="number" step="0.01" name="size_price[]" class="form-control" placeholder="السعر ($)"></div>
                                            <div class="col-md-3"><input type="number" name="size_qty[]" class="form-control" placeholder="الكمية"></div>
                                            <div class="col-md-2"><button type="button" class="btn btn-danger remove-variant w-100">حذف</button></div>
                                            <input type="hidden" name="variant_id[]" value="0">
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($variants as $v): ?>
                                            <div class="row g-2 mb-2 variant-row">
                                                <div class="col-md-4"><input type="text" name="size_name[]" class="form-control" value="<?php echo htmlspecialchars($v['size_name']); ?>" required></div>
                                                <div class="col-md-3"><input type="number" step="0.01" name="size_price[]" class="form-control" value="<?php echo $v['price_usd']; ?>" required></div>
                                                <div class="col-md-3"><input type="number" name="size_qty[]" class="form-control" value="<?php echo $v['stock_quantity']; ?>" required></div>
                                                <div class="col-md-2"><button type="button" class="btn btn-danger remove-variant w-100">حذف</button></div>
                                                <input type="hidden" name="variant_id[]" value="<?php echo $v['id']; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-variant" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-plus-circle"></i> إضافة مقاس آخر</button>
                            </div>

                            <div class="col-12 mt-4 text-center">
                                <hr class="my-4 opacity-10">
                                <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold shadow-sm"><i class="bi bi-save-fill me-2"></i> حفظ التغييرات</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function saveProduct(event, id) {
    event.preventDefault();
    const form = event.target;
    // FormData ستقوم تلقائياً بالتقاط ملفات الصور لأننا أضفنا enctype للفورم
    const formData = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري الحفظ...';
    btn.disabled = true;
    
    fetch('product_edit.php?id=' + id, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> تم الحفظ';
                btn.classList.replace('btn-primary', 'btn-success');
                // إعادة تحميل الصفحة لرؤية الصور الجديدة
                setTimeout(() => window.location.reload(), 1000);
            } else throw new Error(data.message);
        })
        .catch(error => {
            alert('❌ خطأ: ' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('variants-container');
    const addBtn = document.getElementById('add-variant');
    
    function attachRemoveEvent(row) {
        row.querySelector('.remove-variant')?.addEventListener('click', () => {
            // إضافة حقل مخفي ليتم حذفه من قاعدة البيانات إذا كان مسجلاً مسبقاً
            const variantIdInput = row.querySelector('input[name="variant_id[]"]');
            if (variantIdInput && variantIdInput.value !== "0") {
                const hiddenDelete = document.createElement('input');
                hiddenDelete.type = 'hidden';
                hiddenDelete.name = 'delete_variants[]';
                hiddenDelete.value = variantIdInput.value;
                document.getElementById('editProductForm').appendChild(hiddenDelete);
            }

            if (document.querySelectorAll('.variant-row').length > 1) row.remove();
            else alert('لا يمكن حذف آخر مقاس، يمكنك تركه فارغاً');
        });
    }
    
    addBtn.addEventListener('click', () => {
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-2 variant-row';
        newRow.innerHTML = `
            <div class="col-md-4"><input type="text" name="size_name[]" class="form-control" placeholder="الحجم (مثال: 100ml)"></div>
            <div class="col-md-3"><input type="number" step="0.01" name="size_price[]" class="form-control" placeholder="السعر ($)"></div>
            <div class="col-md-3"><input type="number" name="size_qty[]" class="form-control" placeholder="الكمية"></div>
            <div class="col-md-2"><button type="button" class="btn btn-danger remove-variant w-100">حذف</button></div>
            <input type="hidden" name="variant_id[]" value="0">
        `;
        container.appendChild(newRow);
        attachRemoveEvent(newRow);
    });
    
    document.querySelectorAll('.variant-row').forEach(row => attachRemoveEvent(row));
});
</script>

<?php include 'includes/footer.php'; ob_end_flush(); ?>