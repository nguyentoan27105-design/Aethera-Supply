<?php
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/SupplyChainModel.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/CsrfHelper.php';

class OrderController {
    private $db;
    private $orderModel;
    private $supplyChainModel;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Khởi tạo kết nối Database và Model
        $this->db = (new Database())->getConnection();
        $this->orderModel = new OrderModel($this->db);
        $this->supplyChainModel = new SupplyChainModel($this->db);
        $this->supplyChainModel->ensureSchema();
    }

    /**
     * Xử lý Hủy đơn và hoàn lại số lượng vào kho
     */
    public function cancel($id) {
        $this->cancelOrder($id);
    }

    public function cancelOrder($id) {
        if (!CsrfHelper::verifyPost()) {
            $_SESSION['msg'] = "Yeu cau khong hop le hoac da het phien bao mat.";
            $_SESSION['msg_type'] = "danger";
            header("Location: /webbanhang/account/profile?tab=orders");
            exit();
        }

        // 1. Kiểm tra đăng nhập
        if (!isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
            header("Location: /webbanhang/account/login");
            exit();
        }

        // Ép kiểu ID để đảm bảo an toàn dữ liệu
        $session_user_id = isset($_SESSION['user'])
            ? (int)$_SESSION['user']->id
            : (int)$_SESSION['user_id'];
        $order_id = (int)$id;

        // 2. Lấy thông tin đơn hàng hiện tại
        $order = $this->orderModel->getOrderById($order_id);
        
        // 3. Kiểm tra logic: Đơn hàng tồn tại + Đúng chủ + Trạng thái đang Chờ xử lý (0)
        if ($order && (int)$order->user_id === $session_user_id && $order->status == OrderModel::STATUS_PENDING) {
            
            // 4. Gọi Model thực hiện: Hoàn kho + Chuyển status = 3 trong 1 Transaction
            $result = $this->orderModel->cancelOrder($order_id, $session_user_id);

            if ($result) {
                $_SESSION['msg'] = "Đã hủy đơn hàng #$order_id và hoàn trả sản phẩm vào kho thành công.";
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['msg'] = "Có lỗi xảy ra trong quá trình xử lý database.";
                $_SESSION['msg_type'] = "danger";
            }
        } else {
            $_SESSION['msg'] = "Đơn hàng này không thể hủy (có thể đã được giao hoặc bạn không có quyền).";
            $_SESSION['msg_type'] = "warning";
        }

        // 5. Quay về trang Profile và focus vào tab Lịch sử mua hàng
        header("Location: /webbanhang/account/profile?tab=orders");
        exit();
    }

    /**
     * Hiển thị chi tiết đơn hàng
     */
    public function detail($id) {
        $order_id = (int)$id;
        $order = $this->orderModel->getOrderById($order_id);
        
        if (!$order) {
            $_SESSION['msg'] = "Không tìm thấy thông tin đơn hàng!";
            $_SESSION['msg_type'] = "danger";
            header("Location: /webbanhang/account/profile");
            exit();
        }

        // Lấy danh sách sản phẩm trong đơn hàng này
        $order_items = $this->orderModel->getOrderDetails($order_id);
        $shipment = $this->supplyChainModel->getShipmentForOrder($order_id);
        $shipmentTimelineMap = $this->supplyChainModel->getShipmentTimelinesForOrders([$order]);
        $shipmentTimeline = $shipmentTimelineMap[$order_id] ?? [];
        $orderEvents = $this->orderModel->getOrderEvents($order_id);
        
        // Load giao diện
        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/account/orderDetail.php'; 
        include __DIR__ . '/../views/shares/footer.php';
    }
}
