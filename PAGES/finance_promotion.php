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
    die("Database Connection Failed: " . $conn->connect_error);
}

// HANDLE FINANCE APPROVAL / REJECTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_finance_promotion') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $request_id = intval($_POST['request_id'] ?? 0);
    $decision = $conn->real_escape_string($_POST['decision'] ?? ''); 

    if ($request_id <= 0 || !in_array($decision, ['Approved', 'Rejected'])) {
        echo json_encode(["success" => false, "message" => "Invalid parameters provided."]);
        exit;
    }

    if ($decision === 'Approved') {
        $updateReq = $conn->prepare("UPDATE promotion_requests SET finance_status = 'Approved', status = 'Pending Admin' WHERE id = ?");
        $updateReq->bind_param("i", $request_id);
        if ($updateReq->execute()) {
            echo json_encode(["success" => true, "message" => "Promotion approved by Finance! Forwarded to Admin."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request."]);
        }
        $updateReq->close();
    } else {
        $updateReq = $conn->prepare("UPDATE promotion_requests SET finance_status = 'Rejected', status = 'Rejected' WHERE id = ?");
        $updateReq->bind_param("i", $request_id);
        if ($updateReq->execute()) {
            echo json_encode(["success" => true, "message" => "Promotion request rejected."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request."]);
        }
        $updateReq->close();
    }

    $conn->close();
    exit;
}

// FETCH FINANCE PROMOTION REQUESTS
if (isset($_GET['action']) && $_GET['action'] === 'fetch_finance_promotions') {
    header('Content-Type: application/json');

    $query = "SELECT pr.*, COALESCE(e.full_name, 'Unknown Employee') as full_name, e.employee_id as custom_emp_id, COALESCE(e.department, 'Unassigned') as department, COALESCE(e.salary, 0) as current_salary, COALESCE(e.position_title, 'Staff') as current_position 
              FROM promotion_requests pr 
              LEFT JOIN employees e ON pr.employee_id = e.id 
              WHERE pr.finance_status = 'Pending' OR pr.status = 'Pending' OR pr.status = 'Pending Finance'
              ORDER BY pr.id DESC";
              
    $result = $conn->query($query);
    $requests = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['current_salary'] = floatval($row['current_salary']);
            $row['new_salary'] = floatval($row['new_salary']);
            $requests[] = $row;
        }
    }

    echo json_encode($requests);
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Promotion & Salary Review</title>
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
<body class="bg-slate-50 font-sans antialiased h-screen overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-4 lg:p-6 bg-slate-50 min-w-0">
      
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 bg-white px-5 py-4 rounded-xl shadow-sm border border-slate-100 gap-2">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-900 text-white flex items-center justify-center text-base shadow-sm">
            <i class="bi bi-award-fill"></i>
          </div>
          <div>
            <h1 class="text-xl font-bold text-blue-950 tracking-tight leading-snug">Promotion & Salary Review</h1>
            <p class="text-xs text-slate-500">Evaluate and review pending employee promotion requests.</p>
          </div>
        </div>
        <div class="flex items-center gap-2 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200/60">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Live Queue</span>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
        
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Selected Employee:</span>
            <span id="selectedEmployeeNameText" class="text-xs font-bold text-blue-900 bg-blue-50 px-3 py-1 rounded-md border border-blue-200/60 shadow-sm">None Selected</span>
          </div>
          <div class="flex items-center gap-2">
            <button id="btnApprove" onclick="processSingleDecision('Approved')" disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm border-0 disabled:opacity-40 disabled:cursor-not-allowed">
              <i class="bi bi-check-lg text-sm"></i> Approve
            </button>
            <button id="btnReject" onclick="processSingleDecision('Rejected')" disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors border border-rose-200 disabled:opacity-40 disabled:cursor-not-allowed">
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
                <th class="py-3 px-4 font-semibold">ID</th>
                <th class="py-3 px-4 font-semibold">Department</th>
                <th class="py-3 px-4 font-semibold">Current Role</th>
                <th class="py-3 px-4 font-semibold text-right">Current Salary</th>
                <th class="py-3 px-4 font-semibold">Proposed Role</th>
                <th class="py-3 px-4 font-semibold text-right">New Salary</th>
                <th class="py-3 px-4 font-semibold">Reason</th>
              </tr>
            </thead>
            <tbody id="financePromotionTableBody" class="divide-y divide-slate-100 text-sm">
              </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
  <script>
    let promotionRequests = [];
    let selectedRequestId = null;
    let selectedEmployeeName = '';
    const endpointUrl = 'finance_promotion.php';

    async function loadPromotionRequests() {
      try {
        const res = await fetch(`${endpointUrl}?action=fetch_finance_promotions`);
        const rawText = await res.text();
        
        try {
          promotionRequests = JSON.parse(rawText);
          selectedRequestId = null;
          selectedEmployeeName = '';
          renderTable();
        } catch (jsonErr) {
          console.error("JSON Parse Error:", jsonErr);
          promotionRequests = [];
          renderTable();
        }
      } catch (err) {
        console.error("Failed to load requests:", err);
      }
    }

    function renderTable() {
      const tbody = document.getElementById('financePromotionTableBody');
      tbody.innerHTML = '';
      
      if (!Array.isArray(promotionRequests) || promotionRequests.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-10 text-slate-400 italic bg-slate-50/50 font-medium text-sm">No pending promotion requests requiring finance review.</td></tr>`;
        updateToolbarState();
        return;
      }

      promotionRequests.forEach(req => {
        const isSelected = selectedRequestId == req.id;
        const tr = document.createElement('tr');
        tr.className = `transition-colors cursor-pointer ${isSelected ? 'bg-blue-50/70 hover:bg-blue-50' : 'hover:bg-slate-50/80'}`;
        
        tr.onclick = (e) => {
          // Toggle selection kapag na-click ulit ang kasalukuyang selected row
          if (selectedRequestId == req.id) {
            clearSelection();
          } else {
            selectedRequestId = req.id;
            selectedEmployeeName = req.full_name;
            renderTable();
          }
        };

        tr.innerHTML = `
          <td class="py-3.5 px-3 text-center" onclick="event.stopPropagation()">
            <input type="radio" name="employee_selection" value="${req.id}" ${isSelected ? 'checked' : ''} class="row-radio w-4 h-4 text-blue-900 focus:ring-blue-800 border-slate-300 cursor-pointer" onclick="handleRadioClick(${req.id}, '${escapeHtml(req.full_name)}', event)">
          </td>
          <td class="py-3.5 px-4 font-bold text-slate-900 text-sm whitespace-nowrap">${req.full_name || 'N/A'}</td>
          <td class="py-3.5 px-4 font-mono font-medium text-slate-600 text-xs whitespace-nowrap">${req.custom_emp_id || 'N/A'}</td>
          <td class="py-3.5 px-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60 shadow-sm">${req.department || 'Unassigned'}</span>
          </td>
          <td class="py-3.5 px-4 font-medium text-slate-700 text-sm whitespace-nowrap">${req.current_position || 'Staff'}</td>
          <td class="py-3.5 px-4 text-right font-mono whitespace-nowrap">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/60 shadow-sm">₱${Number(req.current_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
          </td>
          <td class="py-3.5 px-4 font-bold text-blue-900 text-sm whitespace-nowrap">${req.proposed_position || 'N/A'}</td>
          <td class="py-3.5 px-4 text-right font-mono whitespace-nowrap">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200/60 shadow-sm">₱${Number(req.new_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
          </td>
          <td class="py-3.5 px-4 text-slate-600 text-sm max-w-[200px] truncate" title="${req.reason_for_promotion || ''}">
            ${req.reason_for_promotion || 'No reason provided'}
          </td>
        `;
        tbody.appendChild(tr);
      });
      updateToolbarState();
    }

    function handleRadioClick(id, name, event) {
      event.stopPropagation();
      if (selectedRequestId == id) {
        // Kung naka-check na tapos pinindot ulit, i-unselect
        clearSelection();
      } else {
        selectedRequestId = id;
        selectedEmployeeName = name;
        renderTable();
      }
    }

    function clearSelection() {
      selectedRequestId = null;
      selectedEmployeeName = '';
      renderTable();
    }

    function updateToolbarState() {
      const nameText = document.getElementById('selectedEmployeeNameText');
      const btnApprove = document.getElementById('btnApprove');
      const btnReject = document.getElementById('btnReject');

      if (selectedRequestId) {
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

    async function processSingleDecision(decision) {
      if (!selectedRequestId) return;

      Swal.fire({
        title: `${decision} Request for ${selectedEmployeeName}?`,
        text: `Are you sure you want to ${decision.toLowerCase()} this employee's promotion request?`,
        icon: decision === 'Approved' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: decision === 'Approved' ? '#059669' : '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${decision}`,
        customClass: {
          popup: 'rounded-2xl shadow-xl border border-slate-100',
          confirmButton: 'rounded-lg px-3.5 py-2 font-bold text-xs',
          cancelButton: 'rounded-lg px-3.5 py-2 font-bold text-xs'
        }
      }).then(async (result) => {
        if (result.isConfirmed) {
          const formData = new URLSearchParams();
          formData.append('action', 'process_finance_promotion');
          formData.append('request_id', selectedRequestId);
          formData.append('decision', decision);

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
              loadPromotionRequests();
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: data.message, customClass: { popup: 'rounded-2xl shadow-xl' } });
            }
          } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to communicate with server.', customClass: { popup: 'rounded-2xl shadow-xl' } });
          }
        }
      });
    }

    window.addEventListener('DOMContentLoaded', loadPromotionRequests);
  </script>
</body>
</html>