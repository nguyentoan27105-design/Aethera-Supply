<?php $selectedCategoryId = (int)($selectedCategoryId ?? 0); ?>

<div class="container mt-5" style="font-family: 'Nunito', sans-serif; max-width: 850px;">
    <div class="card add-product-card text-white shadow-lg border-0">
        
        <!-- HEADER -->
        <div class="card-header add-product-header d-flex align-items-center">
            <a href="/webbanhang/admin/inventory" class="btn btn-back me-3">
                <i class="fa fa-arrow-left"></i>
            </a>
            <h3 class="mb-0 fw-bold">
                <i class="fa-solid fa-leaf me-2"></i> Thêm Nông Sản Xuất Khẩu
            </h3>
        </div>

        <!-- BODY -->
        <div class="card-body p-4">
            <form action="/webbanhang/admin/add" method="POST" enctype="multipart/form-data">
                <?php if ($selectedCategoryId > 0): ?>
                    <input type="hidden" name="redirect_category_id" value="<?= $selectedCategoryId ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold small text-muted">
                            <i class="fa-solid fa-seedling me-1"></i> Tên sản phẩm
                        </label>
                        <input type="text" name="name" class="form-control input-custom" 
                               placeholder="VD: Gạo ST25, Khoai lang tím..." required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-muted">
                            <i class="fa-solid fa-coins me-1"></i> Giá (VNĐ)
                        </label>
                        <input type="number" name="price" class="form-control input-custom" 
                               placeholder="Giá mỗi đơn vị" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">
                        <i class="fa-solid fa-layer-group me-1"></i> Danh mục
                    </label>
                    <select name="category_id" class="form-select input-custom" required>
                        <option value="">-- Chọn loại hàng --</option>
                        <?php foreach ($categories as $cat): ?>
                         <option value="<?= $cat->id ?>" <?= (int)$cat->id === $selectedCategoryId ? 'selected' : '' ?>><?= htmlspecialchars($cat->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">
                        <i class="fa-solid fa-align-left me-1"></i> Mô tả & tiêu chuẩn
                    </label>
                    <textarea name="description" class="form-control input-custom" rows="4" 
                              placeholder="VietGAP, GlobalGAP, độ ẩm, đóng gói..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted">
                        <i class="fa-solid fa-image me-1"></i> Hình ảnh
                    </label>
                    <div class="upload-box">
                        <input type="file" name="image" id="inputGroupFile02">
                        <div class="upload-ui">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Chọn ảnh hoặc kéo thả</span>
                        </div>
                    </div>
                    <small class="text-muted">JPG, PNG, WEBP (< 2MB)</small>
                </div>

                <hr class="my-4" style="border-color: rgba(255,255,255,0.05);">

                <div class="d-flex justify-content-end">
                    <a href="/webbanhang/admin/inventory" class="btn btn-cancel me-2">
                        <i class="fa fa-times me-1"></i> Hủy
                    </a>
                    <button type="submit" class="btn btn-save">
                        <i class="fa fa-save me-1"></i> Lưu sản phẩm
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
/* ===== AETHERA SUPPLY GREEN ===== */
:root {
    --green-main: #22c55e;
    --green-soft: #4ade80;
    --green-dark: #166534;
}

/* CARD */
.add-product-card {
    background: rgba(255,255,255,0.03);
    backdrop-filter: blur(20px);
    border-radius: 20px;
}

/* HEADER */
.add-product-header {
    background: linear-gradient(135deg, #22c55e, #4ade80);
    padding: 20px;
    border-radius: 20px 20px 0 0;
}

/* INPUT */
.input-custom {
    background: rgba(255,255,255,0.05);
    border: none;
    border-radius: 12px;
    color: #fff;
    padding: 12px;
}

.input-custom::placeholder {
    color: rgba(255,255,255,0.4);
}

.input-custom:focus {
    background: rgba(255,255,255,0.08);
    box-shadow: 0 0 0 2px rgba(34,197,94,0.3);
    color: #fff;
}

/* FILE UPLOAD */
.upload-box {
    position: relative;
    border: 2px dashed rgba(255,255,255,0.1);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.upload-box:hover {
    border-color: var(--green-main);
    background: rgba(34,197,94,0.05);
}

.upload-box input {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.upload-ui i {
    font-size: 28px;
    color: var(--green-soft);
    margin-bottom: 10px;
    display: block;
}

/* BUTTON */
.btn-save {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border: none;
    color: #fff;
    padding: 10px 25px;
    border-radius: 999px;
    font-weight: 600;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(34,197,94,0.3);
}

/* CANCEL */
.btn-cancel {
    background: rgba(255,255,255,0.05);
    color: #ccc;
    border-radius: 999px;
    padding: 10px 20px;
}

.btn-cancel:hover {
    background: #ef4444;
    color: #fff;
}

/* BACK */
.btn-back {
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    width: 38px;
    height: 38px;
    color: #fff;
}

.btn-back:hover {
    background: var(--green-dark);
}
</style>
