<?php
require_once __DIR__ . '/../../helpers/UrlHelper.php';

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$isAdmin = isset($_SESSION['user_role']) 
    && $_SESSION['user_role'] === 'admin';

$product = $product ?? (object)[];
$batch = $batch ?? null;
$traceability = $traceability ?? null;
$traceScanHistory = $traceScanHistory ?? [];

if (!function_exists('traceFormatDate')) {
    function traceFormatDate($date): string {
        if (empty($date)) {
            return 'Chưa cập nhật';
        }
        $time = strtotime((string)$date);
        return $time ? date('d/m/Y', $time) : 'Chưa cập nhật';
    }
}

if (!function_exists('traceWeightLabel')) {
    function traceWeightLabel($value): string {
        $value = (float)$value;
        if ($value >= 1000 && fmod($value, 1000.0) === 0.0) {
            return number_format($value / 1000, 0, ',', '.') . ' tấn';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 2, ',', '.') . ' tấn';
        }
        return number_format($value, 0, ',', '.') . ' kg';
    }
}

$pName = $product->name ?? 'Sản phẩm nông sản';
$batchCode = $batch->batch_code ?? ('HT-' . date('Ymd', strtotime($product->harvest_date ?? date('Y-m-d'))) . '-' . ($product->id ?? 0));
$traceUrl = app_trace_url((int)($product->id ?? 0), $batchCode);
$farm = $batch->supplier_name ?? $product->supplier ?? $traceability->farm_name ?? 'Trang trại đối tác Aethera Supply';
$location = $batch->farm_location ?? $traceability->origin_location ?? $product->origin ?? 'Vùng trồng tiêu chuẩn';
$standard = $batch->quality_standard ?? $product->standard ?? $traceability->certification ?? 'Tiêu chuẩn nội bộ';
$image = trim((string)($product->image ?? ''));
$imageUrl = $image !== '' ? '/webbanhang/public/uploads/' . htmlspecialchars($image) : '/webbanhang/public/uploads/default-product.svg';
$harvestDate = $batch->harvest_date ?? $traceability->harvest_date ?? $product->harvest_date ?? null;
$plantingDate = $batch->planting_date ?? null;
$packagingDate = $traceability->packaging_date ?? ($harvestDate ? date('Y-m-d', strtotime($harvestDate . ' +2 days')) : null);
$warehouse = $batch->warehouse_name ?? 'Kho Aethera Supply';
$warehouseLocation = $batch->warehouse_location ?? $product->warehouse_address ?? '';
$preservation = $batch->preservation_method ?? 'Bảo quản theo tiêu chuẩn kho lạnh';
$temperature = $batch->storage_temperature ?? $batch->temperature ?? 5;
$humidity = $batch->storage_humidity ?? $batch->humidity ?? 70;
$cultivationMethod = trim((string)($batch->cultivation_method ?? 'Chưa cập nhật'));
$fertilizer = trim((string)($batch->fertilizer ?? 'Chưa cập nhật'));
$pesticide = trim((string)($batch->pesticide ?? 'Chưa cập nhật'));
$area = isset($batch->area) ? (float)$batch->area : 0;
$certificateNumber = $batch->certificate_number ?? $product->certificate_number ?? 'AETHERA-2026-0079';
$issuedDate = $batch->certificate_date ?? $traceability->certificate_date ?? ($harvestDate ? date('Y-m-d', strtotime($harvestDate . ' +3 days')) : date('Y-m-d'));
$issuer = $batch->certificate_issuer ?? 'Vinacontrol CE';
$inspector = $batch->inspector_name ?? 'Nguyễn Văn An';
$validity = $batch->certificate_valid_until ?? date('Y-m-d', strtotime('+1 year'));
$remaining = isset($batch->calculated_remaining_quantity)
    ? (float)$batch->calculated_remaining_quantity
    : (isset($batch->remaining_quantity) ? (float)$batch->remaining_quantity : (float)($product->stock ?? 0));
$quantity = isset($batch->quantity) ? (float)$batch->quantity : (float)($product->stock ?? 0);
$stockPercent = $quantity > 0 ? max(0, min(100, ($remaining / $quantity) * 100)) : 0;
$statusLabel = $remaining > 0 ? 'Đang lưu hành' : 'Đã hết lô';
$statusClass = $remaining > 0 ? 'is-live' : 'is-empty';

$inspectionSteps = [
    ['icon' => 'fa-clipboard-check', 'title' => 'Tiếp nhận hồ sơ', 'text' => 'Đối chiếu nhà cung cấp, mã lô, nhật ký canh tác và chứng nhận vùng trồng.'],
    ['icon' => 'fa-magnifying-glass-chart', 'title' => 'Kiểm tra ngoại quan', 'text' => 'Đánh giá màu sắc, kích cỡ, độ dập hỏng, tạp chất và điều kiện đóng gói.'],
    ['icon' => 'fa-vial-circle-check', 'title' => 'Kiểm nghiệm an toàn', 'text' => 'Theo dõi dư lượng thuốc BVTV, kim loại nặng, vi sinh và chỉ tiêu bảo quản.'],
    ['icon' => 'fa-file-shield', 'title' => 'Duyệt chứng từ', 'text' => 'Xác nhận VietGAP/GlobalGAP/HACCP hoặc tiêu chuẩn nội bộ trước khi nhập kho.'],
    ['icon' => 'fa-qrcode', 'title' => 'Kích hoạt QR', 'text' => 'Mã QR liên kết sản phẩm, lô thu hoạch, kho bảo quản và lịch sử vận chuyển.'],
];
?>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Truy xuất nguồn gốc - <?= htmlspecialchars($pName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/webbanhang/public/css/agri-theme.css?v=20260521">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --trace-green: #007956;
            --trace-green-dark: #064e3b;
            --trace-mint: #e8fff4;
            --trace-border: #cdebdc;
            --trace-text: #071b14;
            --trace-muted: #5f726b;
            --trace-orange: #f59e0b;
            --trace-blue: #0ea5e9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(16, 185, 129, .18), transparent 30%),
                linear-gradient(180deg, #f5fff9 0%, #eefaf3 100%);
            color: var(--trace-text);
            font-family: 'Be Vietnam Pro', Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .trace-page {
            width: min(100%, 980px);
            margin: 0 auto;
            padding: 16px;
        }

        .trace-shell {
            overflow: hidden;
            border: 1px solid var(--trace-border);
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(6, 78, 59, .10);
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .trace-hero {
            position: relative;
            min-height: 360px;
            display: grid;
            align-items: end;
            padding: 22px;
            color: #fff;
            background:
                linear-gradient(180deg, rgba(4, 47, 36, .25), rgba(4, 47, 36, .92)),
                url("<?= $imageUrl ?>") center/cover;
            background-blend-mode: overlay;
            text-shadow: 0 6px 22px rgba(4, 47, 36, .6);
        }

        .trace-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 88% 16%, rgba(52, 211, 153, .34), transparent 22%);
            pointer-events: none;
        }

        .trace-hero-content {
            position: relative;
            z-index: 1;
        }

        .trace-back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .92);
            color: #0f3f2d;
            font-size: .95rem;
            font-weight: 900;
            text-decoration: none;
            border: 1px solid rgba(4, 120, 87, .16);
            box-shadow: 0 10px 24px rgba(4, 120, 87, .08);
            transition: transform .18s ease, background .18s ease;
        }

        .trace-back-button:hover {
            transform: translateY(-1px);
            background: rgba(4, 120, 87, .12);
        }

        .trace-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 0 13px;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 999px;
            background: rgba(255, 255, 255, .13);
            backdrop-filter: blur(8px);
            font-weight: 900;
            font-size: .82rem;
        }

        .trace-title {
            margin: 16px 0 8px;
            max-width: 760px;
            font-size: clamp(1.8rem, 6vw, 3.4rem);
            line-height: .95;
            font-weight: 950;
            letter-spacing: .01em;
            text-transform: uppercase;
        }

        .trace-subtitle {
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-weight: 700;
        }

        .trace-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #047857;
            font-weight: 900;
        }

        .trace-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
            padding: 4px;
            background: rgba(4, 120, 87, .06);
            border-radius: 18px;
        }

        .trace-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: #f4fff8;
            color: #047857;
            font-weight: 700;
            cursor: pointer;
            transition: all .18s ease;
        }

        .trace-tab:hover {
            background: #ecfff1;
        }

        .trace-tab.active {
            background: var(--trace-green-dark);
            border-color: rgba(255,255,255,.18);
            color: #ffffff;
            box-shadow: 0 14px 36px rgba(4, 120, 87, .16);
        }

        .trace-tab-content {
            display: none;
        }

        .trace-tab-content.active {
            display: block;
        }

        .trace-status.is-empty {
            background: #fee2e2;
            color: #b91c1c;
        }

        .trace-body {
            padding: 22px;
        }

        .trace-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .metric-card,
        .info-card,
        .process-card,
        .timeline-card,
        .cert-card {
            border: 1px solid var(--trace-border);
            border-radius: 20px;
            background: #fbfffd;
        }

        .metric-card {
            padding: 15px;
        }

        .metric-label {
            color: var(--trace-muted);
            font-size: .76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .metric-value {
            margin-top: 6px;
            font-size: 1.25rem;
            font-weight: 950;
            color: var(--trace-green-dark);
        }

        .progress {
            height: 9px;
            border-radius: 999px;
            background: #e5f4ec;
        }

        .progress-bar {
            background: linear-gradient(90deg, #10b981, #0ea5e9);
        }

        .info-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .info-card {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 15px;
        }

        .trace-icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: inline-grid;
            place-items: center;
            background: #e6fff3;
            color: var(--trace-green);
            box-shadow: inset 0 0 0 1px rgba(16, 185, 129, .16);
        }

        .trace-icon i {
            width: 20px;
            height: 20px;
            display: inline-grid;
            place-items: center;
            line-height: 1;
        }

        .info-card small,
        .process-card small,
        .timeline-card small {
            color: var(--trace-muted);
            font-weight: 800;
        }

        .info-card strong,
        .process-card strong,
        .timeline-card strong {
            display: block;
            margin-top: 4px;
            color: var(--trace-text);
            font-weight: 950;
            line-height: 1.28;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 26px 0 12px;
            color: var(--trace-green-dark);
            font-size: 1.12rem;
            font-weight: 950;
        }

        .section-title i {
            width: 28px;
            height: 28px;
            display: inline-grid;
            place-items: center;
            border-radius: 10px;
            background: #dff9eb;
            color: var(--trace-green);
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .process-card {
            padding: 14px;
            min-height: 140px;
            transition: transform .14s ease, box-shadow .14s ease;
            box-shadow: 0 8px 26px rgba(6,78,59,0.04);
        }

        .process-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 40px rgba(6,78,59,0.08);
        }

        .process-card .trace-icon {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            margin-bottom: 10px;
        }

        .process-card:nth-child(2) .trace-icon {
            color: #0369a1;
            background: #e0f2fe;
        }

        .process-card:nth-child(3) .trace-icon {
            color: #b45309;
            background: #fef3c7;
        }

        .process-card:nth-child(4) .trace-icon {
            color: #7c3aed;
            background: #ede9fe;
        }

        .process-card:nth-child(5) .trace-icon {
            color: #be123c;
            background: #ffe4e6;
        }

        .timeline-wrap {
            display: grid;
            gap: 10px;
        }

        .timeline-card {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 12px;
            padding: 13px;
            align-items: start;
        }

        .timeline-step {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            background: linear-gradient(135deg, #007956, #10b981);
            color: #fff;
            font-weight: 950;
        }

        .cert-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            margin-top: 18px;
            padding: 20px;
            background: linear-gradient(135deg, #fffdf3, #f7fff9);
            border: 1px solid rgba(245, 158, 11, .18);
            box-shadow: 0 16px 34px rgba(249, 115, 22, .08);
        }

        .cert-card h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .cert-card button {
            border: 0;
            border-radius: 14px;
            min-height: 44px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f59e0b;
            color: #fff;
            font-weight: 950;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .cert-card button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(245, 158, 11, .2);
        }

        .cert-card button {
            border: 0;
            border-radius: 14px;
            min-height: 44px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #f59e0b;
            color: #fff;
            font-weight: 950;
        }



        .cert-upload-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .upload-cert-btn {
            border: 0;
            border-radius: 16px;
            min-height: 44px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #10b981, #007956);
            color: #fff;
            font-weight: 950;
            text-decoration: none;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .upload-cert-btn:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(16, 185, 129, .22);
        }

        .cert-preview {
            border: 1px solid rgba(16,185,129,.14);
            border-radius: 24px;
            padding: 18px;
            background: #ffffff;
        }

        .cert-preview-label {
            letter-spacing: 0.12em;
        }

        .cert-preview img {
            width: 100%;
            border-radius: 20px;
            max-height: 520px;
            object-fit: cover;
            display: block;
        }

        .trace-footer {
            padding: 18px 22px 22px;
            border-top: 1px solid var(--trace-border);
            background: #f3fff8;
            color: var(--trace-muted);
            text-align: center;
            font-weight: 800;
        }

        @media (max-width: 767px) {
            .trace-page {
                padding: 10px;
            }

            .trace-shell {
                border-radius: 22px;
            }

            .trace-hero {
                min-height: 300px;
                padding: 18px;
            }

            .trace-body {
                padding: 16px;
            }

            .trace-grid,
            .info-list,
            .process-grid {
                grid-template-columns: 1fr;
            }

            .metric-card {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 10px;
                align-items: center;
            }

            .metric-card .progress {
                grid-column: 1 / -1;
            }

            .process-card {
                min-height: auto;
                display: grid;
                grid-template-columns: 42px minmax(0, 1fr);
                gap: 12px;
                align-items: start;
            }

            .process-card .trace-icon {
                margin-bottom: 0;
            }

            .cert-card {
                grid-template-columns: 1fr;
            }

            .cert-card button {
                width: 100%;
            }
        }

        #qrcode {
            display: inline-grid;
            place-items: center;
            width: min(100%, 280px);
            aspect-ratio: 1;
            padding: 16px;
            border: 2px solid var(--trace-border);
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 36px rgba(6, 78, 59, .10);
            margin: 0 auto;
        }

        #qrcode img,
        #qrcode canvas {
            width: 100% !important;
            height: auto !important;
            border-radius: 14px;
        }

        .qr-container {
            display: grid;
            gap: 20px;
            align-items: center;
            justify-items: center;
        }

        .qr-instructions {
            text-align: center;
            padding: 16px;
            background: #f3fff8;
            border-radius: 16px;
            border: 1px solid var(--trace-border);
        }

        .qr-instructions p {
            margin: 0;
            color: var(--trace-text);
            font-weight: 700;
            line-height: 1.5;
        }

        .qr-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            width: 100%;
            max-width: 400px;
        }

        .qr-info-item {
            padding: 12px;
            background: #fbfffd;
            border: 1px solid var(--trace-border);
            border-radius: 14px;
            text-align: center;
        }

        .qr-info-item small {
            display: block;
            color: var(--trace-muted);
            font-weight: 800;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .02em;
            margin-bottom: 4px;
        }

        .qr-info-item strong {
            display: block;
            color: var(--trace-green-dark);
            font-weight: 950;
            font-size: 1.1rem;
        }

        /* Professional traceability layout overrides */
        .trace-page { width: min(100%, 1120px); padding: 22px 16px 42px; }
        .trace-shell { border-radius: 20px; border-color: #d8e9df; box-shadow: 0 18px 42px rgba(15, 23, 42, .08); }
        .trace-hero { min-height: 320px; padding: 24px; }
        .trace-title { letter-spacing: 0; text-transform: none; line-height: 1.05; }
        .trace-subtitle { color: rgba(255, 255, 255, .88); }
        .trace-tabs { background: #eefbf3; border: 1px solid #d8e9df; border-radius: 14px; }
        .trace-tab { background: transparent; border-color: transparent; font-weight: 800; color: #065f46; }
        .trace-tab.active {
            background: #065f46;
            color: #fff;
            box-shadow: none;
        }
        .trace-body { padding: 22px; background: #fff; }
        .metric-card, .info-card, .process-card, .timeline-card, .cert-card {
            border-radius: 14px;
            border-color: #d8e9df;
            background: #fcfefd;
        }
        .metric-value { font-size: 1.18rem; }
        .info-card, .process-card, .timeline-card, .cert-card { box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
        .timeline-node { box-shadow: none; border: 1px solid #d8e9df; }
        .qr-instructions { background: #f8fcf9; border-color: #d8e9df; }
        .qr-info-item { border-color: #d8e9df; background: #fff; }

        @media (max-width: 780px) {
            .trace-hero { min-height: 280px; }
            .trace-body { padding: 16px; }
        }
    </style>
</head>
<body>
<main class="trace-page">
    <article class="trace-shell">
        <section class="trace-hero">
            <div class="trace-hero-content">
                <a href="/webbanhang/product" class="trace-back-button">
                    <i class="fa-solid fa-arrow-left-long"></i>
                    Quay lại
                </a>
                <span class="trace-badge">
                    <i class="fas fa-shield-halved"></i>
                    Verified by Aethera Supply
                </span>
                <h2 class="trace-title">Truy xuất nguồn gốc</h2>
                <p class="trace-subtitle"><?= htmlspecialchars($pName) ?> · Mã lô <?= htmlspecialchars($batchCode) ?></p>
                <span class="trace-status <?= $statusClass ?>">
                    <i class="fas <?= $remaining > 0 ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= $statusLabel ?>
                </span>
            </div>
        </section>

        <section class="trace-body">
            <div class="trace-tabs" role="tablist" aria-label="Chuyển đổi tab truy xuất">
    <button type="button" class="trace-tab active" data-section="overview">
        <i class="fas fa-circle-info"></i> Tổng quan
    </button>

    <button type="button" class="trace-tab" data-section="process">
        <i class="fas fa-vial-circle-check"></i> Quy trình
    </button>

    <button type="button" class="trace-tab" data-section="timeline">
        <i class="fas fa-route"></i> Hành trình
    </button>
    
    <button type="button" class="trace-tab" data-section="certificate">
        <i class="fas fa-file-shield"></i> Chứng từ
    </button>
</div>

            <div class="trace-tab-content active" data-section="overview">
                <div class="trace-grid">
                    <div class="metric-card">
                        <div class="metric-label">Tồn lô</div>
                        <div class="metric-value"><?= traceWeightLabel($remaining) ?></div>
                        <div class="progress mt-2" aria-label="Tỷ lệ tồn lô">
                            <div class="progress-bar" style="width: <?= $stockPercent ?>%"></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Tổng nhập</div>
                        <div class="metric-value"><?= traceWeightLabel($quantity) ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Nhiệt độ</div>
                        <div class="metric-value"><?= number_format((float)$temperature, 1, ',', '.') ?>°C</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Độ ẩm</div>
                        <div class="metric-value"><?= number_format((float)$humidity, 0, ',', '.') ?>%</div>
                    </div>
                </div>

                <div class="info-list">
                    <div class="info-card">
                        <span class="trace-icon"><i class="fas fa-warehouse"></i></span>
                        <div>
                            <small>Cơ sở sản xuất</small>
                            <strong><?= htmlspecialchars($farm) ?></strong>
                        </div>
                    </div>
                    <div class="info-card">
                        <span class="trace-icon"><i class="fas fa-map-location-dot"></i></span>
                        <div>
                            <small>Vùng trồng</small>
                            <strong><?= htmlspecialchars($location) ?></strong>
                        </div>
                    </div>
                    <div class="info-card">
                        <span class="trace-icon"><i class="fas fa-boxes-stacked"></i></span>
                        <div>
                            <small>Kho bảo quản</small>
                            <strong><?= htmlspecialchars($warehouse) ?></strong>
                            <?php if (!empty($warehouseLocation)): ?>
                                <span class="d-block text-muted fw-semibold mt-1"><?= htmlspecialchars($warehouseLocation) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <span class="trace-icon"><i class="fas fa-temperature-low"></i></span>
                        <div>
                            <small>Phương pháp bảo quản</small>
                            <strong><?= htmlspecialchars($preservation) ?></strong>
                        </div>
                    </div>
                    <div class="info-card">
                        <span class="trace-icon"><i class="fas fa-seedling"></i></span>
                        <div>
                            <small>Nhật ký canh tác</small>
                            <strong><?= htmlspecialchars($cultivationMethod) ?></strong>
                            <span class="d-block text-muted fw-semibold mt-1">
                                Phân bón: <?= htmlspecialchars($fertilizer) ?> · BVTV: <?= htmlspecialchars($pesticide) ?>
                                <?= $area > 0 ? ' · Diện tích: ' . number_format($area, 2, ',', '.') . ' ha' : '' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="trace-tab-content" data-section="process">
                <h2 class="section-title"><i class="fas fa-vial-circle-check"></i> Quy trình kiểm tra an toàn thực phẩm</h2>
                <div class="process-grid">
                    <?php foreach ($inspectionSteps as $step): ?>
                        <div class="process-card">
                            <span class="trace-icon"><i class="fas <?= $step['icon'] ?>"></i></span>
                            <div>
                                <strong><?= htmlspecialchars($step['title']) ?></strong>
                                <small class="d-block mt-2"><?= htmlspecialchars($step['text']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="trace-tab-content" data-section="timeline">
                <h2 class="section-title"><i class="fas fa-route"></i> Hành trình truy xuất</h2>
                <div class="timeline-wrap">
                    <?php if ($plantingDate): ?>
                        <div class="timeline-card">
                            <span class="timeline-step">1</span>
                            <div>
                                <small><?= traceFormatDate($plantingDate) ?></small>
                                <strong>Gieo trồng và ghi nhận nhật ký canh tác</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="timeline-card">
                        <span class="timeline-step"><?= $plantingDate ? '2' : '1' ?></span>
                        <div>
                            <small><?= traceFormatDate($harvestDate) ?></small>
                            <strong>Thu hoạch, sơ chế và phân loại tại vùng trồng</strong>
                        </div>
                    </div>
                    <div class="timeline-card">
                        <span class="timeline-step"><?= $plantingDate ? '3' : '2' ?></span>
                        <div>
                            <small><?= traceFormatDate($packagingDate) ?></small>
                            <strong>Đóng gói, kiểm định và phê duyệt chứng từ</strong>
                        </div>
                    </div>
                    <div class="timeline-card">
                        <span class="timeline-step"><?= $plantingDate ? '4' : '3' ?></span>
                        <div>
                            <small><?= traceFormatDate($packagingDate ? date('Y-m-d', strtotime($packagingDate . ' +1 day')) : null) ?></small>
                            <strong>Nhập kho, theo dõi nhiệt độ/độ ẩm và sẵn sàng giao hàng</strong>
                        </div>
                    </div>
                </div>
            </div>

            

            <div class="trace-tab-content" data-section="certificate">
                <h2 class="section-title"><i class="fas fa-file-shield"></i> Chứng từ kiểm định</h2>
                <?php $certificateFile = $batch->certificate_file ?? ''; ?>

                <?php if ($isAdmin): ?>

                <form action="/webbanhang/product/uploadCertificate"
                      method="POST"
                      enctype="multipart/form-data"
                      class="cert-card">
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Tiêu chuẩn chất lượng</small>
                        <h3 class="h5 fw-black mb-1"><?= htmlspecialchars($standard) ?></h3>
                        <p class="mb-0 text-muted fw-semibold">Chứng từ kiểm định và giấy phép liên quan đến lô hàng này.</p>
                    </div>

                    <input type="hidden" name="product_id" value="<?= (int)($product->id ?? 0) ?>">
                    <input type="hidden" name="batch_code" value="<?= htmlspecialchars($batchCode) ?>">
                    <?= CsrfHelper::field() ?>

                    <div class="cert-upload-actions">
                        <label class="upload-cert-btn">
                            <i class="fas fa-file-circle-plus"></i>
                            Chọn chứng từ
                            <input type="file" name="certificate_file" accept="image/*,.pdf" hidden required>
                        </label>

                        <button type="submit" class="upload-cert-btn">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Lưu chứng từ
                        </button>
                    </div>
                </form>

                <?php endif; ?>

                <div class="cert-detail-card mt-4 p-4 border rounded-4" style="background:#fafaf7; border-color: rgba(16,185,129,.18);">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <span class="text-muted small text-uppercase">Số chứng từ</span>
                            <div class="fw-bold fs-6"><?= htmlspecialchars($certificateNumber) ?></div>
                        </div>
                        <div class="row gx-3 gy-3">
                            <div class="col-12 col-md-6">
                                <span class="text-muted small text-uppercase">Ngày cấp</span>
                                <div class="fw-semibold"><?= traceFormatDate($issuedDate) ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-muted small text-uppercase">Hết hạn</span>
                                <div class="fw-semibold"><?= traceFormatDate($validity) ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-muted small text-uppercase">Cơ quan cấp</span>
                                <div class="fw-semibold"><?= htmlspecialchars($issuer) ?></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-muted small text-uppercase">Kiểm định viên</span>
                                <div class="fw-semibold"><?= htmlspecialchars($inspector) ?></div>
                            </div>
                        </div>
                        <div class="text-muted small">Chứng từ này xác nhận sản phẩm đã được kiểm định theo tiêu chuẩn an toàn vệ sinh thực phẩm, đáp ứng yêu cầu truy xuất nguồn gốc, kiểm tra chất lượng và bảo quản trong toàn bộ hành trình.</div>
                    </div>
                </div>

                <div class="cert-preview mt-4">
    <div class="cert-preview-label text-muted small text-uppercase mb-2">
        Xem trước chứng từ
    </div>

    <?php if (!empty($certificateFile)): ?>

        <?php if (strtolower(pathinfo($certificateFile, PATHINFO_EXTENSION)) === 'pdf'): ?>

            <a href="/webbanhang/<?= htmlspecialchars($certificateFile) ?>"
               target="_blank"
               class="upload-cert-btn">
                <i class="fas fa-file-pdf"></i>
                Xem file PDF
            </a>

        <?php else: ?>

            <img src="/webbanhang/<?= htmlspecialchars($certificateFile) ?>"
                 alt="Chứng từ kiểm định">

        <?php endif; ?>

        <?php if ($isAdmin): ?>

            <form action="/webbanhang/product/deleteCertificate"
                  method="POST"
                  class="mt-3">

                <input type="hidden"
                       name="product_id"
                       value="<?= (int)($product->id ?? 0) ?>">

                <input type="hidden"
                       name="batch_code"
                       value="<?= htmlspecialchars($batchCode) ?>">
                <?= CsrfHelper::field() ?>

                <button type="submit"
                        class="upload-cert-btn"
                        style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
                    <i class="fas fa-trash"></i>
                    Xóa chứng từ
                </button>

            </form>

        <?php endif; ?>

    <?php else: ?>

        <div class="text-muted fw-semibold">
            Chưa có chứng từ nào được upload.
        </div>

    <?php endif; ?>
</div>
            </div>
        </section>

        <footer class="trace-footer">
            Dữ liệu truy xuất được đồng bộ từ sản phẩm, lô hàng, kho và nhật ký kiểm định của Aethera Supply.
        </footer>
    </article>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.trace-tab');
        const contents = document.querySelectorAll('.trace-tab-content');

        function activateTab(tabElement) {
            const target = tabElement.dataset.section;
            tabs.forEach(el => el.classList.toggle('active', el === tabElement));
            contents.forEach(panel => panel.classList.toggle('active', panel.dataset.section === target));
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + target);
            }
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function () {
                activateTab(this);
            });
        });

        const initialHash = window.location.hash.slice(1);
        if (initialHash) {
            const tab = document.querySelector('.trace-tab[data-section="' + initialHash + '"]');
            if (tab) {
                activateTab(tab);
            }
        }

        // Tạo mã QR truy xuất
        const qrContainer = document.getElementById('qrcode');
        if (qrContainer && typeof QRCode !== 'undefined') {
            const traceUrl = <?= json_encode($traceUrl, JSON_UNESCAPED_SLASHES) ?>;
            
            const isMobile = window.matchMedia('(max-width: 640px)').matches;
            const qrSize = isMobile ? 200 : 240;

            qrContainer.innerHTML = '';
            
            new QRCode(qrContainer, {
                text: traceUrl,
                width: qrSize,
                height: qrSize,
                colorDark: '#007956',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });

            console.log('QR Code URL: ' + traceUrl);
        }
    });

</script>

</body>

</html>
