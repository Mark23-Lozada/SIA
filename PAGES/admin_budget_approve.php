<?php
ob_start();
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'finance' && $current_role !== 'hr') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$current_page = basename($_SERVER['PHP_SELF']);

// 1. FETCH BUDGET REQUESTS VIA AJAX
if (isset($_GET['action']) && $_GET['action'] === 'fetch_requests') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM budget_requests WHERE status = 'Approved by Finance' OR status LIKE '%Fully Approved%' OR status LIKE '%Rejected by Admin%' ORDER BY created_at DESC";
    $result = $conn->query($query);
    $requests = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $row['amount'] = floatval($row['amount']);
            $requests[] = $row;
        }
    }
    echo json_encode($requests);
    $conn->close();
    exit;
}

// 2. PROCESS APPROVAL ACTION
if (isset($_GET['action']) && $_GET['action'] === 'approve_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $req_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($req_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
        exit;
    }

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    $req_query = $conn->prepare("SELECT title FROM budget_requests WHERE id = ?");
    $req_query->bind_param("i", $req_id);
    $req_query->execute();
    $req_res = $req_query->get_result()->fetch_assoc();
    $req_query->close();
    
    if (!$req_res) {
        echo json_encode(['success' => false, 'message' => 'Budget request not found.']);
        $conn->close();
        exit;
    }

    // Update Inventory
    if (preg_match('/Restock\s+([0-9.]+)\s+\S+\s+of\s+(.+)/i', $req_res['title'], $matches)) {
        $restock_qty = (float)$matches[1];
        $ingredient_name = trim($matches[2]);
        $update_stock = $conn->prepare("UPDATE ingredients SET stock = stock + ? WHERE ingredient_name = ?");
        if ($update_stock) {
            $update_stock->bind_param("ds", $restock_qty, $ingredient_name);
            $update_stock->execute();
            $update_stock->close();
        }
    }

    $update_stmt = $conn->prepare("UPDATE budget_requests SET status = 'Fully Approved (Admin)' WHERE id = ?");
    $update_stmt->bind_param("i", $req_id);
    $update_stmt->execute();
    $update_stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Budget request approved and inventory updated successfully!']);
    exit;
}

// 3. PROCESS REJECTION ACTION
if (isset($_GET['action']) && $_GET['action'] === 'reject_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $req_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($req_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
        exit;
    }

    $conn = new mysqli($host, $user, $pass, $dbname);
    $update_stmt = $conn->prepare("UPDATE budget_requests SET status = 'Rejected by Admin' WHERE id = ?");
    $update_stmt->bind_param("i", $req_id);
    $update_stmt->execute();
    $update_stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Budget request has been rejected.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Final Budget Approval</title>
    <!-- Tailwind CSS CDN -->
    <script src="../LIBRARIES/tailwind.js"></script> 
    <!-- SweetAlert2 -->
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- AOS Animations -->
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased h-screen overflow-hidden">
  
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    
    <div class="flex-1 h-screen overflow-y-auto flex flex-col justify-between">
      <div class="p-8 max-w-7xl w-full mx-auto">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
          <div>
            
            <h1 class="text-2xl md:text-3xl font-bold text-amber-500 tracking-tight">Final Budget Approval</h1>
            <p class="text-slate-500 text-sm mt-1">Review finance-approved requests and authorize final inventory distribution or allocation.</p>
          </div>
          <div class="flex items-center gap-3">
            <button onclick="loadRequests()" class="inline-flex items-center gap-2 bg-white hover:bg-slate-100 text-slate-700 font-medium px-4 py-2.5 rounded-xl border border-slate-200 shadow-sm transition text-sm">
              <i class="bi bi-arrow-clockwise"></i> Refresh Data
            </button>
          </div>
        </div>
        
        <!-- Main Content Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="text-white border-b border-slate-200 bg-amber-500 text-xs font-semibold uppercase tracking-wider">
                  <th class="py-4 px-6">Request ID / Title</th>
                  <th class="py-4 px-6">Requested By</th>
                  <th class="py-4 px-6 text-end">Amount</th>
                  <th class="py-4 px-6 text-center">Status</th>
                  <th class="py-4 px-6 text-center">Actions</th>
                </tr>
              </thead>
              <tbody id="budgetBody" class="divide-y divide-slate-100 text-sm">
                <!-- Data populated dynamically -->
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    let allRequests = [];
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadRequests() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_requests`);
        allRequests = await response.json();
        renderTable();
      } catch (error) {
        console.error("Failed to load requests:", error);
      }
    }

    function renderTable() {
      const tbody = document.getElementById('budgetBody');
      tbody.innerHTML = '';

      if (allRequests.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" class="text-center py-12 text-slate-400">
              <div class="flex flex-col items-center justify-center gap-2">
                <i class="bi bi-inbox text-3xl"></i>
                <p class="text-sm font-medium">No pending requests available for final review.</p>
              </div>
            </td>
          </tr>`;
        return;
      }

      allRequests.forEach(req => {
        const isApproved = req.status.includes('Fully Approved');
        const isRejected = req.status.includes('Rejected');
        
        let statusBadge = '';
        if (isApproved) {
          statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>${req.status}</span>`;
        } else if (isRejected) {
          statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200/60"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>${req.status}</span>`;
        } else {
          statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/60"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>${req.status}</span>`;
        }

        const tr = document.createElement('tr');
        tr.className = "hover:bg-slate-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-4 px-6">
            <div class="font-semibold text-slate-900">${req.request_id || '#' + req.id}</div>
            <div class="text-xs text-slate-500 mt-0.5">${req.title}</div>
          </td>
          <td class="py-4 px-6 text-slate-600 font-medium">${req.requested_by}</td>
         <td class="py-3.5 px-4 text-end">
              <span class="inline-block px-3 py-1 bg-blue-50 text-amber-500 font-bold border border-blue-100 rounded-xl">
                  ₱${Number(req.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
              </span>
          </td>
          <td class="py-4 px-6 text-center">${statusBadge}</td>
          <td class="py-4 px-6 text-center">
            ${req.status === 'Approved by Finance' ? `
              <div class="inline-flex items-center gap-2">
                <button onclick="processAction(${req.id}, 'approve')" class="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
                <button onclick="processAction(${req.id}, 'reject')" class="inline-flex items-center gap-1 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition">
                  <i class="bi bi-x-lg"></i> Reject
                </button>
              </div>
            ` : `
              <span class="text-xs text-slate-400 font-medium italic">Completed</span>
            `}
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function processAction(id, actionType) {
      const actionName = actionType === 'approve' ? 'approve' : 'reject';
      
      const confirmResult = await Swal.fire({
        title: `Are you sure?`,
        text: `Do you want to ${actionName} this budget request?`,
        icon: actionType === 'approve' ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: actionType === 'approve' ? '#059669' : '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, ${actionName} it!`
      });

      if (!confirmResult.isConfirmed) return;

      const fd = new FormData();
      fd.append('id', id);
      try {
        const res = await fetch(`${phpEndpoint}?action=${actionType}_request`, { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) { 
          loadRequests(); 
          Swal.fire({
            title: 'Success!',
            text: data.message,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          }); 
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      } catch (err) {
        Swal.fire('Error', 'An unexpected network error occurred.', 'error');
      }
    }

    loadRequests();
  </script>
</body>
</html>
