<?php
ob_start();
include 'includes/header.php';

// حذف تقييم
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)$_GET['delete']]);
    header("Location: reviews.php"); exit;
}

// جلب كل التقييمات
$reviews = $pdo->query("SELECT r.*, p.name as p_name, u.full_name as u_name FROM reviews r JOIN products p ON r.product_id = p.id JOIN users u ON r.user_id = u.id ORDER BY r.id DESC")->fetchAll();
?>

<div class="container-fluid py-4">
    <h3 class="fw-bold mb-4">⭐ إدارة تقييمات المنتجات</h3>
    
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="bg-light">
                    <tr>
                        <th>المنتج</th>
                        <th>الزبون</th>
                        <th>التقييم</th>
                        <th width="30%">التعليق</th>
                        <th>التاريخ</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($reviews as $r): ?>
                    <tr>
                        <td><?php echo $r['p_name']; ?></td>
                        <td><?php echo $r['u_name']; ?></td>
                        <td><span class="text-warning fw-bold"><?php echo $r['rating']; ?> / 5</span></td>
                        <td class="small"><?php echo $r['comment']; ?></td>
                        <td><?php echo date('Y/m/d', strtotime($r['created_at'])); ?></td>
                        <td>
                            <a href="reviews.php?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-danger rounded-circle" onclick="return confirm('حذف؟')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>