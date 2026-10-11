<?php
$productOptions = $productOptions ?? [];
$adminFlash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold">
                Sản phẩm thuộc danh mục:
                <?= htmlspecialchars($category->name) ?>
            </h3>
        </div>

        <a href="/webbanhang/admin/categories"
           class="btn btn-secondary">
            Quay lại
        </a>
    </div>

    <?php if ($adminFlash): ?>
        <div class="alert alert-info shadow-sm border-0 rounded-4">
            <?= htmlspecialchars($adminFlash) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form method="POST" action="/webbanhang/admin/moveProductToCategory" class="row g-3 align-items-end">
                <?= CsrfHelper::field() ?>
                <input type="hidden" name="category_id" value="<?= (int)$category->id ?>">
                <div class="col-md-9">
                    <label class="form-label fw-bold">Chọn sản phẩm có sẵn để chuyển vào danh mục này</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php foreach ($productOptions as $option): ?>
                            <option value="<?= (int)$option->id ?>">
                                #<?= (int)$option->id ?> - <?= htmlspecialchars($option->name ?? 'Sản phẩm') ?>
                                <?php if (!empty($option->category_name)): ?>
                                    (đang ở: <?= htmlspecialchars($option->category_name) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100" type="submit" <?= empty($productOptions) ? 'disabled' : '' ?>>
                        <i class="fas fa-arrows-rotate me-1"></i>
                        Chuyển danh mục
                    </button>
                </div>
                <?php if (empty($productOptions)): ?>
                    <div class="col-12 text-muted small">
                        Không còn sản phẩm nào ở danh mục khác để chuyển vào đây.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên</th>
                            <th>Giá</th>
                            <th>Tồn kho</th>
                            <th class="text-center">Hành động</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($products)): ?>

                            <?php foreach ($products as $product): ?>

                                <tr>

                                    <td>
                                        #<?= $product->id ?>
                                    </td>

                                    <td>
                                        <img src="/webbanhang/public/uploads/<?= htmlspecialchars($product->image) ?>"
                                             width="60"
                                             height="60"
                                             style="object-fit:cover;border-radius:12px;">
                                    </td>

                                    <td class="fw-bold">
                                        <?= htmlspecialchars($product->name) ?>
                                    </td>

                                    <td>
                                        <?= number_format($product->price, 0, ',', '.') ?> đ
                                    </td>

                                    <td>
                                        <?= (int)$product->stock ?>
                                    </td>

                                    <td class="text-center">

                                        <a href="/webbanhang/admin/edit/<?= $product->id ?>"
                                           class="btn btn-warning btn-sm">

                                            <i class="fas fa-pen"></i>
                                            Sửa
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6"
                                    class="text-center py-5 text-muted">

                                    Danh mục chưa có sản phẩm

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
