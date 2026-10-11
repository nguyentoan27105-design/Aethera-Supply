<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);

unset($_SESSION['debug_otp']);
?>

<div class="vh-100 d-flex justify-content-center align-items-center"
     style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);">

  <div class="card shadow border-0"
       style="width: 420px; border-radius: 18px; overflow: hidden;">

    <!-- HEADER -->
    <div class="text-center p-4 text-white"
         style="background: linear-gradient(to right, #22c55e, #4ade80);">
      <h3 class="mb-1 fw-bold">Quên mật khẩu</h3>
      <p class="mb-0 opacity-75">Nhập email để nhận mã xác nhận</p>
    </div>

    <div class="card-body p-4">

      <?php if($message): ?>
        <div class="alert border-0 shadow-sm mb-3 text-center"
             style="font-size: 0.9rem;
             background: <?= $messageType == 'danger' ? '#fef2f2' : '#ecfdf5' ?>;
             color: <?= $messageType == 'danger' ? '#ffffff' : '#065f46' ?>;
             border-radius: 10px;">
             
            <i class="fas 
              <?= $messageType == 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> me-2"></i> 
            <?= htmlspecialchars($message) ?>
        </div>
        
      <?php endif; ?>

      <form method="post" action="/webbanhang/account/forgot" class="mt-2">

        <div class="mb-3">
          <label class="form-label ms-2 fw-bold" style="font-size: 0.85rem; color:#065f46;">
            Email hoặc tài khoản
          </label>
          <input type="text" name="username_or_email"
                 class="form-control rounded-pill px-4 shadow-sm border-0"
                 style="height: 50px; background:#f0fdf4;"
                 placeholder="Nhập email hoặc username..." required>
        </div>

        <button type="submit"
                class="btn btn-lg w-100 text-white fw-bold shadow-sm transition-hover"
                style="background: linear-gradient(to right, #22c55e, #16a34a); border-radius: 30px; border: none;">
          GỬI MÃ XÁC NHẬN
        </button>

      </form>

      <div class="text-center mt-4">
        <a href="/webbanhang/account/login"
           class="text-decoration-none small fw-bold"
           style="color:#065f46;">
          <i class="fas fa-arrow-left me-1"></i> Quay lại đăng nhập
        </a>
      </div>

    </div>
  </div>
</div>

<style>
.transition-hover:hover {
    transform: translateY(-2px);
    opacity: 0.95;
    transition: all 0.2s;
}

.form-control:focus {
    background-color: #ffffff !important;
    box-shadow: 0 0 0 0.2rem rgba(34,197,94,0.25) !important;
    border: none;
}

.alert {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php include __DIR__ . '/../shares/footer.php'; ?>
