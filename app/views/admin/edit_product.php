<?php
$product = is_array($product ?? null) ? (object)$product : ($product ?? null);
$productId = (int)($product->id ?? 0);
$productName = (string)($product->name ?? '');
$productPrice = (string)($product->price ?? '');
$productCategoryId = (int)($product->category_id ?? 0);
$productDescription = (string)($product->description ?? '');
$productImage = (string)($product->image ?? '');
$productOptions = array_values(array_filter(
    array_map(fn($option) => is_array($option) ? (object)$option : $option, $productOptions ?? []),
    fn($option) => (int)($option->id ?? 0) > 0 && trim((string)($option->name ?? '')) !== ''
));
?>

<div class="container py-4 edit-product-page">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card edit-product-card border-0">
                <div class="card-header text-center">
                    <h4 class="fw-bold mb-1">
                        <i class="fas fa-box-open me-2"></i>Chỉnh sửa sản phẩm
                    </h4>
                    <small>Cập nhật thông tin sản phẩm</small>
                </div>

                <div class="card-body p-4">
                    <form action="/webbanhang/admin/update/<?php echo $productId; ?>" method="POST" enctype="multipart/form-data">
                        <?php if (class_exists('CsrfHelper')) echo CsrfHelper::field(); ?>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Tên sản phẩm</label>
                            <div class="input-group">
                                <span class="input-group-text input-icon"><i class="fas fa-tag"></i></span>
                                <input type="text"
                                       name="name"
                                       class="form-control custom-input"
                                       value="<?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"
                                       autocomplete="off"
                                       required>
                            </div>
                            <small class="edit-current-id">
                                Đang chỉnh sửa sản phẩm #<?php echo $productId; ?>. Lưu form này chỉ cập nhật đúng sản phẩm này.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label small fw-bold">Giá</label>
                                <div class="input-group">
                                    <span class="input-group-text input-icon"><i class="fas fa-dollar-sign"></i></span>
                                    <input type="number" name="price" class="form-control custom-input"
                                           value="<?php echo htmlspecialchars($productPrice, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Danh mục</label>
                            <select name="category_id" class="form-select custom-input">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat->id ?>" <?= ((int)$cat->id === $productCategoryId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Mô tả</label>
                            <textarea name="description" class="form-control custom-input" rows="3"><?php echo htmlspecialchars($productDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Hình ảnh</label>
                            <div class="image-preview mb-3 text-center">
                                <?php if ($productImage !== ''): ?>
                                    <img src="/webbanhang/public/uploads/<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>" class="preview-img" alt="Ảnh sản phẩm">
                                <?php else: ?>
                                    <div class="preview-img placeholder-preview"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </div>

                            <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="input-group">
                                <span class="input-group-text input-icon"><i class="fas fa-image"></i></span>
                                <input type="file" name="image" class="form-control custom-input">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between gap-3 mt-4 edit-actions">
                            <a href="/webbanhang/admin/inventory" class="btn btn-glass px-4">
                                <i class="fas fa-arrow-left"></i> Hủy
                            </a>

                            <div class="d-flex flex-wrap gap-3 edit-actions-right">
                                <?php if ($productId > 0): ?>
                                    <button type="submit" form="delete-product-form" class="btn btn-danger-product px-4 fw-bold">
                                        <i class="fas fa-trash-alt"></i> Xóa
                                    </button>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-glow-primary px-4 fw-bold">
                                    <i class="fas fa-save"></i> Cập nhật
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($productId > 0): ?>
                        <form id="delete-product-form"
                              action="/webbanhang/admin/delete/<?php echo $productId; ?>"
                              method="POST"
                              class="d-none"
                              onsubmit="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này? Hành động này không thể hoàn tác.');">
                            <?php if (class_exists('CsrfHelper')) echo CsrfHelper::field(); ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.edit-product-page {
    color: #1f2937;
}

.edit-product-card {
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb !important;
    border-radius: 16px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, .08);
}

.edit-product-card .card-header {
    min-height: 72px;
    padding: 18px 22px;
    color: #fff;
    background: linear-gradient(135deg, #007956, #00bb7f);
}

.edit-product-card .card-header h4 {
    color: #fff;
    font-size: 1.25rem;
}

.edit-product-card .card-header small {
    color: rgba(255, 255, 255, .82);
    font-weight: 700;
}

.edit-product-page .form-label {
    color: #1f2937;
    text-shadow: none;
}

.custom-input {
    background: #fff;
    border: 1px solid #d1d5db;
    color: #1f2937;
    border-radius: 12px;
}

.custom-input:focus {
    background: #fff;
    border-color: #00a86b;
    box-shadow: 0 0 0 4px rgba(0, 168, 107, .14);
    color: #1f2937;
}

.input-icon {
    background: #f8fafc;
    border: 1px solid #d1d5db;
    color: #3b82f6;
}

.edit-current-id {
    display: block;
    margin-top: 8px;
    color: #64748b;
    font-weight: 800;
}

.image-preview {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    padding: 16px;
    border-radius: 14px;
}

.preview-img {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 12px 24px rgba(15, 23, 42, .14);
}

.placeholder-preview {
    margin: 0 auto;
    display: grid;
    place-items: center;
    background: #eef2f7;
    color: #64748b;
    font-size: 1.6rem;
}

.btn-glow-primary,
.btn-danger-product,
.btn-glass {
    min-height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

.edit-actions-right {
    justify-content: flex-end;
}

.btn-glow-primary {
    border: 0;
    color: #fff;
    background: linear-gradient(135deg, #007956, #00bb7f);
    box-shadow: 0 10px 20px rgba(0, 187, 127, .18);
}

.btn-danger-product {
    border: 0;
    color: #fff;
    background: #dc2626;
    box-shadow: 0 10px 20px rgba(220, 38, 38, .16);
}

.btn-danger-product:hover {
    color: #fff;
    background: #b91c1c;
}

.btn-glass {
    border: 1px solid #d1d5db;
    color: #1f2937;
    background: #fff;
}

.btn-glass:hover {
    border-color: #b7ebd3;
    color: #007956;
    background: #e9fbf2;
}

@media (max-width: 575.98px) {
    .edit-actions,
    .edit-actions-right,
    .edit-actions .btn {
        width: 100%;
    }
}
</style>
