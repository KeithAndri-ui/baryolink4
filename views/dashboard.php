<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login&msg=login_required");
    exit();
}
// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Resident';

// Initialize variables
$total_requests    = 0;
$approved_count    = 0;
$pending_count     = 0;
$complaints_count  = 0;

$recent_requests   = [];
$recent_complaints = [];
$notifications     = [];
$announcements     = [];

if ($pdo) {
    // 1. Fetch Request Stats & Recent Requests Data
    try {
        // Stats
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE user_id = ?");
        $stmtTotal->execute([$user_id]);
        $total_requests = $stmtTotal->fetchColumn() ?: 0;

        $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE user_id = ? AND LOWER(status) IN ('approved', 'completed', 'ready for pickup')");
        $stmtApproved->execute([$user_id]);
        $approved_count = $stmtApproved->fetchColumn() ?: 0;

        $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE user_id = ? AND LOWER(status) = 'pending'");
        $stmtPending->execute([$user_id]);
        $pending_count = $stmtPending->fetchColumn() ?: 0;

        // Recent Document Requests (Latest 5 items)
        $stmtRecent = $pdo->prepare("SELECT id, document_type, purpose, status, created_at FROM document_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
        $stmtRecent->execute([$user_id]);
        $recent_requests = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Fallback if table doesn't exist
    }

    // 2. Fetch Complaints / Blotter Reports Data
    try {
        // Total Complaints Count
        $stmtCompCount = $pdo->prepare("SELECT COUNT(*) FROM blotter_reports WHERE user_id = ?");
        $stmtCompCount->execute([$user_id]);
        $complaints_count = $stmtCompCount->fetchColumn() ?: 0;

        // Recent Complaints (Latest 5 items)
        $stmtBlotter = $pdo->prepare("SELECT id, incident_type, details, status, created_at FROM blotter_reports WHERE user_id = ? ORDER BY id DESC LIMIT 5");
        $stmtBlotter->execute([$user_id]);
        $recent_complaints = $stmtBlotter->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Fallback if blotter_reports table doesn't exist
    }

    // 3. Fetch User Notifications Count
    try {
        $stmtNotif = $pdo->prepare("SELECT title, message, created_at, status FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
        $stmtNotif->execute([$user_id]);
        $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        foreach ($recent_requests as $req) {
            $notifications[] = [
                'title' => $req['document_type'] . ' Request',
                'message' => "Your request status is currently: " . ucfirst($req['status']),
                'created_at' => $req['created_at'],
                'status' => $req['status']
            ];
        }
    }

    // 4. Fetch Barangay Announcements
    try {
        $stmtAnnounce = $pdo->query("SELECT id, title, content, created_at, updated_at FROM announcements ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 6");
        $announcements = $stmtAnnounce->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        try {
            $stmtAnnounce = $pdo->query("SELECT id, title, content, created_at FROM announcements ORDER BY id DESC LIMIT 6");
            $announcements = $stmtAnnounce->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            $announcements = [
                [
                    'title' => 'Barangay Clean-Up Drive',
                    'content' => 'Join us this coming Saturday at 6:00 AM for our monthly community clean-up drive.',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => null
                ],
                [
                    'title' => 'Free Health Medical Mission',
                    'content' => 'Free medical consultation and basic medicines will be available at the Barangay Hall on Friday.',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'updated_at' => null
                ]
            ];
        }
    }
}
// Fetch user status directly from database to avoid stale session data
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['status'] !== 'approved') {
        // Render Pending Approval View
        include 'views/pending_approval.php';
        exit();
    }
}
?>

<div class="max-w-6xl mx-auto my-8 px-4 space-y-8">
    
    <!-- Welcome Header -->
    <div class="bg-gradient-to-r from-blue-700 to-indigo-800 rounded-3xl p-6 md:p-8 text-white shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <span class="bg-white/20 text-white text-xs px-3 py-1 rounded-full font-semibold uppercase tracking-wider">Resident Dashboard</span>
            <h1 class="text-2xl md:text-3xl font-extrabold mt-2">Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p class="text-blue-100 text-xs md:text-sm mt-1">Manage your document requests and track filed complaints in real time.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="index.php?page=permits" class="bg-white text-blue-700 hover:bg-blue-50 font-bold px-4 py-2.5 rounded-xl text-xs shadow transition">
                + Request Document
            </a>
            <a href="index.php?page=blotter" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs shadow transition">
                🚨 File Complaint
            </a>
        </div>
    </div>

    <!-- Live Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Total Requests -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold shrink-0">
                📄
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Document Requests</p>
                <h3 class="text-2xl font-black text-slate-800"><?php echo number_format($total_requests); ?></h3>
            </div>
        </div>

        <!-- Approved -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold shrink-0">
                ✅
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Approved Permits</p>
                <h3 class="text-2xl font-black text-emerald-600"><?php echo number_format($approved_count); ?></h3>
            </div>
        </div>

        <!-- Filed Complaints -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl font-bold shrink-0">
                🚨
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Filed Complaints</p>
                <h3 class="text-2xl font-black text-rose-600"><?php echo number_format($complaints_count); ?></h3>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold shrink-0">
                🔔
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Notifications</p>
                <h3 class="text-2xl font-black text-indigo-600"><?php echo count($notifications); ?></h3>
            </div>
        </div>

    </div>

    <!-- Community Announcements Section -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-slate-800 flex items-center">
                <span class="mr-2">📢</span> Community Announcements & Advisory
            </h3>
            <span class="text-xs font-semibold text-blue-600">Barangay Updates</span>
        </div>

        <?php if (!empty($announcements)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($announcements as $item): ?>
                    <?php 
                        $is_edited = !empty($item['updated_at']) && $item['updated_at'] !== '0000-00-00 00:00:00' && $item['updated_at'] !== $item['created_at'];
                    ?>
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-0.5 rounded-md font-bold uppercase">Notice</span>
                                    <?php if ($is_edited): ?>
                                        <span class="bg-amber-100 text-amber-800 text-[9px] px-1.5 py-0.5 rounded font-extrabold uppercase tracking-wide">
                                            ✏️ Updated
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] text-slate-400">
                                    <?php 
                                        if ($is_edited) {
                                            echo 'Updated ' . date("M d, Y", strtotime($item['updated_at']));
                                        } else {
                                            echo date("M d, Y", strtotime($item['created_at']));
                                        }
                                    ?>
                                </span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($item['title']); ?></h4>
                            <p class="text-xs text-slate-600 leading-relaxed"><?php echo htmlspecialchars($item['content']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-xs text-slate-400 italic">No active announcements at the moment.</p>
        <?php endif; ?>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Center/Left Column: Tables (Takes 2 Columns) -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- 1. Recent Document Requests Section -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-800 flex items-center">
                        <span class="mr-2">📑</span> Recent Document Requests
                    </h3>
                    <a href="index.php?page=permits" class="text-xs font-bold text-blue-600 hover:underline">
                        View All / Submit New &rarr;
                    </a>
                </div>

                <?php if (!empty($recent_requests)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-2">Document</th>
                                    <th class="py-3 px-2">Purpose</th>
                                    <th class="py-3 px-2">Date Submitted</th>
                                    <th class="py-3 px-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                                <?php foreach ($recent_requests as $req): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-2 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($req['document_type']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-500 max-w-[180px] truncate">
                                            <?php echo htmlspecialchars($req['purpose'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-400">
                                            <?php echo date("M d, Y", strtotime($req['created_at'])); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-right">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase inline-block
                                                <?php 
                                                    $st = strtolower($req['status']);
                                                    if (in_array($st, ['approved', 'completed', 'ready for pickup'])) echo 'bg-emerald-100 text-emerald-700';
                                                    elseif ($st === 'pending') echo 'bg-amber-100 text-amber-700';
                                                    elseif ($st === 'processing') echo 'bg-blue-100 text-blue-700';
                                                    elseif ($st === 'rejected') echo 'bg-red-100 text-red-700';
                                                    else echo 'bg-slate-100 text-slate-600';
                                                ?>">
                                                <?php echo htmlspecialchars($req['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-400">
                        <p class="text-2xl mb-1">📋</p>
                        <p class="text-xs font-semibold text-slate-600">No document requests found.</p>
                        <a href="index.php?page=permits" class="inline-block mt-2 bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow hover:bg-blue-700 transition">
                            Create First Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. Recent Submitted Complaints / Blotter Reports Section -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-800 flex items-center">
                        <span class="mr-2">🚨</span> Recent Incident Reports & Complaints
                    </h3>
                    <a href="index.php?page=blotter" class="text-xs font-bold text-red-600 hover:underline">
                        View All / Submit New &rarr;
                    </a>
                </div>

                <?php if (!empty($recent_complaints)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-2">Incident Type</th>
                                    <th class="py-3 px-2">Details</th>
                                    <th class="py-3 px-2">Date Reported</th>
                                    <th class="py-3 px-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                                <?php foreach ($recent_complaints as $complaint): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-2 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($complaint['incident_type']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-500 max-w-[200px] truncate">
                                            <?php echo htmlspecialchars($complaint['details']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-400">
                                            <?php echo date("M d, Y", strtotime($complaint['created_at'])); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-right">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase inline-block
                                                <?php 
                                                    $cst = strtolower($complaint['status']);
                                                    if ($cst === 'resolved') echo 'bg-emerald-100 text-emerald-700';
                                                    elseif ($cst === 'under investigation') echo 'bg-blue-100 text-blue-700';
                                                    elseif ($cst === 'pending') echo 'bg-amber-100 text-amber-700';
                                                    else echo 'bg-slate-100 text-slate-600';
                                                ?>">
                                                <?php echo htmlspecialchars($complaint['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-400">
                        <p class="text-2xl mb-1">🛡️</p>
                        <p class="text-xs font-semibold text-slate-600">No incident reports or complaints submitted.</p>
                        <a href="index.php?page=blotter" class="inline-block mt-2 bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow hover:bg-red-700 transition">
                            File a Complaint
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right Sidebar: Quick Actions -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="text-base font-bold text-slate-800 mb-4 flex items-center">
                    <span class="mr-2">⚡</span> Quick Actions
                </h3>
                <div class="space-y-3">
                    <a href="index.php?page=permits" class="block w-full p-3.5 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 border border-slate-200 rounded-xl transition text-xs font-bold text-slate-700 hover:text-blue-700 flex items-center justify-between">
                        <span>📋 Request Barangay Clearance / Permit</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>
                    <a href="index.php?page=blotter" class="block w-full p-3.5 bg-slate-50 hover:bg-red-50 hover:border-red-200 border border-slate-200 rounded-xl transition text-xs font-bold text-slate-700 hover:text-red-700 flex items-center justify-between">
                        <span>🚨 Submit Incident / Complaint Report</span>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>