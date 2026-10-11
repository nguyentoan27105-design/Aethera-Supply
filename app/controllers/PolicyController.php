<?php

class PolicyController
{
    private string $baseUrl = '/webbanhang';

    private array $policies = [
        'shipping' => [
            'title' => 'Chính sách vận chuyển',
            'icon' => 'fa-truck-fast',
            'summary' => 'Quy định giao hàng nội địa, quốc tế, phí vận chuyển và theo dõi hành trình.',
            'sections' => [
                ['Phạm vi giao hàng', 'Aethera Supply hỗ trợ giao nội địa toàn quốc và đơn quốc tế theo quốc gia được chọn khi thanh toán. Đơn chưa xuất kho sẽ ở trạng thái Kho Aethera.'],
                ['Cách tính phí', 'Phí nội địa tính theo kg, dịch vụ và đơn vị vận chuyển. Đơn nội địa từ 300.000đ và không quá 20 kg được miễn phí tùy theo vị trí địa lý. Đơn quốc tế tính theo tuyến express hoặc container lạnh.'],
                ['Thời gian dự kiến','Rau củ quả tươi được ưu tiên giao nhanh nhằm đảm bảo chất lượng: nội thành trong ngày (2–24 giờ), liên tỉnh 1–3 ngày và quốc tế theo lịch vận chuyển, kiểm dịch và chuỗi bảo quản lạnh.'],
                ['Theo dõi đơn', 'Khách hàng xem trạng thái trong Hồ sơ > Đơn hàng. Admin cập nhật từng chặng sau khi kho xuất hàng.'],
            ],
        ],
        'returns' => [
            'title' => 'Chính sách đổi trả và hoàn hàng',
            'icon' => 'fa-rotate-left',
            'summary' => 'Áp dụng cho nông sản lỗi chất lượng, sai lô, hư hỏng do vận chuyển hoặc giao thiếu.',
            'sections' => [
                ['Điều kiện đổi trả', 'Sản phẩm còn trong thời gian phản hồi 24h sau khi nhận hàng, có hình ảnh/video xác minh và mã đơn hàng.'],
                ['Trường hợp hỗ trợ', 'Sai sản phẩm, thiếu số lượng, lô hàng bị thu hồi, sản phẩm dập/hư hỏng vượt mức cho phép hoặc không đúng mô tả.'],
                ['Hoàn hàng', 'Khi hoàn hàng được duyệt, kho ghi nhận nhập hoàn theo mã đơn để đồng bộ tồn kho và lịch sử báo cáo.'],
                ['Không áp dụng', 'Không hỗ trợ với sản phẩm đã sử dụng sai hướng dẫn bảo quản hoặc quá thời hạn phản hồi.'],
            ],
        ],
        'payment' => [
            'title' => 'Chính sách thanh toán',
            'icon' => 'fa-credit-card',
            'summary' => 'Hỗ trợ COD, chuyển khoản ngân hàng nội địa, Visa/MasterCard và thanh toán quốc tế qua SWIFT/TT.',
            'sections' => [
                ['Phương thức thanh toán', 'Khách hàng có thể chọn COD cho đơn nội địa, chuyển khoản ngân hàng, Visa/MasterCard hoặc SWIFT/TT cho đơn xuất khẩu. Thông tin thanh toán được lưu kèm đơn để admin đối soát.'],
                ['Xác nhận thanh toán', 'Sau khi khách xác nhận thanh toán, đơn chuyển sang chờ xử lý. Khi admin xuất kho, đơn chuyển sang vận chuyển.'],
                ['Hoàn tiền', 'Nếu đơn đã thanh toán bị hủy, hệ thống ghi nhận trạng thái hoàn tiền và số tiền hoàn trên chi tiết đơn.'],
                ['Đặt cọc đơn hàng','Để xác nhận đơn và đảm bảo nguồn cung, khách hàng có thể đặt cọc trước từ 40%–60% giá trị đơn hàng hoặc thanh toán đủ 100%. Đơn chỉ được xử lý sau khi xác nhận thanh toán trước thành công.'],
                ['Xác nhận xử lý','Đơn hàng chỉ được chuyển sang trạng thái xử lý sau khi hệ thống xác nhận thanh toán tiền đặt cọc thành công.'],
                ['Thanh toán phần còn lại','Khách hàng thanh toán phần giá trị còn lại theo thời hạn đã thỏa thuận hoặc trước thời điểm giao hàng.'],
                ['Hoàn và khấu trừ cọc','Khoản đặt cọc được khấu trừ vào tổng giá trị đơn hàng khi hoàn tất thanh toán. Trường hợp bên bán không thể cung cấp hàng theo cam kết, khoản cọc sẽ được hoàn lại theo chính sách áp dụng.'],
                ['Hủy đơn','Trường hợp khách hàng hủy đơn sau khi xác nhận đặt cọc, khoản cọc được xử lý theo điều khoản giao dịch hoặc hợp đồng đã ký kết.'],
            ],
        ],
        'privacy' => [
            'title' => 'Chính sách bảo mật',
            'icon' => 'fa-shield-halved',
            'summary' => 'Cam kết bảo vệ thông tin tài khoản, địa chỉ giao hàng và dữ liệu giao dịch.',
            'sections' => [
                ['Dữ liệu thu thập', 'Họ tên, số điện thoại, email, địa chỉ giao hàng, thông tin thanh toán tham chiếu và lịch sử đơn hàng.'],
                ['Mục đích sử dụng', 'Xử lý đơn hàng, giao nhận, bảo hành/đổi trả, truy xuất nguồn gốc và chăm sóc khách hàng.'],
                ['Bảo vệ dữ liệu', 'Thông tin được phân quyền theo vai trò người dùng. Admin chỉ dùng dữ liệu cho vận hành chuỗi cung ứng.'],
                ['Không chia sẻ trái phép', 'Không bán hoặc chia sẻ dữ liệu cá nhân cho bên thứ ba ngoài đơn vị vận chuyển/thanh toán cần thiết để xử lý đơn.'],
            ],
        ],
        'terms' => [
            'title' => 'Điều khoản sử dụng',
            'icon' => 'fa-file-contract',
            'summary' => 'Quy định trách nhiệm của người dùng khi mua hàng và sử dụng hệ thống Aethera.',
            'sections' => [
                ['Tài khoản', 'Người dùng chịu trách nhiệm với thông tin đăng nhập, địa chỉ nhận hàng và nội dung giao dịch phát sinh từ tài khoản.'],
                ['Đặt hàng', 'Đơn hàng chỉ được xử lý khi thông tin nhận hàng, phương thức thanh toán và vận chuyển hợp lệ.'],
                ['Hủy đơn', 'Khách hàng chỉ hủy được đơn ở trạng thái chờ xử lý. Đơn đã xuất kho sẽ xử lý theo quy trình hoàn hàng/hoàn tiền.'],
                ['Thay đổi chính sách', 'Aethera có thể cập nhật chính sách để phù hợp vận hành, luật áp dụng và yêu cầu xuất khẩu.'],
            ],
        ],
        'quality' => [
            'title' => 'Chính sách chất lượng và truy xuất',
            'icon' => 'fa-qrcode',
            'summary' => 'Minh bạch lô hàng, nhà cung cấp, thu hoạch, bảo quản và QR truy xuất nguồn gốc.',
            'sections' => [
                ['Truy xuất QR', 'Mỗi lô hàng có mã QR liên kết sản phẩm, nhà cung cấp, ngày thu hoạch, kho bảo quản và tiêu chuẩn chất lượng.'],
                ['Bảo quản', 'Nhiệt độ, độ ẩm và phương pháp bảo quản được ghi nhận theo lô để giảm rủi ro hư hỏng.'],
                ['Thu hồi lô', 'Lô hàng có vấn đề từ đối tác có thể bị thu hồi/xóa khỏi cửa hàng để không phát sinh đơn mới.'],
                ['Cam kết', 'Ưu tiên sản phẩm có tiêu chuẩn VietGAP, GlobalGAP, HACCP hoặc chứng nhận phù hợp từng nhóm nông sản.'],
            ],
        ],
    ];

    public function index(): void { $this->show('shipping'); }
    public function shipping(): void { $this->show('shipping');}
    public function returns(): void { $this->show('returns'); }
    public function payment(): void { $this->show('payment'); }
    public function privacy(): void { $this->show('privacy'); }
    public function terms(): void { $this->show('terms'); }
    public function quality(): void { $this->show('quality'); }
    private function show(string $slug): void
    {
        $policy = $this->policies[$slug] ?? $this->policies['shipping'];
        $policies = $this->policies;
        $currentSlug = $slug;

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/policy/index.php';
        include __DIR__ . '/../views/shares/footer.php';
    }
}
