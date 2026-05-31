<?php include 'includes/header.php'; ?>

<?php
// === كود إضافة موظف جديد ===
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role']; // admin أو staff

    // التحقق هل البريد مستخدم سابقاً
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $message = '<div class="alert alert-danger">❌ هذا البريد الإلكتروني مسجل مسبقاً!</div>';
    } else {
        // تشفير كلمة المرور (هنا السر: لا نحفظها كنص عادي)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_verified) VALUES (?, ?, ?, ?, 1)");
        if ($stmt->execute([$name, $email, $hashed_password, $role])) {
            $message = '<div class="alert alert-success">✅ تم إنشاء حساب الموظف بنجاح!</div>';
        }
    }
}

// === كود حذف موظف ===
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // منع حذف النفس (المدير الحالي)
    if ($id != $_SESSION['admin_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo "<script>window.location.href='users.php';</script>";
    } else {
        $message = '<div class="alert alert-warning">⚠️ لا يمكنك حذف حسابك الحالي!</div>';
    }
}
?>

<h2 class="mb-4">👥 إدارة الموظفين والصلاحيات</h2>
<?php echo $message; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">➕ إضافة موظف جديد</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>الاسم الكامل</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>البريد الإلكتروني (للدخول)</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>كلمة المرور</label>
                        <input type="text" name="password" class="form-control" required placeholder="اكتب كلمة مرور...">
                    </div>
                    <div class="mb-3">
                        <label>الصلاحية</label>
                        <select name="role" class="form-select">
                            <option value="admin">مدير كامل (Admin)</option>
                            <option value="staff">موظف مبيعات (Staff)</option>
                        </select>
                        <small class="text-muted">
                            * المدير: تحكم كامل.<br>
                            * الموظف: لا يرى الإعدادات ولا يحذف الموظفين.
                        </small>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary w-100">إنشاء الحساب</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">قائمة الموظفين</div>
            <div class="card-body">
                <table class="table table-bordered text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>الاسم</th>
                            <th>الإيميل</th>
                            <th>الصلاحية</th>
                            <th>حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // عرض المدراء والموظفين فقط (استثناء الزبائن)
                        $sql = "SELECT * FROM users WHERE role IN ('admin', 'staff') ORDER BY id DESC";
                        $stmt = $pdo->query($sql);
                        while ($row = $stmt->fetch()) {
                            $role_badge = $row['role'] == 'admin' ? '<span class="badge bg-danger">مدير</span>' : '<span class="badge bg-info text-dark">موظف</span>';
                            
                            echo "<tr>
                                <td>{$row['full_name']}</td>
                                <td>{$row['email']}</td>
                                <td>{$role_badge}</td>
                                <td>";
                                if ($row['id'] != $_SESSION['admin_id']) {
                                    echo "<a href='users.php?delete={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"حذف هذا الموظف؟\")'>🗑️</a>";
                                } else {
                                    echo "<span class='text-muted'>الحالي</span>";
                                }
                            echo "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>