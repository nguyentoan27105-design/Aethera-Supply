<div class="container py-5" style="font-family: 'Inter', sans-serif;">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <div class="card glass-card shadow-lg border-0">

                <!-- HEADER -->
                <div class="card-header border-0 text-center">
                    <h5 class="fw-bold text-gradient mb-1">
                        <i class="fas fa-pen-nib me-2"></i>
                        Chỉnh sửa danh mục
                    </h5>
                    <small class="text-muted">
                        <?= htmlspecialchars($category->name) ?>
                    </small>
                </div>

                <!-- BODY -->
                <div class="card-body p-4">

                    <form action="/webbanhang/admin/edit_category/<?= $category->id ?>" method="POST">

                        <!-- NAME -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Tên danh mục</label>
                            <div class="input-group">
                                <span class="input-group-text input-icon">
                                    <i class="fas fa-tag"></i>
                                </span>
                                <input type="text" 
                                       name="name" 
                                       value="<?= htmlspecialchars($category->name) ?>" 
                                       class="form-control custom-input"
                                       placeholder="Nhập tên danh mục..."
                                       required>
                            </div>
                        </div>

                        <!-- DESCRIPTION -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Mô tả</label>
                            <div class="input-group">
                                <span class="input-group-text input-icon">
                                    <i class="fas fa-align-left"></i>
                                </span>
                                <textarea name="description" 
                                          rows="3"
                                          class="form-control custom-input"
                                          placeholder="Mô tả danh mục..."><?= htmlspecialchars($category->description ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- ACTION -->
                        <div class="d-flex justify-content-between align-items-center mt-4">

                            <a href="/webbanhang/admin/categories" 
                               class="btn btn-glass px-4">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>

                            <button type="submit" 
                                    class="btn btn-glow-warning px-4 fw-bold">
                                <i class="fas fa-save me-1"></i> Cập nhật
                            </button>

                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<style>
/* ===== BACKGROUND & CHỮ TỔNG THỂ ===== */
body {
    background: #f1f5f9; /* Đổi nền trang sáng lên để chữ đen nổi bật */
    color: #000000 !important; /* Ép tất cả chữ cơ bản thành màu đen */
}

/* ===== CARD (Đổi từ tối sang sáng) ===== */
.glass-card {
    background: #ffffff !important; /* Nền card trắng sứ */
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}

/* ===== TIÊU ĐỀ (Giữ Gradient nhưng đậm hơn) ===== */
.text-gradient {
    background: linear-gradient(45deg, #b45309, #d97706); /* Màu vàng nâu đậm */
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
}

/* ===== CHỮ TRÊN HEADER & LABEL ===== */
.text-muted, label, small {
    color: #000000 !important; /* Ép màu đen cho label và các chữ nhỏ */
    opacity: 0.8;
}

/* ===== Ô NHẬP LIỆU (INPUT) - CHỮ ĐEN ===== */
.custom-input {
    background: #f8fafc !important;
    border: 1px solid #cbd5e1 !important;
    color: #000000 !important; /* CHỮ GÕ VÀO MÀU ĐEN */
    font-weight: 500 !important;
}

.custom-input:focus {
    background: #ffffff !important;
    border-color: #facc15 !important;
    box-shadow: 0 0 0 0.25rem rgba(250,204,21,0.2) !important;
    color: #000000 !important; /* KHI FOCUS VẪN MÀU ĐEN */
    outline: none;
}

.custom-input::placeholder {
    color: #94a3b8 !important; /* Màu placeholder xám dễ nhìn */
}

/* ===== INPUT ICON ===== */
.input-icon {
    background: #f1f5f9 !important;
    border: 1px solid #cbd5e1 !important;
    color: #d97706 !important; /* Icon đậm màu hơn */
    border-right: none !important;
}

/* ===== BUTTON CẬP NHẬT ===== */
.btn-glow-warning {
    background: linear-gradient(45deg, #facc15, #f59e0b);
    border: none;
    color: #000000 !important; /* Chữ trên nút màu đen */
    border-radius: 12px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

/* ===== NÚT QUAY LẠI ===== */
.btn-glass {
    background: #e2e8f0 !important;
    border: none;
    color: #000000 !important; /* Chữ nút hủy màu đen */
    border-radius: 12px;
    font-weight: 600;
}

.btn-glass:hover {
    background: #cbd5e1 !important;
}

/* ===== FIX BO GÓC ===== */
.input-group-text {
    border-radius: 12px 0 0 12px !important;
}

.custom-input {
    border-radius: 0 12px 12px 0 !important;
}

textarea.custom-input {
    border-radius: 0 12px 12px 0 !important;
}
</style>