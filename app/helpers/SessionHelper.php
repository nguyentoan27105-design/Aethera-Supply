<?php

class SessionHelper {
    private static function ensureWritableSessionPath(): void {
        $sessionPath = ini_get('session.save_path');
        $sessionPath = $sessionPath !== false ? trim((string)$sessionPath) : '';
        if (str_contains($sessionPath, ';')) {
            $sessionPath = trim((string)substr($sessionPath, strrpos($sessionPath, ';') + 1));
        }

        if ($sessionPath !== '' && is_dir($sessionPath) && is_writable($sessionPath)) {
            return;
        }

        $fallbackSessionPath = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($fallbackSessionPath)) {
            @mkdir($fallbackSessionPath, 0775, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            session_save_path($fallbackSessionPath);
        }
    }

    /**
     * Khởi tạo session nếu chưa có
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            self::ensureWritableSessionPath();
            session_start();
        }
    }

    /**
     * Kiểm tra đăng nhập
     */
    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['user']) || isset($_SESSION['user_id']);
    }

    /**
     * Kiểm tra quyền Admin (Sửa theo yêu cầu của bạn)
     */
    public static function isAdmin() {
        self::init();
        if (!self::isLoggedIn()) return false;
        
        // Kiểm tra role từ mảng user hoặc session riêng lẻ
        $role = $_SESSION['user']['role'] ?? ($_SESSION['user_role'] ?? null);
        return ($role == 'admin' || $role == '1');
    }

    /**
     * Lấy toàn bộ thông tin User dưới dạng Object
     */
    public static function getUser() {
        self::init();
        if (isset($_SESSION['user'])) {
            return (object)$_SESSION['user'];
        }
        return null;
    }

    /**
     * Lấy tên hiển thị chuyên nghiệp
     */
    public static function getDisplayName() {
        self::init();
        $user = self::getUser();
        if ($user) {
            return !empty($user->fullname) ? $user->fullname : $user->username;
        }
        return 'Khách hàng';
    }

    public static function setFlash($type, $message) {
        self::init();
        $_SESSION['msg_type'] = $type;
        $_SESSION['msg'] = $message;
    }

    public static function destroy() {
        self::init();
        session_unset();
        session_destroy();
    }
}
