<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Database Connection Failed: " . $conn->connect_error]);
        exit;
    }
    die("Database Connection Failed: " . $conn->connect_error);
}

// HANDLE ADMIN APPROVAL / REJECTION ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_admin_promotion') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $request_id = intval($_POST['request_id'] ?? 0);
    $decision = $conn->real_escape_string($_POST['decision'] ?? ''); 

    if ($request_id <= 0 || !in_array($decision, ['Approved', 'Rejected'])) {
        echo json_encode(["success" => false, "message" => "Invalid parameters provided."]);
        exit;
    }

    $reqQuery = "SELECT pr.*, e.full_name, e.department FROM promotion_requests pr LEFT JOIN employees e ON pr.employee_id = e.id WHERE pr.id = $request_id LIMIT 1";
    $reqResult = $conn->query($reqQuery);

    if (!$reqResult || $reqResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Promotion request not found."]);
        exit;
    }

    $promoData = $reqResult->fetch_assoc();
    $employee_id = intval($promoData['employee_id']);
    $employee_name = $promoData['full_name'] ?? 'Unknown Employee';
    $department = $promoData['department'] ?? 'Management';
    $proposed_position = $conn->real_escape_string($promoData['proposed_position']);
    $new_salary = floatval($promoData['new_salary']);

    if ($decision === 'Approved') {
        $conn->begin_transaction();

        try {
            // 1. Update employee salary and position[cite: 1]
            $updateEmp = $conn->prepare("UPDATE employees SET position_title = ?, salary = ? WHERE id = ?");
            $updateEmp->bind_param("sdi", $proposed_position, $new_salary, $employee_id);
            $updateEmp->execute();
            $updateEmp->close();

            // 2. Update promotion request status (Inalis ang admin_approval_date para maiwasan ang error)
            $updateReq = $conn->prepare("UPDATE promotion_requests SET status = 'Approved', admin_status = 'Approved' WHERE id = ?");
            $updateReq->bind_param("i", $request_id);
            $updateReq->execute();
            $updateReq->close();

            // 3. Record transaction to budget_requests so it reflects in transaction.php
            $transId = "PROMO-" . $request_id;
            $transTitle = "Promotion & Salary Adjustment: " . $proposed_position;
            $insertTrans = $conn->prepare("INSERT INTO budget_requests (request_id, title, requested_by, department, amount, status, created_at) VALUES (?, ?, ?, ?, ?, 'Approved', NOW())");
            $insertTrans->bind_param("ssssd", $transId, $transTitle, $employee_name, $department, $new_salary);
            $insertTrans->execute();
            $insertTrans->close();
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Promotion approved successfully! Employee salary/position updated and recorded in transactions."]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "Transaction failed: " . $e->getMessage()]);
        }
    } else {
        $updateReq = $conn->prepare("UPDATE promotion_requests SET status = 'Rejected', admin_status = 'Rejected' WHERE id = ?");
        $updateReq->bind_param("i", $request_id);
        if ($updateReq->execute()) {
            echo json_encode(["success" => true, "message" => "Promotion request has been rejected."]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update request status."]);
        }
        $updateReq->close();
    }

    $conn->close();
    exit;
}

// FETCH PENDING ADMIN PROMOTION REQUESTS ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'fetch_admin_promotions') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $query = "SELECT pr.*, 
                     COALESCE(e.full_name, 'Unknown Employee') as full_name, 
                     COALESCE(e.department, 'Unassigned') as department, 
                     COALESCE(e.salary, 0) as current_salary,
                     COALESCE(e.position_title, 'Staff') as current_position 
              FROM promotion_requests pr
              LEFT JOIN employees e ON pr.employee_id = e.id 
              WHERE pr.finance_status = 'Approved' 
                AND pr.status = 'Pending Admin'
              ORDER BY pr.id DESC";
              
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(["error" => $conn->error]);
        $conn->close();
        exit;
    }

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $row['current_salary'] = floatval($row['current_salary'] ?? 0);
        $row['new_salary'] = floatval($row['new_salary'] ?? 0);
        $requests[] = $row;
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
  <title>Admin Promotion & Salary Approval Portal</title>
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
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Admin Promotion Approvals</h1>
          <p class="text-sm text-gray-500">Review and authorize employee promotions and salary adjustments pre-approved by Finance.</p>
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
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Reason / Details</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="adminPromotionTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
  <script>
    let promotionRequests = [];

    async function loadPromotionRequests() {
      try {
        const baseUrl = window.location.href.split('?')[0];
        const res = await fetch(`${baseUrl}?action=fetch_admin_promotions`);
        const rawText = await res.text();
        try {
          const data = JSON.parse(rawText);
          promotionRequests = Array.isArray(data) ? data : [];
        } catch (jsonErr) {
          promotionRequests = [];
        }
        renderTable();
      } catch (err) {
        console.error("Failed to load requests:", err);
      }
    }

    function renderTable() {
      const tbody = document.getElementById('adminPromotionTableBody');
      tbody.innerHTML = '';
      if (promotionRequests.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No finance-approved promotion requests requiring admin approval.</td></tr>`;
        return;
      }
      promotionRequests.forEach(req => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-semibold text-gray-800">${req.full_name} <br><small class="text-gray-400 font-mono font-normal">ID: ${req.employee_id || ''}</small></td>
          <td class="py-3 px-4 text-gray-600">${req.department || 'Unassigned'}</td>
          <td class="py-3 px-4">
            <span class="text-xs text-gray-700 font-semibold block">${req.current_position || 'Staff'}</span>
            <span class="text-xs font-mono font-bold text-gray-900">₱${Number(req.current_salary || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</span>
          </td>
          <td class="py-3 px-4">
            <span class="text-xs text-amber-700 font-semibold block">${req.proposed_position}</span>
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
        text: `Are you sure you want to ${decision === 'Approved' ? 'approve this promotion and update the employee salary' : 'reject this promotion'}?`,
        icon: decision === 'Approved' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: decision === 'Approved' ? '#198754' : '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${decision}`
      }).then(async (result) => {
        if (result.isConfirmed) {
          const formData = new URLSearchParams();
          formData.append('action', 'process_admin_promotion');
          formData.append('request_id', requestId);
          formData.append('decision', decision);

          try {
            const baseUrl = window.location.href.split('?')[0];
            const res = await fetch(baseUrl, { method: 'POST', body: formData });
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