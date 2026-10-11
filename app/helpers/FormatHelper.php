<?php

class FormatHelper {

    /**
     * HÀM PHP: Hiển thị giá rút gọn (Dùng cho danh sách sản phẩm hoặc badge)
     * Ví dụ: 1.500.000 -> 1.5tr, 500.000 -> 500k
     */
    public static function shortMoney($num) {
        if (!is_numeric($num) || $num <= 0) return "0đ";

        if ($num >= 1000000000) {
            return round($num / 1000000000, 1) . ' tỷ';
        } elseif ($num >= 1000000) {
            return round($num / 1000000, 1) . 'tr';
        } elseif ($num >= 1000) {
            return round($num / 1000, 0) . 'k';
        }
        
        return number_format($num, 0, ',', '.') . 'đ';
    }

    /**
     * HÀM PHP: Hiển thị đầy đủ có dấu phân cách
     * Ví dụ: 1500000 -> 1.500.000đ
     */
    public static function money($num) {
        if (!is_numeric($num)) return "0đ";
        return number_format($num, 0, ',', '.') . 'đ';
    }

    /**
     * HÀM PHP: Định dạng ngày tháng năm (Dùng cho lịch sử đơn hàng)
     * Ví dụ: 2026-04-10 -> 10/04/2026
     */
    public static function formatDate($date) {
        if (!$date) return "---";
        return date('d/m/Y', strtotime($date));
    }

    public static function cleanText($value, string $fallback = ''): string {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return $fallback;
        }

        $text = trim((string)$value);
        if ($text === '') {
            return $fallback;
        }

        $replacements = [
            'H?ng nh?n ???c t??i, ngu?n g?c r? r?ng. L?n sau t?i v?n mu?n ??t ti?p s?n ph?m n?y.' => 'Hàng nhận được tươi, nguồn gốc rõ ràng. Lần sau tôi vẫn muốn đặt tiếp sản phẩm này.',
            '??a ch?' => 'Địa chỉ',
            '?i?n tho?i' => 'Điện thoại',
            'ng??i nh?n' => 'người nhận',
            'Ng??i nh?n' => 'Người nhận',
            'Ph?m vi' => 'Phạm vi',
            'N?i ??a' => 'Nội địa',
            'Qu?c t?' => 'Quốc tế',
            'Ph??ng/X?' => 'Phường/Xã',
            'Qu?n/Huy?n' => 'Quận/Huyện',
            'T?nh/Th?nh ph?' => 'Tỉnh/Thành phố',
            'Qu?c gia' => 'Quốc gia',
            'V?n chuy?n' => 'Vận chuyển',
            'Giao ti?u chu?n' => 'Giao tiêu chuẩn',
            'Kh?i l??ng' => 'Khối lượng',
            'Ph? v?n chuy?n' => 'Phí vận chuyển',
            'Thanh to?n' => 'Thanh toán',
            'Thu ti?n' => 'Thu tiền',
            'nh?n h?ng' => 'nhận hàng',
            'Nh?n h?ng' => 'Nhận hàng',
            't?i' => 'tôi',
            'v?n' => 'vẫn',
            'mu?n' => 'muốn',
            '??t' => 'đặt',
            'ti?p' => 'tiếp',
            'S?n ph?m' => 'Sản phẩm',
            's?n ph?m' => 'sản phẩm',
            'ngu?n g?c' => 'nguồn gốc',
            'r? r?ng' => 'rõ ràng',
            'Nguy?n' => 'Nguyễn',
            ' V?n ' => ' Văn ',
            'Vi?t Nam' => 'Việt Nam',
            'An B?nh' => 'An Bình',
            'Ninh Ki?u' => 'Ninh Kiều',
            'C?n Th?' => 'Cần Thơ',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    public static function escape($value, string $fallback = ''): string {
        return htmlspecialchars(self::cleanText($value, $fallback), ENT_QUOTES, 'UTF-8');
    }

    public static function escapeMultiline($value, string $fallback = ''): string {
        return nl2br(self::escape($value, $fallback));
    }

    public static function dateTime($value, string $format = 'd/m/Y H:i', string $fallback = 'Chưa cập nhật'): string {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return $fallback;
        }

        $timestamp = strtotime($value);
        return $timestamp ? date($format, $timestamp) : $fallback;
    }

    /**
     * HÀM JS: Tự động format các thẻ có attribute [data-money]
     * Giúp cập nhật giá tiền nhanh bằng Javascript mà không cần load lại trang
     */
    public static function autoScript(){
        return '
        <script>
        document.addEventListener("DOMContentLoaded", function(){

            function formatMoney(num){
                num = Number(num);
                if (isNaN(num)) return "0 đ";

                if(num < 1000000){ 
                    return num.toLocaleString("vi-VN") + " đ";
                }

                if(num >= 1000000000000){
                    return (num/1000000000000).toFixed(1).replace(/\.0$/,"") + " nghìn tỷ";
                }

                if(num >= 1000000000){
                    return (num/1000000000).toFixed(1).replace(/\.0$/,"") + " tỷ";
                }

                return (num/1000000).toFixed(1).replace(/\.0$/,"") + " triệu";
            }

            // Tìm tất cả các thẻ có data-money và format lại nội dung
            document.querySelectorAll("[data-money]").forEach(el => {
                let value = el.getAttribute("data-money");
                if(value !== null && value !== "") {
                    el.innerText = formatMoney(value);
                }
            });
        });
        </script>
        ';
    }
}
