<!-- views/admin/categories.php -->
<div class="container-fluid py-4" style="font-family: 'Inter', sans-serif;">
    <div class="row">
        <div class="col-12">
            <div class="card glass-card shadow-lg rounded-4 border-0">

                <!-- HEADER -->
                <div class="card-header d-flex justify-content-between align-items-center py-3 border-0">
                    <div>
                        <h5 class="mb-1 fw-bold text-gradient">
                            <i class="fas fa-tags me-2"></i>QUẢN LÝ DANH MỤC
                        </h5>
                        <small class="text-muted">Quản lý toàn bộ danh mục và sản phẩm</small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="/webbanhang/admin/add_category" class="btn btn-glow-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-folder-plus me-1"></i> Thêm
                        </a>
                    </div>
                </div>

                <!-- BODY -->
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table align-middle custom-table mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Danh mục</th>
                                    <th>Mô tả</th>
                                    <th class="text-center">Hành động</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td class="fw-bold text-primary">#<?= $cat->id ?></td>

                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="dot me-2"></div>
                                                    <span class="fw-bold">
                                                        <?= htmlspecialchars($cat->name) ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="text-muted small">
                                                <?= htmlspecialchars($cat->description ?? 'Chưa có mô tả') ?>
                                            </td>

                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">

                                                    <a href="/webbanhang/admin/edit_category/<?= $cat->id ?>" 
                                                       class="btn btn-action edit">
                                                        <i class="fas fa-pen"></i>
                                                    </a>

                                                    <a href="/webbanhang/admin/category_products/<?= $cat->id ?>"
                                                       class="btn btn-action view">
                                                        <i class="fas fa-box"></i>
                                                    </a>

                                                    <button onclick="confirmDelete(<?= $cat->id ?>, '<?= htmlspecialchars($cat->name) ?>')" 
                                                            class="btn btn-action delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>

                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3 d-block opacity-50"></i>
                                            Không có dữ liệu danh mục
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>

                        </table>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="card-footer border-0 small text-muted">
                    <i class="text-muted small"></i>
                    Tip: Nhấn vào icon <b>📦</b> để xem sản phẩm trong danh mục
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Xóa danh mục "${name}"?\nHành động này không thể hoàn tác!`)) {
        window.submitPostWithCsrf(`/webbanhang/admin/delete_category/${id}`);
    }
}
</script>

<style>
/* ===== BACKGROUND ===== */
body {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #e2e8f0;
}

/* ===== GLASS CARD ===== */
.glass-card {
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.06);
}

/* ===== TEXT ===== */
.text-gradient {
    background: linear-gradient(45deg, #4facfe, #00f2fe);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* ===== TABLE ===== */
.custom-table thead th {
    font-size: 11px;
    text-transform: uppercase;
    color: #94a3b8;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.custom-table td {
    border-bottom: 1px solid rgba(255,255,255,0.03);
    padding: 16px;
}

.custom-table tbody tr:hover {
    background: rgba(79, 172, 254, 0.05);
    transition: 0.3s;
}

/* ===== DOT ===== */
.dot {
    width: 10px;
    height: 10px;
    background: linear-gradient(45deg, #4facfe, #00f2fe);
    border-radius: 50%;
}

/* ===== BUTTON ===== */
.btn-glass {
    background: rgba(255,255,255,0.05);
    color: #fff;
    border: none;
}
.btn-glass:hover {
    background: rgba(255,255,255,0.1);
}

/* ===== PRIMARY BUTTON ===== */
.btn-glow-primary {
    background: linear-gradient(45deg, #4facfe, #00f2fe);
    border: none;
    color: #fff;
    box-shadow: 0 0 15px rgba(79,172,254,0.4);
}
.btn-glow-primary:hover {
    opacity: 0.9;
}

/* ===== ACTION BUTTON ===== */
.btn-action {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: 0.25s;
}

/* EDIT */
.btn-action.edit {
    background: rgba(255,193,7,0.1);
    color: #ffc107;
}
.btn-action.edit:hover {
    background: #ffc107;
    color: #000;
}

/* VIEW */
.btn-action.view {
    background: rgba(23,162,184,0.1);
    color: #17a2b8;
}
.btn-action.view:hover {
    background: #17a2b8;
    color: #fff;
}

/* DELETE */
.btn-action.delete {
    background: rgba(220,53,69,0.1);
    color: #dc3545;
}
.btn-action.delete:hover {
    background: #dc3545;
    color: #fff;
}
</style>
