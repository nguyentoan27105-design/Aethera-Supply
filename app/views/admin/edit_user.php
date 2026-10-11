<?php require_once __DIR__ . '/../../helpers/Rbac.php'; ?>

<div class="container py-5" style="font-family: 'Nunito', sans-serif;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-lg edit-user-card">
                
                <!-- HEADER -->
                <div class="card-header border-0 p-4 text-white header-gradient">
                    <h4 class="mb-0 fw-bold">
                        <i class="fa fa-user-edit me-2"></i> Chỉnh sửa thành viên
                    </h4>
                    <small class="opacity-75">Cập nhật thông tin tài khoản hệ thống</small>
                </div>

                <div class="card-body p-4">

                    <form action="/webbanhang/admin/editUser/<?= $user->id ?>" method="POST">

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Tên đăng nhập (Cố định)</label>
                            <input type="text" 
                                   class="form-control custom-input disabled-input" 
                                   value="<?= $user->username ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Họ và Tên</label>
                            <input type="text" name="fullname" 
                                   class="form-control custom-input" 
                                   value="<?= htmlspecialchars($user->fullname ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Email</label>
                            <input type="email" name="email" 
                                   class="form-control custom-input" 
                                   value="<?= htmlspecialchars($user->email ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Số điện thoại</label>
                            <input type="text" name="phone" 
                                   class="form-control custom-input" 
                                   value="<?= htmlspecialchars($user->phone ?? '') ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted small text-uppercase">Vai trò hệ thống</label>
                            <select name="role" class="form-select custom-input">
                                <?php foreach (Rbac::roleOptions() as $roleValue => $roleLabel): ?>
                                    <option value="<?= htmlspecialchars($roleValue) ?>" <?= Rbac::normalizeRole($user->role ?? 'user') === $roleValue ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($roleLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-2">
                                Phân quyền: kho chỉ thấy kho/lô, xuất khẩu chỉ thấy xuất khẩu, nhân viên chỉ xử lý đơn nội địa, nhà cung cấp không vào quản trị nội bộ.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted small text-uppercase">Liên kết hồ sơ nhà cung cấp</label>
                            <select name="supplier_id" class="form-select custom-input">
                                <option value="">Không liên kết</option>
                                <?php foreach (($suppliers ?? []) as $supplier): ?>
                                    <option value="<?= (int)$supplier->id ?>" <?= (int)($user->supplier_id ?? 0) === (int)$supplier->id ? 'selected' : '' ?>>
                                        #<?= (int)$supplier->id ?> - <?= htmlspecialchars($supplier->name ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block mt-2">Chỉ cần chọn khi tài khoản có vai trò Nhà cung cấp.</small>
                        </div>

                        <!-- ACTION -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="/webbanhang/admin/account" 
                               class="btn btn-outline-light rounded-pill px-4 btn-back">
                                <i class="fa fa-arrow-left me-1"></i> Quay lại
                            </a>

                            <button type="submit" 
                                    class="btn btn-gradient rounded-pill px-4 fw-bold shadow">
                                <i class="fa fa-save me-1"></i> Lưu thay đổi
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* CARD */
.edit-user-card {
    border-radius: 18px;
    background: #15171c;
    overflow: hidden;
}

/* HEADER GRADIENT */
.header-gradient {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
}

/* INPUT */
.custom-input {
    background: #1f2228;
    border: 1px solid rgba(255,255,255,0.08);
    color: #fff;
    border-radius: 12px;
    padding: 12px 15px;
    transition: all 0.25s ease;
}

.custom-input:focus {
    background: #1f2228;
    border-color: #00c6ff;
    box-shadow: 0 0 0 0.2rem rgba(0,198,255,0.2);
    color: #fff;
}

/* INPUT READONLY */
.disabled-input {
    opacity: 0.6;
    cursor: not-allowed;
}

/* SELECT FIX */
select.custom-input option {
    background: #1f2228;
    color: #fff;
}

/* BUTTON GRADIENT */
.btn-gradient {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    border: none;
    color: #fff;
    transition: 0.25s;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 198, 255, 0.3);
}

/* BACK BUTTON */
.btn-back {
    border-color: rgba(255,255,255,0.2);
    color: #ccc;
}

.btn-back:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

/* LABEL */
label {
    letter-spacing: 0.5px;
}
</style>
