</div> </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. تعريف العناصر
    const sidebar = document.getElementById('adminSidebar');
    const mainContent = document.getElementById('adminMain');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    // 2. دالة التبديل (فتح/إغلاق)
    function toggleSidebar() {
        // التحقق من عرض الشاشة
        if (window.innerWidth >= 992) {
            // شاشات كبيرة: تصغير/تكبير القائمة
            sidebar.classList.toggle('compact');
            mainContent.classList.toggle('compact');
        } else {
            // شاشات صغيرة (جوال): إظهار/إخفاء القائمة
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    }
    
    // 3. تفعيل الزر عند الضغط عليه
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    
    // 4. إغلاق القائمة عند الضغط على الخلفية (للجوال فقط)
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
    
    // 5. إصلاح العرض عند تغيير حجم الشاشة
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            // إخفاء وضع الجوال عند تكبير الشاشة
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        } else {
            // إزالة وضع Compact عند تصغير الشاشة لضمان عمل القائمة المنزلقة
            sidebar.classList.remove('compact');
            mainContent.classList.remove('compact');
        }
    });
});
</script>

</body>
</html>