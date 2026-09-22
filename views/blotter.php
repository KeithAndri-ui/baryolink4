<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if guest user tries to access directly
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login&msg=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_type_select = trim($_POST['incident_type_select'] ?? '');
    $custom_type          = trim($_POST['custom_incident_type'] ?? '');
    $details              = trim($_POST['details'] ?? '');

    // Determine final incident type (If "Others", take custom value)
    $final_incident_type = ($incident_type_select === 'Others') ? $custom_type : $incident_type_select;

    // File Upload Processing
    $image_filename = null;
    $upload_ok = true;

    if (isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $file_name    = $_FILES['evidence_image']['name'];
        $file_size    = $_FILES['evidence_image']['size'];
        $file_tmp     = $_FILES['evidence_image']['tmp_name'];
        $ext          = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts)) {
            $error = 'Invalid image format. Only JPG, PNG, and WEBP files are allowed.';
            $upload_ok = false;
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB limit
            $error = 'Attachment size exceeds maximum limit of 5MB.';
            $upload_ok = false;
        } else {
            $upload_dir = 'uploads/blotter/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_filename = 'incident_' . time() . '_' . uniqid() . '.' . $ext;
            $target_filepath = $upload_dir . $image_filename;

            if (!move_uploaded_file($file_tmp, $target_filepath)) {
                $error = 'Failed to save evidence photo. Please try again.';
                $upload_ok = false;
            }
        }
    }

    if ($upload_ok) {
        if (empty($final_incident_type) || empty($details)) {
            $error = 'Please select or specify the complaint type and provide incident details.';
        } else {
            if ($pdo) {
                try {
                    // Try inserting report with image column
                    try {
                        $stmt = $pdo->prepare("INSERT INTO blotter_reports (user_id, incident_type, details, image, status) VALUES (?, ?, ?, ?, 'Pending')");
                        $executed = $stmt->execute([$user_id, $final_incident_type, $details, $image_filename]);
                    } catch (PDOException $e) {
                        // Fallback if image column doesn't exist yet
                        $stmt = $pdo->prepare("INSERT INTO blotter_reports (user_id, incident_type, details, status) VALUES (?, ?, ?, 'Pending')");
                        $executed = $stmt->execute([$user_id, $final_incident_type, $details]);
                    }

                    if ($executed) {
                        $success = 'Your complaint/report has been submitted successfully and routed to BPAT.';

                        // Optional: Insert notification record
                        try {
                            $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                            $stmtNotif->execute([
                                $user_id, 
                                'Blotter Report Filed', 
                                'Your incident report regarding "' . $final_incident_type . '" is now pending review.'
                            ]);
                        } catch (PDOException $ex) {
                            // Ignore if notifications table doesn't exist
                        }
                    } else {
                        $error = 'Failed to submit complaint. Please try again.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            } else {
                $error = 'Database connection failure.';
            }
        }
    }
}

// 2. Fetch Resident's Filed Complaints
$my_reports = [];
if ($pdo) {
    try {
        $stmtHistory = $pdo->prepare("SELECT id, incident_type, details, image, status, created_at FROM blotter_reports WHERE user_id = ? ORDER BY id DESC");
        $stmtHistory->execute([$user_id]);
        $my_reports = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback without image column if missing
        try {
            $stmtHistory = $pdo->prepare("SELECT id, incident_type, details, status, created_at FROM blotter_reports WHERE user_id = ? ORDER BY id DESC");
            $stmtHistory->execute([$user_id]);
            $my_reports = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {}
    }
}
?>

<div class="max-w-6xl mx-auto my-8 px-4 space-y-8">

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-red-700 to-rose-800 rounded-3xl p-6 md:p-8 text-white shadow-xl">
        <h1 class="text-2xl md:text-3xl font-extrabold">Barangay Complaint Submission</h1>
        <p class="text-red-100 text-xs md:text-sm mt-1">File formal complaints directly to the Barangay Peacekeeping Action Team (BPAT).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Complaint Submission Form -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-xl font-bold text-slate-800 mb-1">Submit Complaints</h2>
                <p class="text-slate-500 text-xs mb-4">Report an incident confidentially to local authorities.</p>

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

                <form action="index.php?page=blotter" method="POST" enctype="multipart/form-data" class="space-y-4">
                    
                    <!-- Complaint Type Dropdown -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Complaint Type *</label>
                        <select name="incident_type_select" id="incidentTypeSelect" required onchange="toggleCustomInput(this.value)" class="w-full border border-slate-300 rounded-xl p-2.5 bg-white text-slate-800 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none">
                            <option value="">-- Select Type --</option>
                            <option value="Noise Disturbance">Noise Disturbance</option>
                            <option value="Property Dispute">Property Dispute</option>
                            <option value="Public Nuisance">Public Nuisance</option>
                            <option value="Physical / Verbal Altercation">Physical / Verbal Altercation</option>
                            <option value="Boundary / Land Conflict">Boundary / Land Conflict</option>
                            <option value="Theft / Vandalism">Theft / Vandalism</option>
                            <option value="Others">Others (Specify below)</option>
                        </select>
                    </div>

                    <!-- Custom Input (Hidden by default) -->
                    <div id="customTypeContainer" class="hidden">
                        <label class="block text-xs font-bold text-red-700 uppercase mb-1">Specify Other Complaint Type *</label>
                        <input type="text" name="custom_incident_type" id="customTypeInput" class="w-full border border-red-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none bg-red-50/30" placeholder="e.g., Unattended Pets, Illegal Parking...">
                    </div>

                    <!-- Details Textarea -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Details / Description *</label>
                        <textarea name="details" required class="w-full border border-slate-300 rounded-xl p-2.5 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none" rows="4" placeholder="Provide complete incident details (Date, Time, Location, Involved Persons)..."></textarea>
                    </div>

                    <!-- Photo Evidence Upload Field -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Incident Photo / Evidence (Optional)</label>
                        <div class="mt-1 flex justify-center px-4 pt-4 pb-4 border-2 border-slate-300 border-dashed rounded-xl hover:border-red-400 transition bg-slate-50/50">
                            <div class="space-y-1 text-center">
                                <i class="fa-solid fa-camera text-slate-400 text-2xl mb-1"></i>
                                <div class="flex text-xs text-slate-600 justify-center">
                                    <label for="evidence_image" class="relative cursor-pointer bg-white rounded-md font-bold text-red-600 hover:text-red-500 focus-within:outline-none px-2 py-0.5 shadow-sm border border-slate-200">
                                        <span>Upload photo</span>
                                        <input id="evidence_image" name="evidence_image" type="file" accept="image/png, image/jpeg, image/webp" class="sr-only" onchange="previewSelectedImage(event)">
                                    </label>
                                    <p class="pl-1 self-center">or drag and drop</p>
                                </div>
                                <p class="text-[10px] text-slate-400">PNG, JPG, WEBP up to 5MB</p>
                            </div>
                        </div>

                        <!-- Upload Preview Box -->
                        <div id="imagePreviewContainer" class="hidden mt-3 relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 max-h-40 flex justify-center items-center">
                            <img id="imagePreview" src="#" alt="Incident Evidence Preview" class="object-cover max-h-40 w-full">
                            <button type="button" onclick="removeSelectedImage()" class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1.5 shadow-md hover:bg-red-700 transition">
                                <i class="fa-solid fa-xmark text-xs w-3 h-3 flex items-center justify-center"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl shadow-lg transition text-xs">
                        Submit Complaint
                    </button>
                </form>
            </div>
        </div>

        <!-- History of Filed Complaints -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center">
                    <span class="mr-2">🚨</span> My Filed Incident Reports
                </h3>

                <?php if (!empty($my_reports)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-2">Incident Type</th>
                                    <th class="py-3 px-2">Details</th>
                                    <th class="py-3 px-2 text-center">Photo Evidence</th>
                                    <th class="py-3 px-2">Date Reported</th>
                                    <th class="py-3 px-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                                <?php foreach ($my_reports as $report): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3.5 px-2 font-bold text-slate-800">
                                            <?php echo htmlspecialchars($report['incident_type']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-500 max-w-[200px] truncate">
                                            <?php echo htmlspecialchars($report['details']); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-center">
                                            <?php if (!empty($report['image'])): ?>
                                                <button onclick="openImageModal('uploads/blotter/<?php echo htmlspecialchars($report['image']); ?>')" class="inline-flex items-center space-x-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-2.5 py-1 rounded-lg text-[11px] font-bold transition">
                                                    <i class="fa-solid fa-image"></i>
                                                    <span>View</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[11px] italic">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-slate-400">
                                            <?php echo date("M d, Y • h:i A", strtotime($report['created_at'])); ?>
                                        </td>
                                        <td class="py-3.5 px-2 text-right">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase inline-block
                                                <?php 
                                                    if ($report['status'] === 'Resolved') echo 'bg-emerald-100 text-emerald-700';
                                                    elseif ($report['status'] === 'Under Investigation') echo 'bg-blue-100 text-blue-700';
                                                    elseif ($report['status'] === 'Pending') echo 'bg-amber-100 text-amber-700';
                                                    else echo 'bg-slate-100 text-slate-600';
                                                ?>">
                                                <?php echo htmlspecialchars($report['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 text-slate-400">
                        <p class="text-3xl mb-2">🛡️</p>
                        <p class="text-xs font-semibold text-slate-600">No incident reports filed.</p>
                        <p class="text-xs text-slate-400 mt-1">If you have peace and order concerns, submit a report using the form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Evidence Preview Modal -->
<div id="imageModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeImageModal()">
    <div class="relative max-w-3xl w-full bg-white rounded-2xl overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
        <div class="p-3 bg-slate-900 text-white flex justify-between items-center">
            <span class="text-xs font-bold uppercase tracking-wider flex items-center">
                <i class="fa-solid fa-camera mr-2 text-red-500"></i> Incident Photo Attachment
            </span>
            <button onclick="closeImageModal()" class="text-slate-400 hover:text-white text-lg font-bold px-2">
                &times;
            </button>
        </div>
        <div class="p-2 bg-slate-950 flex justify-center items-center">
            <img id="modalImg" src="" alt="Incident Photo Evidence" class="max-h-[80vh] w-auto object-contain rounded-lg">
        </div>
    </div>
</div>

<!-- JavaScript Controls -->
<script>
function toggleCustomInput(value) {
    const customContainer = document.getElementById('customTypeContainer');
    const customInput = document.getElementById('customTypeInput');

    if (value === 'Others') {
        customContainer.classList.remove('hidden');
        customInput.setAttribute('required', 'required');
        customInput.focus();
    } else {
        customContainer.classList.add('hidden');
        customInput.removeAttribute('required');
        customInput.value = '';
    }
}

function previewSelectedImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewContainer').classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    }
}

function removeSelectedImage() {
    const fileInput = document.getElementById('evidence_image');
    fileInput.value = '';
    document.getElementById('imagePreviewContainer').classList.add('hidden');
    document.getElementById('imagePreview').src = '#';
}

function openImageModal(imgSrc) {
    document.getElementById('modalImg').src = imgSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.getElementById('modalImg').src = '';
}
</script>