<?php
ob_start();
include 'includes/header.php';

if (!isset($_GET['id'])) { header("Location: offers.php"); exit; }
$id = (int)$_GET['id'];
$msg = "";

// معالجة التعديل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_offer'])) {
    $product_id = $_POST['product_id'];
    $discount   = $_POST['discount'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($end_date < $start_date) {
        $msg = '<div class="alert alert-danger">خطأ في التواريخ!</div>';
    } else {
        $stmt = $pdo->prepare("UPDATE offers SET product_id=?, discount_percentage=?, start_date=?, end_date=?, is_active=? WHERE id=?");
        if ($stmt->execute([$product_id, $discount, $start_date, $end_date, $is_active, $id])) {
            $msg = '<div class="alert alert-success">تم تحديث العرض بنجاح!</div>';
        }
    }
}

// جلب بيانات العرض الحالي
$offer = $pdo->query("SELECT * FROM offers WHERE id = $id")->fetch();
if (!$offer) { die("العرض غير موجود"); }

// جلب المنتجات
$products = $pdo->query("SELECT id, name FROM products WHERE status = 1")->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between mb-4">
        <h3>✏️ تعديل العرض</h3>
        <a href="offers.php" class="btn btn-secondary">عودة</a>
    </div>

    <?php echo $msg; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">المنتج</label>
                        <select name="product_id" class="form-select" required>
                            <?php foreach($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $offer['product_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">نسبة الخصم (%)</label>
                        <input type="number" name="discount" class="form-control" value="<?php echo $offer['discount_percentage']; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">من تاريخ</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $offer['start_date']; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">إلى تاريخ</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $offer['end_date']; ?>" required>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="activeCheck" <?php echo $offer['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="activeCheck">تفعيل العرض</label>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" name="update_offer" class="btn btn-warning w-100 btn-lg rounded-pill">
                            حفظ التعديلات
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
ob_end_flush();
include 'includes/footer.php'; 
?>