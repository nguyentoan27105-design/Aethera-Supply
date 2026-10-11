<div class="container py-4">
    <div class="card add-card shadow-lg border-0">
        
        <div class="card-header add-header d-flex align-items-center">
            <i class="fa-solid fa-seedling me-2"></i>
            <h5 class="mb-0 fw-bold">Thêm danh mục nông sản</h5>
        </div>

        <div class="card-body p-4">
            <form action="/webbanhang/admin/add_category" method="POST">
                
                <div class="mb-4">
                    <label class="form-label fw-bold small">
                        <i class="fa-solid fa-tag me-1"></i> Tên danh mục
                    </label>
                    <input type="text" name="name" 
                           class="form-control input-custom" 
                           placeholder="Ví dụ: Rau sạch, Trái cây hữu cơ..." required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small">
                        <i class="fa-solid fa-align-left me-1"></i> Mô tả
                    </label>
                    <textarea name="description" 
                              class="form-control input-custom" 
                              rows="3"
                              placeholder="Mô tả ngắn về danh mục..."></textarea>
                </div>

                <div class="d-flex align-items-center border-top pt-4">
                    <button type="submit" class="btn btn-save me-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Lưu danh mục
                    </button>

                    <a href="/webbanhang/admin/categories" class="btn btn-cancel">
                        <i class="fa-solid fa-arrow-left me-1"></i> Hủy
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #22c55e;
    --primary-hover: #16a34a;
    --bg: #f1f5f9;
    --card: #ffffff;
    --border: #cbd5e1;
    --text-main: #1e293b; /* Màu chữ đen đậm */
    --text-muted: #64748b;
    --placeholder: #94a3b8;
}

body {
    background: var(--bg);
}

.add-card {
    background: var(--card);
    border-radius: 24px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.add-header {
    background: linear-gradient(135deg, #22c55e, #10b981);
    padding: 20px 24px;
    color: #fff;
    border: none;
}

.form-label {
    color: var(--text-muted) !important;
    font-size: 14px;
    margin-bottom: 8px;
}

/* INPUT CUSTOM - FIX LỖI CHỮ TRẮNG */
.input-custom {
    background: #f8fafc !important;
    border: 1px solid var(--border) !important;
    border-radius: 14px !important;
    padding: 12px 16px !important;
    color: var(--text-main) !important; /* Buộc chữ phải màu đen đậm */
    font-weight: 500 !important;
    transition: all .2s;
}

.input-custom::placeholder {
    color: var(--placeholder) !important;
}

.input-custom:focus {
    background: #fff !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.15) !important;
    color: #000 !important; /* Chữ đen tuyền khi focus */
    outline: none;
}

/* BUTTONS */
.btn-save {
    background: var(--primary);
    border: none;
    color: white;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 600;
    transition: .2s;
}

.btn-save:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
    color: white;
}

.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border-radius: 12px;
    padding: 10px 20px;
    font-weight: 600;
    border: none;
    text-decoration: none;
}

.btn-cancel:hover {
    background: #fee2e2;
    color: #ef4444;
}
</style>