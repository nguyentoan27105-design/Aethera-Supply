<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['cart']) || !isset($_SESSION['user_id'])) {
    header("Location: /webbanhang/Cart");
    exit();
}

$cart = $_SESSION['cart'] ?? [];
$account = $user ?? (object)[];
$total = 0;
$totalWeightKg = 0;
foreach ($cart as $item) {
    $quantityKg = (int)($item['quantity'] ?? 0);
    $total += (float)$item['price'] * $quantityKg;
    $totalWeightKg += $quantityKg;
}
$discountPercent = (float)($_SESSION['discount_percent'] ?? 0);
$discountAmount = ($total * $discountPercent) / 100;
$finalTotal = max(0, $total - $discountAmount);
$fullName = $_SESSION['fullname'] ?? ($account->fullname ?? '');
$email = $_SESSION['email'] ?? ($account->email ?? '');
$phone = $_SESSION['phone'] ?? ($account->phone ?? '');
$address = $account->address ?? '';
if (!function_exists('checkoutWeightLabel')) {
    function checkoutWeightLabel(int $kg): string {
        if ($kg >= 1000) {
            $tons = intdiv($kg, 1000);
            $remain = $kg % 1000;
            return $remain > 0 ? "{$tons} tấn {$remain} kg" : "{$tons} tấn";
        }
        return "{$kg} kg";
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<div class="checkout-page checkout-domestic">
    <div class="checkout-shell">
        <a class="checkout-back" href="/webbanhang/Cart">
            <i class="fa-solid fa-chevron-left"></i> Quay lại giỏ hàng
        </a>

        <form method="POST" action="/webbanhang/Cart/processCheckout" id="checkoutForm" class="checkout-layout" data-subtotal="<?= (float)$total ?>" data-weight="<?= (int)$totalWeightKg ?>">
            <section class="checkout-main">
                <div class="checkout-hero">
                    <div>
                        <span>Thanh toán an toàn</span>
                        <h1>Thanh toán đơn hàng</h1>
                        <p>Điền thông tin nhận hàng, chọn cách thanh toán và đơn vị vận chuyển phù hợp.</p>
                    </div>
                    <div class="scope-switch" role="radiogroup" aria-label="Phạm vi giao hàng">
                        <input type="radio" name="delivery_scope" id="scopeDomestic" value="domestic" checked>
                        <label for="scopeDomestic"><i class="fa-solid fa-house"></i> Nội địa</label>
                        <input type="radio" name="delivery_scope" id="scopeInternational" value="international">
                        <label for="scopeInternational"><i class="fa-solid fa-globe"></i> Quốc tế</label>
                    </div>
                </div>

                <div class="checkout-flow" aria-label="Quy trình thanh toán">
                    <div class="active"><span>1</span><strong>Người nhận</strong><small>Thông tin liên hệ</small></div>
                    <div><span>2</span><strong>Địa chỉ</strong><small>Nội địa/quốc tế</small></div>
                    <div><span>3</span><strong>Thanh toán</strong><small>Cọc hoặc trả đủ</small></div>
                    <div><span>4</span><strong>Vận chuyển</strong><small>Tính phí theo địa chỉ</small></div>
                </div>

                <div class="checkout-section">
                    <div class="section-title">
                        <span>1</span>
                        <div>
                            <h2>Thông tin người nhận</h2>
                            <p>Dùng để liên hệ khi giao nông sản.</p>
                        </div>
                    </div>
                    <div class="field-grid two">
                        <label class="field">
                            <span>Họ và tên</span>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($fullName) ?>" required>
                        </label>
                        <label class="field">
                            <span>Số điện thoại</span>
                            <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="0901234567" required>
                        </label>
                        <label class="field wide">
                            <span>Email nhận hóa đơn</span>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="name@example.com">
                        </label>
                    </div>
                </div>

                <div class="checkout-section">
                    <div class="section-title">
                        <span>2</span>
                        <div>
                            <h2>Địa chỉ giao hàng</h2>
                            <p class="domestic-copy">Giao hàng nội địa chỉ cần địa chỉ Việt Nam chi tiết, không bắt buộc mã bưu chính.</p>
                            <p class="international-copy">Đơn quốc tế cần quốc gia, mã bưu chính và vùng/bang để hỗ trợ chứng từ vận chuyển.</p>
                        </div>
                    </div>
                    <div class="field-grid two">
                        <label class="field country-field">
                            <span>Quốc gia</span>
                            <select name="recipient_country" id="recipientCountry" required>
                                <option value="Việt Nam" selected>Việt Nam</option>
                                <option value="Nhật Bản">Nhật Bản</option>
                                <option value="Hàn Quốc">Hàn Quốc</option>
                                <option value="Singapore">Singapore</option>
                                <option value="Hoa Kỳ">Hoa Kỳ</option>
                                <option value="Úc">Úc</option>
                                <option value="Đức">Đức</option>
                                <option value="Khác">Khác</option>
                            </select>
                        </label>
                        <label class="field international-only">
                            <span>Mã bưu chính / Postal code</span>
                            <input type="text" name="postal_code" id="postalCode" placeholder="100-0001">
                        </label>
                        <label class="field">
                            <span>Tỉnh/Thành phố</span>
                            <input type="text" name="recipient_city" placeholder="TP.HCM" required>
                        </label>
                        <label class="field international-only">
                            <span>Bang/Vùng</span>
                            <input type="text" name="recipient_region" placeholder="Kanto, California...">
                        </label>
                        <label class="field domestic-only">
                            <span>Quận/Huyện</span>
                            <input type="text" name="recipient_district" id="recipientDistrict" placeholder="Quận 1">
                        </label>
                        <label class="field domestic-only">
                            <span>Phường/Xã</span>
                            <input type="text" name="recipient_ward" id="recipientWard" placeholder="Bến Nghé">
                        </label>
                        <label class="field wide">
                            <span>Địa chỉ cụ thể</span>
                            <textarea name="address_detail" placeholder="Số nhà, tên đường, tòa nhà, tầng..." required><?= htmlspecialchars($address) ?></textarea>
                        </label>
                    </div>
                </div>

                <div class="checkout-section">
                    <div class="section-title">
                        <span>3</span>
                        <div>
                            <h2>Thanh toán</h2>
                            <p>Hỗ trợ COD, chuyển khoản ngân hàng, Visa/MasterCard và SWIFT/TT cho đơn xuất khẩu.</p>
                        </div>
                    </div>

                    <input type="hidden" name="payment_brand" id="paymentBrand" value="">
                    <div class="payment-grid">
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="Cash on Delivery" data-brand="COD" data-scope="domestic" data-upfront="0" checked>
                            <span>
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                <strong>COD</strong>
                                <small>Thanh toán khi nhận hàng nội địa.</small>
                            </span>
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="Domestic Bank Transfer" data-brand="Bank Account" data-scope="all" data-upfront="1">
                            <span>
                                <i class="fa-solid fa-building-columns"></i>
                                <strong>Chuyển khoản</strong>
                                <small>Chuyển khoản ngân hàng nội địa.</small>
                            </span>
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="International Card" data-brand="Visa/MasterCard" data-scope="all" data-upfront="1">
                            <span>
                                <i class="fa-brands fa-cc-visa"></i>
                                <strong>Visa/MasterCard</strong>
                                <small>Thanh toán bằng thẻ quốc tế.</small>
                            </span>
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="SWIFT TT" data-brand="SWIFT/TT" data-scope="international" data-upfront="1">
                            <span>
                                <i class="fa-solid fa-globe"></i>
                                <strong>SWIFT/TT</strong>
                                <small>Chuyển khoản quốc tế cho đơn xuất khẩu.</small>
                            </span>
                        </label>
                    </div>

                    <label class="field payment-reference">
                        <span>Mã giao dịch / ghi chú thanh toán</span>
                        <input type="text" name="payment_reference" placeholder="Mã chuyển khoản, 4 số cuối thẻ hoặc ghi chú COD">
                    </label>
                </div>

                <div class="checkout-section">
                    <div class="section-title">
                        <span>4</span>
                        <div>
                            <h2>Đơn vị vận chuyển</h2>
                            <p>Chọn nhà vận chuyển phù hợp với phạm vi giao hàng.</p>
                        </div>
                    </div>
                    <div class="field-grid two">
                        <label class="field">
                            <span>Đơn vị vận chuyển</span>
                            <select name="shipping_carrier" id="shippingCarrier" required></select>
                        </label>
                        <label class="field">
                            <span>Dịch vụ</span>
                            <select name="shipping_service" id="shippingService" required></select>
                        </label>
                        <label class="field international-only">
                            <span>Incoterm</span>
                            <select name="shipping_incoterm">
                                <option value="DAP">DAP - Giao tại nơi đến</option>
                                <option value="FOB">FOB - Giao lên tàu</option>
                                <option value="CIF">CIF - Giá, bảo hiểm, cước</option>
                                <option value="EXW">EXW - Giao tại xưởng</option>
                            </select>
                        </label>
                        <div class="shipping-estimate wide">
                            <i class="fa-solid fa-scale-balanced"></i>
                            <div>
                                <strong>Khối lượng tính phí: <?= htmlspecialchars(checkoutWeightLabel((int)$totalWeightKg)) ?></strong>
                                <span id="shippingFormulaText">Phí vận chuyển sẽ được tính theo phạm vi, đơn vị vận chuyển và tổng kg trong giỏ.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="summary-card">
                <div class="summary-head">
                    <div>
                        <span>Đơn hàng của bạn</span>
                        <h2><?= count($cart) ?> sản phẩm</h2>
                    </div>
                </div>

                <div class="summary-items">
                    <?php foreach ($cart as $item): ?>
                        <?php
                        $subtotal = (float)$item['price'] * (int)$item['quantity'];
                        $img = !empty($item['image']) ? "/webbanhang/public/uploads/" . basename($item['image']) : "https://placehold.co/100x100?text=Product";
                        ?>
                        <div class="summary-item">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.src='https://placehold.co/100x100?text=Product'">
                            <div>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <small>Khối lượng: <?= htmlspecialchars(checkoutWeightLabel((int)$item['quantity'])) ?></small>
                            </div>
                            <span><?= number_format($subtotal, 0, ',', '.') ?>đ</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-total">
                    <div><span>Tạm tính</span><strong><?= number_format($total, 0, ',', '.') ?>đ</strong></div>
                    <?php if ($discountPercent > 0): ?>
                        <div class="discount-line"><span>Mã <?= htmlspecialchars($_SESSION['voucher_code'] ?? '') ?> (<?= $discountPercent ?>%)</span><strong>-<?= number_format($discountAmount, 0, ',', '.') ?>đ</strong></div>
                    <?php endif; ?>
                    <div><span>Vận chuyển</span><strong id="shippingFeeText">Đang tính</strong></div>
                    <div class="grand"><span>Tổng thanh toán</span><strong id="grandTotalText"><?= number_format($finalTotal, 0, ',', '.') ?>đ</strong></div>
                    <div class="deposit-due"><span>Cần thanh toán trước</span><strong id="depositAmountText"><?= number_format($finalTotal * 0.4, 0, ',', '.') ?>đ</strong></div>
                    <div><span>Còn lại</span><strong id="remainingAmountText"><?= number_format($finalTotal * 0.6, 0, ',', '.') ?>đ</strong></div>
                </div>

                <input type="hidden" name="client_shipping_fee" id="clientShippingFee" value="0">

                <div class="deposit-policy">
                    <strong>Chọn mức thanh toán trước</strong>
                    <div class="deposit-grid">
                        <label>
                            <input type="radio" name="deposit_percent" value="40" checked>
                            <span>Cọc 40%</span>
                        </label>
                        <label>
                            <input type="radio" name="deposit_percent" value="50">
                            <span>Cọc 50%</span>
                        </label>
                        <label>
                            <input type="radio" name="deposit_percent" value="60">
                            <span>Cọc 60%</span>
                        </label>
                        <label>
                            <input type="radio" name="deposit_percent" value="100">
                            <span>Thanh toán 100%</span>
                        </label>
                    </div>
                    <small id="depositPolicyText">
                        Đơn hàng được ghi nhận sau khi thanh toán trước 40-60% hoặc thanh toán đủ 100%.
                    </small>
                </div>

                <button type="submit" class="checkout-submit">
                    Thanh toán trước <span id="submitAmountText"><?= number_format($finalTotal * 0.4, 0, ',', '.') ?>đ</span> <i class="fa-solid fa-arrow-right"></i>
                </button>
                <a href="/webbanhang/Cart" class="back-cart">Sửa giỏ hàng</a>
            </aside>
        </form>
    </div>
</div>

<style>
.checkout-page { background:#f5faf7; padding:28px 16px 48px; font-family:'Inter',sans-serif; color:#0f172a; }
.checkout-shell { max-width:1180px; margin:0 auto; }
.checkout-back { display:inline-flex; align-items:center; gap:8px; margin-bottom:16px; color:#047857; font-weight:900; text-decoration:none; }
.checkout-layout { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:22px; align-items:start; }
.checkout-main,.summary-card { background:#fff; border:1px solid #dbeee4; border-radius:22px; box-shadow:0 18px 40px rgba(6,78,59,.08); }
.checkout-main { padding:26px; }
.checkout-hero { display:grid; grid-template-columns:minmax(0,1fr) 290px; gap:18px; align-items:start; padding-bottom:22px; border-bottom:1px solid #edf5ef; }
.checkout-hero span { display:inline-flex; border-radius:999px; background:#dcfce7; color:#047857; padding:7px 12px; font-size:.76rem; font-weight:900; text-transform:uppercase; }
.checkout-hero h1 { margin:12px 0 8px; color:#052e2b; font-size:2rem; line-height:1.12; font-weight:950; }
.checkout-hero p { margin:0; color:#64748b; line-height:1.6; font-weight:650; }
.scope-switch { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.scope-switch input { display:none; }
.scope-switch label { min-height:48px; border:2px solid #dbeee4; border-radius:15px; display:flex; align-items:center; justify-content:center; gap:9px; font-weight:900; cursor:pointer; }
.scope-switch input:checked + label { border-color:#059669; background:#ecfdf5; color:#047857; }
.checkout-flow { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin:18px 0 2px; }
.checkout-flow div { min-height:74px; border:1px solid #dbeee4; border-radius:15px; background:#fff; padding:12px; display:grid; grid-template-columns:30px 1fr; column-gap:10px; align-items:center; }
.checkout-flow span { width:30px; height:30px; border-radius:50%; display:grid; place-items:center; background:#ecfdf5; color:#047857; font-weight:950; grid-row:1 / span 2; }
.checkout-flow strong { color:#052e2b; font-size:.9rem; line-height:1.2; }
.checkout-flow small { color:#64748b; font-weight:700; line-height:1.3; }
.checkout-flow .active { border-color:#059669; background:#ecfdf5; }
.checkout-flow .active span { background:#059669; color:#fff; }
.checkout-section { padding-top:22px; margin-top:22px; border-top:1px solid #edf5ef; }
.checkout-section:first-of-type { border-top:0; margin-top:0; }
.section-title { display:flex; gap:12px; margin-bottom:14px; }
.section-title > span { width:32px; height:32px; flex:0 0 32px; border-radius:50%; display:grid; place-items:center; background:#059669; color:#fff; font-weight:950; }
.section-title h2 { margin:0; color:#052e2b; font-size:1.08rem; font-weight:950; }
.section-title p { margin:4px 0 0; color:#64748b; font-size:.9rem; font-weight:650; }
.field-grid { display:grid; gap:14px; }
.field-grid.two { grid-template-columns:repeat(2,minmax(0,1fr)); }
.field.wide { grid-column:1 / -1; }
.field span { display:block; margin-bottom:7px; color:#334155; font-size:.76rem; font-weight:900; text-transform:uppercase; }
.field input,.field select,.field textarea { width:100%; min-height:46px; border:1px solid #d6e7dd; border-radius:13px; padding:10px 12px; outline:0; background:#fff; color:#0f172a; font-weight:650; }
.field textarea { min-height:90px; resize:vertical; }
.field input:focus,.field select:focus,.field textarea:focus { border-color:#059669; box-shadow:0 0 0 4px rgba(5,150,105,.12); }
.shipping-estimate { grid-column:1 / -1; display:flex; gap:12px; align-items:flex-start; border:1px solid #a7f3d0; border-radius:15px; background:#ecfdf5; padding:13px 14px; color:#047857; }
.shipping-estimate i { width:34px; height:34px; border-radius:12px; display:grid; place-items:center; background:#059669; color:#fff; flex:0 0 34px; }
.shipping-estimate strong { display:block; color:#052e2b; font-weight:950; }
.shipping-estimate span { display:block; margin-top:3px; color:#64748b; font-weight:700; line-height:1.45; }
.payment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.pay-option input { display:none; }
.pay-option span { min-height:82px; border:2px solid #dbeee4; border-radius:16px; display:grid; grid-template-columns:28px 1fr; column-gap:10px; align-items:center; padding:12px 14px; cursor:pointer; }
.pay-option i { color:#059669; font-size:1.1rem; grid-row:1 / span 2; align-self:start; margin-top:2px; }
.pay-option strong { display:block; grid-column:2; color:#0f172a; font-size:.95rem; line-height:1.25; }
.pay-option small { display:block; grid-column:2; margin-top:3px; color:#64748b; font-weight:650; line-height:1.35; }
.pay-option input:checked + span { border-color:#059669; background:#ecfdf5; }
.pay-option.payment-disabled { opacity:.45; pointer-events:none; }
.payment-reference { display:block; margin-top:12px; }
.summary-card { padding:22px; position:sticky; top:18px; }
.summary-head span { color:#047857; font-size:.78rem; font-weight:900; text-transform:uppercase; }
.summary-head h2 { margin:5px 0 0; color:#052e2b; font-size:1.35rem; font-weight:950; }
.summary-items { display:grid; gap:12px; max-height:355px; overflow:auto; padding-right:4px; margin-top:16px; }
.summary-item { display:grid; grid-template-columns:58px 1fr auto; gap:12px; align-items:center; border:1px solid #edf5ef; border-radius:15px; padding:10px; }
.summary-item img { width:58px; height:58px; border-radius:12px; object-fit:cover; }
.summary-item strong { display:block; max-width:130px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.summary-item small { color:#64748b; font-weight:700; }
.summary-item span { color:#047857; font-weight:950; font-size:.86rem; }
.summary-total { border-top:1px solid #edf5ef; margin-top:18px; padding-top:16px; display:grid; gap:10px; }
.summary-total div { display:flex; justify-content:space-between; gap:12px; color:#64748b; }
.summary-total strong { color:#0f172a; }
.summary-total .discount-line strong { color:#16a34a; }
.summary-total .grand { border-top:1px solid #edf5ef; padding-top:12px; color:#052e2b; font-weight:950; }
.summary-total .grand strong { color:#047857; font-size:1.35rem; }
.summary-total .deposit-due { border-top:1px solid #edf5ef; padding-top:12px; color:#052e2b; font-weight:950; }
.summary-total .deposit-due strong { color:#dc2626; font-size:1.12rem; }
.deposit-policy { margin-top:16px; border:1px solid #bbf7d0; border-radius:16px; background:#f0fdf4; padding:14px; }
.deposit-policy > strong { display:block; color:#052e2b; font-weight:950; margin-bottom:10px; }
.deposit-policy small { display:block; color:#047857; font-weight:750; line-height:1.45; }
.deposit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin-bottom:10px; }
.deposit-grid input { display:none; }
.deposit-grid span { min-height:42px; border:2px solid #dbeee4; border-radius:13px; display:flex; align-items:center; justify-content:center; padding:0 10px; background:#fff; color:#0f172a; font-weight:900; cursor:pointer; text-align:center; }
.deposit-grid input:checked + span { border-color:#059669; background:#dcfce7; color:#047857; }
.payment-cod-mode .deposit-grid { opacity:.45; }
.payment-cod-mode .deposit-grid span { cursor:not-allowed; }
.checkout-submit { width:100%; min-height:54px; margin-top:18px; border:0; border-radius:15px; background:linear-gradient(135deg,#047857,#10b981); color:#fff; font-weight:950; font-size:1rem; }
.back-cart { display:flex; align-items:center; justify-content:center; min-height:42px; margin-top:10px; border-radius:13px; background:#f1f5f9; color:#334155; font-weight:850; text-decoration:none; }
.international-only,.international-copy { display:none; }
.checkout-international .international-only,.checkout-international .international-copy { display:block; }
.checkout-international .domestic-only,.checkout-international .domestic-copy { display:none; }
.checkout-domestic .country-field select { pointer-events:none; background:#f8fafc; color:#047857; }
@media(max-width:980px){ .checkout-layout,.checkout-hero{grid-template-columns:1fr;} .summary-card{position:static;} }
@media(max-width:760px){ .checkout-flow{grid-template-columns:repeat(2,minmax(0,1fr));} }
@media(max-width:640px){ .checkout-main,.summary-card{padding:18px;} .field-grid.two,.payment-grid,.deposit-grid,.checkout-flow{grid-template-columns:1fr;} .checkout-hero h1{font-size:1.55rem;} }
</style>

<script>
const carrierData = {
    domestic: {
        'GHN - Giao Hàng Nhanh': ['Nội thành 24h', 'Liên tỉnh 2-3 ngày', 'Hàng lạnh nông sản'],
        'Aethera Express': ['Giao trong ngày 2-4 giờ', 'Giao hẹn giờ trong ngày', 'Giao lạnh trong ngày'],
        'GrabExpress': ['Siêu tốc nội thành 1-2 giờ', 'Giao trong ngày'],
        'Viettel Post': ['Chuyển phát tiêu chuẩn', 'Chuyển phát nhanh', 'Chuyển phát tiết kiệm'],
        'Ahamove': ['Nội thành hỏa tốc', 'Xe tải lạnh nội thành', 'Giao hẹn giờ']
    },
    international: {
        'DHL Express': ['Express Worldwide', 'Medical/Fresh Priority'],
        'FedEx International': ['International Priority', 'International Economy'],
        'UPS': ['Worldwide Saver', 'Worldwide Expedited'],
        'Maersk Logistics': ['Container lạnh đường biển', 'Door-to-port export']
    }
};

const page = document.querySelector('.checkout-page');
const domestic = document.getElementById('scopeDomestic');
const international = document.getElementById('scopeInternational');
const country = document.getElementById('recipientCountry');
const postalCode = document.getElementById('postalCode');
const district = document.getElementById('recipientDistrict');
const ward = document.getElementById('recipientWard');
const carrier = document.getElementById('shippingCarrier');
const service = document.getElementById('shippingService');
const paymentBrand = document.getElementById('paymentBrand');
const checkoutForm = document.getElementById('checkoutForm');
const orderSubtotal = Number(checkoutForm.dataset.subtotal || 0);
const orderWeightKg = Math.max(1, Number(checkoutForm.dataset.weight || 1));
const shippingFeeText = document.getElementById('shippingFeeText');
const grandTotalText = document.getElementById('grandTotalText');
const shippingFormulaText = document.getElementById('shippingFormulaText');
const clientShippingFee = document.getElementById('clientShippingFee');
const depositAmountText = document.getElementById('depositAmountText');
const remainingAmountText = document.getElementById('remainingAmountText');
const submitAmountText = document.getElementById('submitAmountText');
const depositPolicyText = document.getElementById('depositPolicyText');
const depositPercentInputs = document.querySelectorAll('input[name="deposit_percent"]');
const paymentInputs = document.querySelectorAll('input[name="payment_method"]');
let currentGrandTotal = <?= json_encode((float)$finalTotal) ?>;

function populateShipping(scope) {
    carrier.innerHTML = '';
    Object.keys(carrierData[scope]).forEach((name) => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        carrier.appendChild(option);
    });
    populateServices(scope);
}

function populateServices(scope) {
    service.innerHTML = '';
    (carrierData[scope][carrier.value] || []).forEach((name) => {
        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        service.appendChild(option);
    });
}

function setScope(scope) {
    const isInternational = scope === 'international';
    page.classList.toggle('checkout-international', isInternational);
    page.classList.toggle('checkout-domestic', !isInternational);
    country.value = isInternational ? 'Nhật Bản' : 'Việt Nam';
    postalCode.required = isInternational;
    district.required = !isInternational;
    ward.required = !isInternational;
    populateShipping(scope);
    updatePaymentOptions(scope);
    updateShippingEstimate();
}

function updatePaymentOptions(scope) {
    let checkedMethod = document.querySelector('input[name="payment_method"]:checked');
    paymentInputs.forEach((input) => {
        const allowedScope = input.dataset.scope || 'all';
        const allowed = allowedScope === 'all' || allowedScope === scope;
        input.disabled = !allowed;
        input.closest('.pay-option').classList.toggle('payment-disabled', !allowed);
        if (!allowed && input.checked) {
            input.checked = false;
            checkedMethod = null;
        }
    });
    if (!checkedMethod || checkedMethod.disabled) {
        const fallback = Array.from(paymentInputs).find((input) => !input.disabled);
        if (fallback) {
            fallback.checked = true;
            checkedMethod = fallback;
        }
    }
    paymentBrand.value = checkedMethod ? (checkedMethod.dataset.brand || '') : '';
    updateDepositSummary();
}

function calculateShippingFee(scope, carrierName, serviceName) {
    const carrierService = `${carrierName} ${serviceName}`.toLowerCase();
    if (scope === 'domestic') {
        const isPremiumSameDay = carrierService.includes('trong ngày')
            || carrierService.includes('hẹn giờ')
            || carrierService.includes('siêu tốc')
            || carrierService.includes('hỏa tốc')
            || carrierService.includes('hoa toc')
            || carrierService.includes('hàng lạnh')
            || carrierService.includes('xe tải lạnh')
            || carrierService.includes('giao lạnh');

        if (!isPremiumSameDay && orderSubtotal >= 300000 && orderWeightKg <= 20) {
            return { fee: 0, formula: 'Miễn phí nội địa cho đơn từ 300.000đ và không quá 20 kg.' };
        }

        let base = 18000;
        let perKg = 2500;
        let formulaName = 'Giao tiêu chuẩn';
        if (carrierService.includes('giao lạnh trong ngày') || carrierService.includes('hàng lạnh') || carrierService.includes('xe tải lạnh')) {
            base = 60000; perKg = 6000; formulaName = 'Giao lạnh trong ngày';
        } else if (carrierService.includes('siêu tốc')) {
            base = 38000; perKg = 4500; formulaName = 'Siêu tốc nội thành';
        } else if (carrierService.includes('trong ngày') || carrierService.includes('hẹn giờ') || carrierService.includes('hỏa tốc') || carrierService.includes('hoa toc')) {
            base = 32000; perKg = 4200; formulaName = 'Giao trong ngày/hẹn giờ';
        } else if (carrierService.includes('nội thành')) {
            base = 25000; perKg = 3500; formulaName = 'Nội thành/hỏa tốc';
        } else if (carrierService.includes('liên tỉnh') || carrierService.includes('chuyển phát nhanh')) {
            base = 28000; perKg = 3200; formulaName = 'Liên tỉnh/chuyển phát nhanh';
        } else if (carrierService.includes('thu hộ')) {
            base = 22000; perKg = 2800; formulaName = 'Dịch vụ thu hộ';
        }
        const fee = base + Math.max(0, orderWeightKg - 1) * perKg;
        return { fee, formula: `${formulaName}: ${formatMoney(base)}đ + ${formatMoney(perKg)}đ/kg từ kg thứ 2.` };
    }

    if (carrierService.includes('container') || carrierService.includes('maersk') || carrierService.includes('đường biển')) {
        const fee = Math.max(1500000, 850000 + orderWeightKg * 12000);
        return { fee, formula: `Đường biển/container: tối thiểu 1.500.000đ, hoặc 850.000đ + 12.000đ/kg.` };
    }

    let base = 380000;
    let perKg = 45000;
    let formulaName = 'Quốc tế tiêu chuẩn';
    if (carrierService.includes('economy') || carrierService.includes('worldwide expedited')) {
        base = 320000; perKg = 38000; formulaName = 'Quốc tế tiết kiệm';
    } else if (carrierService.includes('priority') || carrierService.includes('express')) {
        base = 420000; perKg = 52000; formulaName = 'Quốc tế ưu tiên/express';
    }
    const fee = base + Math.max(0, orderWeightKg - 1) * perKg;
    return { fee, formula: `${formulaName}: ${formatMoney(base)}đ + ${formatMoney(perKg)}đ/kg từ kg thứ 2.` };
}

function updateShippingEstimate() {
    const scope = international.checked ? 'international' : 'domestic';
    const result = calculateShippingFee(scope, carrier.value || '', service.value || '');
    const discountedTotal = <?= json_encode((float)$finalTotal) ?>;
    const grand = discountedTotal + result.fee;
    currentGrandTotal = grand;
    shippingFeeText.textContent = result.fee > 0 ? `${formatMoney(result.fee)}đ` : 'Miễn phí';
    grandTotalText.textContent = `${formatMoney(grand)}đ`;
    shippingFormulaText.textContent = `${result.formula} Tổng khối lượng: ${formatWeight(orderWeightKg)}.`;
    clientShippingFee.value = Math.round(result.fee);
    updateDepositSummary();
}

function selectedDepositPercent() {
    const method = document.querySelector('input[name="payment_method"]:checked');
    if (method && method.dataset.upfront === '0') {
        return 0;
    }
    const selected = document.querySelector('input[name="deposit_percent"]:checked');
    return Math.max(40, Math.min(100, Number(selected ? selected.value : 40)));
}

function updateDepositSummary() {
    const method = document.querySelector('input[name="payment_method"]:checked');
    const percent = selectedDepositPercent();
    const depositAmount = Math.round(currentGrandTotal * percent / 100);
    const remainingAmount = Math.max(0, Math.round(currentGrandTotal - depositAmount));
    const isCod = method && method.dataset.upfront === '0';
    document.querySelector('.deposit-policy').classList.toggle('payment-cod-mode', !!isCod);
    depositPercentInputs.forEach((input) => {
        input.disabled = !!isCod;
    });
    depositAmountText.textContent = `${formatMoney(depositAmount)}đ`;
    remainingAmountText.textContent = remainingAmount > 0 ? `${formatMoney(remainingAmount)}đ` : '0đ';
    submitAmountText.textContent = isCod ? 'COD' : `${formatMoney(depositAmount)}đ`;
    depositPolicyText.textContent = isCod
        ? 'Bạn chọn COD, đơn được ghi nhận và khách thanh toán khi nhận hàng.'
        : percent === 100
        ? 'Bạn chọn thanh toán đủ 100%, đơn hàng sẽ được ghi nhận là đã thanh toán.'
        : `Bạn chọn cọc ${percent}% giá trị đơn hàng, phần còn lại thanh toán theo thỏa thuận trước khi giao.`;
}

function formatMoney(value) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0));
}

function formatWeight(kg) {
    kg = Math.round(Number(kg) || 0);
    if (kg >= 1000) {
        const tons = Math.floor(kg / 1000);
        const remain = kg % 1000;
        return remain > 0 ? `${tons} tấn ${remain} kg` : `${tons} tấn`;
    }
    return `${kg} kg`;
}

domestic.addEventListener('change', () => setScope('domestic'));
international.addEventListener('change', () => setScope('international'));
carrier.addEventListener('change', () => {
    populateServices(international.checked ? 'international' : 'domestic');
    updateShippingEstimate();
});
service.addEventListener('change', updateShippingEstimate);
paymentInputs.forEach((input) => {
    input.addEventListener('change', () => {
        paymentBrand.value = input.dataset.brand || '';
        updateDepositSummary();
    });
});
depositPercentInputs.forEach((input) => input.addEventListener('change', updateDepositSummary));
setScope('domestic');

checkoutForm.addEventListener('submit', function(e) {
    const phone = document.getElementById('phone').value.replace(/\s/g, '');
    const isInternational = international.checked;
    const validPhone = isInternational ? /^\+?[0-9]{8,15}$/.test(phone) : /^(0|84)(3|5|7|8|9)[0-9]{8}$/.test(phone);
    if (!validPhone) {
        e.preventDefault();
        alert('Số điện thoại không hợp lệ với phạm vi giao hàng đã chọn.');
        return;
    }

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    const depositPercent = selectedDepositPercent();
    const isCod = paymentMethod && paymentMethod.dataset.upfront === '0';
    const allowedDeposit = isCod || depositPercent === 100 || (depositPercent >= 40 && depositPercent <= 60);
    const allowedPayment = paymentMethod && !paymentMethod.disabled && ['Cash on Delivery', 'Domestic Bank Transfer', 'International Card', 'SWIFT TT'].includes(paymentMethod.value);
    if (!allowedPayment || !allowedDeposit) {
        e.preventDefault();
        alert('Vui lòng chọn phương thức thanh toán hợp lệ và mức cọc 40-60% hoặc 100%. COD chỉ áp dụng cho đơn nội địa.');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
    btn.disabled = true;
});
</script>
