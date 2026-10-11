<?php
// File: app/helpers/PHPMailer/MailHelper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

class MailHelper {
    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_USER = 'toankha.3528@gmail.com'; 
    // DÁN MÃ 16 KÝ TỰ VÀO ĐÂY (Ví dụ: 'abcd efgh ijkl mnop')
    private const SMTP_PASS = 'xxxx xxxx xxxx xxxx'; 
    private const SMTP_PORT = 587;

    public static function sendResetCode($toEmail, $otp) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USER;
            $mail->Password   = self::SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(self::SMTP_USER, 'Web Ban Hang Support');
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = '=?UTF-8?B?'.base64_encode('Mã xác nhận khôi phục mật khẩu').'?='; 
            
            // Nội dung Email chuyên nghiệp
            $mail->Body = "
                <div style='font-family: Arial; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #007bff;'>Xác nhận thay đổi mật khẩu</h2>
                    <p>Chào bạn, mã OTP của bạn là:</p>
                    <div style='background: #f4f4f4; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; color: #d9534f;'>
                        $otp
                    </div>
                    <p>Mã này sẽ hết hạn sau 15 phút.</p>
                </div>";

            return $mail->send();
        } catch (Exception $e) {
            // Ghi lỗi chi tiết vào log của Laragon để debug nếu vẫn lỗi
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}