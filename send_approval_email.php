<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function sendApprovalEmail($recipientEmail, $recipientName) {
    $mail = new PHPMailer(true);

    try {
        // Disabled debug output for production use
        $mail->SMTPDebug = 0; 
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        $mail->Username   = 'keithandrimagaso74@gmail.com'; 
        $mail->Password   = 'kzjl zqqo ucbt xxwr'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('keithandrimagaso74@gmail.com', 'Barangay Tubod System');
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = 'Account Approved - Barangay Tubod Portal';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333; max-width: 600px; border: 1px solid #e5e7eb; border-radius: 8px;'>
                <h2 style='color: #2563eb; margin-top: 0;'>Account Approved!</h2>
                <p>Hello <b>" . htmlspecialchars($recipientName) . "</b>,</p>
                <p>Great news! Your resident account for the <b>Barangay Tubod Portal</b> has been verified and approved by the administrator.</p>
                <p>You can now log in to request barangay certificates, view announcements, and access community services online.</p>
                <br>
                <a href='http://localhost/baryolink/index.php?page=login' style='background-color: #2563eb; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Log In to Portal</a>
                <br><br>
                <hr style='border: none; border-top: 1px solid #e5e7eb;'>
                <p style='font-size: 12px; color: #6b7280;'>If you did not request this account, please ignore this email.</p>
            </div>
        ";

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>