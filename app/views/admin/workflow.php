<?php
if (!isset($_SESSION['user_id']) || !Rbac::can($_SESSION['user_role'] ?? 'user', 'workflow')) {
    header('Location: /webbanhang/account/login');
    exit;
}

$guide = $guide ?? [];
$steps = $guide['steps'] ?? [];
$tips = $guide['tips'] ?? [];
$situations = $guide['situations'] ?? [];
$roleLabel = Rbac::label($role ?? ($_SESSION['user_role'] ?? 'user'));
?>

<main class="admin-workflow-page">
    <section class="workflow-hero">
        <div>
            <span class="workflow-kicker"><?= htmlspecialchars($roleLabel) ?></span>
            <h1><?= htmlspecialchars($guide['title'] ?? 'Quy trình làm việc') ?></h1>
            <p><?= htmlspecialchars($guide['intro'] ?? 'Hướng dẫn thao tác theo chức vụ trong hệ thống.') ?></p>
        </div>
        <?php if (!empty($guide['primaryUrl'])): ?>
            <a class="workflow-primary" href="<?= htmlspecialchars($guide['primaryUrl']) ?>">
                <i class="fa-solid fa-arrow-right"></i>
                <span><?= htmlspecialchars($guide['primaryLabel'] ?? 'Bắt đầu') ?></span>
            </a>
        <?php endif; ?>
    </section>

    <?php if (!empty($situations)): ?>
        <section class="workflow-start">
            <div class="workflow-section-head">
                <span>Bắt đầu tại đây</span>
                <strong>Chọn đúng tình huống đang gặp, hệ thống sẽ đưa tới màn hình cần làm.</strong>
            </div>
            <div class="workflow-situation-grid">
                <?php foreach ($situations as $situation): ?>
                    <a class="workflow-situation" href="<?= htmlspecialchars($situation['url'] ?? '#') ?>">
                        <i class="fa-solid <?= htmlspecialchars($situation['icon'] ?? 'fa-list-check') ?>"></i>
                        <strong><?= htmlspecialchars($situation['label'] ?? '') ?></strong>
                        <span><?= htmlspecialchars($situation['text'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="workflow-layout">
        <div class="workflow-main">
            <div class="workflow-section-head">
                <span>Quy trình chuẩn</span>
                <strong>Làm từ trên xuống khi cần xử lý trọn một việc.</strong>
            </div>

            <div class="workflow-steps">
                <?php foreach ($steps as $index => $step): ?>
                    <article class="workflow-step">
                        <div class="workflow-step-number"><?= $index + 1 ?></div>
                        <div class="workflow-step-body">
                            <h2><?= htmlspecialchars($step['title'] ?? '') ?></h2>
                            <p><?= htmlspecialchars($step['text'] ?? '') ?></p>
                            <?php if (!empty($step['url'])): ?>
                                <a class="workflow-step-link" href="<?= htmlspecialchars($step['url']) ?>">
                                    <i class="fa-solid fa-up-right-from-square"></i>
                                    <span><?= htmlspecialchars($step['label'] ?? 'Mở màn hình') ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="workflow-notes">
            <div class="workflow-safe-box">
                <i class="fa-solid fa-shield-halved"></i>
                <strong>Nếu không chắc</strong>
                <span>Chỉ xem thông tin, chưa đổi trạng thái, chưa sửa tồn kho. Hỏi quản lý hoặc kiểm lại dữ liệu trước.</span>
            </div>

            <div class="workflow-section-head compact">
                <span>Lưu ý nhanh</span>
                <strong>Những điểm cần kiểm tra trước khi cập nhật trạng thái.</strong>
            </div>
            <ul>
                <?php foreach ($tips as $tip): ?>
                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= htmlspecialchars($tip) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </section>
</main>

<style>
.admin-workflow-page {
    padding: 28px;
    background: #f5f8f6;
    min-height: calc(100vh - 72px);
}

.workflow-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 18px;
    border: 1px solid #0f6f5b;
    border-radius: 16px;
    background: linear-gradient(125deg, #063b33 0%, #0d6b57 58%, #15936f 100%);
    color: #fff;
    padding: 22px;
    box-shadow: 0 18px 36px rgba(6, 59, 51, .22);
}

.workflow-kicker {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .28);
    font-weight: 800;
    font-size: 12px;
    margin-bottom: 12px;
}

.workflow-hero h1 {
    margin: 0;
    color: #fff;
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 950;
}

.workflow-hero p {
    margin: 9px 0 0;
    color: rgba(255, 255, 255, .9);
    max-width: 760px;
    line-height: 1.6;
    font-weight: 620;
}

.workflow-primary,
.workflow-step-link {
    min-height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    font-weight: 850;
    white-space: nowrap;
}

.workflow-primary {
    background: #fff;
    color: #065f46;
}

.workflow-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 18px;
    margin-top: 18px;
}

.workflow-start {
    border: 1px solid #d6e6dd;
    border-radius: 14px;
    background: #fff;
    padding: 18px;
    margin-top: 18px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
}

.workflow-situation-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.workflow-situation {
    min-height: 150px;
    border: 1px solid #cfe7da;
    border-radius: 14px;
    background: #f7fffb;
    padding: 16px;
    text-decoration: none;
    color: #244136;
    display: grid;
    grid-template-rows: auto auto 1fr;
    gap: 9px;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.workflow-situation:hover {
    border-color: #34d399;
    color: #12372c;
    box-shadow: 0 14px 30px rgba(16, 185, 129, .16);
    transform: translateY(-2px);
}

.workflow-situation i {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #dcfce7;
    color: #047857;
    font-size: 18px;
}

.workflow-situation strong {
    color: #12372c;
    font-size: 1rem;
    font-weight: 950;
}

.workflow-situation span {
    color: #526159;
    line-height: 1.5;
    font-weight: 620;
}

.workflow-main,
.workflow-notes {
    border: 1px solid #d6e6dd;
    border-radius: 14px;
    background: #fff;
    padding: 18px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .07);
}

.workflow-section-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: center;
    margin-bottom: 14px;
}

.workflow-section-head.compact {
    display: block;
}

.workflow-section-head span {
    color: #047857;
    font-weight: 950;
    text-transform: uppercase;
    font-size: 12px;
}

.workflow-section-head strong {
    color: #405047;
    font-size: 13px;
}

.workflow-steps {
    display: grid;
    gap: 12px;
}

.workflow-step {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 12px;
    border: 1px solid #e1ece6;
    border-radius: 12px;
    padding: 14px;
    background: #fbfefd;
}

.workflow-step-number {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #dcfce7;
    color: #047857;
    font-weight: 950;
}

.workflow-step h2 {
    margin: 0;
    color: #12372c;
    font-size: 1rem;
    font-weight: 950;
}

.workflow-step p {
    margin: 6px 0 12px;
    color: #526159;
    line-height: 1.55;
}

.workflow-step-link {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #bbf7d0;
}

.workflow-notes ul {
    list-style: none;
    padding: 0;
    margin: 14px 0 0;
    display: grid;
    gap: 10px;
}

.workflow-notes li {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr);
    gap: 9px;
    color: #43524a;
    line-height: 1.5;
}

.workflow-notes i {
    color: #059669;
    margin-top: 3px;
}

.workflow-safe-box {
    border: 1px solid #fde68a;
    border-radius: 12px;
    background: #fffbeb;
    padding: 14px;
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 4px 10px;
    color: #66460b;
}

.workflow-safe-box i {
    grid-row: span 2;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #fef3c7;
    color: #b45309;
    margin: 0;
}

.workflow-safe-box strong {
    color: #78350f;
    font-weight: 950;
}

.workflow-safe-box span {
    line-height: 1.5;
}

@media (max-width: 980px) {
    .workflow-hero,
    .workflow-layout,
    .workflow-situation-grid {
        grid-template-columns: 1fr;
    }

    .workflow-primary {
        justify-self: start;
    }
}

@media (max-width: 640px) {
    .admin-workflow-page {
        padding: 18px 12px;
    }

    .workflow-section-head {
        display: block;
    }

    .workflow-step {
        grid-template-columns: 1fr;
    }
}
</style>
