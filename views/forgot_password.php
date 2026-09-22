<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your registered email address.';
    } elseif (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a secure 32-character token and set expiration to 1 hour from now
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $update->execute([$token, $expires, $email]);

            // Construct Reset Link
          $reset_link = "http://localhost/baryolink/index.php?page=reset_password&token=" . $token;

            // FOR LOCALHOST TESTING: Show direct link on screen (In production, send via mail())
            $msg = "Password reset link generated! <br><a href='$reset_link' class='underline font-bold text-blue-700 mt-2 block'>Click here to reset your password</a>";
        } else {
            // Secure notice (prevents email enumeration)
            $msg = 'If an account exists with that email address, a password reset link has been generated.';
        }
    } else {
        $error = 'Database connection error.';
    }
}
?>

<div class="max-w-md mx-auto my-10 bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-3">
            🔑
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Reset Password</h2>
        <p class="text-xs text-slate-500 mt-1">Enter your registered email address to receive password reset instructions.</p>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-xs font-semibold mb-4 border border-blue-200 leading-relaxed">
            <i class="fa-solid fa-circle-info mr-1"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-xl text-xs font-semibold mb-4 border border-red-200">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="index.php?page=forgot_password" method="POST" class="space-y-4">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
            <input type="email" name="email" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="juan@gmail.com">
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition">
            Request Reset Link
        </button>

        <p class="text-xs text-center text-slate-500 mt-4">
            Remembered your password? <a href="index.php?page=login" class="text-blue-600 font-bold hover:underline">Log In</a>
        </p>
    </form>
</div>