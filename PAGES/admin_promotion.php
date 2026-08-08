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
            // 1. Update employee salary and position, at tiyakin na pananatilihin ang tamang role/position title[cite: 1]
            // 1. Update employee salary and position
           // 1. I-update lamang ang position_title at salary. Hayaang manatili ang role bilang 'Staff'.
$updateEmp = $conn->prepare("UPDATE employees SET position_title = ?, salary = ? WHERE id = ?");
$updateEmp->bind_param("sdi", $proposed_position, $new_salary, $employee_id);
$updateEmp->execute();
$updateEmp->close();
            // 2. Update promotion request status
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
// FETCH PENDING ADMIN PROMOTION REQUESTS ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'fetch_admin_promotions') {
    // Linisin ang anumang naunang output o warnings
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    // I-on ang error reporting bilang exception para mahuli ng try-catch
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
      $query = "SELECT pr.*, 
                         COALESCE(e.full_name, 'Unknown Employee') as full_name, 
                         COALESCE(e.department, 'Unassigned') as department, 
                         COALESCE(e.salary, 0) as current_salary,
                         COALESCE(e.position_title, 'Staff') as current_position 
                  FROM promotion_requests pr
                  LEFT JOIN employees e ON pr.employee_id = e.id 
                  WHERE pr.finance_status = 'Approved' 
                    AND (pr.status = 'Pending Admin' OR pr.admin_status = 'Pending')
                  ORDER BY pr.id DESC";
        $result = $conn->query($query);
        
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $row['current_salary'] = floatval($row['current_salary'] ?? 0);
            $row['new_salary'] = floatval($row['new_salary'] ?? 0);
            $requests[] = $row;
        }

        echo json_encode($requests);
    } catch (Exception $e) {
        echo json_encode(["error" => true, "message" => $e->getMessage()]);
    }

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
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Promotion & Salary Approvals (Admin)</h1>
          <p class="text-sm text-gray-500">Review and give final approval for promotion requests forwarded by Finance.</p>
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
            <tbody id="adminPromotionTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
  <script>
    let adminPromotionRequests = [];
    const adminEndpointUrl = 'admin_promotion.php';

    async function loadAdminPromotionRequests() {
      try {
        const res = await fetch(`${adminEndpointUrl}?action=fetch_admin_promotions`);
        const rawText = await res.text();
        
        try {
          adminPromotionRequests = JSON.parse(rawText);
          renderAdminTable();
        } catch (jsonErr) {
          console.error("JSON Parse Error:", jsonErr);
          adminPromotionRequests = [];
          renderAdminTable();
        }
      } catch (err) {
        console.error("Failed to load requests:", err);
      }
    }

    function renderAdminTable() {
      const tbody = document.getElementById('adminPromotionTableBody');
      tbody.innerHTML = '';
      if (!Array.isArray(adminPromotionRequests) || adminPromotionRequests.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No pending promotion requests requiring admin approval.</td></tr>`;
        return;
      }
      adminPromotionRequests.forEach(req => {
        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-semibold text-gray-800">${req.full_name || 'N/A'}</td>
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
              <button onclick="processAdminDecision(${req.id}, 'Approved')" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg">
                <i class="bi bi-check-lg"></i> Approve
              </button>
              <button onclick="processAdminDecision(${req.id}, 'Rejected')" class="btn btn-sm btn-outline-danger py-1 px-2 text-xs font-semibold rounded-lg">
                <i class="bi bi-x-lg"></i> Reject
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function processAdminDecision(requestId, decision) {
      Swal.fire({
        title: `${decision} Promotion Request?`,
        text: `Are you sure you want to ${decision.toLowerCase()} this promotion? This will update employee salary, position, and record the transaction.`,
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
            const res = await fixedFetch(adminEndpointUrl, formData);
            if (res.success) {
              Swal.fire({ icon: 'success', title: 'Success!', text: res.message, timer: 1800, showConfirmButton: false });
              loadAdminPromotionRequests();
            } else {
              Swal.fire('Error', res.message || 'Action failed.', 'error');
            }
          } catch (err) {
            Swal.fire('Error', 'Failed to communicate with server.', 'error');
          }
        }
      });
    }

    async function fixedFetch(url, formData) {
      const response = await fetch(url, { method: 'POST', body: formData });
      return await response.json();
    }

    window.addEventListener('DOMContentLoaded', loadAdminPromotionRequests);
  </script>
</body>
</html>