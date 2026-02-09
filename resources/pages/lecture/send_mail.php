<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../../vendor/autoload.php'; 

function sendAttendanceEmail($studentEmail, $studentName, $courseName, $status) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'YOUR_REAL_EMAIL_HERE';
        $mail->Password   = 'YOUR_REAL_PASSWORD_HERE';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('YOUR_REAL_EMAIL_HERE', 'github/Muhammad-SE-Eng');
        $mail->addAddress($studentEmail, $studentName);

        $mail->isHTML(true);
        $mail->Subject = "Attendance Alert - " . $status;
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;'>
                    <h2 style='color: #d9534f; border-bottom: 2px solid #d9534f; padding-bottom: 10px;'>
                        Attendance Notification
                    </h2>
                    <p style='font-size: 16px;'>Dear <strong>$studentName</strong>,</p>
                    <p style='font-size: 15px;'>
                        You have been marked as <strong style='color: #d9534f; font-size: 18px;'>$status</strong> 
                        for the course:
                    </p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #d9534f; margin: 20px 0;'>
                        <p style='margin: 0; font-size: 16px;'><strong>Course:</strong> $courseName</p>
                        <p style='margin: 5px 0 0 0; font-size: 14px; color: #666;'><strong>Date:</strong> " . date('l, F d, Y') . "</p>
                    </div>
                    <p style='font-size: 14px; color: #555;'>
                        Please ensure regular attendance to maintain good academic standing. 
                        Repeated absences may affect your grade.
                    </p>
                    <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='font-size: 13px; color: #999;'>
                        Best regards,<br>
                        <strong>BlackHat University</strong><br>
                        Attendance Management System
                    </p>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email failed to $studentEmail: " . $mail->ErrorInfo);
        return false;
    }
}
?>
