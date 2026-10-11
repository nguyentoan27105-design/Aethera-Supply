<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: /webbanhang/account/forgot");
    exit();
}

$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>

<style>
:root {
    --primary: #22c55e;
    --primary-dark: #15803d;
    --primary-light: #dcfce7;
}

.form-control {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(34,197,94,0.25) !important;
    border-color: var(--primary);
    background: #fff !important;
}

.btn-green {
    background: linear-gradient(135deg, #22c55e, #4ade80);
    border: none;
    border-radius: 30px;
    transition: 0.3s;
}

.btn-green:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(34,197,94,0.3);
}

.btn-green:disabled {
    background: #ccc !important;
    cursor: not-allowed;
}

.card-glass {
    backdrop-filter: blur(12px);
    background: rgba(255,255,255,0.9);
}

.alert-custom {
    border-radius: 20px;
    font-size: 0.9rem;
}
</style>

<div class="vh-100 d-flex justify-content-center align-items-center" 
     style="background: linear-gradient(135deg, #d1fae5, #bbf7d0); font-family: 'Segoe UI', sans-serif;">

    <div class="card card-glass shadow-lg border-0" style="width: 400px; border-radius: 20px;">
        
        <div class="text-center p-4 text-white" 
             style="background: linear-gradient(to right, #16a34a, #22c55e); border-radius: 20px 20px 0 0;">
            
            <div class="mb-2">
                <i class="fas fa-leaf fa-2x"></i>
            </div>

            <h4 class="fw-bold mb-1">Aethera Supply</h4>
            <small class="opacity-75">Nông sản sạch Việt Nam</small>

            <div class="mt-3">
                <i class="fas fa-key fa-lg"></i>
            </div>
        </div>

        <div class="card-body p-4">

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-custom border-0 shadow-sm text-center mb-3">
                    <i class="fas <?= $messageType == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?> me-1"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="/webbanhang/account/reset_password" method="POST" id="resetForm">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">MẬT KHẨU MỚI</label>
                    <input type="password" name="password" id="password"
                           class="form-control form-control-lg rounded-pill px-4" 
                           placeholder="Ít nhất 6 ký tự" required autofocus minlength="6">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">XÁC NHẬN MẬT KHẨU</label>
                    <input type="password" name="confirm_password" id="confirm_password"
                           class="form-control form-control-lg rounded-pill px-4" 
                           placeholder="Nhập lại mật khẩu" required>
                    <div id="check-msg" class="small mt-1 ms-3"></div>
                </div>

                <button type="submit" id="btnSubmit" 
                        class="btn btn-green btn-lg w-100 fw-bold text-white shadow mb-3">
                    <i class="fas fa-seedling me-2"></i> CẬP NHẬT NGAY
                </button>

                <div class="text-center">
                    <a href="/webbanhang/account/login" class="text-decoration-none small fw-bold" style="color: var(--primary-dark);">
                        <i class="fas fa-arrow-left me-1"></i> Quay lại đăng nhập
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const checkMsg = document.getElementById('check-msg');
const btnSubmit = document.getElementById('btnSubmit');

function validatePassword() {
    if (confirmPassword.value === '') {
        checkMsg.innerHTML = '';
        return;
    }

    if (password.value === confirmPassword.value) {
        checkMsg.innerHTML = '<i class="fas fa-check-circle me-1"></i> Mật khẩu trùng khớp';
        checkMsg.className = 'small mt-1 ms-3 text-success';
        btnSubmit.disabled = false;
    } else {
        checkMsg.innerHTML = '<i class="fas fa-times-circle me-1"></i> Mật khẩu không khớp';
        checkMsg.className = 'small mt-1 ms-3 text-danger';
        btnSubmit.disabled = true;
    }
}

password.onchange = validatePassword;
confirmPassword.onkeyup = validatePassword;
</script>

<?php include __DIR__ . '/../shares/footer.php'; ?>
