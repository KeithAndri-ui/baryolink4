<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'registered') {
        $success = 'Account created successfully! Please wait for staff verification before logging in.';
    } elseif ($_GET['msg'] === 'login_required') {
        $error = 'Please sign in to access that service.';
    } elseif ($_GET['msg'] === 'unauthorized') {
        $error = 'You do not have permission to access that page.';
    } elseif ($_GET['msg'] === 'password_reset') {
        $success = 'Your password has been reset successfully! Please log in with your new password.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                
                // CHECK ACCOUNT STATUS BEFORE ALLOWING LOGIN
                $user_status = strtolower($user['status'] ?? 'pending');

                if ($user_status === 'rejected') {
                    $error = 'Access Denied: Your account registration has been rejected by the Barangay administration. Please contact the barangay hall for assistance.';
                } elseif ($user_status === 'pending') {
                    $error = 'Account Pending: Your residency registration is still awaiting verification by Barangay staff.';
                } else {
                    // Approved or Staff/Admin Role -> Proceed to Login
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['status']    = $user['status'];

                    if (in_array($user['role'], ['staff', 'admin'])) {
                        header("Location: index.php?page=staff_dashboard");
                    } else {
                        header("Location: index.php?page=dashboard");
                    }
                    exit();
                }

            } else {
                $error = 'Invalid email address or password.';
            }
        } else {
            $error = 'Database connection error. Please check config/db.php.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<div class="max-w-md mx-auto my-10 bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">
            🏛️
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Log In</h2>
        <p class="text-xs text-slate-500 mt-1">Sign in to your BaryoLink account</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="bg-emerald-50 text-emerald-700 p-3 rounded-xl text-xs font-semibold mb-4 border border-emerald-200 flex items-center">
            <i class="fa-solid fa-circle-check mr-2 text-base"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-xl text-xs font-semibold mb-4 border border-red-200 flex items-start">
            <i class="fa-solid fa-circle-exclamation mr-2 text-base mt-0.5"></i> 
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <form action="index.php?page=login" method="POST" class="space-y-4">
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="juan@gmail.com">
        </div>
        
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="block text-xs font-bold text-slate-700 uppercase">Password</label>
                <a href="index.php?page=forgot_password" class="text-xs text-blue-600 hover:underline font-semibold">Forgot Password?</a>
            </div>
            <div class="relative">
                <input type="password" id="login_password" name="password" required class="w-full border border-slate-300 rounded-xl pl-4 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="••••••••">
                <button type="button" onclick="togglePassword('login_password', 'login_icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <i id="login_icon" class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition">
            Log In
        </button>
        <p class="text-xs text-center text-slate-500 mt-4">
            Don't have an account yet? <a href="index.php?page=register" class="text-blue-600 font-bold hover:underline">Register here</a>
        </p>
    </form>
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