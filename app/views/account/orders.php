<?php 
// include 'app/views/shares/header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-11">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="fw-bold text-white mb-1">Lịch sử đơn hàng</h2>
                    <p class="text-muted small mb-0">Theo dõi trạng thái đơn hàng của bạn</p>
                </div>
                <a href="/webbanhang" class="btn btn-outline-success btn-sm rounded-pill px-3">
                    <i class="fas fa-leaf me-2"></i>Tiếp tục mua sắm
                </a>
            </div>

            <div class="order-card-container shadow-lg">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle custom-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Mã ĐH</th>
                                <th>Thời gian</th>
                                <th>Sản phẩm</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th class="text-end pe-4">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($orders)): ?>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-bold text-success">#<?= $order->id ?></span>
                                    </td>
                                    <td class="text-light-gray small">
                                        <?= date('d/m/Y', strtotime($order->created_at ?? 'now')) ?><br>
                                        <span class="extra-small opacity-50"><?= date('H:i', strtotime($order->created_at ?? 'now')) ?></span>
                                    </td>
                                    <td>
                                        <div style="max-width: 250px;">
                                            <span class="text-white small">
                                                <?= htmlspecialchars($order->product_names ?? 'Đang cập nhật...'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            <?= number_format($order->total ?? 0, 0, ',', '.') ?>đ
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusMap = [
                                                '0' => ['Chờ xử lý', 'bg-soft-warning text-warning'],
                                                'pending' => ['Chờ xử lý', 'bg-soft-warning text-warning'],
                                                'confirmed' => ['Đã xác nhận', 'bg-soft-info text-info'],
                                                'preparing' => ['Chuẩn bị hàng', 'bg-soft-info text-info'],
                                                'packed' => ['Đã đóng gói', 'bg-soft-info text-info'],
                                                'shipped' => ['Đang giao', 'bg-soft-info text-info'],
                                                'completed' => ['Hoàn thành', 'bg-soft-success text-success'],
                                                'cancelled' => ['Đã hủy', 'bg-soft-danger text-danger'],
                                            ];
                                            [$statusText, $statusClass] = $statusMap[(string)($order->status ?? 'pending')] ?? ['Không xác định', 'bg-soft-secondary text-white'];
                                        ?>
                                        <span class="badge rounded-pill px-3 py-2 <?= $statusClass ?>">
                                            <i class="fas fa-circle me-1" style="font-size: 8px;"></i> <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group-action">
                                            <a href="/webbanhang/Account/orderDetail/<?= $order->id ?>" class="btn btn-action-view" title="Xem chi tiết">
                                                <i class="far fa-eye"></i>
                                            </a>
                                            <?php if($order->status == 'pending' || $order->status == '0'): ?>
                                                <form method="POST" action="/webbanhang/Account/cancelOrder/<?= (int)$order->id ?>" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')">
                                                    <button type="submit" class="btn btn-action-cancel" title="Hủy đơn">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-box-open fa-2x mb-2 text-muted"></i>
                                        <p class="text-muted">Bạn chưa có đơn hàng nào.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --dark-card: #0f1f17;
        --dark-bg: #0b1410;
        --primary-green: #22c55e;
        --light-green: #4ade80;
    }

    body {
        background: var(--dark-bg);
        font-family: 'Inter', sans-serif;
    }

    .order-card-container {
        background: var(--dark-card);
        border-radius: 20px;
        border: 1px solid rgba(34, 197, 94, 0.15);
    }

    .custom-table thead th {
        background: rgba(34, 197, 94, 0.05);
        color: rgba(255,255,255,0.5);
        font-size: 0.75rem;
        padding: 15px;
    }

    .custom-table td {
        padding: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.03);
        color: #eee;
    }

    .bg-soft-warning { background: rgba(255, 193, 7, 0.1); }
    .bg-soft-danger { background: rgba(220, 53, 69, 0.1); }
    .bg-soft-success { background: rgba(34, 197, 94, 0.15); }
    .bg-soft-info { background: rgba(59, 130, 246, 0.1); }
    .bg-soft-secondary { background: rgba(108, 117, 125, 0.1); }

    .btn-action-view,
    .btn-action-cancel {
        width: 35px;
        height: 35px;
        border-radius: 10px;
        border: none;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .btn-action-view {
        background: rgba(34, 197, 94, 0.1);
        color: var(--primary-green);
    }

    .btn-action-view:hover {
        background: var(--primary-green);
        color: #fff;
    }

    .btn-action-cancel {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .btn-action-cancel:hover {
        background: #ef4444;
        color: #fff;
    }

    .btn-group-action {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .extra-small { font-size: 0.7rem; }
    .text-light-gray { color: #ccc; }
</style>

<?php 
// include 'app/views/shares/footer.php'; 
?>
