<?php
session_start();

// 1. Authentication Check
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Anti-back/Cache control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Database Connection
$conn = new mysqli("localhost", "root", "", "pos");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ==========================================
// BACKEND ACTION HANDLERS
// ==========================================
$department_query = $conn->query("SELECT department FROM job_openings WHERE status = 'Active'");
// ACTION: REGISTER HR ACCOUNT
if (isset($_POST['action']) && $_POST['action'] == 'register_hr') {
    $hr_check = $conn->query("SELECT COUNT(*) as total FROM hr_accounts");
    if ($hr_check->fetch_assoc()['total'] >= 1) {
        echo "<script>alert('An HR Account is already registered.'); window.location.href='admin_applicants.php';</script>";
        exit;
    }
    $hashed_pass = password_hash($_POST['hr_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO hr_accounts (gmail, password, role) VALUES (?, ?, ?)");
    $role = 'hr';
    $stmt->bind_param("sss", $_POST['hr_gmail'], $hashed_pass, $role);
    $stmt->execute();
    echo "<script>alert('HR Account registered successfully!'); window.location.href='admin_applicants.php';</script>";
    exit;
}

// ACTION: APPROVE
// ACTION: APPROVE
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // I-update ang status
    $update = $conn->query("UPDATE applicants SET status = 'Approved' WHERE id = $id");
    
    // Siguraduhing walang ibang output bago ang JSON
    header('Content-Type: application/json');
    
    if ($update) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit;
}
// ACTION: CONFIRM HIRE & REGISTER EMPLOYEE
if (isset($_POST['action']) && $_POST['action'] == 'confirm_hire') {
    $app_id = intval($_POST['applicant_id']);
    
    // 1. Check for duplicates
    // 1. Check for duplicates
$check = $conn->prepare("SELECT id FROM employees WHERE employee_gmail = ? OR email = ? OR phone = ? OR gsis_id = ? OR philhealth_id = ? OR pagibig_id = ? OR sss_id = ?");

// Siguraduhing 7 ang 's' at 7 ang variables
$check->bind_param("sssssss", 
    $_POST['employee_gmail'], 
    $_POST['email'], 
    $_POST['phone'], 
    $_POST['gsis_id'], 
    $_POST['philhealth_id'], 
    $_POST['pagibig_id'], 
    $_POST['sss_id']
);
$check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Duplicate credentials found.']);
        exit;
    }

    // 2. Insert into employees
    $insert_sql = "INSERT INTO employees (full_name, phone, email, employee_gmail, address, gsis_id, sss_id, philhealth_id, pagibig_id, department, employee_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $hashed_password = password_hash($_POST['employee_password'], PASSWORD_BCRYPT);
    $stmt->bind_param("sssssssssss", 
        $_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['employee_gmail'], 
        $_POST['address'], $_POST['gsis_id'], $_POST['sss_id'], $_POST['philhealth_id'], 
        $_POST['pagibig_id'], $_POST['department'], $hashed_password
    );

    if ($stmt->execute()) {
        // 3. IMPORTANT: Update status to 'Hired' so it disappears from the pending list
        $conn->query("UPDATE applicants SET status = 'Hired' WHERE id = $app_id"); 
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Employee added successfully.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// Data Fetching: Filter out 'Hired' applicants
$admin_pipeline = $conn->query("
    SELECT * FROM applicants 
    WHERE status IN ('Final Interview Set', 'Approved') 
    AND status != 'Hired' 
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Control Board - Administration</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
   
</head>
<body class="bg-zinc-100 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">
        
 
     
     <?php include 'sidebar.php'; ?>


        <div class="flex-1 h-screen overflow-y-auto p-8 bg-zinc-100 min-w-0">
            <div class="max-w-6xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-3xl font-black text-zinc-800 tracking-tight">Final Decision Terminal</h1>
                    <p class="text-sm text-zinc-500">Review applicants forwarded by the HR Screening Desk. Complete corporate onboarding details upon hiring.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                       
                        <tbody class="text-sm text-zinc-700 divide-y divide-zinc-200">
                            <?php if($admin_pipeline->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-zinc-400 font-medium">No candidates are currently scheduled for executive decision review.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php while($row = $admin_pipeline->fetch_assoc()): ?>
                                <tr class="hover:bg-zinc-50/50 transition-colors" data-id="<?= $row['id'] ?>">
                                    <td class="p-4">
                                        <div class="font-bold text-zinc-900"><?= htmlspecialchars($row['full_name']) ?></div>
                                        <span class="text-[10px] uppercase font-bold tracking-tight px-1.5 py-0.5 bg-indigo-100 text-indigo-800 rounded mt-1 inline-block">Board Review Status</span>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-medium text-zinc-800"><?= htmlspecialchars($row['email']) ?></div>
                                        <div class="text-xs text-zinc-400 mt-0.5"><?= htmlspecialchars($row['phone']) ?></div>
                                    </td>
                                    <td class="p-4">
                                        <?php if (!empty($row['resume_path'])): ?>
                                            <a href="<?= htmlspecialchars($row['resume_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold no-underline">
                                                <i class="bi bi-file-earmark-pdf-fill"></i> View Resume
                                            </a>
                                        <?php else: ?>
                                            <span class="text-zinc-400 text-xs">No Resume</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-zinc-600 font-semibold">
                                        <i class="bi bi-calendar-event text-red-500 mr-1.5"></i>
                                        <?= date('M d, Y - h:i A', strtotime($row['final_interview_date'])) ?>
                                    </td>
                            <td class="p-4 space-x-2" id="action-cell-<?= $row['id'] ?>">
    <?php if ($row['status'] === 'Final Interview Set'): ?>
        <button type="button" onclick="approveApplicant(<?= $row['id'] ?>, '<?= addslashes($row['full_name']) ?>', '<?= $row['email'] ?>', '<?= $row['phone'] ?>')" ...>
        Approve
    </button>
    <?php elseif ($row['status'] === 'Approved'): ?>
        <button type="button" onclick="openHireModal(<?= $row['id'] ?>, '<?= addslashes($row['full_name']) ?>', '<?= $row['email'] ?>', '<?= $row['phone'] ?>')" 
                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
            Hire
        </button>
    <?php endif; ?>
    <a href="admin_applicants.php?action=admin_reject&id=<?= $row['id'] ?>" 
       class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs inline-block">Reject</a>
</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <div id="hrModal" class="hidden fixed inset-0 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center gap-2 mb-4 text-blue-600">
                <i class="bi bi-person-plus-fill text-xl"></i>
                <h3 class="text-lg font-bold text-zinc-900">Create HR Admin Account</h3>
            </div>
            <form id="hireForm" method="POST">
                <input type="hidden" name="action" value="register_hr">
                <div>
                    <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1">HR Gmail Address</label>
                    <input type="email" name="hr_gmail" required placeholder="example@gmail.com" class="w-full text-sm border border-zinc-300 rounded-xl px-4 py-2.5 focus:outline-none focus:border-blue-600 text-zinc-800">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1">Account Password</label>
                    <input type="password" name="hr_password" required placeholder="Enter secure password" class="w-full text-sm border border-zinc-300 rounded-xl px-4 py-2.5 focus:outline-none focus:border-blue-600 text-zinc-800">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeHrModal()" class="px-4 py-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-600 font-bold rounded-xl text-xs transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">Register & Log In</button>
                </div>
            </form>
        </div>
    </div>

    <div id="hireModal" class="hidden fixed inset-0 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-2xl w-full max-w-2xl p-6 my-8">
            <div class="flex items-center gap-2 mb-2 text-emerald-600">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <h3 class="text-lg font-bold text-zinc-900">Official Employee Onboarding Terminal</h3>
            </div>
            <p id="hireModalName" class="text-xs text-zinc-500 mb-6"></p>

           <form id="confirmHireForm" method="POST" class="space-y-4">
    <input type="hidden" name="action" value="confirm_hire">
    <input type="hidden" name="applicant_id" id="hire_candidate_id"> 
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-1">Full Name</label>
                        <input type="text" name="full_name" id="modal_full_name" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2.5 text-zinc-500 outline-none select-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-1">Phone Number</label>
                        <input type="text" name="phone" id="modal_phone" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2.5 text-zinc-500 outline-none select-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-1">Personal Email Address</label>
                        <input type="email" name="email" id="modal_personal_email" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2.5 text-zinc-500 outline-none select-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1">Assign Employee Work Email</label>
                        <input type="email" name="employee_gmail" required placeholder="e.g. juandelacruz@company.com" class="w-full text-sm border border-zinc-300 rounded-xl px-4 py-2.5 focus:outline-none focus:border-emerald-600 text-zinc-800">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1">Home Address</label>
                    <textarea name="address" required placeholder="Enter complete address" rows="2" class="w-full text-sm border border-zinc-300 rounded-xl px-4 py-2.5 focus:outline-none focus:border-emerald-600 text-zinc-800"></textarea>
                </div>

                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-zinc-700 uppercase tracking-wide mb-3">Statutory & Government Identification</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">GSIS ID</label>
                            <input type="text" name="gsis_id" placeholder="XX-XXXXXXX-X" class="w-full text-sm bg-white border border-zinc-300 rounded-xl px-4 py-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">SSS ID</label>
                            <input type="text" name="sss_id" placeholder="XX-XXXXXXX-X" class="w-full text-sm bg-white border border-zinc-300 rounded-xl px-4 py-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">PhilHealth ID</label>
                            <input type="text" name="philhealth_id" placeholder="XX-XXXXXXXXX-X" class="w-full text-sm bg-white border border-zinc-300 rounded-xl px-4 py-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Pag-IBIG MID</label>
                            <input type="text" name="pagibig_id" placeholder="XXXX-XXXX-XXXX" class="w-full text-sm bg-white border border-zinc-300 rounded-xl px-4 py-2">
                        </div>
                    </div>
                </div>

                <div class="bg-orange-50/70 border border-orange-100 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-orange-800 uppercase tracking-wide mb-3">Corporate Configuration</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Department Deployment</label>
                            <select name="department" required class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2.5 text-zinc-800 focus:outline-none">
                                <option value="" disabled selected>Select Department</option>
                                <?php if($department_query && $department_query->num_rows > 0): ?>
                                    <?php while($dept_row = $department_query->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($dept_row['department']) ?>"><?= htmlspecialchars($dept_row['department']) ?></option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="Executive Board">Executive Board</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Looking For Job">Looking For Job</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Set Employee Password</label>
                            <input type="text" name="employee_password" required placeholder="Type custom account password" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2.5 text-zinc-800">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeHireModal()" class="px-4 py-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-600 font-bold rounded-xl text-xs transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">Confirm Board Deployment</button>
                </div>
            </form>
        </div>
    </div>
 <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
   <script>
    // --- 1. MODAL FUNCTIONS ---
    function openHireModal(id, name, email, phone) {
        document.getElementById('hire_candidate_id').value = id;
        document.getElementById('modal_full_name').value = name;
        document.getElementById('modal_personal_email').value = email;
        document.getElementById('modal_phone').value = phone;
        document.getElementById('hireModal').classList.remove('hidden');
    }

    function closeHireModal() {
        document.getElementById('hireModal').classList.add('hidden');
    }

    // --- 2. APPROVE ACTION ---
    async function approveApplicant(id, name, email, phone) {
    const result = await Swal.fire({
        title: 'Approve Candidate?',
        text: "Are you sure you want to approve?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Approve'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('admin_applicants.php?action=approve&id=' + id);
            
            // DITO ANG PAGBABAGO: I-parse natin ang JSON
            const data = await response.json(); 

            if (data.status === 'success') {
                const cell = document.getElementById('action-cell-' + id);
                
                // Palitan ang content ng button
                cell.innerHTML = `
                    <button type="button" onclick="openHireModal(${id}, '${name}', '${email}', '${phone}')" 
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
                        Hire
                    </button>
                    <a href="admin_applicants.php?action=admin_reject&id=${id}" 
                       class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs inline-block">Reject</a>
                `;

                Swal.fire('Success!', 
                'Na-approve na ang candidate.', 'success');
            }
        } catch (error) {
            console.error("Error parsing JSON:", error);
            Swal.fire('Error', 'error');
        }
    }
}
    // --- 3. VALIDATION FUNCTION ---
    function validateForm(formData) {
        const email = formData.get('employee_gmail');
        const phone = formData.get('phone');
        const fullName = formData.get('full_name').trim();
        const password = formData.get('employee_password');
        const govtIdPattern = /^\d{2}-\d{7}-\d{1}$/;
        const philHealthPattern = /^\d{2}-\d{9}-\d{1}$/;
        const pagibigPattern = /^\d{4}-\d{4}-\d{4}$/;

        if (!fullName.includes(' ')) {
            Swal.fire('Error', 'Full name must include a space.', 'warning');
            return false;
        }
        if (!/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/.test(email)) {
            Swal.fire('Invalid Email', 'Please enter a valid email format.', 'warning');
            return false;
        }
        if (!/^09\d{9}$/.test(phone)) {
            Swal.fire('Invalid Phone', 'Phone number must be 11 digits and start with 09.', 'warning');
            return false;
        }
        // Regex breakdown:
// (?=.*[a-z])      - Must contain at least one lowercase letter
// (?=.*[A-Z])      - Must contain at least one uppercase letter
// (?=.*\d)         - Must contain at least one number
// (?=.*[@$!%*?&])  - Must contain at least one special character
// [A-Za-z\d@$!%*?&]{8,} - Must be at least 8 characters long
const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

if (!strongPasswordRegex.test(password)) {
    Swal.fire(
        'Weak Password', 
        'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character (@$!%*?&).', 
        'warning'
    );
    return false;
}
        if (!govtIdPattern.test(formData.get('gsis_id')) ) {
            Swal.fire('Invalid GSIS ID', 'Format: XX-XXXXXXX-X', 'warning');
            return false;
        }
        if(!govtIdPattern.test(formData.get('sss_id'))){
            Swal.fire('Invalid SSS ID', 'Format: XX-XXXXXXX-X', 'warning');
            return false;
        }
        if (!philHealthPattern.test(formData.get('philhealth_id'))) {
            Swal.fire('Invalid PhilHealth ID', 'Format: XX-XXXXXXXXX-X', 'warning');
            return false;
        }
        if (!pagibigPattern.test(formData.get('pagibig_id'))) {
            Swal.fire('Invalid Pag-IBIG MID', 'Format: XXXX-XXXX-XXXX', 'warning');
            return false;
        }
        return true;
    }

    // --- 4. INITIALIZATION ---
   // --- 4. INITIALIZATION ---
document.addEventListener("DOMContentLoaded", function() {
    
    // Hire Form Handler
    const hireForm = document.getElementById('confirmHireForm');// Siguraduhin na may id="hireForm" ang iyong <form>
    
    if (hireForm) {
        hireForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Pipigilan ang pag-reload ng page
            
            let formData = new FormData(this);
         // I-set ang action sa loob ng FormData

            // I-validate ang form bago mag-fetch
            if (!validateForm(formData)) return;

            // Ipadala sa server
            fetch('admin_applicants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
    // I-check kung ok ang response
    if (!response.ok) {
        throw new Error('Server responded with status: ' + response.status);
    }
    return response.text(); // I-convert muna sa text para makita kung may error sa PHP
})
.then(text => {
    try {
        const data = JSON.parse(text);
        if (data.status === 'success') {
            Swal.fire('Success', data.message, 'success');
            
            // Hanapin ang row ng applicant gamit ang ID at tanggalin ito
            const candidateId = document.getElementById('hire_candidate_id').value;
            const rowToRemove = document.querySelector(`tr[data-id="${candidateId}"]`); // Kailangan mong lagyan ng data-id ang tr mo
            if (rowToRemove) {
                rowToRemove.remove();
            }
            
            closeHireModal();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) {
        console.error('Raw response:', text);
        Swal.fire('Error', 'Invalid JSON response.', 'error');
    }
})
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'error');
            });
        });
    }

    // Logout Handler
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Are you sure?',
                text: "Terminate administrative dashboard runtime?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Yes, Sign Out'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'logout.php';
            });
        });
    }
});
</script>
</body>
</html>