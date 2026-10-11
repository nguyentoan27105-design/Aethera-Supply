<?php include __DIR__ . '/../shares/header.php'; ?>

<?php
$message = $_SESSION['msg'] ?? '';
$messageType = $_SESSION['msg_type'] ?? 'danger';
unset($_SESSION['msg'], $_SESSION['msg_type']);
$debugAccountOtp = (string)($_SESSION['debug_account_otp'] ?? '');
$verificationEmail = $verificationEmail ?? '';
$verificationExpired = $verificationExpired ?? false;
?>

<style>
.verify-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%); }
.verify-card { width:min(100%,430px); background:#fff; border:1px solid #d7efe2; border-radius:18px; box-shadow:0 20px 45px rgba(6,78,59,.12); overflow:hidden; }
.verify-head { padding:24px; color:#fff; background:linear-gradient(120deg,#047857,#22c55e); text-align:center; }
.verify-head h3 { margin:0; font-weight:950; letter-spacing:0; color:#fff; }
.verify-head small { color:rgba(255,255,255,.82); font-weight:700; }
.verify-body { padding:24px; }
.verify-alert { border-radius:12px; padding:12px 14px; margin-bottom:14px; font-weight:800; text-align:center; }
.verify-alert.success { background:#ecfdf5; color:#065f46; }
.verify-alert.warning { background:#fffbeb; color:#92400e; }
.verify-alert.danger { background:#fef2f2; color:#991b1b; }
.verify-hint { color:#64748b; text-align:center; font-weight:750; margin:0 0 14px; line-height:1.45; }
.verify-hint strong { color:#064e3b; }
.verify-code-box { border:1px dashed #86efac; background:#f0fdf4; color:#065f46; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-weight:850; text-align:center; }
.verify-code-box strong { display:block; font-size:1.4rem; letter-spacing:3px; margin-top:4px; }
.verify-input { width:100%; min-height:48px; border:1px solid #bbf7d0; border-radius:999px; background:#f0fdf4; padding:0 18px; text-align:center; font-size:1.15rem; font-weight:950; letter-spacing:4px; outline:0; }
.verify-input:focus { background:#fff; border-color:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.16); }
.verify-btn { min-height:46px; border:0; border-radius:999px; padding:0 18px; width:100%; display:inline-flex; align-items:center; justify-content:center; gap:8px; background:linear-gradient(120deg,#22c55e,#16a34a); color:#fff; font-weight:950; margin-top:14px; }
.verify-link-btn { min-height:42px; border:1px solid #bbf7d0; border-radius:999px; padding:0 16px; width:100%; background:#fff; color:#047857; font-weight:900; margin-top:10px; }
.verify-foot { text-align:center; margin-top:16px; color:#64748b; font-weight:750; }
.verify-foot a { color:#15803d; font-weight:950; text-decoration:none; }
</style>

<main class="verify-page">
    <section class="verify-card">
        <div class="verify-head">
            <h3>Xác thực tài khoản</h3>
            <small>Aethera Supply</small>
        </div>
        <div class="verify-body">
            <?php if ($message): ?>
                <div class="verify-alert <?= htmlspecialchars($messageType) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($verificationEmail !== ''): ?>
                <p class="verify-hint">
                    Mã xác thực của tài khoản
                    <strong><?= htmlspecialchars($verificationEmail) ?></strong>
                    có hiệu lực trong 15 phút.
                </p>
            <?php endif; ?>

            <?php if ($verificationExpired): ?>
                <div class="verify-alert warning">
                    Mã xác thực đã hết hạn. Bấm “Gửi lại mã” để tạo mã mới.
                </div>
            <?php elseif ($debugAccountOtp !== ''): ?>
                <div class="verify-code-box">
                    Mã xác thực local
                    <strong><?= htmlspecialchars($debugAccountOtp) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="/webbanhang/account/verifyAccount">
                <input class="verify-input" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" value="<?= htmlspecialchars($verificationExpired ? '' : $debugAccountOtp) ?>" autocomplete="one-time-code" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);" required autofocus>
                <button class="verify-btn" type="submit">
                    <i class="fa-solid fa-circle-check"></i> Xác thực
                </button>
            </form>

            <form method="POST" action="/webbanhang/account/resendAccountVerification">
                <button class="verify-link-btn" type="submit">
                    <i class="fa-solid fa-rotate-right"></i> Gửi lại mã
                </button>
            </form>

            <p class="verify-foot">
                Đã xác thực? <a href="/webbanhang/account/login">Đăng nhập</a>
            </p>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../shares/footer.php'; ?>
