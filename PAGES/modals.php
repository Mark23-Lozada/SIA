<?php
$popup_script = "";

// Handle Change Password Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Get current employee ID from session (supporting all possible session keys)
    $emp_id = $_SESSION['employee_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 0;

    // Establish connection if not already available
    if (!isset($conn) || $conn->connect_error) {
        $conn = new mysqli("localhost", "root", "", "pos");
    }

    if ($emp_id === 0 && isset($_SESSION['employee_string_id'])) {
        $stmt_s = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt_s->bind_param("s", $_SESSION['employee_string_id']);
        $stmt_s->execute();
        $res_s = $stmt_s->get_result();
        if ($row_s = $res_s->fetch_assoc()) {
            $emp_id = $row_s['id'];
        }
        $stmt_s->close();
    }

    if ($emp_id > 0) {
        if (!empty($new_password) && $new_password === $confirm_password) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE employees SET employee_password = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $emp_id);

            if ($stmt->execute()) {
                $popup_script = "
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Updated Successfully',
                            text: 'Your password has been successfully updated. Please click confirm to proceed.',
                            confirmButtonText: 'Confirm',
                            confirmButtonColor: '#9333ea',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = window.location.pathname;
                            }
                        });
                    });
                ";
            } else {
                $popup_script = "
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Action Failed',
                            text: 'Database error: Unable to update password.',
                            confirmButtonText: 'Confirm',
                            confirmButtonColor: '#9333ea',
                            allowOutsideClick: false
                        });
                    });
                ";
            }
            $stmt->close();
        } else {
            $popup_script = "
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Action Failed',
                        text: 'New passwords do not match or fields are empty.',
                        confirmButtonText: 'Confirm',
                        confirmButtonColor: '#9333ea',
                        allowOutsideClick: false
                    });
                });
            ";
        }
    } else {
        $popup_script = "
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Session Error',
                    text: 'Active employee session ID not found.',
                    confirmButtonText: 'Confirm',
                    confirmButtonColor: '#9333ea',
                    allowOutsideClick: false
                });
            });
        ";
    }
}
?>

<!-- Render SweetAlert if $popup_script is set -->
<?php if (!empty($popup_script)): ?>
    <script><?= $popup_script ?></script>
<?php endif; ?>

<!-- CHANGE PASSWORD MODAL -->
<div id="changePasswordModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-purple-100 animate-fade-in">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800 flex items-center gap-2"><i class="bi bi-shield-lock-fill text-purple-600"></i> Change Password</h3>
            <button onclick="closeChangePasswordModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4" onsubmit="return validatePasswordMatch()">
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password..." class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 pr-10 text-sm font-semibold text-zinc-700 focus:outline-purple-500">
                    <button type="button" onclick="togglePasswordVisibility('new_password', 'toggleNewIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-700 focus:outline-none">
                        <i id="toggleNewIcon" class="bi bi-eye-slash text-base"></i>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Re-enter New Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password..." class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 pr-10 text-sm font-semibold text-zinc-700 focus:outline-purple-500">
                    <button type="button" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-zinc-700 focus:outline-none">
                        <i id="toggleConfirmIcon" class="bi bi-eye-slash text-base"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="submit_change_password" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-purple-500/20 text-sm">
                Update Password
            </button>
        </form>
    </div>
</div>

<!-- LEAVE REQUEST MODAL -->
<div id="leaveModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-purple-100 animate-fade-in">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800 flex items-center gap-2"><i class="bi bi-calendar-plus text-amber-500"></i> File Leave Request</h3>
            <button onclick="closeLeaveModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Leave Type</label>
                <select name="leave_type" required class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-purple-500">
                    <option value="Vacation Leave">Vacation Leave</option>
                    <option value="Sick Leave">Sick Leave</option>
                    <option value="Emergency Leave">Emergency Leave</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Reason Statement</label>
                <textarea name="reason" rows="3" required placeholder="State your reason for absence..." class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-purple-500"></textarea>
            </div>
            <button type="submit" name="submit_leave" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-purple-500/20 text-sm">
                Submit Leave Application
            </button>
        </form>
    </div>
</div>

<!-- SALARY ADVANCE REQUEST MODAL -->
<div id="advanceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-purple-100 animate-fade-in">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800 flex items-center gap-2"><i class="bi bi-cash-stack text-cyan-500"></i> Request Salary Advance</h3>
            <button onclick="closeAdvanceModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4" onsubmit="return validateAdvanceAmount()">
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Amount Requested (₱) [Min: ₱1,000 - Max: ₱10,000]</label>
                <input type="number" step="0.01" id="adv_amount_input" name="amount" min="1000" max="10000" required placeholder="Enter amount (1,000 - 10,000)..." class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-purple-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Reason Statement</label>
                <textarea name="reason" rows="3" required placeholder="State the reason for advance..." class="w-full bg-purple-50/40 border border-purple-100 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-purple-500"></textarea>
            </div>
            <button type="submit" name="submit_advance" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-cyan-500/20 text-sm">
                Submit Advance Request
            </button>
        </form>
    </div>
</div>

<!-- GEOLOCATION ATTENDANCE MODAL -->
<div id="attendanceModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-purple-100 text-center animate-fade-in">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800 flex items-center gap-2"><i class="bi bi-geo-alt-fill text-emerald-500"></i> Geo-Location Attendance</h3>
            <button onclick="closeAttendanceModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <p class="text-xs text-zinc-500 mb-6">Choose whether to record Time In or Time Out using your GPS coordinates.</p>
        
        <div id="geoStatus" class="mb-4 text-xs font-mono font-bold text-amber-600 bg-amber-50 p-2.5 rounded-xl border border-amber-200">
            Waiting for GPS Location...
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="recordAttendance('time_in')" id="btnTimeIn" disabled class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-all shadow-md text-xs opacity-50 cursor-not-allowed">
                <i class="bi bi-box-arrow-in-right"></i> RECORD TIME IN
            </button>
            <button onclick="recordAttendance('time_out')" id="btnTimeOut" disabled class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 rounded-xl transition-all shadow-md text-xs opacity-50 cursor-not-allowed">
                <i class="bi bi-box-arrow-out-right"></i> RECORD TIME OUT
            </button>
        </div>
    </div>
</div>

<!-- EXACT PAYSLIP STATEMENT MODAL (PH Standards) -->
<div id="payslipModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-purple-100 flex flex-col overflow-hidden animate-fade-in">
        <div class="flex justify-between items-center bg-purple-50/50 px-6 py-4 border-b border-purple-100 no-print">
            <h5 class="modal-title font-bold text-purple-900 flex items-center gap-2 text-sm">
                <i class="bi bi-receipt text-purple-600"></i> Corporate Payroll Statement (PH Standards)
            </h5>
            <button onclick="closePayslipModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[75vh]" id="printArea">
            <div class="border border-purple-100 p-6 bg-white rounded-xl text-gray-800 text-xs shadow-sm">
                <div class="text-center border-b pb-4 mb-4">
                    <h3 class="font-black text-xl tracking-wide uppercase text-gray-900"><?= htmlspecialchars($employee['company_name'] ?? 'PannaKoda Stores Inc.') ?></h3>
                    <p class="text-[11px] text-gray-500 font-medium"><?= htmlspecialchars($employee['company_address'] ?? '123 Business Corporate Center, Cavite, Philippines') ?></p>
                    <p class="text-[11px] text-gray-400 font-mono">TIN: 000-123-456-000 &bull; SSS Employer No: 03-9876543-2</p>
                    <div class="mt-2 inline-block bg-purple-50 text-purple-800 font-mono text-[11px] font-bold px-3 py-1 rounded border border-purple-100">
                        OFFICIAL PAYSLIP STATEMENT | <?= $cutOffPeriod ?? '' ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-4 bg-purple-50/30 p-3 rounded-lg">
                   <div>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee ID:</span> <span class="font-mono font-bold text-gray-800"><?= htmlspecialchars($employee['employee_id'] ?? 'EMP-' . str_pad($employee['id'] ?? 1, 4, '0', STR_PAD_LEFT)) ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee Name:</span> <span class="font-bold text-gray-800"><?= htmlspecialchars($employee['full_name'] ?? '') ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Department:</span> <span class="font-semibold text-gray-800"><?= htmlspecialchars($employee['department'] ?? 'Unassigned') ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Tax Status:</span> <span class="font-semibold text-gray-800">Single / S / Z</span></p>
                   </div>
                   <div>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Position/Role:</span> <span class="font-bold text-gray-800"><?= htmlspecialchars($role ?? '') ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Pay Date:</span> <span class="font-mono text-gray-800"><?= $payDateStr ?? '' ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employment Type:</span> <span class="font-semibold text-purple-600">Regular</span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Statutory Ref:</span> <span class="font-mono text-gray-600 text-[10px]">SSS/PH/PAG-IBIG Compliant</span></p>
                   </div>
                </div>

                <div class="grid grid-cols-2 gap-6 items-start mb-4">
                    <div>
                        <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Earnings (Semi-Monthly Breakdown)</h6>
                        <div class="space-y-1">
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Basic Salary (Semi-Monthly)</span> 
                                <span class="font-semibold font-mono">₱<?= number_format($kinsenas_base ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Rice & Clothing Allowance</span> 
                                <span class="font-semibold font-mono">₱<?= number_format($kinsenas_allowance ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-purple-50/50 px-2 rounded mt-1">
                                <span>Gross Pay (Period)</span> 
                                <span class="font-mono text-purple-700">₱<?= number_format($kinsenas_gross ?? 0, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Statutory & Tax Deductions</h6>
                        <div class="space-y-1">
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">SSS Contribution (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_sss ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">PhilHealth (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_philhealth ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Pag-IBIG Fund (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_pagibig ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">BIR Withholding Tax</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_tax ?? 0, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-red-50/50 px-2 rounded mt-1">
                                <span>Total Deductions</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_deductions ?? 0, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-purple-50/40 p-3 rounded-lg mb-4 text-[11px] grid grid-cols-2 gap-2 text-gray-700 border border-purple-100">
                    <div><span class="font-semibold">Monthly Basic Salary:</span> ₱<?= number_format($monthly_base ?? 0, 2) ?></div>
                    <div><span class="font-semibold">Monthly Gross Earnings:</span> ₱<?= number_format($monthly_gross ?? 0, 2) ?></div>
                    <div><span class="font-semibold">Monthly Total Statutory & Tax:</span> ₱<?= number_format($ph['total_deductions'] ?? 0, 2) ?></div>
                    <div><span class="font-semibold">Monthly Net Pay Reference:</span> ₱<?= number_format($monthly_net ?? 0, 2) ?></div>
                </div>

                <div class="bg-purple-950 text-white p-4 rounded-xl flex justify-between items-center shadow-inner">
                    <div>
                        <h4 class="text-[10px] uppercase tracking-widest text-purple-300">Net Pay for this Period</h4>
                        <p class="text-[10px] text-purple-400">Semi-Monthly Payout (15-Day Cycle)</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-2xl font-black text-purple-300 font-mono">₱<?= number_format($kinsenas_net ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-purple-50/30 px-6 py-3 border-t border-purple-100 flex justify-end gap-2 no-print">
            <button onclick="closePayslipModal()" class="px-4 py-2 bg-white border border-purple-200 hover:bg-purple-50 rounded-xl text-xs font-semibold text-gray-700">Close</button>
            <button onclick="window.print()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="bi bi-printer"></i> Print Statement
            </button>
        </div>
    </div>
</div>

<script>
    function openChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.remove('hidden');
    }
    function closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('hidden');
    }
    function togglePasswordVisibility(fieldId, iconId) {
        const inputField = document.getElementById(fieldId);
        const iconElement = document.getElementById(iconId);
        if (inputField.type === 'password') {
            inputField.type = 'text';
            iconElement.classList.remove('bi-eye-slash');
            iconElement.classList.add('bi-eye');
        } else {
            inputField.type = 'password';
            iconElement.classList.remove('bi-eye');
            iconElement.classList.add('bi-eye-slash');
        }
    }
    function validatePasswordMatch() {
        const p1 = document.getElementById('new_password').value;
        const p2 = document.getElementById('confirm_password').value;
        if (p1 !== p2) {
            Swal.fire({
                icon: 'warning',
                title: 'Password Mismatch',
                text: 'New passwords do not match!',
                confirmButtonText: 'Confirm',
                confirmButtonColor: '#9333ea'
            });
            return false;
        }
        return true;
    }
</script>