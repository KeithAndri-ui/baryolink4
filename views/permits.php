<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if guest user tries to access directly
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login&msg=login_required");
    exit();
}

$user_id =$_SESSION['user_id'];
$success = '';$error   = '';

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_types =$_POST['document_type'] ?? [];
    $purpose        = trim($_POST['purpose'] ?? '');

    if (empty($document_types) || !is_array($document_types)) {$error = 'Please select at least one document type.';
    } elseif (empty($purpose)) {$error = 'Please state the purpose of your request.';
    } else {
        if ($pdo) {
            try {
                $pdo->beginTransaction();

                $stmt =$pdo->prepare("INSERT INTO document_requests (user_id, document_type, purpose, status) VALUES (?, ?, ?, 'Pending')");
                $stmtNotif =$pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");

                foreach ($document_types as$doc_type) {
                    $doc_type = trim($doc_type);
                    if (!empty($doc_type)) {
                        $stmt->execute([$user_id, $doc_type,$purpose]);

                        // Optional: Create notification entry
                        try {
                            $stmtNotif->execute([
                                $user_id,$doc_type . ' Submitted', 
                                'Your request is currently pending review by the Barangay official.'
                            ]);
                        } catch (PDOException $ex) {
                            // Ignore if notifications table doesn't exist
                        }
                    }
                }

                $pdo->commit();
                $count = count($document_types);
                $success =$count === 1 
                    ? 'Your document request has been submitted successfully!' 
                    : "Successfully submitted $count document requests!";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {$pdo->rollBack();
                }
                $error = 'Database error: ' .$e->getMessage();
            }
        } else {
            $error = 'Database connection failure.';
        }
    }
}

// 2. Fetch User's Document Requests History
$my_requests = [];
if ($pdo) {
    try {
        $stmtHistory =$pdo->prepare("SELECT id, document_type, purpose, status, remarks, created_at FROM document_requests WHERE user_id = ? ORDER BY id DESC");
        $stmtHistory->execute([$user_id]);
        $my_requests =$stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table document_requests might not exist yet
    }
}

$available_documents = [
    "Barangay Clearance",
    "Certificate of Indigency",
    "Business Permit Clearance",
    "Certificate of Residency"
];
?>

<div class="max-w-6xl mx-auto my-8 px-4 space-y-8">

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-700 to-indigo-800 rounded-3xl p-6 md:p-8 text-white shadow-xl">
        <h1 class="text-2xl md:text-3xl font-extrabold">Barangay Document Request</h1>
        <p class="text-blue-100 text-xs md:text-sm mt-1">Apply for official clearances, certificates, and permits online.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Document Request Form (Takes 1 Column) -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-800 mb-4">Submit Document Request</h2>

                <?php if (!empty($success)): ?>
                    <div class="bg-emerald-50 text-emerald-700 p-3.5 rounded-xl text-xs font-semibold mb-4 border border-emerald-200 flex items-center">
                        <span class="mr-2 text-base">✅</span> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 text-red-700 p-3.5 rounded-xl text-xs font-semibold mb-4 border border-red-200 flex items-center">
                        <span class="mr-2 text-base">⚠️</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="index.php?page=permits" method="POST" class="space-y-4">
                    
                    <!-- CUSTOM CIRCLE MULTI-SELECT DROPDOWN -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Document Type *</label>
                        
                        <!-- Toggle Button -->
                        <button type="button" id="dropdownToggle" onclick="toggleDocDropdown()" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-slate-700 text-xs flex justify-between items-center focus:ring-2 focus:ring-blue-500 focus:outline-none shadow-sm">
                            <span id="selectedDocsLabel" class="truncate">-- Select Documents --</span>
                            <i class="fa-solid fa-chevron-down text-slate-400 text-xs ml-2"></i>
                        </button>

                        <!-- Options List -->
                        <div id="dropdownMenu" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-20 p-2 space-y-1">
                            <?php foreach ($available_documents as$doc): ?>
                                <label class="flex items-center space-x-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition select-none">
                                    <input type="checkbox" name="document_type[]" value="<?php echo htmlspecialchars($doc); ?>" class="hidden peer doc-checkbox" onchange="updateDropdownText()">
                                    
                                    <!-- Custom Circle Indicator -->
                                    <div class="w-4 h-4 rounded-full border-2 border-slate-300 peer-checked:border-blue-600 peer-checked:bg-blue-600 flex items-center justify-center transition shrink-0">
                                        <i class="fa-solid fa-check text-[9px] text-white opacity-0 peer-checked:opacity-100 transition"></i>
                                    </div>
                                    
                                    <span class="text-xs text-slate-700 font-medium peer-checked:font-bold peer-checked:text-blue-900"><?php echo htmlspecialchars($doc); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Purpose *</label>
                        <textarea name="purpose" required class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none" rows="4" placeholder="State reason for application (e.g., Employment, Scholarship, Local Business)..."></textarea>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition text-xs">
                        Submit Request(s)
                    </button>
                </form>
            </div>
        </div>

        <!-- History of Submitted Requests (Takes 2 Columns) -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
                    <span class="mr-2">📑</span> My Request History
                </h3>

                <?php if (!empty($my_requests)): ?>
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
                                <?php foreach ($my_requests as$req): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-2 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($req['document_type']); ?>
                                            <?php if (!empty($req['remarks'])): ?>
                                                <div class="text-[10px] text-slate-400 font-normal italic mt-0.5">Note: <?php echo htmlspecialchars($req['remarks']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-500 max-w-[200px] truncate">
                                            <?php echo htmlspecialchars($req['purpose']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-400">
                                            <?php echo date("M d, Y • h:i A", strtotime($req['created_at'])); ?>
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
                    <div class="text-center py-12 text-slate-400">
                        <p class="text-3xl mb-2">📋</p>
                        <p class="text-xs font-semibold text-slate-600">No requests submitted yet.</p>
                        <p class="text-xs text-slate-400 mt-1">Fill out the form to request official barangay documents.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleDocDropdown() {
        const menu = document.getElementById('dropdownMenu');
        menu.classList.toggle('hidden');
    }

    function updateDropdownText() {
        const checkboxes = document.querySelectorAll('.doc-checkbox:checked');
        const label = document.getElementById('selectedDocsLabel');

        if (checkboxes.length === 0) {
            label.textContent = '-- Select Documents --';
            label.classList.remove('text-slate-900', 'font-bold');
            label.classList.add('text-slate-700');
        } else if (checkboxes.length === 1) {
            label.textContent = checkboxes[0].value;
            label.classList.add('text-slate-900', 'font-bold');
        } else {
            label.textContent = `${checkboxes.length} Documents Selected`;
            label.classList.add('text-slate-900', 'font-bold');
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const toggle = document.getElementById('dropdownToggle');
        const menu = document.getElementById('dropdownMenu');
        if (toggle && menu && !toggle.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });
</script>