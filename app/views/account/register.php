<?php include __DIR__ . '/../shares/header.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    :root {
        --primary: #22c55e;
        --primary-light: #bbf7d0;
        --primary-dark: #15803d;
    }

    .password-wrapper { position: relative; }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #6b7280;
        z-index: 10;
        transition: 0.3s;
    }
    .toggle-password:hover { color: var(--primary); }

    .form-control.rounded-pill { padding-right: 45px; }

    .form-control {
        background: #f0fdf4;
        border: 1px solid #d1fae5;
    }

    .form-control:focus {
        box-shadow: 0 0 0 0.2rem rgba(34,197,94,0.25);
        border-color: var(--primary);
        background: #fff;
    }

    .error-feedback {
        color: #dc3545;
        font-size: 0.85rem;
        padding-left: 15px;
        margin-top: 4px;
        display: none;
    }

    .is-invalid { border-color: #dc3545 !important; }

    .btn-green {
        background: linear-gradient(135deg, #22c55e, #4ade80);
        border: none;
        border-radius: 30px;
        padding: 12px;
        transition: 0.3s;
    }

    .btn-green:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(34,197,94,0.3);
    }

    .card-glass {
        backdrop-filter: blur(12px);
        background: rgba(255,255,255,0.9);
    }
</style>

<div class="vh-100 d-flex justify-content-center align-items-center"
     style="background: linear-gradient(135deg, #d1fae5, #bbf7d0);">

  <div class="card card-glass shadow-lg border-0" 
       style="width: 420px; border-radius: 20px; overflow: hidden;">

    <div class="text-center p-4 text-white"
         style="background: linear-gradient(to right, #16a34a, #22c55e);">
      <h3 class="mb-1 fw-bold">Aethera Supply</h3>
      <small class="opacity-75">Nông sản sạch Việt Nam</small>
    </div>

    <div class="card-body p-4">

      <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show rounded-pill shadow-sm">
          <i class="fa-solid <?= $_SESSION['msg_type'] == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?> me-2"></i>
          <?= $_SESSION['msg'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
      <?php endif; ?>

      <form action="/webbanhang/account/register" method="post" id="registerForm" novalidate>

        <div class="mb-3">
          <input type="text" name="username" class="form-control rounded-pill px-3"
                 placeholder="👤 Tên đăng nhập" required>
        </div>

        <div class="mb-3">
          <input type="text" name="fullname" class="form-control rounded-pill px-3"
                 placeholder="🧑 Họ và tên" required>
        </div>

        <div class="mb-3">
          <input type="email" name="email" id="email" class="form-control rounded-pill px-3"
                 placeholder="📧 Email" required>
          <div class="error-feedback" id="emailError">Email không đúng định dạng!</div>
        </div>

        <div class="mb-3 password-wrapper">
          <input type="password" name="password" id="password"
                 class="form-control rounded-pill px-3"
                 placeholder="🔒 Mật khẩu" required>
          <i class="fa-solid fa-eye toggle-password" onclick="togglePass('password', this)"></i>
          <div class="error-feedback" id="passwordError">Mật khẩu quá ngắn!</div>
        </div>

        <div class="mb-3 password-wrapper">
          <input type="password" name="confirm_password" id="confirm"
                 class="form-control rounded-pill px-3"
                 placeholder="🔒 Nhập lại mật khẩu" required>
          <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm', this)"></i>
          <div class="error-feedback" id="confirmError">Mật khẩu không khớp!</div>
        </div>

        <button type="submit" class="btn btn-green w-100 text-white fw-bold shadow-sm">
          <i class="fa-solid fa-seedling me-2"></i> Đăng ký ngay
        </button>

      </form>

      <p class="text-center mt-3 mb-0">
        Đã có tài khoản? 
        <a href="/webbanhang/account/login" class="text-decoration-none fw-bold" style="color: var(--primary-dark);">
          Đăng nhập ngay
        </a>
      </p>
      <p class="text-center mt-2 mb-0">
        <a href="/webbanhang/account/supplierRegister" class="text-decoration-none fw-bold" style="color: var(--primary-dark);">
          Đăng ký nhà cung cấp
        </a>
      </p>

    </div>
  </div>
</div>

<script>
function togglePass(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    }
}

document.getElementById('registerForm').onsubmit = function(e) {
    let isValid = true;
    const email = document.getElementById('email');
    const pass = document.getElementById('password');
    const confirm = document.getElementById('confirm');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    document.querySelectorAll('.error-feedback').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));

    if (!emailRegex.test(email.value)) {
        document.getElementById('emailError').style.display = 'block';
        email.classList.add('is-invalid');
        isValid = false;
    }

    if (pass.value.length < 6) {
        document.getElementById('passwordError').style.display = 'block';
        pass.classList.add('is-invalid');
        isValid = false;
    }

    if (pass.value !== confirm.value) {
        document.getElementById('confirmError').style.display = 'block';
        confirm.classList.add('is-invalid');
        isValid = false;
    }

    if (!isValid) e.preventDefault();
    return isValid;
};

document.querySelectorAll('.form-control').forEach(input => {
    input.oninput = function() {
        this.classList.remove('is-invalid');
        const errorId = this.id + 'Error';
        const errorEl = document.getElementById(errorId);
        if (errorEl) errorEl.style.display = 'none';
    };
});
</script>

<?php include __DIR__ . '/../shares/footer.php'; ?>
