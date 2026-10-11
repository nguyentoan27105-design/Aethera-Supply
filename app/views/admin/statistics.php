<?php
$supplyReports = $supply_reports ?? [];
$countries = $supplyReports['countries'] ?? [];
$harvests = $supplyReports['harvests'] ?? [];
$exportDueOrders = $export_due_orders ?? [];
$domesticDueOrders = $domestic_due_orders ?? [];
$lowStockProducts = $low_stock_products ?? [];
$inventoryLogs = $inventory_logs ?? [];
$orderEvents = $order_events ?? [];
$filters = $report_filters ?? [
    'from_date' => date('Y-m-d', strtotime('-6 days')),
    'to_date' => date('Y-m-d'),
    'status' => '',
    'scope' => '',
];
$exportQuery = http_build_query(array_filter($filters, static fn($value) => (string)$value !== ''));
$exportHref = '/webbanhang/admin/exportStatistics' . ($exportQuery ? ('?' . $exportQuery) : '');
$unitLabel = 'kg';
$statusOptions = [
    '' => 'Tất cả trạng thái',
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'preparing' => 'Đang chuẩn bị',
    'packed' => 'Đã đóng gói',
    'shipped' => 'Đang giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
];
$scopeOptions = [
    '' => 'Tất cả đơn',
    'domestic' => 'Nội địa',
    'international' => 'Xuất khẩu',
];
$filterSummary = date('d/m/Y', strtotime($filters['from_date'])) . ' - ' . date('d/m/Y', strtotime($filters['to_date']));

if (!function_exists('reportDueLabel')) {
    function reportDueLabel($date)
    {
        if (!$date) {
            return 'Chưa có hạn';
        }
        $today = new DateTimeImmutable('today');
        $due = new DateTimeImmutable(date('Y-m-d', strtotime($date)));
        $days = (int)$today->diff($due)->format('%r%a');
        if ($days < 0) {
            return 'Quá hạn ' . abs($days) . ' ngày';
        }
        if ($days === 0) {
            return 'Đến hạn hôm nay';
        }
        return 'Còn ' . $days . ' ngày';
    }
}

if (!function_exists('reportFilterUrl')) {
    function reportFilterUrl(array $filters, array $overrides = []): string
    {
        $next = array_merge($filters, $overrides);
        $query = http_build_query(array_filter($next, static fn($value) => (string)$value !== ''));
        return '/webbanhang/admin/statistics' . ($query ? ('?' . $query) : '');
    }
}

$today = date('Y-m-d');
$presetLinks = [
    ['label' => 'Hôm nay', 'href' => reportFilterUrl($filters, ['from_date' => $today, 'to_date' => $today])],
    ['label' => '7 ngày', 'href' => reportFilterUrl($filters, ['from_date' => date('Y-m-d', strtotime('-6 days')), 'to_date' => $today])],
    ['label' => 'Tháng này', 'href' => reportFilterUrl($filters, ['from_date' => date('Y-m-01'), 'to_date' => $today])],
];

$metricCards = [
    ['label' => 'Doanh thu', 'value' => number_format((float)($revenue ?? 0), 0, ',', '.') . 'đ', 'icon' => 'fa-coins'],
    ['label' => 'Đơn hàng', 'value' => number_format((int)($total_orders ?? 0), 0, ',', '.'), 'icon' => 'fa-file-invoice'],
    ['label' => 'Khách hàng mới', 'value' => number_format((int)($total_users ?? 0), 0, ',', '.'), 'icon' => 'fa-users'],
    ['label' => 'Nhà cung cấp mới', 'value' => number_format((int)($supplyReports['suppliers'] ?? 0), 0, ',', '.'), 'icon' => 'fa-handshake-angle'],
    ['label' => 'Lô hàng theo ngày', 'value' => number_format((int)($supplyReports['batches'] ?? 0), 0, ',', '.'), 'icon' => 'fa-qrcode'],
    ['label' => 'Tồn kho theo lô', 'value' => number_format((int)($supplyReports['inventory_quantity'] ?? 0), 0, ',', '.') . ' kg', 'icon' => 'fa-warehouse'],
    ['label' => 'Kho lưu trữ', 'value' => number_format((int)($supplyReports['warehouses'] ?? 0), 0, ',', '.'), 'icon' => 'fa-boxes-stacked'],
    ['label' => 'Đơn xuất khẩu', 'value' => number_format((int)($supplyReports['export_orders'] ?? 0), 0, ',', '.'), 'icon' => 'fa-truck-fast'],
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.report-page { padding: 28px; background: #f4f8f6; }
.report-head { margin-bottom: 20px; }
.report-hero {
    border-radius: 18px;
    border: 1px solid #0e6a57;
    background: linear-gradient(125deg, #062f2a 0%, #0a5f4d 58%, #10906e 100%);
    color: #fff;
    padding: 20px;
    box-shadow: 0 18px 38px rgba(6, 47, 42, .26);
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 14px;
    align-items: start;
}
.report-head h1 { margin: 0; color: #fff; font-weight: 950; font-size: clamp(1.25rem, 2.5vw, 1.95rem); }
.report-head p { margin: 8px 0 0; color: rgba(255,255,255,.9); line-height: 1.55; font-weight: 620; max-width: 760px; }
.report-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
.report-action-btn {
    min-height: 40px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,.3);
    background: rgba(255,255,255,.14);
    color: #fff;
    font-weight: 850;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.report-action-btn.primary { background: #fff; color: #065f46; border-color: #fff; }
.report-action-btn:hover { color: #fff; }
.report-action-btn.primary:hover { color: #065f46; }
.report-filter { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; align-items:end; margin:0 0 18px; padding:16px; background:#fff; border:1px solid #d9e8de; border-radius:16px; box-shadow:0 10px 28px rgba(6,78,59,.06); }
.report-filter label { display:block; margin-bottom:6px; color:#64748b; font-size:.72rem; font-weight:900; text-transform:uppercase; }
.report-filter input,.report-filter select { width:100%; min-height:40px; border:1px solid #d6e7dd; border-radius:10px; padding:7px 10px; color:#0f172a; background:#fff; font-weight:800; outline:0; }
.report-filter button,.report-filter a { min-height:40px; border:0; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:0 12px; background:#059669; color:#fff; font-weight:900; text-decoration:none; }
.report-filter a { background:#ecfdf5; color:#047857; border:1px solid #bbf7d0; }
.report-filter-actions { display:flex; gap:8px; align-items:center; }
.report-filter-actions button,.report-filter-actions a { flex:1; white-space:nowrap; }
.report-filter-note { grid-column:1 / -1; margin:-2px 0 0; color:#64748b; font-size:.82rem; font-weight:700; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.report-filter-note strong { color:#0f172a; }
.report-presets { display:flex; gap:8px; flex-wrap:wrap; }
.report-presets a { min-height:32px; padding:0 10px; font-size:.78rem; }
.report-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.report-card {
    border: 1px solid #d9e8de;
    border-radius: 16px;
    padding: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #fcfefd 100%);
    box-shadow: 0 10px 28px rgba(6, 78, 59, .06);
}
.report-card i {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #065f46;
    background: #dcfce7;
    margin-bottom: 10px;
}
.report-card small { display: block; color: #6b7280; font-weight: 850; text-transform: uppercase; font-size: .72rem; letter-spacing: .03em; }
.report-card strong { display: block; margin-top: 8px; color: #052e2b; font-size: clamp(1.15rem, 2.3vw, 1.7rem); line-height: 1.2; font-weight: 950; }
.report-layout { display: grid; grid-template-columns: 1.75fr 1fr; gap: 18px; }
.report-panel {
    background: #fff;
    border: 1px solid #d9e8de;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(6, 78, 59, .07);
    padding: 20px;
    min-height: 360px;
}
.report-panel.wide { grid-column: 1 / -1; min-height: auto; }
.report-panel h5 { margin: 0 0 14px; color: #052e2b; font-weight: 900; font-size: 1.02rem; }
.chart-box { height: 300px; }
.country-list { display: grid; gap: 4px; }
.country-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    padding: 12px 2px;
    border-bottom: 1px dashed #e5eee8;
    color: #0f172a;
    font-weight: 800;
}
.country-row:last-child { border-bottom: 0; }
.country-row span { color: #64748b; font-weight: 700; }
.report-table-wrap { overflow-x: auto; margin: 0 -20px -20px; }
.report-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 980px; }
.report-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #eefbf3;
    color: #475569;
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: 13px 14px;
    white-space: nowrap;
}
.report-table td { padding: 14px; border-top: 1px solid #ecf3ef; vertical-align: top; background: #fff; }
.report-table tbody tr:nth-child(even) td { background: #fbfefc; }
.report-table .transaction-col { text-align: center; vertical-align: middle; }
.report-badge { display: inline-flex; border-radius: 999px; padding: 5px 10px; font-size: .76rem; font-weight: 900; }
.report-badge.export { background: #fee2e2; color: #b91c1c; }
.report-badge.import { background: #dcfce7; color: #047857; }
.report-muted { color: #64748b; font-weight: 700; }
@media (max-width: 1200px) { .report-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .report-layout { grid-template-columns: 1fr; } .report-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width: 860px) { .report-hero { grid-template-columns: 1fr; } .report-actions { justify-content: flex-start; } }
@media (max-width: 700px) { .report-page { padding: 14px 10px; } .report-grid,.report-filter { grid-template-columns: 1fr; } .report-panel { padding: 16px; } }
</style>

<div class="report-page">
    <div class="report-head">
        <div class="report-hero">
            <div>
                <h1><i class="fa-solid fa-chart-column me-2"></i>Báo cáo tổng quan chuỗi cung ứng</h1>
                <p>Gộp dashboard và báo cáo: doanh thu, tồn kho kg, lô hàng, nhà cung cấp, thu hoạch và đơn xuất khẩu.</p>
            </div>
            <div class="report-actions">
                <a class="report-action-btn primary" href="<?= htmlspecialchars($exportHref) ?>">
                    <i class="fa-solid fa-file-arrow-down"></i> Xuất báo cáo CSV
                </a>
            </div>
        </div>
    </div>

    <form class="report-filter" method="GET" action="/webbanhang/admin/statistics">
        <div>
            <label for="from_date">Từ ngày</label>
            <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($filters['from_date'] ?? '') ?>">
        </div>
        <div>
            <label for="to_date">Đến ngày</label>
            <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($filters['to_date'] ?? '') ?>">
        </div>
        <div>
            <label for="status">Trạng thái</label>
            <select id="status" name="status">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="scope">Phạm vi</label>
            <select id="scope" name="scope">
                <?php foreach ($scopeOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['scope'] ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="report-filter-actions">
            <button type="submit"><i class="fa-solid fa-filter"></i> Lọc</button>
            <a href="/webbanhang/admin/statistics"><i class="fa-solid fa-rotate-left"></i> Xóa</a>
        </div>
        <div class="report-filter-note">
            <span>Đang xem: <strong><?= htmlspecialchars($filterSummary) ?></strong></span>
            <?php if (($filters['status'] ?? '') !== ''): ?>
                <span>Trạng thái: <strong><?= htmlspecialchars($statusOptions[$filters['status']] ?? $filters['status']) ?></strong></span>
            <?php endif; ?>
            <?php if (($filters['scope'] ?? '') !== ''): ?>
                <span>Phạm vi: <strong><?= htmlspecialchars($scopeOptions[$filters['scope']] ?? $filters['scope']) ?></strong></span>
            <?php endif; ?>
            <span class="report-presets">
                <?php foreach ($presetLinks as $preset): ?>
                    <a href="<?= htmlspecialchars($preset['href']) ?>"><?= htmlspecialchars($preset['label']) ?></a>
                <?php endforeach; ?>
            </span>
        </div>
    </form>

    <section class="report-grid">
        <?php foreach ($metricCards as $card): ?>
            <article class="report-card">
                <i class="fa-solid <?= $card['icon'] ?>"></i>
                <small><?= htmlspecialchars($card['label']) ?></small>
                <strong><?= htmlspecialchars($card['value']) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="report-layout">
        <article class="report-panel">
            <h5>Doanh thu theo bộ lọc</h5>
            <div class="chart-box"><canvas id="revenueChart"></canvas></div>
        </article>

        <article class="report-panel">
            <h5>Thị trường giao hàng theo bộ lọc</h5>
            <div class="country-list">
                <?php foreach ($countries as $country): ?>
                    <div class="country-row">
                        <strong><?= htmlspecialchars($country['name'] ?? 'Nội địa') ?></strong>
                        <span><?= number_format((int)($country['total'] ?? 0), 0, ',', '.') ?> đơn</span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($countries)): ?>
                    <p class="text-muted mb-0">Chưa có dữ liệu quốc gia.</p>
                <?php endif; ?>
            </div>
        </article>

        <article class="report-panel">
            <h5>Khối lượng thu hoạch theo tháng (kg)</h5>
            <div class="chart-box"><canvas id="harvestChart"></canvas></div>
        </article>

        <article class="report-panel">
            <h5>Cơ cấu danh mục theo đơn hàng</h5>
            <div class="chart-box"><canvas id="categoryChart"></canvas></div>
        </article>

        <article class="report-panel wide">
            <h5>Lịch sử nhập/xuất kho theo đơn hàng</h5>
            <p class="report-muted mb-3">Nguồn dữ liệu chính: order_events theo order_id. Inventory_log chỉ còn là sổ kho kỹ thuật.</p>
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th class="transaction-col">Giao dịch</th>
                            <th>Đơn liên kết</th>
                            <th>Sản phẩm / Lô</th>
                            <th>Kho</th>
                            <th>Khối lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderEvents as $event): ?>
                            <?php
                                $eventType = (string)($event->event_type ?? '');
                                $eventIsLogistics = in_array($eventType, ['inventory_exported', 'shipment_created', 'shipment_step_completed', 'shipment_updated'], true);
                                $eventMeta = json_decode((string)($event->metadata_json ?? '{}'), true) ?: [];
                                $log = (object)[
                                    'type' => $eventIsLogistics ? 'export' : 'import',
                                    'delivery_scope' => $event->delivery_scope ?? 'domestic',
                                    'created_at' => $event->created_at ?? null,
                                    'order_id' => $event->order_id ?? null,
                                    'order_customer' => $event->order_customer ?? 'Khach hang',
                                    'order_status' => $event->to_status ?? $event->order_status ?? '',
                                    'product_name' => $event->title ?? $eventType,
                                    'batch_code' => $event->note ?? '',
                                    'warehouse_name' => $event->actor_name ?? 'He thong',
                                    'quantity' => $eventMeta['quantity'] ?? 0,
                                    'unit' => $eventMeta['quantity'] ?? null ? 'kg' : '',
                                ];
                            ?>
                            <?php
                                $isExport = ($log->type ?? '') === 'export';
                                $scopeLabel = ($log->delivery_scope ?? 'domestic') === 'international' ? 'Quốc tế' : 'Nội địa';
                            ?>
                            <tr>
                                <td><?= !empty($log->created_at) ? date('d/m/Y H:i', strtotime($log->created_at)) : '-' ?></td>
                                <td class="transaction-col">
                                    <span class="report-badge <?= $isExport ? 'export' : 'import' ?>">
                                        <?= $isExport ? 'Xuất kho' : 'Nhập kho' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log->order_id)): ?>
                                        <strong>#<?= (int)$log->order_id ?> - <?= htmlspecialchars($log->order_customer ?? 'Khách hàng') ?></strong><br>
                                        <small class="report-muted"><?= htmlspecialchars($scopeLabel) ?> · <?= htmlspecialchars($log->order_status ?? 'pending') ?></small>
                                    <?php else: ?>
                                        <span class="report-muted">Không gắn đơn</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($log->product_name ?? 'Nông sản') ?></strong><br>
                                    <small class="report-muted"><?= htmlspecialchars($log->batch_code ?? 'N/A') ?></small>
                                </td>
                                <td><?= htmlspecialchars($log->warehouse_name ?? 'Chưa gán') ?></td>
                                <td><strong><?= number_format((int)($log->quantity ?? 0), 0, ',', '.') ?> <?= htmlspecialchars($log->unit ?? $unitLabel) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orderEvents)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">Chưa có lịch sử nhập/xuất kho.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</div>

<script>
const revenueLabels = <?= json_encode($chart_data['labels'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const revenueValues = <?= json_encode($chart_data['values'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const categoryLabels = <?= json_encode($pie_data['labels'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const categoryValues = <?= json_encode($pie_data['values'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const harvestLabels = <?= json_encode(array_column($harvests, 'label'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const harvestValues = <?= json_encode(array_map('intval', array_column($harvests, 'total')), JSON_INVALID_UTF8_SUBSTITUTE) ?>;

Chart.defaults.font.family = 'Be Vietnam Pro, Arial, sans-serif';
Chart.defaults.color = '#64748b';

new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: revenueLabels.length ? revenueLabels : ['Không có dữ liệu'],
        datasets: [{
            data: revenueValues.length ? revenueValues : [0],
            borderColor: '#059669',
            backgroundColor: 'rgba(5,150,105,.13)',
            fill: true,
            tension: .35,
            borderWidth: 3,
            pointBackgroundColor: '#059669'
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
    }
});

new Chart(document.getElementById('harvestChart'), {
    type: 'bar',
    data: {
        labels: harvestLabels.length ? harvestLabels : ['Chưa có'],
        datasets: [{
            label: 'Khối lượng',
            data: harvestValues.length ? harvestValues : [0],
            backgroundColor: '#10b981',
            borderRadius: 8
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
    }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categoryLabels.length ? categoryLabels : ['N/A'],
        datasets: [{
            data: categoryValues.length ? categoryValues : [1],
            backgroundColor: ['#047857', '#10b981', '#65a30d', '#bbf7d0', '#14b8a6', '#84cc16'],
            borderColor: '#ffffff',
            borderWidth: 5
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
