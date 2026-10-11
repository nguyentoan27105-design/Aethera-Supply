<?php 
/**
 * View: Chi tiết đơn hàng (Order Detail) - Premium Glassmorphism Edition
 */
if (!isset($order) || !$order) {
    echo "<div class='container py-5 text-center text-white'>
            <div class='detail-card p-5'>
                <i class='fas fa-box-open fa-4x mb-3 opacity-25'></i>
                <h3>Đơn hàng không tồn tại</h3>
                <p class='text-muted'>Có vẻ như đường dẫn này không chính xác hoặc đơn hàng đã bị xóa.</p>
                <a href='/webbanhang/account/profile' class='btn btn-primary rounded-pill px-4'>Quay lại trang cá nhân</a>
            </div>
          </div>";
    return;
}
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.08);
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        --accent-color: #00f2fe;
    }

    body { 
        background: radial-gradient(circle at top right, #1e293b, #0f172a); 
        color: #e2e8f0; 
        font-family: 'Inter', sans-serif;
    }

    .detail-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        transition: transform 0.3s ease;
    }

    /* Status Badge Animation */
    .badge-animated {
        animation: pulse-badge 2s infinite;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-size: 10px !important;
    }

    @keyframes pulse-badge {
        0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
        100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
    }

    /* Stepper Premium - Updated with dynamic line */
    .stepper-wrapper {
        display: flex;
        justify-content: space-between;
        margin-bottom: 3.5rem;
        margin-top: 1rem;
        position: relative;
    }

    /* Đường kẻ nền mờ */
    .stepper-wrapper::before {
        content: '';
        position: absolute;
        top: 25px; 
        left: 10%; 
        width: 80%; 
        height: 2px;
        background: rgba(255,255,255,0.05);
        z-index: 1;
    }

    /* Đường kẻ tiến trình động */
    .stepper-progress-line {
        position: absolute;
        top: 25px; 
        left: 10%; 
        height: 2px;
        background: var(--primary-gradient);
        z-index: 1;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .step-item {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100px;
    }

    .step-icon {
        width: 50px; 
        height: 50px;
        border-radius: 18px;
        background: #1e293b;
        border: 2px solid var(--glass-border);
        display: flex; 
        align-items: center; 
        justify-content: center;
        margin-bottom: 12px;
        transition: 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        color: #64748b;
    }

    .step-item.active .step-icon {
        background: var(--primary-gradient);
        color: white;
        border-color: transparent;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        transform: scale(1.1);
    }

    .step-label { 
        font-size: 11px; 
        font-weight: 700; 
        color: #64748b; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .step-item.active .step-label { 
        color: white; 
    }

    /* Table Styling */
    .table { color: #cbd5e1; border-color: rgba(255,255,255,0.05); }
    .table thead th { border-top: none; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
    
    .product-img-wrapper {
        background: white;
        padding: 4px;
        border-radius: 12px;
        box-shadow: 0 8px 15px rgba(0,0,0,0.2);
    }

    .info-label {
        font-size: 10px;
        color: #6366f1;
        font-weight: 800;
        letter-spacing: 1.5px;
        display: block;
        margin-bottom: 4px;
    }

    /* Button Hover */
    .btn-cancel {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
        transition: 0.3s;
    }
    .btn-cancel:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
    }
</style>

<div class="container py-5 mt-4">
    <div class="mb-5 d-flex align-items-center">
        <a href="/webbanhang/account/profile" class="btn btn-link text-decoration-none text-muted p-0">
            <div class="d-flex align-items-center detail-card px-3 py-2" style="border-radius: 12px;">
                <i class="fas fa-chevron-left me-2 small"></i>
                <span class="small fw-bold">QUAY LẠI</span>
            </div>
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card detail-card p-4 h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <div class="d-flex align-items-center gap-3">
                            <h2 class="fw-black mb-0 text-white" style="letter-spacing: -1px;">#<?= $order->id ?></h2>
                            <?php 
                                $status_map = [
                                    'pending' => ['Chờ xử lý', 'bg-warning text-dark'],
                                    'confirmed' => ['Đã xác nhận', 'bg-primary text-white'],
                                    'preparing' => ['Chuẩn bị hàng', 'bg-info text-white'],
                                    'packed' => ['Đã đóng gói', 'bg-info text-white'],
                                    'shipped' => ['Đang giao', 'bg-info text-white'],
                                    'completed' => ['Hoàn tất', 'bg-success text-white'],
                                    'cancelled' => ['Đã hủy', 'bg-danger text-white']
                                ];
                                $st = $status_map[$order->status] ?? ['Không rõ', 'bg-secondary'];
                            ?>
                            <span class="badge <?= $st[1] ?> rounded-pill px-3 badge-animated">
                                <?= $st[0] ?>
                            </span>
                        </div>
                        <p class="text-muted small mt-2"><i class="far fa-calendar-alt me-1"></i> Ngày đặt: <?= date('H:i, d/m/Y', strtotime($order->created_at)) ?></p>
                    </div>
                </div>

                <div class="stepper-wrapper px-4">
                    <?php 
                        $orderStatus = (string)($order->status ?? 'pending');
                        $steps = [
                            ['status' => 'pending', 'label' => 'Tiếp nhận', 'icon' => 'fas fa-file-invoice'],
                            ['status' => 'confirmed', 'label' => 'Xác nhận', 'icon' => 'fas fa-circle-check'],
                            ['status' => 'preparing', 'label' => 'Chuẩn bị', 'icon' => 'fas fa-box-open'],
                            ['status' => 'packed', 'label' => 'Đóng gói', 'icon' => 'fas fa-box'],
                            ['status' => 'shipped', 'label' => 'Đang giao', 'icon' => 'fas fa-shipping-fast'],
                            ['status' => 'completed', 'label' => 'Hoàn tất', 'icon' => 'fas fa-check-double'],
                        ];
                        $stepIndex = array_flip(array_column($steps, 'status'));
                        $currentStep = $stepIndex[$orderStatus] ?? 0;
                        $progress_width = $orderStatus === 'cancelled'
                            ? '80%'
                            : ((int)round(($currentStep / max(1, count($steps) - 1)) * 80)) . '%';
                    ?>
                    
                    <div class="stepper-progress-line" style="width: <?= $progress_width ?>"></div>

                    <?php if ($orderStatus == 'cancelled'): ?>
                        <div class="step-item active">
                            <div class="step-icon"><i class="fas fa-file-invoice"></i></div>
                            <div class="step-label">Tiếp nhận</div>
                        </div>
                        <div class="step-item active">
                            <div class="step-icon" style="background: #ef4444; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);">
                                <i class="fas fa-times"></i>
                            </div>
                            <div class="step-label">Đã hủy</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($steps as $index => $step): ?>
                            <div class="step-item <?= $index <= $currentStep ? 'active' : '' ?>">
                                <div class="step-icon"><i class="<?= $step['icon'] ?>"></i></div>
                                <div class="step-label"><?= $step['label'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Sản phẩm</th>
                                <th class="text-center">Khối lượng</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($order_items)): foreach($order_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="product-img-wrapper me-3">
                                            <img src="/webbanhang/public/uploads/<?= $item->image ?>" width="50" height="50" style="object-fit: cover; border-radius: 8px;">
                                        </div>
                                        <div>
                                            <div class="fw-bold text-white mb-0"><?= htmlspecialchars($item->name) ?></div>
                                            <div class="text-muted" style="font-size: 10px;">ID: #<?= $item->product_id ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold"><?= number_format((int)$item->quantity, 0, ',', '.') ?> kg</td>
                                <td class="text-end text-muted small"><?= number_format($item->price, 0, ',', '.') ?>đ</td>
                                <td class="text-end text-white fw-bold"><?= number_format($item->price * $item->quantity, 0, ',', '.') ?>đ</td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card detail-card p-4 mb-4 border-0">
                <h6 class="fw-bold mb-4 text-white"><i class="fas fa-map-marker-alt text-primary me-2"></i> THÔNG TIN NHẬN HÀNG</h6>
                <div class="mb-4">
                    <span class="info-label">KHÁCH HÀNG</span>
                    <p class="mb-0 fw-bold text-white"><?= htmlspecialchars($order->fullname) ?></p>
                </div>
                <div class="mb-4">
                    <span class="info-label">ĐIỆN THOẠI</span>
                    <p class="mb-0 fw-bold text-white"><?= htmlspecialchars($order->phone) ?></p>
                </div>
                <div class="mb-0">
                    <span class="info-label">ĐỊA CHỈ</span>
                    <p class="mb-0 text-muted small lh-base"><?= htmlspecialchars($order->address) ?></p>
                </div>
            </div>

            <?php
                $shippingFee = (float)($order->shipping_fee ?? 0);
                $subtotalAmount = max(0, (float)($order->total ?? 0) - $shippingFee);
                $paymentStatus = strtolower((string)($order->payment_status ?? 'pending'));
                $depositPercent = (float)($order->deposit_percent ?? 0);
                $depositAmount = (float)($order->deposit_amount ?? 0);
                if ($depositPercent <= 0 && $paymentStatus === 'paid') {
                    $depositPercent = 100;
                }
                if ($depositAmount <= 0 && $depositPercent > 0) {
                    $depositAmount = round((float)($order->total ?? 0) * ($depositPercent / 100));
                }
                $remainingAmount = (float)($order->remaining_amount ?? max(0, (float)($order->total ?? 0) - $depositAmount));
            ?>
            <div class="card detail-card p-4 shadow-lg border-0" style="background: var(--primary-gradient);">
                <div class="d-flex justify-content-between mb-3 opacity-75 text-white">
                    <span class="small">Tạm tính</span>
                    <span class="fw-bold"><?= number_format($subtotalAmount, 0, ',', '.') ?>đ</span>
                </div>
                <div class="d-flex justify-content-between mb-3 opacity-75 text-white">
                    <span class="small">Phí vận chuyển</span>
                    <span class="fw-bold"><?= $shippingFee > 0 ? number_format($shippingFee, 0, ',', '.') . 'đ' : 'Miễn phí' ?></span>
                </div>
                <?php if ($depositPercent > 0): ?>
                    <div class="d-flex justify-content-between mb-3 opacity-75 text-white">
                        <span class="small">Thanh toán trước <?= number_format($depositPercent, $depositPercent == (int)$depositPercent ? 0 : 2, ',', '.') ?>%</span>
                        <span class="fw-bold"><?= number_format($depositAmount, 0, ',', '.') ?>đ</span>
                    </div>
                    <?php if ($remainingAmount > 0): ?>
                        <div class="d-flex justify-content-between mb-3 opacity-75 text-white">
                            <span class="small">Còn lại</span>
                            <span class="fw-bold"><?= number_format($remainingAmount, 0, ',', '.') ?>đ</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="my-3" style="border-top: 1px dashed rgba(255,255,255,0.3);"></div>
                <div class="d-flex justify-content-between align-items-center mb-4 text-white">
                    <span class="fw-bold">TỔNG CỘNG</span>
                    <h3 class="fw-black m-0" style="text-shadow: 0 4px 10px rgba(0,0,0,0.2);"><?= number_format($order->total, 0, ',', '.') ?>đ</h3>
                </div>

                <?php if($order->status == 'pending'): ?>
                    <form method="POST" action="/webbanhang/order/cancel/<?= (int)$order->id ?>" onsubmit="return confirmCancel(event)" class="m-0">
                        <button type="submit" class="btn btn-cancel w-100 py-3 rounded-pill fw-bold border-0">
                            <i class="fas fa-times-circle me-2"></i> HỦY ĐƠN HÀNG
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <p class="text-center mt-4 text-muted small opacity-50">Cảm ơn bạn đã mua hàng tại WebBanHang!</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmCancel(event) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: 'Xác nhận hủy?',
        text: "Bạn sẽ không thể khôi phục lại đơn hàng này!",
        icon: 'warning',
        showCancelButton: true,
        background: '#1e293b',
        color: '#fff',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Đồng ý hủy',
        cancelButtonText: 'Đóng'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    })
}
</script>
