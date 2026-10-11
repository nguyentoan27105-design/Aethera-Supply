<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);
?>

<div class="vh-100 d-flex justify-content-center align-items-center"
     style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);">

  <div class="card shadow border-0"
       style="width: 400px; border-radius: 18px; overflow: hidden;">

    <!-- HEADER -->
    <div class="text-center p-4 text-white"
         style="background: linear-gradient(to right, #22c55e, #4ade80);">
      <h3 class="mb-0 fw-bold">Đăng nhập</h3>
      <small class="opacity-75">Aethera Supply</small>
    </div>

    <div class="card-body p-4">

      <?php if($message): ?>
        <div class="alert border-0 shadow-sm mb-4 text-center"
             style="font-size: 0.9rem;
             background: <?= $messageType == 'danger' ? '#fef2f2' : '#ecfdf5' ?>;
             color: <?= $messageType == 'danger' ? '#7f1d1d' : '#065f46' ?>;
             border-radius: 10px;">
             
          <i class="fas 
            <?= $messageType == 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> me-2"></i> 
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form action="/webbanhang/account/login" method="post">
        <?= CsrfHelper::field() ?>

        <div class="mb-3">
          <label class="form-label ms-2 small fw-bold" style="color:#065f46;">
            Tài khoản hoặc Email
          </label>
          <input type="text" name="username"
                 class="form-control rounded-pill px-4 shadow-sm border-0"
                 style="background:#f0fdf4;"
                 placeholder="Nhập username hoặc email..." required>
        </div>

        <div class="mb-3">
          <label class="form-label ms-2 small fw-bold" style="color:#065f46;">
            Mật khẩu
          </label>
          <div class="input-group">
            <input type="password" name="password" id="password"
                   class="form-control rounded-start-pill px-4 shadow-sm border-0"
                   style="background:#f0fdf4;"
                   placeholder="Nhập mật khẩu..." required>

            <button class="btn border-0 shadow-sm px-3"
                    style="background:#f0fdf4;"
                    type="button" onclick="togglePass()">
              <i id="eye-icon" class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-4 text-end">
          <a href="/webbanhang/account/forgot"
             class="text-decoration-none small fw-bold"
             style="color:#16a34a;">
            Quên mật khẩu?
          </a>
        </div>

        <button type="submit"
                class="btn btn-lg w-100 text-white fw-bold shadow-sm transition-hover"
                style="background: linear-gradient(to right, #22c55e, #16a34a); border-radius: 30px; border: none;">
          ĐĂNG NHẬP
        </button>

      </form>

      <p class="text-center mt-4 mb-0 small text-secondary">
        Chưa có tài khoản? 
        <a href="/webbanhang/account/register"
           class="fw-bold text-decoration-none"
           style="color:#16a34a;">
          Đăng ký ngay
        </a>
      </p>
      <p class="text-center mt-2 mb-0 small text-secondary">
        Là nhà cung cấp?
        <a href="/webbanhang/account/supplierRegister"
           class="fw-bold text-decoration-none"
           style="color:#15803d;">
          Gửi hồ sơ hợp tác
        </a>
      </p>

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

<script>
function togglePass(){
  const p = document.getElementById('password');
  const icon = document.getElementById('eye-icon');

  if(p.type === 'password'){
    p.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    p.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>

<?php include __DIR__ . '/../shares/footer.php'; ?>
