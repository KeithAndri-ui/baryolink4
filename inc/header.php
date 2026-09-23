<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Mark as Read Actions
if (isset($_SESSION['user_id']) && isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_notif_read') {
        $notif_id = intval($_POST['notif_id'] ?? 0);
        if ($notif_id > 0) {
            try {
                $stmtMark = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmtMark->execute([$notif_id, $_SESSION['user_id']]);
            } catch (PDOException $e) {}
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'mark_all_notif_read') {
        try {
            $stmtMarkAll = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmtMarkAll->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {}
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Fetch unread user notifications for header dropdown
$header_notifications = [];
$all_notifications = [];

if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        // Fetch Unread Notifications for Header Dropdown
        $stmtHeaderNotif = $pdo->prepare("SELECT id, title, message, created_at, status FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 5");
        $stmtHeaderNotif->execute([$_SESSION['user_id']]);
        $header_notifications = $stmtHeaderNotif->fetchAll(PDO::FETCH_ASSOC);

        // Fetch All Notifications (Both Read and Unread) for History Modal
        $stmtAllNotif = $pdo->prepare("SELECT id, title, message, is_read, created_at, status FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20");
        $stmtAllNotif->execute([$_SESSION['user_id']]);
        $all_notifications = $stmtAllNotif->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback to recent document requests if notifications table is missing
        try {
            $stmtHeaderReq = $pdo->prepare("SELECT id, document_type, status, created_at FROM document_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
            $stmtHeaderReq->execute([$_SESSION['user_id']]);
            $recent_reqs = $stmtHeaderReq->fetchAll(PDO::FETCH_ASSOC);
            foreach ($recent_reqs as $req) {
                $header_notifications[] = [
                    'id' => $req['id'],
                    'title' => $req['document_type'] . ' Request',
                    'message' => "Status: " . ucfirst($req['status']),
                    'created_at' => $req['created_at'],
                    'status' => $req['status']
                ];
            }
            $all_notifications = $header_notifications;
        } catch (PDOException $ex) {
            $header_notifications = [];
            $all_notifications = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BaryoLink - Online Barangay Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col font-sans">

    <!-- Top Alert Bar -->
    <div class="bg-blue-900 text-blue-100 text-xs py-1.5 px-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <span class="hidden sm:inline"><i class="fa-solid fa-phone mr-1"></i> Emergency Hotline: <strong>911 / (032) 123-4567</strong></span>
        </div>
    </div>

   <!-- Main Navigation Bar -->
<header class="bg-blue-600 text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
        <!-- Logo -->
        <a href="index.php?page=landing" class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center font-bold text-2xl shadow-inner">
                🏛️
            </div>
            <div>
                <h1 class="text-xl font-black tracking-tight leading-none">BaryoLink <span class="text-xs bg-blue-800 text-blue-200 px-2 py-0.5 rounded-full font-semibold ml-1">E-BARANGAY</span></h1>
                <p class="text-xs text-blue-100 mt-0.5">Barangay Tubod, Toledo City, Cebu • Online Portal</p>
            </div>
        </a>

        <nav class="hidden md:flex items-center space-x-1 text-sm font-semibold">
            <?php 
            $current_page = $_GET['page'] ?? '';
            if ($current_page !== 'staff_dashboard'): 
            ?>
                <a href="index.php?page=landing" class="px-3 py-2 rounded-lg hover:bg-blue-700 transition <?php echo $current_page === 'landing' ? 'bg-blue-700' : ''; ?>">Home</a>
                <a href="index.php?page=dashboard" class="px-3 py-2 rounded-lg hover:bg-blue-700 transition <?php echo $current_page === 'dashboard' ? 'bg-blue-700' : ''; ?>">Dashboard</a>
                <a href="index.php?page=permits" class="px-3 py-2 rounded-lg hover:bg-blue-700 transition <?php echo $current_page === 'permits' ? 'bg-blue-700' : ''; ?>">Request Documents</a>
                <a href="index.php?page=blotter" class="px-3 py-2 rounded-lg hover:bg-blue-700 transition <?php echo $current_page === 'blotter' ? 'bg-blue-700' : ''; ?>">Submit Complaints</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="index.php?page=profile" class="px-3 py-2 rounded-lg hover:bg-blue-700 transition <?php echo $current_page === 'profile' ? 'bg-blue-700' : ''; ?>">Profile</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff', 'admin'])): ?>
                <a href="index.php?page=staff_dashboard" class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-slate-900 font-bold transition flex items-center shadow-sm ml-2">
                    <i class="fa-solid fa-user-shield mr-1"></i> Staff Portal
                </a>
            <?php endif; ?>
        </nav>

        <!-- Dynamic User Auth Buttons & Notification Center -->
        <div class="flex items-center space-x-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                
                <!-- NOTIFICATIONS DROPDOWN MENU -->
                <div class="relative">
                    <button id="notifDropdownBtn" onclick="toggleNotifDropdown()" class="relative p-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white transition flex items-center justify-center focus:outline-none">
                        <i class="fa-solid fa-bell text-base"></i>
                        <?php if (count($header_notifications) > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-blue-600 shadow-sm animate-pulse">
                                <?php echo count($header_notifications); ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Notification Dropdown Panel -->
                    <div id="notifDropdownMenu" class="hidden absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 text-slate-800 z-50 overflow-hidden transition-all duration-200">
                        <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <span class="font-bold text-xs uppercase tracking-wider text-slate-600 flex items-center">
                                <i class="fa-solid fa-bell text-blue-600 mr-2"></i> Unread Notifications
                            </span>
                            <?php if (count($header_notifications) > 0): ?>
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="mark_all_notif_read">
                                    <button type="submit" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                        Mark all as read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <?php if (!empty($header_notifications)): ?>
                                <?php foreach ($header_notifications as $notif): ?>
                                    <div class="p-3.5 hover:bg-blue-50/50 transition flex items-start space-x-3 group relative">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs shrink-0 mt-0.5">
                                            📌
                                        </div>
                                        <div class="flex-grow pr-6">
                                            <h4 class="text-xs font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                            <p class="text-xs text-slate-500 mt-0.5 leading-relaxed"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <span class="text-[10px] text-slate-400 mt-1 block">
                                                <?php echo date("M d, Y • h:i A", strtotime($notif['created_at'])); ?>
                                            </span>
                                        </div>

                                        <!-- Mark as Read Button -->
                                        <?php if (isset($notif['id'])): ?>
                                            <form method="POST" action="" class="absolute right-3 top-3">
                                                <input type="hidden" name="action" value="mark_notif_read">
                                                <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                                <button type="submit" title="Mark as Read & Remove" class="text-slate-400 hover:text-emerald-600 p-1 rounded-full hover:bg-emerald-50 transition">
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-slate-400">
                                    <i class="fa-regular fa-bell-slash text-2xl mb-2 text-slate-300"></i>
                                    <p class="text-xs font-medium text-slate-500">No unread notifications.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex justify-between items-center text-[11px] font-bold px-4">
                            <button onclick="openNotifHistoryModal()" class="text-slate-600 hover:text-blue-600 transition flex items-center">
                                <i class="fa-solid fa-clock-rotate-left mr-1"></i> View Previous Notifications
                            </button>
                            <a href="index.php?page=dashboard" class="text-blue-600 hover:text-blue-800 transition">
                                Dashboard &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- USER PROFILE MENU DROPDOWN -->
                <div class="relative">
                    <button id="profileDropdownBtn" onclick="toggleProfileDropdown()" class="flex items-center space-x-2 bg-blue-700 hover:bg-blue-800 px-3 py-1.5 rounded-xl transition focus:outline-none">
                        <div class="w-7 h-7 bg-white text-blue-600 font-bold rounded-full flex items-center justify-center text-xs shadow-sm">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-100 hidden sm:inline">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Resident'); ?>
                        </span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-blue-200"></i>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="profileDropdownMenu" class="hidden absolute right-0 mt-3 w-48 bg-white rounded-2xl shadow-2xl border border-slate-200 text-slate-800 z-50 overflow-hidden transition-all duration-200">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100">
                            <p class="text-xs text-slate-500">Signed in as</p>
                            <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Resident'); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="index.php?page=profile" class="flex items-center px-4 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                <i class="fa-solid fa-id-card w-4 mr-2 text-slate-400"></i> My Profile
                            </a>
                            <a href="index.php?page=dashboard" class="flex items-center px-4 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                <i class="fa-solid fa-gauge w-4 mr-2 text-slate-400"></i> My Dashboard
                            </a>
                        </div>
                        <div class="border-t border-slate-100 py-1">
                            <a href="index.php?page=logout" class="flex items-center px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition">
                                <i class="fa-solid fa-right-from-bracket w-4 mr-2"></i> Sign Out
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Logged Out State -->
                <a href="index.php?page=login" class="bg-blue-800 hover:bg-blue-900 text-white text-xs px-3 py-2 rounded-lg font-semibold transition flex items-center">
                    <i class="fa-solid fa-right-to-bracket mr-1"></i> Log in
                </a>
                <a href="index.php?page=register" class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs px-3 py-2 rounded-lg font-semibold transition flex items-center">
                    <i class="fa-solid fa-user-plus mr-1"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- PREVIOUS NOTIFICATIONS MODAL -->
<div id="notifHistoryModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeNotifHistoryModal()">
    <div class="relative max-w-lg w-full bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200" onclick="event.stopPropagation()">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-clock-rotate-left mr-2 text-blue-400"></i> Notification History
            </span>
            <button onclick="closeNotifHistoryModal()" class="text-slate-400 hover:text-white text-lg font-bold px-2">
                &times;
            </button>
        </div>
        <div class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 p-2">
            <?php if (!empty($all_notifications)): ?>
                <?php foreach ($all_notifications as $notif): ?>
                    <div class="p-3.5 rounded-xl flex items-start space-x-3 <?php echo !empty($notif['is_read']) ? 'bg-white opacity-70' : 'bg-blue-50/40'; ?>">
                        <div class="w-8 h-8 rounded-full <?php echo !empty($notif['is_read']) ? 'bg-slate-100 text-slate-500' : 'bg-blue-100 text-blue-600'; ?> flex items-center justify-center text-xs shrink-0 mt-0.5">
                            <?php echo !empty($notif['is_read']) ? '✓' : '📌'; ?>
                        </div>
                        <div class="flex-grow">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?php echo !empty($notif['is_read']) ? 'bg-slate-100 text-slate-500' : 'bg-blue-100 text-blue-700'; ?>">
                                    <?php echo !empty($notif['is_read']) ? 'Read' : 'Unread'; ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <span class="text-[10px] text-slate-400 mt-1 block">
                                <?php echo date("M d, Y • h:i A", strtotime($notif['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-slate-400">
                    <p class="text-xs font-medium text-slate-500">No notification history found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function toggleNotifDropdown() {
        const notifMenu = document.getElementById('notifDropdownMenu');
        const profileMenu = document.getElementById('profileDropdownMenu');
        if (profileMenu) profileMenu.classList.add('hidden');
        notifMenu.classList.toggle('hidden');
    }

    function toggleProfileDropdown() {
        const profileMenu = document.getElementById('profileDropdownMenu');
        const notifMenu = document.getElementById('notifDropdownMenu');
        if (notifMenu) notifMenu.classList.add('hidden');
        profileMenu.classList.toggle('hidden');
    }

    function openNotifHistoryModal() {
        document.getElementById('notifDropdownMenu').classList.add('hidden');
        document.getElementById('notifHistoryModal').classList.remove('hidden');
    }

    function closeNotifHistoryModal() {
        document.getElementById('notifHistoryModal').classList.add('hidden');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const notifBtn = document.getElementById('notifDropdownBtn');
        const notifMenu = document.getElementById('notifDropdownMenu');
        const profileBtn = document.getElementById('profileDropdownBtn');
        const profileMenu = document.getElementById('profileDropdownMenu');

        if (notifBtn && notifMenu && !notifBtn.contains(event.target) && !notifMenu.contains(event.target)) {
            notifMenu.classList.add('hidden');
        }

        if (profileBtn && profileMenu && !profileBtn.contains(event.target) && !profileMenu.contains(event.target)) {
            profileMenu.classList.add('hidden');
        }
    });
</script>

<main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">