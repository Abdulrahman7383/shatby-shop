<?php
include 'includes/header.php';

$message = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $discount_percentage = $_POST['discount_percentage'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // حفظ البيانات لإعادة تعبئة النموذج
    $form_data = [
        'product_id' => $product_id,
        'discount_percentage' => $discount_percentage,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'is_active' => $is_active
    ];
    
    // التحقق من صحة التواريخ
    if (strtotime($end_date) < strtotime($start_date)) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-calendar-times me-2"></i>
                        <strong>خطأ في التواريخ!</strong> تاريخ النهاية يجب أن يكون بعد تاريخ البداية.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
    } else {
        try {
            // التحقق من عدم وجود عرض فعال لنفس المنتج
            $check_sql = "SELECT id FROM offers WHERE product_id = ? AND is_active = 1 AND end_date >= CURDATE()";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$product_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>تحذير!</strong> هذا المنتج لديه عرض فعال بالفعل في الفترة الحالية.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
            } else {
                // إضافة العرض
                $sql = "INSERT INTO offers (product_id, discount_percentage, start_date, end_date, is_active) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$product_id, $discount_percentage, $start_date, $end_date, $is_active])) {
                    
                    $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>تمت العملية بنجاح!</strong> تم إضافة العرض الترويجي بنجاح.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                    
                    // مسح بيانات النموذج
                    $form_data = [];
                    
                    // عرض زر الانتقال
                    echo '<script>
                            setTimeout(function() {
                                document.getElementById("redirect-buttons").classList.remove("d-none");
                            }, 500);
                          </script>';
                } else {
                    throw new Exception("فشل تنفيذ الاستعلام");
                }
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>حدث خطأ!</strong> ' . $e->getMessage() . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
        }
    }
}

// جلب المنتجات المتاحة
$products = $pdo->query("
    SELECT p.id, p.name, p.price_usd, p.image, p.stock_quantity,
           (SELECT COUNT(*) FROM offers o WHERE o.product_id = p.id AND o.is_active = 1 AND o.end_date >= CURDATE()) as active_offers
    FROM products p 
    WHERE p.status = 1 
    ORDER BY p.name
")->fetchAll();

// حساب إحصائيات
$total_products = count($products);
$products_with_offers = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM offers WHERE is_active = 1 AND end_date >= CURDATE()")->fetchColumn();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-gift fa-2x text-primary"></i>
                </div>
                <div>
                    <h1 class="h3 mb-1">➕ إضافة عرض ترويجي جديد</h1>
                    <p class="text-muted mb-0">أضف عرضاً ترويجياً جديداً لزيادة مبيعاتك</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex justify-content-lg-end gap-2">
                <a href="offers.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-2"></i>العودة للعروض
                </a>
                <button type="button" class="btn btn-outline-info" onclick="clearForm()">
                    <i class="fas fa-redo me-2"></i>تفريغ النموذج
                </button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 bg-gradient-primary text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">المنتجات المتاحة</h6>
                            <h3 class="card-title mb-0"><?php echo $total_products; ?></h3>
                        </div>
                        <i class="fas fa-boxes fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 bg-gradient-success text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">منتجات مع عروض</h6>
                            <h3 class="card-title mb-0"><?php echo $products_with_offers; ?></h3>
                        </div>
                        <i class="fas fa-tags fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 bg-gradient-info text-white shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">حالة النظام</h6>
                            <h3 class="card-title mb-0">نشط</h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        معلومات العرض الترويجي
                    </h5>
                </div>
                
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <form id="offerForm" method="POST" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-box me-1"></i>
                                اختيار المنتج
                                <span class="text-danger">*</span>
                            </label>
                            <select name="product_id" class="form-select form-select-lg" required 
                                    onchange="updateProductInfo(this.value)">
                                <option value="">-- اختر منتجاً من القائمة --</option>
                                <?php foreach ($products as $product): 
                                    $has_offer = $product['active_offers'] > 0;
                                    // ✅ تصحيح مسار الصورة
                                    $img_src = !empty($product['image']) ? '../assets/uploads/' . $product['image'] : '../assets/images/no-img.png';
                                ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            <?php echo (isset($form_data['product_id']) && $form_data['product_id'] == $product['id']) ? 'selected' : ''; ?>
                                            data-price="<?php echo $product['price_usd']; ?>"
                                            data-image="<?php echo $img_src; ?>"
                                            data-stock="<?php echo $product['stock_quantity']; ?>"
                                            class="<?php echo $has_offer ? 'text-warning' : ''; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?> 
                                    - $<?php echo number_format($product['price_usd'], 2); ?>
                                    <?php if ($has_offer): ?>
                                        (يوجد عرض حالياً)
                                    <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">يرجى اختيار منتج</div>
                            
                            <div id="productInfo" class="mt-3 p-3 bg-light rounded d-none">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <img id="productImage" src="" alt="" 
                                             class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                    </div>
                                    <div class="col">
                                        <h6 id="productName" class="mb-1"></h6>
                                        <div class="d-flex gap-3">
                                            <small class="text-muted"><i class="fas fa-dollar-sign"></i> السعر: <span id="productPrice"></span> $</small>
                                            <small class="text-muted"><i class="fas fa-boxes"></i> المخزون: <span id="productStock"></span> قطعة</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-percent me-1"></i>
                                نسبة الخصم
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg">
                                <input type="range" class="form-range" min="1" max="90" step="1" 
                                       id="discountRange" value="<?php echo $form_data['discount_percentage'] ?? 10; ?>">
                                <input type="number" class="form-control text-center" 
                                       name="discount_percentage" 
                                       id="discountInput"
                                       min="1" max="99" step="0.1"
                                       value="<?php echo $form_data['discount_percentage'] ?? ''; ?>" 
                                       required
                                       oninput="updateDiscountPreview()">
                                <span class="input-group-text bg-primary text-white fw-bold">%</span>
                            </div>
                            <div class="invalid-feedback">يرجى إدخال نسبة خصم صحيحة</div>
                            
                            <div id="discountPreview" class="mt-3 card border-success d-none">
                                <div class="card-body p-3">
                                    <h6 class="card-title text-success mb-2"><i class="fas fa-chart-line me-1"></i>معاينة الخصم</h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">السعر الأصلي</small>
                                            <span id="originalPrice" class="fw-bold text-decoration-line-through"></span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">السعر بعد الخصم</small>
                                            <span id="discountedPrice" class="fw-bold fs-5 text-success"></span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">قيمة التوفير</small>
                                            <span id="savingAmount" class="fw-bold text-danger"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-alt me-1"></i>
                                الفترة الزمنية للعرض
                            </label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" name="start_date" 
                                               class="form-control" 
                                               id="startDate"
                                               value="<?php echo $form_data['start_date'] ?? date('Y-m-d'); ?>" 
                                               required
                                               onchange="updateEndDateMin()">
                                        <label for="startDate">تاريخ البداية</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" name="end_date" 
                                               class="form-control" 
                                               id="endDate"
                                               min="<?php echo date('Y-m-d'); ?>"
                                               value="<?php echo $form_data['end_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>" 
                                               required>
                                        <label for="endDate">تاريخ النهاية</label>
                                    </div>
                                </div>
                            </div>
                            <div id="durationInfo" class="mt-2 text-center">
                                <span class="badge bg-info">
                                    <i class="fas fa-clock me-1"></i>
                                    مدة العرض: <span id="durationDays">7</span> يوم
                                </span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="is_active" id="is_active" 
                                       <?php echo (!isset($form_data['is_active']) || $form_data['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold" for="is_active">
                                    <i class="fas fa-power-off me-2"></i>
                                    تفعيل العرض فوراً
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2 pt-3">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill py-3">
                                <i class="fas fa-save me-2"></i>
                                حفظ العرض الترويجي
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="previewOffer()">
                                <i class="fas fa-eye me-2"></i>
                                معاينة
                            </button>
                        </div>
                        
                        <div id="redirect-buttons" class="d-flex gap-2 mt-3 d-none">
                            <a href="offers.php" class="btn btn-success flex-fill">
                                <i class="fas fa-list me-2"></i>
                                عرض جميع العروض
                            </a>
                            <button type="button" class="btn btn-outline-primary" onclick="clearForm()">
                                <i class="fas fa-plus me-2"></i>
                                إضافة عرض جديد
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="fas fa-lightbulb text-warning me-2"></i>نصائح</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item border-0 px-0"><i class="fas fa-check-circle text-success me-2"></i> خصومات 10-30% تجذب المزيد.</li>
                        <li class="list-group-item border-0 px-0"><i class="fas fa-check-circle text-success me-2"></i> العروض المحدودة الوقت تزيد المبيعات.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>معاينة العرض</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 text-center">
                        <img id="previewImage" src="" alt="" class="img-fluid rounded mb-3">
                        <div class="badge bg-danger fs-5 p-3">خصم <span id="previewDiscount">0</span>%</div>
                    </div>
                    <div class="col-md-7">
                        <h4 id="previewProductName" class="mb-3"></h4>
                        <div class="mb-4">
                            <p class="text-muted mb-1">السعر الأصلي: <span class="text-decoration-line-through" id="previewOriginalPrice"></span></p>
                            <p class="text-success mb-1 fw-bold fs-4">السعر الحالي: <span id="previewDiscountedPrice"></span></p>
                            <p class="text-danger">توفير: <span id="previewSaving"></span></p>
                        </div>
                        <div class="border-top pt-3">
                            <p><i class="fas fa-calendar me-2"></i> من <strong id="previewStartDate"></strong> إلى <strong id="previewEndDate"></strong></p>
                            <p id="previewStatus"><span class="badge bg-success">مفعل</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('offerForm').submit()">تأكيد وإضافة</button>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary { background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #2ec4b6 0%, #20a39e 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #4cc9f0 0%, #3a86ff 100%); }
#productInfo { border-left: 4px solid #4361ee; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    updateEndDateMin();
    const productSelect = document.querySelector('select[name="product_id"]');
    if (productSelect.value) updateProductInfo(productSelect.value);
    
    updateDiscountPreview();
    updateDurationDays();
    
    const form = document.getElementById('offerForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
    
    document.getElementById('discountInput').addEventListener('input', function() {
        document.getElementById('discountRange').value = this.value;
        updateDiscountPreview();
    });
    
    document.getElementById('discountRange').addEventListener('input', function() {
        document.getElementById('discountInput').value = this.value;
        updateDiscountPreview();
    });
    
    document.getElementById('startDate').addEventListener('change', updateDurationDays);
    document.getElementById('endDate').addEventListener('change', updateDurationDays);
});

function updateProductInfo(productId) {
    const select = document.querySelector('select[name="product_id"]');
    const option = select.options[select.selectedIndex];
    const productInfo = document.getElementById('productInfo');
    
    if (productId) {
        const price = option.getAttribute('data-price');
        const image = option.getAttribute('data-image');
        const stock = option.getAttribute('data-stock');
        const name = option.text.split('-')[0].trim();
        
        document.getElementById('productName').textContent = name;
        document.getElementById('productPrice').textContent = parseFloat(price).toFixed(2);
        document.getElementById('productStock').textContent = stock;
        
        if (image && !image.includes('no-img')) {
            document.getElementById('productImage').src = image;
            document.getElementById('productImage').style.display = 'block';
        } else {
            document.getElementById('productImage').style.display = 'none';
        }
        
        productInfo.classList.remove('d-none');
        updateDiscountPreview();
    } else {
        productInfo.classList.add('d-none');
    }
}

function updateDiscountPreview() {
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const select = document.querySelector('select[name="product_id"]');
    const option = select.options[select.selectedIndex];
    const price = parseFloat(option.getAttribute('data-price')) || 0;
    
    if (price > 0 && discount > 0) {
        const originalPrice = price;
        const discountedPrice = originalPrice * (1 - discount/100);
        const savingAmount = originalPrice - discountedPrice;
        
        document.getElementById('originalPrice').textContent = originalPrice.toFixed(2) + ' $';
        document.getElementById('discountedPrice').textContent = discountedPrice.toFixed(2) + ' $';
        document.getElementById('savingAmount').textContent = savingAmount.toFixed(2) + ' $';
        
        document.getElementById('discountPreview').classList.remove('d-none');
    } else {
        document.getElementById('discountPreview').classList.add('d-none');
    }
}

function updateEndDateMin() {
    const startDate = document.getElementById('startDate').value;
    if (startDate) {
        document.getElementById('endDate').min = startDate;
    }
}

function updateDurationDays() {
    const startDate = new Date(document.getElementById('startDate').value);
    const endDate = new Date(document.getElementById('endDate').value);
    
    if (startDate && endDate && startDate <= endDate) {
        const diffTime = Math.abs(endDate - startDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        document.getElementById('durationDays').textContent = diffDays + 1;
    }
}

function previewOffer() {
    const productSelect = document.querySelector('select[name="product_id"]');
    const option = productSelect.options[productSelect.selectedIndex];
    
    if (!productSelect.value) {
        alert('يرجى اختيار منتج أولاً');
        return;
    }
    
    const discount = document.getElementById('discountInput').value;
    const originalPrice = parseFloat(option.getAttribute('data-price'));
    const discountedPrice = originalPrice * (1 - discount/100);
    const saving = originalPrice - discountedPrice;
    
    document.getElementById('previewProductName').textContent = option.text.split('-')[0].trim();
    document.getElementById('previewDiscount').textContent = discount;
    document.getElementById('previewOriginalPrice').textContent = originalPrice.toFixed(2) + ' $';
    document.getElementById('previewDiscountedPrice').textContent = discountedPrice.toFixed(2) + ' $';
    document.getElementById('previewSaving').textContent = saving.toFixed(2) + ' $';
    document.getElementById('previewStartDate').textContent = document.getElementById('startDate').value;
    document.getElementById('previewEndDate').textContent = document.getElementById('endDate').value;
    
    const image = option.getAttribute('data-image');
    if (image && !image.includes('no-img')) {
        document.getElementById('previewImage').src = image;
        document.getElementById('previewImage').style.display = 'block';
    } else {
        document.getElementById('previewImage').style.display = 'none';
    }
    
    const isActive = document.getElementById('is_active').checked;
    const statusElement = document.getElementById('previewStatus');
    statusElement.innerHTML = '<i class="fas fa-power-off me-2"></i> الحالة: ' + 
        (isActive ? '<span class="badge bg-success">مفعل</span>' : '<span class="badge bg-secondary">مسودة</span>');
    
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    previewModal.show();
}

function clearForm() {
    if (confirm('هل تريد تفريغ جميع الحقول؟')) {
        document.getElementById('offerForm').reset();
        document.getElementById('productInfo').classList.add('d-none');
        document.getElementById('discountPreview').classList.add('d-none');
        document.getElementById('redirect-buttons').classList.add('d-none');
        updateEndDateMin();
        updateDurationDays();
    }
}
</script>

<?php include 'includes/footer.php'; ?>