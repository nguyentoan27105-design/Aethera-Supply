<?php 
// Đảm bảo session luôn chạy
if (session_status() === PHP_SESSION_NONE) session_start();

// Lấy dữ liệu giỏ hàng từ session (đã được CartController xử lý)
$cart = $_SESSION['cart'] ?? [];
if (!function_exists('cartWeightLabel')) {
    function cartWeightLabel(int $kg): string {
        if ($kg >= 1000) {
            $tons = intdiv($kg, 1000);
            $remain = $kg % 1000;
            return $remain > 0 ? "{$tons} tấn {$remain} kg" : "{$tons} tấn";
        }
        return "{$kg} kg";
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body{
    background:#f8fafc !important;
    min-height:100vh;
    color:#0f172a;
    font-family:'Plus Jakarta Sans',sans-serif;
}

/* ===== CONTAINER ===== */

.container{
    max-width:1400px;
}

/* ===== GLASS CARD ===== */

.glass-card{
    background:#fff !important;
    border-radius:28px !important;
    border:1px solid #eef2f7 !important;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
    transition:.3s;
    overflow:hidden;
}

.glass-card:hover{
    transform:translateY(-6px);
    box-shadow:0 20px 40px rgba(0,0,0,.1);
}

/* ===== IMAGE ===== */

.cart-img{
    height:220px;
    width:100%;
    object-fit:contain;
    background:#f8fafc;
    padding:18px;
}

/* ===== QUANTITY ===== */

.qty-btn{
    width:38px;
    height:38px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    text-decoration:none;
    border:none;
    background:#f1f5f9;
    color:#0f172a;
    transition:.25s;
}

.qty-btn:hover{
    background:#16a34a;
    color:#fff;
    transform:scale(1.08);
}

.qty-edit{
    display:grid;
    grid-template-columns:minmax(90px,1fr) 104px 46px;
    gap:8px;
    margin-bottom:12px;
}

.qty-edit input,
.qty-edit select{
    min-height:40px;
    border:1px solid #dbe2ea;
    border-radius:12px;
    padding:0 10px;
    font-weight:800;
    color:#0f172a;
    background:#fff;
}

.qty-edit button{
    border:0;
    border-radius:12px;
    background:#059669;
    color:#fff;
    display:inline-grid;
    place-items:center;
}

.qty-edit button i,
.qty-btn i{
    width:16px;
    height:16px;
    display:inline-grid;
    place-items:center;
    line-height:1;
}

/* ===== TOTAL SECTION ===== */

.total-section{
    background:#fff;
    border-radius:30px;
    padding:35px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
    border:1px solid #eef2f7;
}

/* ===== CHECKOUT BUTTON ===== */

.btn-checkout{
    background:linear-gradient(135deg,#22c55e,#16a34a);
    border:none;
    color:#fff !important;
    font-weight:800;
    transition:.3s;
    border-radius:18px !important;
}

.btn-checkout:hover{
    transform:translateY(-3px);
    box-shadow:0 15px 30px rgba(34,197,94,.3);
}

/* ===== VOUCHER ===== */

.voucher-glass{
    background:#fff !important;
    border:1px solid #dbe2ea !important;
    color:#0f172a !important;
    height:52px;
    border-radius:14px 0 0 14px !important;
}

.btn-apply-voucher{
    border-radius:0 14px 14px 0 !important;
    background:#16a34a;
    color:#fff;
    border:none;
    padding:0 22px;
}

/* ===== TEXT ===== */

.text-white{
    color:#0f172a !important;
}

.text-white-50{
    color:#64748b !important;
}

/* ===== MOBILE ===== */

@media(max-width:768px){

    .cart-img{
        height:180px;
    }

    .total-section{
        padding:24px;
    }

    .display-6{
        font-size:2rem;
    }

}
</style>

<div class="container pb-5 animate__animated animate__fadeIn">
    <?php if (!empty($_SESSION['cart_notice'])): ?>
        <div class="alert alert-success border-0 rounded-4 fw-bold mt-4">
            <i class="fas fa-tags me-2"></i><?= htmlspecialchars($_SESSION['cart_notice']) ?>
        </div>
        <?php unset($_SESSION['cart_notice']); ?>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4 mt-4">
        <div>
            <h2 class="fw-bold mb-0 text-white"><i class="fas fa-shopping-cart me-2 text-warning"></i> GIỎ HÀNG</h2>
            <p class="text-white-50 mb-0">Bạn đang có <?= count($cart) ?> sản phẩm trong túi đồ</p>
        </div>
        <?php if(!empty($cart)): ?>
            <form method="POST" action="/webbanhang/Cart/clear" class="m-0"
                  onsubmit="return confirm('Bạn muốn làm trống toàn bộ giỏ hàng?')">
                <?= CsrfHelper::field() ?>
            <button type="submit" class="btn btn-sm btn-outline-light rounded-pill px-3">
                <i class="fas fa-trash-alt me-1"></i> Làm trống
            </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if(empty($cart)): ?>
        <div class="glass-card p-5 text-center my-5 animate__animated animate__zoomIn">
            <div class="mb-4"><i class="fas fa-shopping-bag fa-5x text-white-50"></i></div>
            <h3 class="fw-bold">Giỏ hàng đang trống!</h3>
            <p class="opacity-75">Hãy lấp đầy giỏ hàng bằng những sản phẩm tuyệt vời.</p>
            <a href="/webbanhang/Product" class="btn btn-light rounded-pill px-5 py-2 fw-bold mt-3">ĐI MUA SẮM NGAY</a>
        </div>
    <?php else: ?>
        <?php $subtotal = 0; ?>
        <div class="row g-4">
            <?php foreach($cart as $id => $item): ?>
                <?php 
                    $price = (float)($item['price'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 0);
                    $quantityValue = $quantity >= 1000 && $quantity % 1000 === 0 ? (int)($quantity / 1000) : $quantity;
                    $quantityUnit = $quantity >= 1000 && $quantity % 1000 === 0 ? 'ton' : 'kg';
                    $itemTotal = $price * $quantity; 
                    $subtotal += $itemTotal;
                    
                    // Logic xử lý đường dẫn ảnh linh hoạt
                   $imgUrl = !empty($item['image']) 
    ? "/webbanhang/public/uploads/" . basename($item['image'])
    : "https://placehold.co/400x300?text=No+Image";
                ?>
                <div class="col-sm-6 col-lg-4 d-flex">
                    <div class="card glass-card w-100 h-100 border-0">
                        <div class="position-relative">
                            <img src="<?= $imgUrl ?>" class="card-img-top cart-img" alt="<?= htmlspecialchars($item['name']) ?>" 
                                 onerror="this.src='https://placehold.co/400x300?text=No+Image'">
                            <form method="POST" action="/webbanhang/Cart/removeFromCart/<?= $id ?>"
                                  onsubmit="return confirm('Xóa sản phẩm này?')">
                                <?= CsrfHelper::field() ?>
                                <button type="submit"
                                   class="btn btn-danger position-absolute top-0 end-0 m-2 rounded-circle btn-sm"
                                   style="width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-white mb-1 text-truncate"><?= htmlspecialchars($item['name']) ?></h5>
                            <p class="text-white-50 small mb-3">Đơn giá: <?= number_format($price, 0, ',', '.') ?>đ/kg</p>

                            <form action="/webbanhang/Cart/updateQuantity/<?= $id ?>" method="POST" class="qty-edit">
                                <input type="number" name="quantity_value" value="<?= htmlspecialchars((string)$quantityValue) ?>" min="1" step="1" aria-label="Khối lượng muốn mua">
                                <select name="quantity_unit" aria-label="Đơn vị khối lượng">
                                    <option value="kg" <?= $quantityUnit === 'kg' ? 'selected' : '' ?>>kg</option>
                                    <option value="ton" <?= $quantityUnit === 'ton' ? 'selected' : '' ?>>tấn</option>
                                </select>
                                <button type="submit" title="Cập nhật khối lượng"><i class="fas fa-check"></i></button>
                            </form>

                            <div class="d-flex align-items-center justify-content-between bg-light border p-2 rounded-3 mb-3">
                                <form method="POST" action="/webbanhang/Cart/decreaseQuantity/<?= $id ?>" class="m-0">
                                    <?= CsrfHelper::field() ?>
                                <button type="submit" class="qty-btn">
                                    <i class="fas fa-minus small text-dark"></i>
                                </button>
                                </form>

                                <span class="fw-bold fs-5 text-dark">
                                    <?= htmlspecialchars(cartWeightLabel($quantity)) ?>
                                </span>

                                <form method="POST" action="/webbanhang/Cart/increaseQuantity/<?= $id ?>" class="m-0">
                                    <?= CsrfHelper::field() ?>
                                <button type="submit" class="qty-btn">
                                    <i class="fas fa-plus small text-dark"></i>
                                </button>
                                </form>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-2">
                                <span class="small text-white-50">Tạm tính theo kg:</span>
                                <b class="text-warning fs-5"><?= number_format($itemTotal, 0, ',', '.') ?>đ</b>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total-section mt-5 animate__animated animate__fadeInUp">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h5 class="fw-bold mb-3"><i class="fas fa-tags me-2 text-warning"></i>ƯU ĐÃI</h5>
                    <form action="/webbanhang/Cart/applyVoucher" method="POST">
                        <div class="input-group">
                            <input type="text" name="voucher_code" class="form-control voucher-glass" 
                                   placeholder="Nhập mã giảm giá..." value="<?= htmlspecialchars($_SESSION['voucher_code'] ?? '') ?>">
                            <button class="btn btn-apply-voucher px-4" type="submit">SỬ DỤNG</button>
                        </div>
                        <?php if (isset($_SESSION['discount_percent'])): ?>
                            <div class="mt-2 text-warning small animate__animated animate__headShake">
                                <i class="fas fa-check-circle me-1"></i> Đã áp dụng giảm <?= $_SESSION['discount_percent'] ?>%
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="col-lg-7">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="opacity-75">Tạm tính</span>
                        <span class="fw-bold"><?= number_format($subtotal, 0, ',', '.') ?>đ</span>
                    </div>
                    
                    <?php 
                        $finalTotal = $subtotal;
                        if (isset($_SESSION['discount_percent'])) {
                            $discountAmount = $subtotal * ($_SESSION['discount_percent'] / 100);
                            $finalTotal = $subtotal - $discountAmount;
                    ?>
                        <div class="d-flex justify-content-between mb-2 text-warning">
                            <span class="small">Giảm giá (Voucher)</span>
                            <span class="small">-<?= number_format($discountAmount, 0, ',', '.') ?>đ</span>
                        </div>
                    <?php } ?>

                    <hr class="border-white border-opacity-25">
                    <div class="d-flex justify-content-between align-items-end mb-4">
                        <span class="fs-5">Tổng thanh toán:</span>
                        <span class="display-6 fw-bold text-warning"><?= number_format($finalTotal, 0, ',', '.') ?>đ</span>
                    </div>

                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="/webbanhang/Cart/checkout" class="btn btn-checkout btn-lg w-100 py-3 rounded-3">
                            <i class="fas fa-credit-card me-2"></i> TIẾN HÀNH THANH TOÁN
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-checkout btn-lg w-100 py-3 rounded-3"
                                onclick="Swal.fire({
                                    title: 'Yêu cầu đăng nhập',
                                    text: 'Bạn cần đăng nhập để thực hiện thanh toán!',
                                    icon: 'info',
                                    showCancelButton: true,
                                    confirmButtonText: 'Đăng nhập ngay',
                                    cancelButtonText: 'Để sau'
                                }).then((result) => {
                                    if (result.isConfirmed) window.location.href='/webbanhang/Account/login'
                                })">
                            ĐĂNG NHẬP ĐỂ THANH TOÁN
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
