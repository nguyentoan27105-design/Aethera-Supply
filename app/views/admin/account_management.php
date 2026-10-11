<?php require_once __DIR__ . '/../../helpers/Rbac.php'; ?>

<div class="container-fluid mt-4" style="font-family: 'Nunito', sans-serif;">
    <div class="card admin-card text-white shadow-lg border-0">
        
        <div class="card-header admin-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <h3 class="mb-0 fw-bold">
                    <i class="fa-solid fa-leaf me-2"></i> Aethera Supply - Thành viên
                </h3>
            </div>
            <span class="badge badge-total">
                <?= isset($users) ? count($users) : 0 ?> tài khoản
            </span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle text-white">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Người dùng</th>
                            <th>Họ và Tên</th>
                            <th>Liên hệ</th>
                            <th>Vai trò</th>
                            <th class="text-end pe-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): 
                                $displayName = $user->username ?? $user->fullname ?? 'Unknown';
                                $displayFullname = $user->fullname ?? 'Chưa cập nhật';
                            ?>
                            <tr>
                                <td class="ps-4 text-muted">#<?= $user->id ?></td>
                                
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=22c55e&color=fff" 
                                             class="avatar me-3">
                                        <span class="fw-bold"><?= htmlspecialchars($displayName) ?></span>
                                    </div>
                                </td>

                                <td><?= htmlspecialchars($displayFullname) ?></td>

                                <td>
                                    <div class="small text-success">
                                        <i class="fa-solid fa-envelope me-1"></i>
                                        <?= htmlspecialchars($user->email ?? 'N/A') ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fa-solid fa-phone me-1"></i>
                                        <?= htmlspecialchars($user->phone ?? '---') ?>
                                    </div>
                                </td>

                                <?php
                                    $role = Rbac::normalizeRole($user->role ?? 'user');
                                    $roleIcon = match ($role) {
                                        'admin' => 'fa-crown',
                                        'manager' => 'fa-user-tie',
                                        'warehouse_staff' => 'fa-warehouse',
                                        'export_staff' => 'fa-truck-fast',
                                        'staff' => 'fa-user-gear',
                                        'supplier' => 'fa-handshake-angle',
                                        default => 'fa-user',
                                    };
                                ?>
                                <td>
                                    <span class="badge role-badge role-<?= htmlspecialchars($role) ?>">
                                        <i class="fa-solid <?= $roleIcon ?> me-1"></i>
                                        <?= htmlspecialchars(Rbac::label($role)) ?>
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="btn-group-action">
                                        <a href="/webbanhang/admin/editUser/<?= $user->id ?>" class="btn btn-edit">
                                            <i class="fa fa-edit"></i>
                                        </a>

                                        <?php if(isset($_SESSION['user_id']) && $user->id != $_SESSION['user_id']): ?>
                                        <form method="POST" action="/webbanhang/admin/deleteUser/<?= $user->id ?>" class="d-inline"
                                              onsubmit="return confirm('Bạn có chắc chắn muốn xóa thành viên này?')">
                                            <?= CsrfHelper::field() ?>
                                        <button type="submit" class="btn btn-delete">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    Không có thành viên nào.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== THEME AETHERA SUPPLY (XANH NÔNG SẢN) ===== */
:root {
    --green-main: #22c55e;
    --green-soft: #4ade80;
    --green-dark: #15803d;
    --bg-dark: #0f172a;
}

/* CARD */
.admin-card {
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(20px);
    border-radius: 20px;
}

/* HEADER */
.admin-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* BADGE */
.badge-total {
    background: rgba(34,197,94,0.15);
    color: var(--green-soft);
    padding: 8px 14px;
    border-radius: 999px;
    font-weight: 600;
}

/* AVATAR */
.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
}

/* TABLE */
.table thead th {
    color: rgba(255,255,255,0.5);
    font-size: 12px;
    padding: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.table td {
    padding: 16px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
}

/* HOVER */
.table-hover tbody tr:hover {
    background: rgba(34,197,94,0.08);
    transition: 0.3s;
}

/* BUTTON */
.btn-back {
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    width: 38px;
    height: 38px;
    color: #fff;
}
.btn-back:hover {
    background: var(--green-main);
}

/* ACTION BUTTON */
.btn-group-action {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.btn-edit, .btn-delete {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}

/* EDIT */
.btn-edit {
    background: rgba(34,197,94,0.1);
    color: var(--green-main);
}
.btn-edit:hover {
    background: var(--green-main);
    color: #fff;
}

/* DELETE */
.btn-delete {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}
.btn-delete:hover {
    background: #ef4444;
    color: #fff;
}

/* ROLE */
.role-badge,
.role-admin {
    background: rgba(234,179,8,0.15);
    color: #facc15;
    padding: 6px 12px;
    border-radius: 999px;
}
.role-user {
    background: rgba(34,197,94,0.15);
    color: var(--green-soft);
    padding: 6px 12px;
    border-radius: 999px;
}
.role-manager { background: rgba(14,165,233,0.15); color: #38bdf8; }
.role-staff { background: rgba(99,102,241,0.15); color: #a5b4fc; }
.role-warehouse_staff { background: rgba(16,185,129,0.15); color: #34d399; }
.role-export_staff { background: rgba(249,115,22,0.15); color: #fdba74; }
.role-supplier { background: rgba(20,184,166,0.15); color: #5eead4; }
</style>
