<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // Add this right under session_start() in admin_approve.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
}

require_once 'db_connection.php';
require_once 'send_approval_email.php';
require_once 'send_approval_sms.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // 1. Fetch recipient details from database
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Update status in database
        $updateStmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        
        if ($updateStmt->execute([$user_id])) {
            
            // 3. Send Email Notification
            if (!empty($user['email'])) {
                sendApprovalEmail($user['email'], $user['full_name']);
            }

            // 4. Send SMS Notification
            if (!empty($user['phone'])) {
                sendApprovalSms($user['phone'], $user['full_name']);
            }

            header("Location: index.php?page=admin_dashboard&msg=approved");
            exit();
        }
    }
}
?>