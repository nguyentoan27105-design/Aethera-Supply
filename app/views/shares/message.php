<?php if(isset($_SESSION['msg'])): ?>
    <style>
        .alert-aethera {
            border-radius: 14px;
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            color: #065f46;
        }
        .alert-aethera-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #7f1d1d;
        }
        .alert-aethera .icon {
            font-size: 1.3rem;
        }
    </style>

    <div class="alert 
        <?= $_SESSION['msg_type'] == 'danger' ? 'alert-aethera-danger' : 'alert-aethera' ?> 
        alert-dismissible fade show shadow-sm" 
        role="alert">

        <div class="d-flex align-items-center">
            <i class="icon fa-solid 
                <?= $_SESSION['msg_type'] == 'danger' 
                    ? 'fa-triangle-exclamation' 
                    : 'fa-circle-check' ?> 
                me-2"></i>

            <div>
                <strong>
                    <?= $_SESSION['msg_type'] == 'danger' ? 'Lỗi:' : 'Thành công:' ?>
                </strong>
                <?= $_SESSION['msg'] ?>
            </div>
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <?php 
        unset($_SESSION['msg']); 
        unset($_SESSION['msg_type']); 
    ?>
<?php endif; ?>