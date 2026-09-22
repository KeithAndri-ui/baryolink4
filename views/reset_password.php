<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Validate Token
$user = null;
if (!empty($token) && isset($pdo)) {
    // NEW CHECK (Uses PHP datetime comparison)
$user = null;
if (!empty($token) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $fetched_user = $stmt->fetch();

    if ($fetched_user && strtotime($fetched_user['reset_expires']) > time()) {
        $user = $fetched_user;
    } else {
        $error = 'Invalid or expired password reset token.';
    }
} else {
    $error = 'Missing reset token.';
}

    if (!$user) {
        $error = 'Invalid or expired password reset token.';
    }
} else {
    $error = 'Missing reset token.';
}

// Handle New Password Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm)) {
        $error = 'Please fill in both password fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update Password & Invalidate Reset Token
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        if ($update->execute([$hashed_password, $user['id']])) {
            header("Location: index.php?page=login&msg=password_reset");
            exit();
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>

<div class="max-w-md mx-auto my-10 bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Create New Password</h2>
        <p class="text-xs text-slate-500 mt-1">Please enter your new password below.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-xl text-xs font-semibold mb-4 border border-red-200">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($user): ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">New Password *</label>
                <div class="relative">
                    <input type="password" id="reset_password" name="password" required class="w-full border border-slate-300 rounded-xl pl-4 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="••••••••">
                    <button type="button" onclick="togglePassword('reset_password', 'reset_icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i id="reset_icon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Confirm New Password *</label>
                <div class="relative">
                    <input type="password" id="reset_confirm" name="confirm_password" required class="w-full border border-slate-300 rounded-xl pl-4 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="••••••••">
                    <button type="button" onclick="togglePassword('reset_confirm', 'reset_confirm_icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i id="reset_confirm_icon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition">
                Update Password
            </button>
        </form>
    <?php else: ?>
        <div class="text-center mt-4">
            <a href="index.php?page=forgot_password" class="text-xs bg-blue-600 text-white px-4 py-2 rounded-xl font-bold inline-block">Request New Reset Link</a>
        </div>
    <?php endif; ?>
</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>