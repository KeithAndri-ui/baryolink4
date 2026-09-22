<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// ACCESS CONTROL: Ensure user is logged in AND has staff/admin role
// -------------------------------------------------------------
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    header("Location: index.php?page=login&msg=unauthorized");
    exit();
}

$msg = '';
$error = '';

// Edit announcement state container
$edit_announcement_data = null;

// -------------------------------------------------------------
// POST HANDLERS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {

    // 1. APPROVE / REJECT USER
    if (isset($_POST['action_user'])) {
        $target_user_id = intval($_POST['user_id']);
        $new_status     = $_POST['status']; // 'approved' or 'rejected'

        if (in_array($new_status, ['approved', 'rejected'])) {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $target_user_id]);
            $msg = "User status updated to " . ucfirst($new_status) . ".";
        }
    }

    // 2. CREATE ANNOUNCEMENT
    if (isset($_POST['create_announcement'])) {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $_SESSION['user_id']]);
            $msg = "Announcement posted successfully!";
        } else {
            $error = "Please provide both title and content for the announcement.";
        }
    }

    // 3. EDIT / UPDATE ANNOUNCEMENT
    if (isset($_POST['update_announcement'])) {
        $announcement_id = intval($_POST['announcement_id'] ?? 0);
        $title           = trim($_POST['title'] ?? '');
        $content         = trim($_POST['content'] ?? '');

        if (!empty($title) && !empty($content) && $announcement_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $content, $announcement_id]);
                $msg = "Announcement updated successfully!";
            } catch (PDOException $e) {
                // Fallback if updated_at column isn't added yet
                $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $announcement_id]);
                $msg = "Announcement updated successfully!";
            }
        } else {
            $error = "Please provide both title and content for updating.";
        }
    }

    // 4. UPDATE DOCUMENT REQUEST STATUS
    if (isset($_POST['update_request'])) {
        $request_id = intval($_POST['request_id']);
        $req_status = $_POST['req_status'];
        $remarks    = trim($_POST['remarks'] ?? '');

        // Try updating 'requests' first, fallback to 'document_requests'
        try {
            $stmt = $pdo->prepare("UPDATE requests SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$req_status, $remarks, $request_id]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare("UPDATE document_requests SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$req_status, $remarks, $request_id]);
        }
        $msg = "Document request updated!";
    }
}

// Check if staff clicked "Edit" on an announcement
if (isset($_GET['edit_announcement']) && isset($pdo)) {
    $edit_id = intval($_GET['edit_announcement']);
    $stmtEdit = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmtEdit->execute([$edit_id]);
    $edit_announcement_data = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// -------------------------------------------------------------
// FETCH DATA FOR DASHBOARD
// -------------------------------------------------------------
$pending_users = [];
$announcements = [];
$doc_requests  = [];

if (isset($pdo)) {
    // Fetch Pending Users
    $stmt = $pdo->query("SELECT id, full_name, email, phone, purok, valid_id, created_at FROM users WHERE LOWER(status) = 'pending' ORDER BY id DESC");
    $pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Announcements
    try {
        $stmt = $pdo->query("SELECT a.*, u.full_name as author FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.id DESC LIMIT 10");
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 10");
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch Document/Service Requests
    try {
        $stmt = $pdo->query("
            SELECT r.*, 
                   COALESCE(r.document_type, r.service_type, 'General Request') AS doc_title, 
                   COALESCE(u.full_name, 'Unknown Resident') AS resident_name, 
                   COALESCE(u.phone, 'N/A') AS resident_phone 
            FROM requests r 
            LEFT JOIN users u ON r.user_id = u.id 
            ORDER BY r.id DESC
        ");
        $doc_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("
            SELECT r.*, 
                   COALESCE(r.document_type, 'General Request') AS doc_title, 
                   COALESCE(u.full_name, 'Unknown Resident') AS resident_name, 
                   COALESCE(u.phone, 'N/A') AS resident_phone 
            FROM document_requests r 
            LEFT JOIN users u ON r.user_id = u.id 
            ORDER BY r.id DESC
        ");
        $doc_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="space-y-8">

    <!-- Header Section -->
    <div class="bg-blue-900 text-white p-6 rounded-2xl shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <span class="bg-blue-700 text-blue-200 text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wide">Staff Portal</span>
            <h1 class="text-2xl font-black mt-2">Barangay Administration Dashboard</h1>
            <p class="text-xs text-blue-200 mt-1">Manage resident verifications, official announcements, and document requests.</p>
        </div>
        <div class="text-xs bg-blue-800 px-4 py-2 rounded-xl text-blue-100">
            LoggedIn as: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Staff'); ?></strong>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($msg)): ?>
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-xs font-bold border border-emerald-200">
            <i class="fa-solid fa-circle-check mr-1"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-xl text-xs font-bold border border-red-200">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- SECTION 1 & 2: USER APPROVAL & DOCUMENT REQUESTS -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i class="fa-solid fa-user-check text-blue-600 mr-2"></i> Pending Resident Verification 
                        <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full font-bold"><?php echo count($pending_users); ?></span>
                    </h2>
                </div>

                <?php if (empty($pending_users)): ?>
                    <p class="text-xs text-slate-500 py-6 text-center">No pending user registrations found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[11px] font-bold text-slate-400 uppercase border-b border-slate-100">
                                    <th class="py-3 px-2">Resident</th>
                                    <th class="py-3 px-2">Purok</th>
                                    <th class="py-3 px-2">Valid ID</th>
                                    <th class="py-3 px-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <?php foreach ($pending_users as $u): ?>
                                    <tr>
                                        <td class="py-3 px-2 font-medium text-slate-800">
                                            <div><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($u['email']); ?> • <?php echo htmlspecialchars($u['phone']); ?></div>
                                        </td>
                                        <td class="py-3 px-2 text-slate-600"><?php echo htmlspecialchars($u['purok'] ?: 'N/A'); ?></td>
                                        <td class="py-3 px-2">
                                            <?php if ($u['valid_id']): ?>
                                                <a href="uploads/<?php echo htmlspecialchars($u['valid_id']); ?>" target="_blank" class="text-blue-600 underline text-[11px] font-bold flex items-center">
                                                    <i class="fa-solid fa-file-image mr-1"></i> View ID
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[10px]">No File</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-2 text-right space-x-1">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" name="action_user" value="1" class="bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold transition">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" name="action_user" value="1" class="bg-red-500 hover:bg-red-600 text-white px-2.5 py-1 rounded-lg text-[10px] font-bold transition">
                                                    <i class="fa-solid fa-xmark"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- DOCUMENT REQUEST MANAGEMENT -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
                    <i class="fa-solid fa-file-invoice text-blue-600 mr-2"></i> Manage Document Requests
                </h2>

                <?php if (empty($doc_requests)): ?>
                    <p class="text-xs text-slate-500 py-6 text-center">No document requests logged yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($doc_requests as $req): ?>
                            <div class="border border-slate-100 bg-slate-50/50 rounded-xl p-4 flex flex-col md:flex-row justify-between gap-4">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($req['doc_title']); ?></span>
                                        <span class="text-[10px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-bold"><?php echo htmlspecialchars($req['status'] ?? 'Pending'); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">Requested by: <strong><?php echo htmlspecialchars($req['resident_name']); ?></strong> (<?php echo htmlspecialchars($req['resident_phone']); ?>)</p>
                                    <p class="text-xs text-slate-500 mt-0.5">Purpose: <?php echo htmlspecialchars($req['purpose'] ?? 'Not specified'); ?></p>
                                </div>
                                <form method="POST" class="flex flex-col sm:flex-row gap-2 items-start md:items-center">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="text" name="remarks" placeholder="Remarks/Notes..." value="<?php echo htmlspecialchars($req['remarks'] ?? ''); ?>" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <select name="req_status" class="text-xs border border-slate-300 rounded-lg px-2.5 py-1.5 bg-white font-semibold">
                                        <option value="Pending" <?php echo strtolower($req['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing" <?php echo strtolower($req['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Ready for Pickup" <?php echo strtolower($req['status'] ?? '') === 'ready for pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                        <option value="Completed" <?php echo strtolower($req['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Rejected" <?php echo strtolower($req['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" name="update_request" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-lg font-bold transition">
                                        Update
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 3: ANNOUNCEMENTS MANAGEMENT WITH EDIT CAPABILITY -->
        <div class="space-y-6">
            <!-- Create or Edit Announcement Form -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-md font-bold text-slate-800 flex items-center">
                        <i class="fa-solid fa-bullhorn text-blue-600 mr-2"></i> 
                        <?php echo $edit_announcement_data ? 'Edit Announcement' : 'Post New Announcement'; ?>
                    </h2>
                    <?php if ($edit_announcement_data): ?>
                        <a href="index.php?page=staff_dashboard" class="text-[10px] text-slate-400 hover:text-slate-600 underline">Cancel Edit</a>
                    <?php endif; ?>
                </div>

                <form method="POST" class="space-y-3">
                    <?php if ($edit_announcement_data): ?>
                        <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement_data['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Title</label>
                        <input type="text" name="title" required placeholder="Announcement Title" 
                               value="<?php echo htmlspecialchars($edit_announcement_data['title'] ?? ''); ?>" 
                               class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Content</label>
                        <textarea name="content" rows="4" required placeholder="Write the announcement details..." 
                                  class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"><?php echo htmlspecialchars($edit_announcement_data['content'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($edit_announcement_data): ?>
                        <div class="flex gap-2">
                            <button type="submit" name="update_announcement" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs py-2.5 rounded-xl transition shadow">
                                Save Changes
                            </button>
                            <a href="index.php?page=staff_dashboard" class="w-1/3 text-center bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs py-2.5 rounded-xl transition">
                                Cancel
                            </a>
                        </div>
                    <?php else: ?>
                        <button type="submit" name="create_announcement" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2.5 rounded-xl transition shadow">
                            Publish Announcement
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Recent Announcements Feed -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Recent Posts</h3>
                <?php if (empty($announcements)): ?>
                    <p class="text-xs text-slate-500">No announcements posted yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($announcements as $a): ?>
                            <div class="border-b border-slate-100 pb-3 last:border-0">
                                <div class="flex justify-between items-start gap-2">
                                    <h4 class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($a['title']); ?></h4>
                                    <a href="index.php?page=staff_dashboard&edit_announcement=<?php echo $a['id']; ?>" class="bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-[10px] font-bold px-2 py-0.5 rounded transition shrink-0">
                                        ✏️ Edit
                                    </a>
                                </div>
                                <p class="text-[11px] text-slate-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($a['content']); ?></p>
                                <div class="flex items-center justify-between text-[9px] text-slate-400 mt-1.5">
                                    <span>By <?php echo htmlspecialchars($a['author'] ?? 'Staff'); ?> on <?php echo date('M d, Y', strtotime($a['created_at'])); ?></span>
                                    <?php if (!empty($a['updated_at']) && $a['updated_at'] !== '0000-00-00 00:00:00' && $a['updated_at'] !== $a['created_at']): ?>
                                        <span class="text-amber-600 font-semibold bg-amber-50 px-1.5 py-0.5 rounded">Edited</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>