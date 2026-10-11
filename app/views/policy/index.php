<?php
$policy = $policy ?? [];
$policies = $policies ?? [];
$currentSlug = $currentSlug ?? 'shipping';
?>

<main class="policy-page">
    <section class="policy-hero">
        <span><i class="fa-solid <?= htmlspecialchars($policy['icon'] ?? 'fa-file-lines') ?>"></i> Aethera Policy Center</span>
        <h1><?= htmlspecialchars($policy['title'] ?? 'Chính sách') ?></h1>
        <p><?= htmlspecialchars($policy['summary'] ?? '') ?></p>
    </section>

    <div class="policy-layout">
        <aside class="policy-nav">
            <?php foreach ($policies as $slug => $item): ?>
                <a class="<?= $slug === $currentSlug ? 'active' : '' ?>" href="/webbanhang/policy/<?= htmlspecialchars($slug) ?>">
                    <i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </aside>

        <section class="policy-content">
            <?php foreach (($policy['sections'] ?? []) as $section): ?>
                <article>
                    <h2><?= htmlspecialchars($section[0]) ?></h2>
                    <p><?= htmlspecialchars($section[1]) ?></p>
                </article>
            <?php endforeach; ?>

            <div class="policy-note">
                <i class="fa-solid fa-headset"></i>
                <div>
                    <strong>Cần hỗ trợ thêm?</strong>
                    <p>Liên hệ  hoặc xem trạng thái đơn trong Hồ sơ cá nhân để được hỗ trợ theo mã đơn hàng.</p>
                </div>
            </div>
        </section>
    </div>
</main>

<style>
.policy-page { background: #f4f7f6; padding: 28px 16px 54px; color: #0f172a; font-family: 'Be Vietnam Pro', 'Inter', Arial, sans-serif; }
.policy-hero {
    max-width: 1180px;
    margin: 0 auto 18px;
    padding: 32px;
    border-radius: 20px;
    background: linear-gradient(120deg, #062b2a 0%, #0b4f4a 55%, #087f5b 100%);
    color: #fff;
    box-shadow: 0 20px 45px rgba(6, 43, 42, .2);
}
.policy-hero span { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 8px 12px; background: rgba(255, 255, 255, .14); font-weight: 900; font-size: .78rem; text-transform: uppercase; }
.policy-hero h1 { margin: 14px 0 8px; font-size: clamp(1.7rem, 4vw, 2.7rem); font-weight: 900; letter-spacing: 0; }
.policy-hero p { max-width: 780px; margin: 0; color: rgba(255, 255, 255, .86); font-weight: 650; line-height: 1.65; }
.policy-layout { max-width: 1180px; margin: 0 auto; display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 18px; align-items: start; }
.policy-nav, .policy-content {
    background: #fff;
    border: 1px solid #d9e8de;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}
.policy-nav { padding: 12px; display: grid; gap: 8px; position: sticky; top: 18px; }
.policy-nav a { display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 12px; border-radius: 12px; color: #334155; text-decoration: none; font-weight: 800; border: 1px solid transparent; }
.policy-nav a i { color: #047857; width: 20px; text-align: center; }
.policy-nav a.active, .policy-nav a:hover { background: #eefbf3; color: #065f46; border-color: #d2e4d8; }
.policy-content { padding: 24px; display: grid; gap: 14px; }
.policy-content article { border: 1px solid #e6efe9; border-radius: 14px; padding: 18px; background: #fcfefd; }
.policy-content h2 { margin: 0 0 8px; color: #052e2b; font-size: 1.03rem; font-weight: 900; }
.policy-content p { margin: 0; color: #5f6f67; font-weight: 650; line-height: 1.7; }
.policy-note { display: flex; gap: 14px; align-items: flex-start; border-radius: 14px; padding: 18px; background: #eefbf3; border: 1px solid #cde8d8; }
.policy-note i { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: #047857; color: #fff; flex: 0 0 40px; }
.policy-note strong { display: block; color: #052e2b; font-weight: 900; }
.policy-note p { margin: 4px 0 0; }
@media (max-width: 860px) { .policy-layout { grid-template-columns: 1fr; } .policy-nav { position: static; } .policy-hero, .policy-content { padding: 20px; } }
</style>
