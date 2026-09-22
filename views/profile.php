<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'];

// -------------------------------------------------------------
// FETCH USER DATA
// -------------------------------------------------------------
$user = null;
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$full_name  = $user['full_name'] ?? ($_SESSION['full_name'] ?? 'Resident');
$email      = $user['email'] ?? ($_SESSION['email'] ?? 'N/A');
$phone      = !empty($user['phone']) ? $user['phone'] : 'N/A';
$purok      = !empty($user['purok']) ? $user['purok'] : 'N/A';
$created_at = isset($user['created_at']) ? date("M Y", strtotime($user['created_at'])) : date("M Y");

// Intelligent Name Splitting if database columns are missing or empty
$first_name  = $user['first_name'] ?? '';
$middle_name = $user['middle_name'] ?? '';
$last_name   = $user['last_name'] ?? '';

if (empty($first_name) || empty($last_name)) {
    $name_parts = array_values(array_filter(explode(' ', trim($full_name))));
    $count = count($name_parts);

    if ($count === 1) {
        $first_name  = $name_parts[0];
        $middle_name = 'N/A';
        $last_name   = 'N/A';
    } elseif ($count === 2) {
        $first_name  = $name_parts[0];
        $middle_name = 'N/A';
        $last_name   = $name_parts[1];
    } elseif ($count >= 3) {
        $first_name  = $name_parts[0];
        $last_name   = $name_parts[$count - 1];
        $middle_name = implode(' ', array_slice($name_parts, 1, $count - 2));
    }
}
?>

<div class="max-w-6xl mx-auto my-6 space-y-6">

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 text-white rounded-3xl p-6 shadow-xl flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center space-x-5">
            <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-3xl shadow-inner border border-white/20">
                👤
            </div>
            <div>
                <span class="bg-blue-500/40 border border-white/20 text-[10px] font-bold tracking-widest uppercase px-3 py-1 rounded-full inline-block mb-1">
                    Registered Resident
                </span>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                    <?php echo htmlspecialchars($full_name); ?>
                </h1>
                <div class="flex flex-wrap items-center gap-4 text-xs text-blue-100 mt-1">
                    <span>✉️ <?php echo htmlspecialchars($email); ?></span>
                    <span>•</span>
                    <span>📅 Member since <?php echo htmlspecialchars($created_at); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details (Left 2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Personal Details Card -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        🪪 Personal Details
                    </h2>
                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold px-2.5 py-0.5 rounded-full">
                        Verified Account
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-2 text-sm">
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">First Name</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars(!empty($first_name) ? $first_name : 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Middle Name</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars(!empty($middle_name) ? $middle_name : 'N/A'); ?></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Last Name</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars(!empty($last_name) ? $last_name : 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Contact & Address Card -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                <div class="border-b border-slate-100 pb-4 mb-4">
                    <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        📍 Contact & Residential Address
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-4 text-sm">
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Mobile / Phone Number</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($phone); ?></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Email Address</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Sitio / Zone</span>
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($purok); ?></span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Barangay</span>
                        <span class="font-bold text-slate-800">Barangay Tubod</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">City / Municipality</span>
                        <span class="font-bold text-slate-800">Toledo City</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-400 font-semibold">Province</span>
                        <span class="font-bold text-slate-800">Cebu</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Quick Links Sidebar -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                    ⚡ Account Quick Links
                </h3>
                <div class="space-y-2 text-xs font-bold">
                    <a href="index.php?page=dashboard" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 transition">
                        <span>📊 Resident Dashboard</span>
                        <span>›</span>
                    </a>
                    <a href="index.php?page=permits" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 transition">
                        <span>📄 My Document Requests</span>
                        <span>›</span>
                    </a>
                    <a href="index.php?page=blotter" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 transition">
                        <span>🛡️ My Filed Complaints</span>
                        <span>›</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>