<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    
    // Combine full name
    $full_name = trim(preg_replace('/\s+/', ' ', "$first_name $middle_name $last_name"));

    $email      = trim($_POST['email'] ?? '');
    $raw_phone  = trim($_POST['phone'] ?? '');
    $purok      = trim($_POST['purok'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $certified  = isset($_POST['certify_terms']);

    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
    if (str_starts_with($clean_phone, '0')) {
        $clean_phone = substr($clean_phone, 1);
    }
    if (str_starts_with($clean_phone, '63')) {
        $clean_phone = substr($clean_phone, 2);
    }
    
    $phone = !empty($clean_phone) ? '+63' . $clean_phone : '';

    $id_front_file = $_FILES['valid_id_front'] ?? null;
    $id_back_file  = $_FILES['valid_id_back'] ?? null;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields (First Name, Last Name, Email, Password).';
    } elseif (!empty($clean_phone) && (strlen($clean_phone) !== 10 || $clean_phone[0] !== '9')) {
        $error = 'Please enter a valid 10-digit Philippine mobile number starting with 9 (e.g. 9171234567).';
    } elseif (!$certified) {
        $error = 'You must certify that all details submitted are accurate and agree to the Barangay Data Protection Policy.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!$id_front_file || $id_front_file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload the Front side of your Government ID.';
    } elseif (!$id_back_file || $id_back_file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload the Back side of your Government ID.';
    } else {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext_front = strtolower(pathinfo($id_front_file['name'], PATHINFO_EXTENSION));
        $ext_back  = strtolower(pathinfo($id_back_file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext_front, $allowed_exts) || !in_array($ext_back, $allowed_exts)) {
            $error = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
        } elseif ($id_front_file['size'] > 5 * 1024 * 1024 || $id_back_file['size'] > 5 * 1024 * 1024) {
            $error = 'Uploaded file is too large. Maximum size is 5MB per image.';
        } else {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = 'An account with this email address already exists.';
                } else {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename_front = time() . '_front_' . uniqid() . '.' . $ext_front;
                    $target_path_front  = $upload_dir . $new_filename_front;

                    $new_filename_back = time() . '_back_' . uniqid() . '.' . $ext_back;
                    $target_path_back  = $upload_dir . $new_filename_back;

                    if (move_uploaded_file($id_front_file['tmp_name'], $target_path_front) && move_uploaded_file($id_back_file['tmp_name'], $target_path_back)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, full_name, email, phone, purok, valid_id_front, valid_id_back, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $inserted = $stmt->execute([$first_name, $middle_name, $last_name, $full_name, $email, $phone, $purok, $new_filename_front, $new_filename_back, $hashed_password]);
                        } catch (PDOException $e) {
                            // Fallback if individual name columns don't exist yet
                            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, purok, valid_id_front, valid_id_back, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $inserted = $stmt->execute([$full_name, $email, $phone, $purok, $new_filename_front, $new_filename_back, $hashed_password]);
                        }

                        if ($inserted) {
                            $user_id = $pdo->lastInsertId();
                            
                            // In register.php after successfully executing INSERT statement:
$_SESSION['user_id'] = $user_id;
$_SESSION['full_name'] = $full_name;
$_SESSION['email'] = $email;
$_SESSION['status'] = 'pending';

header("Location: index.php?page=dashboard");
exit();
                        } else {
                            $error = 'Registration failed. Please try again.';
                        }
                    } else {
                        $error = 'Failed to upload Government ID pictures. Please check folder permissions.';
                    }
                }
            } else {
                $error = 'Database connection error.';
            }
        }
    }
}
?>

<div class="max-w-xl mx-auto my-8 bg-white p-8 rounded-2xl shadow-xl border border-slate-200">
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Create an Account</h2>
        <p class="text-xs text-slate-500 mt-1">Barangay Tubod, Toledo City, Cebu</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 text-red-600 p-3 rounded-xl text-xs font-semibold mb-4 border border-red-200 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form id="registerForm" action="index.php?page=register" method="POST" enctype="multipart/form-data" onsubmit="return validateIdUploads(event)" class="space-y-4">
        
        <!-- SEPARATE FIRST, MIDDLE, AND LAST NAME INPUTS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">First Name *</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Juan">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Santos">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Last Name *</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Dela Cruz">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="juan@gmail.com">
            </div>
            
            <!-- PHILIPPINES AUTOMATIC PHONE NUMBER INPUT (+63) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Mobile Number</label>
                <div class="relative flex items-center">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3.5 pr-2 bg-slate-100 border-r border-slate-300 rounded-l-xl text-xs font-bold text-slate-600 select-none">
                        🇵🇭 +63
                    </span>
                    <input type="tel" name="phone" id="phone_input" 
                        value="<?php 
                            $val =$_POST['phone'] ?? '';
                            $val = preg_replace('/[^0-9]/', '',$val);
                            if (str_starts_with($val, '63')) $val = substr($val, 2);
                            if (str_starts_with($val, '0')) $val = substr($val, 1);
                            echo htmlspecialchars($val); 
                        ?>" 
                        maxlength="10"
                        oninput="formatPhPhone(this)"
                        class="w-full border border-slate-300 rounded-xl pl-20 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none tracking-wider font-mono" 
                        placeholder="9171234567">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Enter 10 digits starting with 9 (e.g., 9171234567)</p>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Purok / Zone</label>
            <select name="purok" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                <option value="">-- Select Purok --</option>
                <?php
                $puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7'];
                foreach ($puroks as $p) {$selected = ($_POST['purok'] ?? '') ===$p ? 'selected' : '';
                    echo "<option value=\"$p\" $selected>$p</option>";
                }
                ?>
            </select>
        </div>

        <!-- 2-COLUMN VALID ID UPLOAD SECTION WITH PREVIEW & EDIT SUPPORT -->
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Upload Government Valid ID *</label>
            <p class="text-[11px] text-slate-400 mb-2">Upload clear pictures of both the front and back of your valid ID (National ID, Driver's License, UMID, Passport).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                
                <!-- Front Side Upload Card -->
                <div class="relative border-2 border-dashed border-slate-300 rounded-xl p-3 text-center hover:border-blue-500 transition bg-slate-50/50 min-h-[140px] flex flex-col items-center justify-center overflow-hidden group">
                    <input type="file" name="valid_id_front" id="valid_id_front" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="handleFileSelect(this, 'front_container', 'front_preview', 'front_name', 'front_actions')">
                    
                    <!-- Default Initial State -->
                    <label id="front_container" for="valid_id_front" class="cursor-pointer flex flex-col items-center justify-center w-full h-full py-2">
                        <span class="text-2xl mb-1">🪪</span>
                        <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-0.5">ID (Front Side)</span>
                        <span class="text-xs font-semibold text-blue-600">Click to upload Front</span>
                    </label>

                    <!-- Preview Container (Hidden initially) -->
                    <div id="front_preview" class="hidden w-full h-full flex-col items-center justify-center space-y-1">
                        <div class="relative w-full h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center">
                            <img id="front_img_element" src="" class="hidden max-h-full max-w-full object-contain" alt="ID Front Preview">
                            <div id="front_pdf_element" class="hidden flex flex-col items-center text-slate-500 text-xs font-bold">
                                <span class="text-3xl text-red-500">📄</span>
                                <span>PDF Document</span>
                            </div>
                        </div>
                        <span id="front_name" class="text-[11px] font-semibold text-slate-700 truncate max-w-[180px] block"></span>
                    </div>

                    <!-- Change / Edit Action Buttons Overlay -->
                    <div id="front_actions" class="hidden absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <label for="valid_id_front" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1">
                            <i class="fa-solid fa-pen-to-square"></i> Change
                        </label>
                        <button type="button" onclick="removeFile('valid_id_front', 'front_container', 'front_preview', 'front_actions')" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    </div>
                </div>

                <!-- Back Side Upload Card -->
                <div class="relative border-2 border-dashed border-slate-300 rounded-xl p-3 text-center hover:border-blue-500 transition bg-slate-50/50 min-h-[140px] flex flex-col items-center justify-center overflow-hidden group">
                    <input type="file" name="valid_id_back" id="valid_id_back" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="handleFileSelect(this, 'back_container', 'back_preview', 'back_name', 'back_actions')">
                    
                    <!-- Default Initial State -->
                    <label id="back_container" for="valid_id_back" class="cursor-pointer flex flex-col items-center justify-center w-full h-full py-2">
                        <span class="text-2xl mb-1">🔄</span>
                        <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-0.5">ID (Back Side)</span>
                        <span class="text-xs font-semibold text-blue-600">Click to upload Back</span>
                    </label>

                    <!-- Preview Container (Hidden initially) -->
                    <div id="back_preview" class="hidden w-full h-full flex-col items-center justify-center space-y-1">
                        <div class="relative w-full h-24 rounded-lg overflow-hidden border border-slate-200 bg-slate-100 flex items-center justify-center">
                            <img id="back_img_element" src="" class="hidden max-h-full max-w-full object-contain" alt="ID Back Preview">
                            <div id="back_pdf_element" class="hidden flex flex-col items-center text-slate-500 text-xs font-bold">
                                <span class="text-3xl text-red-500">📄</span>
                                <span>PDF Document</span>
                            </div>
                        </div>
                        <span id="back_name" class="text-[11px] font-semibold text-slate-700 truncate max-w-[180px] block"></span>
                    </div>

                    <!-- Change / Edit Action Buttons Overlay -->
                    <div id="back_actions" class="hidden absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <label for="valid_id_back" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1">
                            <i class="fa-solid fa-pen-to-square"></i> Change
                        </label>
                        <button type="button" onclick="removeFile('valid_id_back', 'back_container', 'back_preview', 'back_actions')" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg shadow transition flex items-center gap-1">
                            <i class="fa-solid fa-trash"></i> Remove
                        </button>
                    </div>
                </div>

            </div>
            <p class="text-[10px] text-slate-400 mt-1.5 text-center">Accepted formats: JPG, PNG, PDF • Max file size: 5MB each</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Password *</label>
                <div class="relative">
                    <input type="password" id="reg_password" name="password" required class="w-full border border-slate-300 rounded-xl pl-4 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="••••••••">
                    <button type="button" onclick="togglePassword('reg_password', 'reg_icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i id="reg_icon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Confirm Password *</label>
                <div class="relative">
                    <input type="password" id="reg_confirm" name="confirm_password" required class="w-full border border-slate-300 rounded-xl pl-4 pr-10 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="••••••••">
                    <button type="button" onclick="togglePassword('reg_confirm', 'reg_confirm_icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i id="reg_confirm_icon" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex items-start space-x-3 pt-2">
            <input type="checkbox" name="certify_terms" id="certify_terms" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
            <label for="certify_terms" class="text-xs text-slate-600 cursor-pointer leading-relaxed">
                I certify that all details submitted are accurate under oath and comply with the <a href="#" class="text-blue-600 hover:underline">Barangay Data Protection Policy</a>.
            </label>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-lg transition mt-2">
            Register Account
        </button>

        <p class="text-xs text-center text-slate-500 mt-4">
            Already registered? <a href="index.php?page=login" class="text-blue-600 font-bold hover:underline">Log In</a>
        </p>
    </form>
</div>

<!-- MISSING ID UPLOAD WARNING POPUP MODAL -->
<div id="missingIdModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center shadow-2xl border border-slate-100 transform transition-all scale-100">
        <div class="w-14 h-14 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner">
            ⚠️
        </div>
        <h3 class="text-lg font-bold text-slate-800">Valid ID Upload Required</h3>
        <p id="missingIdModalText" class="text-xs text-slate-500 mt-2 leading-relaxed">
            Please attach a picture of both the front and back of your valid government ID before proceeding.
        </p>
        <button type="button" onclick="closeIdModal()" class="mt-5 w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 rounded-xl text-xs shadow transition">
            Got it, I'll upload it
        </button>
    </div>
</div>

<script>
// Format Phone input to 10 digits
function formatPhPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.startsWith('0')) value = value.substring(1);
    else if (value.startsWith('63')) value = value.substring(2);
    input.value = value.slice(0, 10);
}

// Handle image preview and editable state
function handleFileSelect(input, containerId, previewId, nameId, actionsId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const container = document.getElementById(containerId);
        const preview = document.getElementById(previewId);
        const fileNameSpan = document.getElementById(nameId);
        const actions = document.getElementById(actionsId);

        const imgEl = document.getElementById(input.id === 'valid_id_front' ? 'front_img_element' : 'back_img_element');
        const pdfEl = document.getElementById(input.id === 'valid_id_front' ? 'front_pdf_element' : 'back_pdf_element');

        fileNameSpan.innerText = file.name;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                imgEl.src = e.target.result;
                imgEl.classList.remove('hidden');
                pdfEl.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            imgEl.classList.add('hidden');
            pdfEl.classList.remove('hidden');
        }

        container.classList.add('hidden');
        preview.classList.remove('hidden');
        preview.classList.add('flex');
        actions.classList.remove('hidden');
        actions.classList.add('flex');
    }
}

// Remove or reset uploaded file so user can re-upload
function removeFile(inputId, containerId, previewId, actionsId) {
    const input = document.getElementById(inputId);
    input.value = ''; // clear input file

    document.getElementById(containerId).classList.remove('hidden');
    
    const preview = document.getElementById(previewId);
    preview.classList.add('hidden');
    preview.classList.remove('flex');

    const actions = document.getElementById(actionsId);
    actions.classList.add('hidden');
    actions.classList.remove('flex');
}

// Show validation alert modal if ID is missing
function validateIdUploads(event) {
    const frontInput = document.getElementById('valid_id_front');
    const backInput = document.getElementById('valid_id_back');

    let missing = [];
    if (!frontInput.files || frontInput.files.length === 0) {
        missing.push('Front side');
    }
    if (!backInput.files || backInput.files.length === 0) {
        missing.push('Back side');
    }

    if (missing.length > 0) {
        event.preventDefault(); // stop submission
        
        const modalText = document.getElementById('missingIdModalText');
        modalText.innerText = `Please upload the ${missing.join(' and ')} of your Government Valid ID to complete your registration.`;
        
        document.getElementById('missingIdModal').classList.remove('hidden');
        return false;
    }
    return true;
}

function closeIdModal() {
    document.getElementById('missingIdModal').classList.add('hidden');
}

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