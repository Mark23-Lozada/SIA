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

    $query = "SELECT pr.*, COALESCE(e.full_name, 'Unknown Employee') as full_name, e.employee_id as custom_emp_id, COALESCE(e.department, 'Unassigned') as department, 0 as current_salary, COALESCE(e.position_title, 'Staff') as current_position 
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
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100 min-w-0">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Promotion & Salary Adjustments Review (Finance)</h1>
          <p class="text-sm text-gray-500">Review pending promotion tickets before forwarding them to Admin[cite: 2].</p>
        </div>
      </div>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="table-responsive bg-white rounded-xl overflow-hidden">
          <table class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Employee Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Current Role & Salary</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Proposed Role & New Salary</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Reason</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="financePromotionTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
  <script>
    let promotionRequests = [];
    const endpointUrl = 'finance_promotion.php';

    async function loadPromotionRequests() {
      try {
        const res = await fetch(`${endpointUrl}?action=fetch_finance_promotions`);
        const rawText = await res.text();
        
        try {
          promotionRequests = JSON.parse(rawText);
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
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No pending promotion requests requiring finance review.</td></tr>`;
        return;
      }
      promotionRequests.forEach(req => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-semibold text-gray-800">${req.full_name || 'N/A'} <br><small class="text-gray-400 font-mono font-normal">${req.custom_emp_id || ''}</small></td>
          <td class="py-3 px-4 text-gray-600">${req.department || 'Unassigned'}</td>
          <td class="py-3 px-4">
            <span class="text-xs text-gray-700 font-semibold block">${req.current_position || 'Staff'}</span>
            <span class="text-xs font-mono font-bold text-gray-900">₱${Number(req.current_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
          </td>
          <td class="py-3 px-4">
            <span class="text-xs text-amber-700 font-semibold block">${req.proposed_position || 'N/A'}</span>
            <span class="text-xs font-mono font-bold text-emerald-600">₱${Number(req.new_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
          </td>
          <td class="py-3 px-4 text-xs text-gray-600 max-w-xs truncate" title="${req.reason_for_promotion || ''}">
            ${req.reason_for_promotion || 'No reason provided'}
          </td>
          <td class="py-3 px-4 text-center">
            <div class="flex items-center justify-center gap-1.5">
              <button onclick="processDecision(${req.id}, 'Approved')" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg">
                <i class="bi bi-check-lg"></i> Approve
              </button>
              <button onclick="processDecision(${req.id}, 'Rejected')" class="btn btn-sm btn-outline-danger py-1 px-2 text-xs font-semibold rounded-lg">
                <i class="bi bi-x-lg"></i> Reject
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function processDecision(requestId, decision) {
      Swal.fire({
        title: `${decision} Request?`,
        text: `Are you sure you want to ${decision.toLowerCase()} this promotion?`,
        icon: decision === 'Approved' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: decision === 'Approved' ? '#198754' : '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${decision}`
      }).then(async (result) => {
        if (result.isConfirmed) {
          const formData = new URLSearchParams();
          formData.append('action', 'process_finance_promotion');
          formData.append('request_id', requestId);
          formData.append('decision', decision);

          try {
            const res = await fetch(endpointUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
              Swal.fire({ icon: 'success', title: 'Success!', text: data.message, timer: 1500, showConfirmButton: false });
              loadPromotionRequests();
            } else {
              Swal.fire('Error', data.message, 'error');
            }
          } catch (err) {
            Swal.fire('Error', 'Failed to communicate with server.', 'error');
          }
        }
      });
    }

    window.addEventListener('DOMContentLoaded', loadPromotionRequests);
  </script>
</body>
</html>