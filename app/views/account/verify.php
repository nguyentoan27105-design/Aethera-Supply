<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);

$resetEmail = $_SESSION['reset_email'] ?? 'Email của bạn';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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

.card-glass {
    backdrop-filter: blur(12px);
    background: rgba(255,255,255,0.9);
}

.alert-custom {
    border-radius: 20px;
    font-size: 0.9rem;
}

input::placeholder {
    letter-spacing: 5px;
    opacity: 0.3;
}

.alert {
    animation: fadeInDown 0.4s ease-out;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="vh-100 d-flex justify-content-center align-items-center"
     style="background: linear-gradient(135deg, #d1fae5, #bbf7d0);">

    <div class="card card-glass shadow-lg border-0"
         style="width: 420px; border-radius: 20px; overflow: hidden;">

        <div class="text-center p-4 text-white"
             style="background: linear-gradient(to right, #16a34a, #22c55e);">
            <i class="fas fa-leaf mb-2"></i>
            <h4 class="fw-bold mb-1">Aethera Supply</h4>
            <small class="opacity-75">Nông sản sạch Việt Nam</small>
        </div>

        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="display-6 mb-2" style="color: var(--primary);">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <small class="text-muted">
                    Kiểm tra email:<br>
                    <strong class="text-dark"><?= htmlspecialchars($resetEmail) ?></strong>
                </small>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-custom border-0 shadow-sm text-center mb-3">
                    <i class="fas <?= $messageType == 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?> me-1"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/webbanhang/account/verify" class="mt-2">
                <div class="mb-4">
                    <label class="form-label ms-2 fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">
                        Mã xác nhận (6 số)
                    </label>

                    <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           class="form-control form-control-lg text-center fw-bold rounded-pill shadow-sm"
                           style="height: 60px; font-size: 1.8rem; letter-spacing: 8px;"
                           placeholder="------" required autofocus>
                </div>

                <button type="submit" class="btn btn-green btn-lg w-100 text-white fw-bold shadow-sm">
                    <i class="fas fa-check-circle me-2"></i> XÁC NHẬN MÃ
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="small text-muted mb-1">Không nhận được mã?</p>
                <div id="resend-countdown" class="small fw-bold text-muted">
                    Gửi lại sau <span id="timer">60</span>s
                </div>
                <a href="/webbanhang/account/forgot" id="resend-link"
                   class="text-decoration-none small fw-bold d-none"
                   style="color: var(--primary-dark);">
                    Gửi lại yêu cầu
                </a>
            </div>

            <div class="text-center mt-3">
                <a href="/webbanhang/account/login"
                   class="text-decoration-none small fw-bold"
                   style="color: var(--primary-dark);">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại đăng nhập
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let timeLeft = 60;
const timerSpan = document.getElementById('timer');
const countdownDiv = document.getElementById('resend-countdown');
const resendLink = document.getElementById('resend-link');

const timer = setInterval(() => {
    timeLeft--;
    timerSpan.textContent = timeLeft;

    if (timeLeft <= 0) {
        clearInterval(timer);
        countdownDiv.classList.add('d-none');
        resendLink.classList.remove('d-none');
    }
}, 1000);
</script>

<?php include __DIR__ . '/../shares/footer.php'; ?>
