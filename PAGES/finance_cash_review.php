<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'finance' && $current_role !== 'admin') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Handle Action via AJAX / POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $id = intval($_POST['id']);
    $action = $_POST['action'];

    if ($id <= 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(["success" => false, "message" => "Invalid parameters provided."]);
        exit;
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Salary advance request rejected."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request."]);
        }
        $stmt->close();
    } elseif ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Finance Approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Salary advance approved by Finance! Forwarded to Admin."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request."]);
        }
        $stmt->close();
    }
    $conn->close();
    exit();
}

// Fetch Pending Salary Advances via AJAX GET
if (isset($_GET['action']) && $_GET['action'] === 'fetch_salary_advances') {
    header('Content-Type: application/json');
    $query = "SELECT sa.*, COALESCE(e.full_name, 'Unknown Employee') as full_name, COALESCE(e.department, 'Unassigned') as department FROM salary_advances sa LEFT JOIN employees e ON sa.employee_id = e.id WHERE sa.status = 'Pending' ORDER BY sa.created_at DESC";
    $result = $conn->query($query);
    $advances = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['amount'] = floatval($row['amount']);
            $advances[] = $row;
        }
    }
    echo json_encode($advances);
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - Salary Advance Review</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        ::-webkit-scrollbar {
          width: 5px;
          height: 5px;
        }
        ::-webkit-scrollbar-track {
          background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
          background: #cbd5e1;
          border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
          background: #94a3b8;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="flex-1 flex flex-col overflow-y-auto p-4 lg:p-6 bg-slate-50 min-w-0">
            
            <!-- Compact Modern Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 bg-white px-5 py-4 rounded-xl shadow-sm border border-slate-100 gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-cyan-600 text-white flex items-center justify-center text-base shadow-sm">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-950 tracking-tight leading-snug">Finance Cash Advance Review</h1>
                        <p class="text-xs text-slate-500">Evaluate and review pending employee salary advance requests.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200/60">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Live Queue</span>
                </div>
            </div>

            <!-- Table Card with Dynamic Single Selection & Toggle Unselect Toolbar -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                
                <!-- Top Action Toolbar -->
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Selected Employee:</span>
                        <span id="selectedEmployeeNameText" class="text-xs font-bold text-blue-900 bg-blue-50 px-3 py-1 rounded-md border border-blue-200/60 shadow-sm">None Selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="btnApprove" onclick="processSingleDecision('approve')" disabled class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm border-0 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="bi bi-check-lg text-sm"></i> Approve to Admin
                        </button>
                        <button id="btnReject" onclick="processSingleDecision('reject')" disabled class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors border border-rose-200 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="bi bi-x-lg text-sm"></i> Reject
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900 text-white text-xs uppercase tracking-wider">
                                <th class="py-3 px-3 font-semibold text-center w-10">
                                    <span class="sr-only">Select</span>
                                </th>
                                <th class="py-3 px-4 font-semibold">Employee Name</th>
                                <th class="py-3 px-4 font-semibold">Department</th>
                                <th class="py-3 px-4 font-semibold text-right">Amount</th>
                                <th class="py-3 px-4 font-semibold">Reason</th>
                            </tr>
                        </thead>
                        <tbody id="salaryAdvanceTableBody" class="divide-y divide-slate-100 text-sm">
                            <!-- Dynamically populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script>
        let salaryAdvances = [];
        let selectedAdvanceId = null;
        let selectedEmployeeName = '';
        const endpointUrl = 'finance_cash_review.php';

        async function loadSalaryAdvances() {
            try {
                const res = await fetch(`${endpointUrl}?action=fetch_salary_advances`);
                const rawText = await res.text();
                
                try {
                    salaryAdvances = JSON.parse(rawText);
                    selectedAdvanceId = null;
                    selectedEmployeeName = '';
                    renderTable();
                } catch (jsonErr) {
                    console.error("JSON Parse Error:", jsonErr);
                    salaryAdvances = [];
                    renderTable();
                }
            } catch (err) {
                console.error("Failed to load requests:", err);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('salaryAdvanceTableBody');
            tbody.innerHTML = '';
            
            if (!Array.isArray(salaryAdvances) || salaryAdvances.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-slate-400 italic bg-slate-50/50 font-medium text-sm">No pending salary advance requests.</td></tr>`;
                updateToolbarState();
                return;
            }

            salaryAdvances.forEach(row => {
                const isSelected = selectedAdvanceId == row.id;
                const tr = document.createElement('tr');
                tr.className = `transition-colors cursor-pointer ${isSelected ? 'bg-blue-50/70 hover:bg-blue-50' : 'hover:bg-slate-50/80'}`;
                
                tr.onclick = () => {
                    if (selectedAdvanceId == row.id) {
                        clearSelection();
                    } else {
                        selectedAdvanceId = row.id;
                        selectedEmployeeName = row.full_name;
                        renderTable();
                    }
                };

                tr.innerHTML = `
                    <td class="py-3.5 px-3 text-center" onclick="event.stopPropagation()">
                        <input type="radio" name="advance_selection" value="${row.id}" ${isSelected ? 'checked' : ''} class="row-radio w-4 h-4 text-blue-900 focus:ring-blue-800 border-slate-300 cursor-pointer" onclick="handleRadioClick(${row.id}, '${escapeHtml(row.full_name)}', event)">
                    </td>
                    <td class="py-3.5 px-4 font-bold text-slate-900 text-sm whitespace-nowrap">${row.full_name || 'N/A'}</td>
                    <td class="py-3.5 px-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60 shadow-sm">${row.department || 'Unassigned'}</span>
                    </td>
                    <td class="py-3.5 px-4 text-right font-mono whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/60 shadow-sm">₱${Number(row.amount || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
                    </td>
                    <td class="py-3.5 px-4 text-slate-600 text-sm max-w-[300px] truncate" title="${row.reason || ''}">
                        ${row.reason || 'No reason provided'}
                    </td>
                `;
                tbody.appendChild(tr);
            });
            updateToolbarState();
        }

        function handleRadioClick(id, name, event) {
            event.stopPropagation();
            if (selectedAdvanceId == id) {
                clearSelection();
            } else {
                selectedAdvanceId = id;
                selectedEmployeeName = name;
                renderTable();
            }
        }

        function clearSelection() {
            selectedAdvanceId = null;
            selectedEmployeeName = '';
            renderTable();
        }

        function updateToolbarState() {
            const nameText = document.getElementById('selectedEmployeeNameText');
            const btnApprove = document.getElementById('btnApprove');
            const btnReject = document.getElementById('btnReject');

            if (selectedAdvanceId) {
                nameText.innerText = selectedEmployeeName;
                nameText.className = "text-xs font-bold text-emerald-800 bg-emerald-50 px-3 py-1 rounded-md border border-emerald-200/60 shadow-sm";
                btnApprove.removeAttribute('disabled');
                btnReject.removeAttribute('disabled');
            } else {
                nameText.innerText = "None Selected";
                nameText.className = "text-xs font-bold text-blue-900 bg-blue-50 px-3 py-1 rounded-md border border-blue-200/60 shadow-sm";
                btnApprove.setAttribute('disabled', 'true');
                btnReject.setAttribute('disabled', 'true');
            }
        }

        function escapeHtml(text) {
            return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        async function processSingleDecision(action) {
            if (!selectedAdvanceId) return;

            let titleText = action === 'approve' ? 'Proceed to Admin' : 'Reject Request?';
            Swal.fire({
                title: titleText,
                text: `Are you sure you want to ${action === 'approve' ? 'forward to Admin' : 'reject'} the salary advance request for ${selectedEmployeeName}?`,
                icon: action === 'approve' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#059669' : '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes',
                customClass: {
                    popup: 'rounded-2xl shadow-xl border border-slate-100',
                    confirmButton: 'rounded-lg px-3.5 py-2 font-bold text-xs',
                    cancelButton: 'rounded-lg px-3.5 py-2 font-bold text-xs'
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const formData = new URLSearchParams();
                    formData.append('action', action);
                    formData.append('id', selectedAdvanceId);

                    try {
                        const res = await fetch(endpointUrl, { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            Swal.fire({ 
                                icon: 'success', 
                                title: 'Success!', 
                                text: data.message, 
                                timer: 1500, 
                                showConfirmButton: false,
                                customClass: { popup: 'rounded-2xl shadow-xl' }
                            });
                            loadSalaryAdvances();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message, customClass: { popup: 'rounded-2xl shadow-xl' } });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to communicate with server.', customClass: { popup: 'rounded-2xl shadow-xl' } });
                    }
                }
            });
        }

        window.addEventListener('DOMContentLoaded', loadSalaryAdvances);
    </script>
</body>
</html>