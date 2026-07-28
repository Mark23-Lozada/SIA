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
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Use a prepared statement instead of interpolating $id directly
    $stmt = $conn->prepare("UPDATE applicants SET status = 'Approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $update = $stmt->execute();

    header('Content-Type: application/json');

    if ($update) {
        // Fetch the full record so the JS can populate the Hire modal
        // with the applicant's submitted address/government IDs.
        $fetch = $conn->prepare("SELECT * FROM applicants WHERE id = ?");
        $fetch->bind_param("i", $id);
        $fetch->execute();
        $applicant = $fetch->get_result()->fetch_assoc();

        echo json_encode(['status' => 'success', 'applicant' => $applicant]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// ACTION: REJECT (permanently delete the applicant record)
if (isset($_GET['action']) && $_GET['action'] == 'admin_reject' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    header('Content-Type: application/json');

    // Look up the resume path first so we can clean up the uploaded file too
    $fetch = $conn->prepare("SELECT resume_path FROM applicants WHERE id = ?");
    $fetch->bind_param("i", $id);
    $fetch->execute();
    $applicant = $fetch->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM applicants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $deleted = $stmt->execute();

    if ($deleted) {
        if ($applicant && !empty($applicant['resume_path']) && file_exists($applicant['resume_path'])) {
            @unlink($applicant['resume_path']);
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// ACTION: CONFIRM HIRE & REGISTER EMPLOYEE
if (isset($_POST['action']) && $_POST['action'] == 'confirm_hire') {
    header('Content-Type: application/json');
    $app_id = intval($_POST['applicant_id']);

    // 1. Check for duplicates
    $check = $conn->prepare("SELECT id FROM employees WHERE employee_gmail = ? OR email = ? OR phone = ? OR gsis_id = ? OR philhealth_id = ? OR pagibig_id = ? OR sss_id = ?");
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
        echo json_encode(['status' => 'error', 'message' => 'Duplicate credentials found.']);
        exit;
    }

    // 2. Insert into employees
    $insert_sql = "INSERT INTO employees (full_name, email, phone, employee_gmail, address, gsis_id, sss_id, philhealth_id, pagibig_id, department, employee_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $hashed_password = password_hash($_POST['employee_password'], PASSWORD_BCRYPT);
    $stmt->bind_param("sssssssssss",
        $_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['employee_gmail'],
        $_POST['address'], $_POST['gsis_id'], $_POST['sss_id'], $_POST['philhealth_id'],
        $_POST['pagibig_id'], $_POST['department'], $hashed_password
    );

    if ($stmt->execute()) {
        // 3. IMPORTANT: Update status to 'Hired' so it disappears from the pending list
        $status_stmt = $conn->prepare("UPDATE applicants SET status = 'Hired' WHERE id = ?");
        $status_stmt->bind_param("i", $app_id);
        $status_stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'Employee added successfully.']);
    } else {
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
        <button type="button" onclick="approveApplicant(<?= $row['id'] ?>)"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
            Approve
        </button>
    <?php elseif ($row['status'] === 'Approved'): ?>
        <button type="button" data-applicant='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>' onclick="openHireModal(this)" 
                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
            Hire
        </button>
    <?php endif; ?>
    <button type="button" onclick="rejectApplicant(<?= $row['id'] ?>)" 
            class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs cursor-pointer">Reject</button>
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
            <form id="registerHrForm" method="POST">
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
                        <input type="email" name="employee_gmail" id="modal_employee_gmail" required placeholder="e.g. delacruz.juan@gmail.com" class="w-full text-sm border border-zinc-300 rounded-xl px-4 py-2.5 focus:outline-none focus:border-emerald-600 text-zinc-800">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-1">Home Address</label>
                    <textarea name="address" id="modal_address" readonly required rows="2" class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2.5 text-zinc-500 outline-none select-none resize-none"></textarea>
                </div>

                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-zinc-500 uppercase tracking-wide mb-3">Statutory & Government Identification <span class="normal-case font-medium text-zinc-400">(as submitted by applicant)</span></h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">GSIS ID</label>
                            <input type="text" name="gsis_id" id="modal_gsis_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">SSS ID</label>
                            <input type="text" name="sss_id" id="modal_sss_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">PhilHealth ID</label>
                            <input type="text" name="philhealth_id" id="modal_philhealth_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Pag-IBIG MID</label>
                            <input type="text" name="pagibig_id" id="modal_pagibig_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                    </div>
                </div>

                <div class="bg-orange-50/70 border border-orange-100 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-orange-800 uppercase tracking-wide mb-3">Corporate Configuration</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Department Applied For</label>
                            <input type="text" name="department" id="modal_department" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2.5 text-zinc-500 outline-none select-none">
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
    // --- 1. HELPER: Generate suggested employee email from full name ---
    // Format: lastname.firstname@gmail.com
    function generateEmployeeEmail(fullName) {
        const parts = fullName.trim().split(/\s+/);
        const clean = (str) => str.toLowerCase().replace(/[^a-z0-9]/g, '');

        if (parts.length < 2) {
            // Fallback: only one name segment available
            return clean(parts[0]) + '@panacoda.com';
        }

        const lastName = parts[parts.length - 1];       // last word = last name
        const firstName = parts.slice(0, -1).join('');  // everything before = first name(s)

        return `${clean(lastName)}.${clean(firstName)}@panacoda.com`;
    }

    // --- 2. MODAL FUNCTIONS ---
    // Takes the button element clicked, and reads the applicant's full
    // record from its data-applicant attribute (JSON), so we don't have
    // to hand-escape every field into onclick="" args.
    function openHireModal(btn) {
        const data = JSON.parse(btn.dataset.applicant);

        document.getElementById('hire_candidate_id').value = data.id;

        // Maps modal field id -> applicant record key
        const readonlyFields = {
            modal_full_name: 'full_name',
            modal_personal_email: 'email',
            modal_phone: 'phone',
            modal_address: 'address',
            modal_gsis_id: 'gsis_id',
            modal_sss_id: 'sss_id',
            modal_philhealth_id: 'philhealth_id',
            modal_pagibig_id: 'pagibig_id',
            modal_department: 'department'
        };

        for (const [elementId, key] of Object.entries(readonlyFields)) {
            document.getElementById(elementId).value = data[key] || '';
        }

        // Auto-suggest the employee work email (admin can still edit it)
        document.getElementById('modal_employee_gmail').value = generateEmployeeEmail(data.full_name);

        document.getElementById('hireModal').classList.remove('hidden');
    }

    function closeHireModal() {
        document.getElementById('hireModal').classList.add('hidden');
    }

    function closeHrModal() {
        document.getElementById('hrModal').classList.add('hidden');
    }

    // --- 3. APPROVE ACTION ---
    async function approveApplicant(id) {
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
                const data = await response.json();

                if (data.status === 'success') {
                    const cell = document.getElementById('action-cell-' + id);
                    // Embed the full applicant record (incl. address/govt IDs) as a
                    // data attribute so openHireModal can read it directly later.
                    const applicantJson = JSON.stringify(data.applicant).replace(/'/g, '&#39;');

                    cell.innerHTML = `
                        <button type="button" data-applicant='${applicantJson}' onclick="openHireModal(this)" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
                            Hire
                        </button>
                        <button type="button" onclick="rejectApplicant(${id})" 
                                class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs cursor-pointer">Reject</button>
                    `;

                    Swal.fire('Success!', 'Na-approve na ang candidate.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                }
            } catch (error) {
                console.error("Error parsing JSON:", error);
                Swal.fire('Error', 'error');
            }
        }
    }

    // --- 4. REJECT ACTION ---
    async function rejectApplicant(id) {
        const result = await Swal.fire({
            title: 'Reject Candidate?',
            text: "This will permanently delete the applicant's record, including their resume file. This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Reject & Delete'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('admin_applicants.php?action=admin_reject&id=' + id);
                const data = await response.json();

                if (data.status === 'success') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) row.remove();
                    Swal.fire('Rejected', 'The applicant record has been removed.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                }
            } catch (error) {
                console.error("Error rejecting applicant:", error);
                Swal.fire('Error', 'error');
            }
        }
    }

    // --- 5. VALIDATION FUNCTION ---
    // Address/GSIS/SSS/PhilHealth/Pag-IBIG are now readonly here — they were
    // already validated when the applicant submitted them via client.php.
    // Only the admin-editable fields (work email, password) need checking.
    function validateForm(formData) {
        const email = formData.get('employee_gmail');
        const password = formData.get('employee_password');

        if (!/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/.test(email)) {
            Swal.fire('Invalid Email', 'Please enter a valid email format.', 'warning');
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
        return true;
    }

    // --- 6. INITIALIZATION ---
    document.addEventListener("DOMContentLoaded", function() {

        // Hire Form Handler
        const hireForm = document.getElementById('confirmHireForm');

        if (hireForm) {
            hireForm.addEventListener('submit', function(e) {
                e.preventDefault();

                let formData = new FormData(this);

                if (!validateForm(formData)) return;

                fetch('admin_applicants.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server responded with status: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            Swal.fire('Success', data.message, 'success');

                            const candidateId = document.getElementById('hire_candidate_id').value;
                            const rowToRemove = document.querySelector(`tr[data-id="${candidateId}"]`);
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