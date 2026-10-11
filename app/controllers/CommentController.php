<?php
require_once('app/config/database.php');
require_once('app/models/CommentModel.php');
require_once('app/helpers/CsrfHelper.php');

class CommentController
{
    private $commentModel;
    private $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = (new Database())->getConnection();
        $this->commentModel = new CommentModel($this->db);
    }

    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfHelper::verifyPost()) {
                $_SESSION['msg_type'] = 'danger';
                $_SESSION['msg'] = 'Yeu cau khong hop le hoac da het phien bao mat.';
                header("Location: /webbanhang/product");
                exit();
            }
            $productId = (int)($_POST['product_id'] ?? 0);
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $_SESSION['msg_type'] = 'danger';
                $_SESSION['msg'] = 'Vui long dang nhap de gui danh gia san pham.';
                header("Location: /webbanhang/account/login");
                exit();
            }

            if ($productId <= 0) {
                $_SESSION['msg_type'] = 'danger';
                $_SESSION['msg'] = 'San pham khong hop le.';
                header("Location: /webbanhang/product");
                exit();
            }

            $userName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Khach hang';
            $content = trim((string)($_POST['content'] ?? ''));
            $rating = $_POST['rating'] ?? 5;

            if ($content === '') {
                $_SESSION['msg_type'] = 'danger';
                $_SESSION['msg'] = 'Noi dung danh gia khong duoc de trong.';
                header("Location: /webbanhang/product/detail/" . $productId);
                exit();
            }

            $ok = $this->commentModel->addComment($productId, $userName, $content, $rating, $userId);
            $_SESSION['msg_type'] = $ok ? 'success' : 'danger';
            $_SESSION['msg'] = $ok
                ? 'Da gui binh luan va danh gia san pham.'
                : 'Khong the luu binh luan luc nay. Vui long thu lai.';

            header("Location: /webbanhang/product/detail/" . $productId);
            exit();
        }

        header("Location: /webbanhang");
        exit();
    }
}
?>
