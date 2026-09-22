<?php
ob_start(); // Buffer output so header() calls can occur anywhere
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the requested page from URL (e.g., index.php?page=logout)
$page = $_GET['page'] ?? 'landing';

// -------------------------------------------------------------
// LOGOUT ROUTE HANDLER
// -------------------------------------------------------------
if ($page === 'logout') {
    // 1. Unset all session variables
    $_SESSION = array();

    // 2. Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. Completely destroy the session
    session_destroy();

    // 4. Redirect to login page
    header("Location: index.php?page=login&msg=logged_out");
    exit();
}

// Include Database Connection
require_once 'config/db.php'; // Or wherever your database file is located

// Include Header
include 'includes/header.php'; // Or wherever your header code is saved

// Page Router Switch
switch ($page) {
    case 'dashboard':
        include 'views/dashboard.php';
        break;
    case 'permits':
        include 'views/permits.php';
        break;
    case 'blotter':
        include 'views/blotter.php';
        break;
    case 'profile':
        include 'views/profile.php';
        break;
    case 'register':
        include 'views/register.php';
        break;
    case 'login':
        include 'views/login.php';
        break;
    case 'staff_dashboard':
        include 'views/staff_dashboard.php';
        break;  
    case 'forgot_password':
        include 'views/forgot_password.php';
        break;
    case 'reset_password':
        include 'views/reset_password.php';
        break;
    case 'landing':
    default:
        include 'views/landing.php';
        break;
}

// Include Footer
include 'includes/footer.php';
?>