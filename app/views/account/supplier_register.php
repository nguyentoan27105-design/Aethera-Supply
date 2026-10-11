<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>

<style>
.supplier-register-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%); }
.supplier-register-card { width:min(100%,780px); background:#fff; border:1px solid #d7efe2; border-radius:18px; box-shadow:0 20px 45px rgba(6,78,59,.12); overflow:hidden; }
.supplier-register-head { padding:24px; color:#fff; background:linear-gradient(120deg,#064e3b,#16a34a); }
.supplier-register-head h3 { margin:0; color:#fff; font-weight:950; letter-spacing:0; }
.supplier-register-head p { margin:6px 0 0; color:rgba(255,255,255,.86); font-weight:700; }
.supplier-register-body { padding:24px; }
.supplier-register-alert { border-radius:12px; padding:12px 14px; margin-bottom:14px; font-weight:800; }
.supplier-register-alert.success { background:#ecfdf5; color:#065f46; }
.supplier-register-alert.danger { background:#fef2f2; color:#991b1b; }
.supplier-register-form { display:grid; grid-template-columns:1fr 1fr; gap:13px; }
.supplier-register-field.full { grid-column:1 / -1; }
.supplier-register-field label { display:block; margin-bottom:6px; color:#64748b; font-size:.74rem; font-weight:900; text-transform:uppercase; }
.supplier-register-field input,.supplier-register-field textarea { width:100%; min-height:44px; border:1px solid #bbf7d0; border-radius:12px; background:#f0fdf4; padding:9px 12px; color:#0f172a; font-weight:750; outline:0; }
.supplier-register-field textarea { min-height:86px; resize:vertical; line-height:1.45; }
.supplier-register-field input:focus,.supplier-register-field textarea:focus { background:#fff; border-color:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.16); }
.supplier-register-password { position:relative; }
.supplier-register-password input { padding-right:44px; }
.supplier-register-toggle-password { position:absolute; right:7px; top:50%; transform:translateY(-50%); width:34px; height:34px; border:0; border-radius:10px; display:grid; place-items:center; background:transparent; color:#15803d; cursor:pointer; }
.supplier-register-toggle-password:hover,.supplier-register-toggle-password:focus { background:#dcfce7; color:#065f46; outline:0; }
.supplier-password-suggestion { display:block; margin-top:7px; color:#15803d; font-size:.78rem; font-weight:800; line-height:1.35; }
.supplier-register-actions { grid-column:1 / -1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; margin-top:4px; }
.supplier-register-btn { min-height:46px; border:0; border-radius:999px; padding:0 18px; display:inline-flex; align-items:center; justify-content:center; gap:8px; background:linear-gradient(120deg,#22c55e,#16a34a); color:#fff; font-weight:950; text-decoration:none; }
.supplier-register-link { color:#15803d; font-weight:950; text-decoration:none; }
@media(max-width:720px){ .supplier-register-page{padding:16px 10px;} .supplier-register-form{grid-template-columns:1fr;} .supplier-register-field.full,.supplier-register-actions{grid-column:auto;} .supplier-register-actions{justify-content:flex-start;} }
</style>

<main class="supplier-register-page">
    <section class="supplier-register-card">
        <div class="supplier-register-head">
            <h3><i class="fa-solid fa-handshake-angle me-2"></i>Đăng ký nhà cung cấp</h3>
            <p>Hồ sơ sẽ ở trạng thái chờ duyệt sau khi xác thực tài khoản.</p>
        </div>
        <div class="supplier-register-body">
            <?php if ($message): ?>
                <div class="supplier-register-alert <?= htmlspecialchars($messageType) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/webbanhang/account/supplierRegister" class="supplier-register-form">
                <div class="supplier-register-field">
                    <label>Tên đăng nhập</label>
                    <input name="username" required>
                </div>
                <div class="supplier-register-field">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="supplier-register-field">
                    <label for="supplierPassword">Mật khẩu</label>
                    <div class="supplier-register-password">
                        <input id="supplierPassword" type="password" name="password" autocomplete="new-password" required>
                        <button class="supplier-register-toggle-password" type="button" data-toggle-password="supplierPassword" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small class="supplier-password-suggestion">Mật khẩu đề xuất: NhaCungCap@2026 hoặc dùng ít nhất 8 ký tự gồm chữ hoa, chữ thường, số và ký tự đặc biệt.</small>
                </div>
                <div class="supplier-register-field">
                    <label for="supplierConfirmPassword">Nhập lại mật khẩu</label>
                    <div class="supplier-register-password">
                        <input id="supplierConfirmPassword" type="password" name="confirm_password" autocomplete="new-password" required>
                        <button class="supplier-register-toggle-password" type="button" data-toggle-password="supplierConfirmPassword" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="supplier-register-field">
                    <label>Người đại diện</label>
                    <input name="fullname" required>
                </div>
                <div class="supplier-register-field">
                    <label>Số điện thoại</label>
                    <input name="phone">
                </div>
                <div class="supplier-register-field">
                    <label>Tên nhà cung cấp</label>
                    <input name="supplier_name" required placeholder="HTX / trang trại / doanh nghiệp">
                </div>
                <div class="supplier-register-field">
                    <label>Vùng trồng</label>
                    <input name="farm_location" placeholder="Cái Bè, Tiền Giang">
                </div>
                <div class="supplier-register-field">
                    <label>Diện tích canh tác (ha)</label>
                    <input type="number" step="0.01" min="0" name="cultivation_area" value="0">
                </div>
                <div class="supplier-register-field">
                    <label>Sản phẩm cung ứng</label>
                    <input name="products" required placeholder="Xoài, cam, bưởi">
                </div>
                <div class="supplier-register-field full">
                    <label>Địa chỉ</label>
                    <textarea name="address" placeholder="Địa chỉ liên hệ, kho gom hàng, ghi chú vùng trồng"></textarea>
                </div>
                <div class="supplier-register-actions">
                    <a class="supplier-register-link" href="/webbanhang/account/login">Đã có tài khoản?</a>
                    <button class="supplier-register-btn" type="submit">
                        <i class="fa-solid fa-paper-plane"></i> Gửi hồ sơ
                    </button>
                </div>
            </form>
        </div>
    </section>
</main>

<script>
document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        const icon = button.querySelector('i');

        if (!input || !icon) {
            return;
        }

        const shouldShow = input.type === 'password';
        input.type = shouldShow ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !shouldShow);
        icon.classList.toggle('fa-eye-slash', shouldShow);
        button.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
    });
});
</script>

<?php include __DIR__ . '/../shares/footer.php'; ?>
