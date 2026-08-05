<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'hr' && $current_role !== 'manager') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_promotion_request') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $request_type = $conn->real_escape_string($_POST['request_type'] ?? '');
    $effective_date = $conn->real_escape_string($_POST['effective_date'] ?? date('Y-m-d'));
    
    $proposed_position = $conn->real_escape_string($_POST['proposed_position'] ?? '');
    $reason_for_promotion = $conn->real_escape_string($_POST['reason_for_promotion'] ?? '');
    $increase_reason = $conn->real_escape_string($_POST['increase_reason'] ?? '');
    $increase_type = $conn->real_escape_string($_POST['increase_type'] ?? 'Percentage');
    $increase_value = floatval($_POST['increase_value'] ?? 0);
    
    $new_salary = floatval($_POST['new_salary'] ?? 0);

    if ($employee_id <= 0) {
        echo json_encode(["success" => false, "message" => "Please select a valid employee."]);
        exit;
    }

    if ($increase_type === 'Percentage' && ($increase_value < 0 || $increase_value > 100)) {
        echo json_encode(["success" => false, "message" => "The increase value for percentage cannot exceed 100% or be below 0."]);
        exit;
    }

    if ($increase_type === 'Fixed' && $increase_value < 0) {
        echo json_encode(["success" => false, "message" => "The fixed amount cannot be negative."]);
        exit;
    }

$stmt = $conn->prepare("INSERT INTO promotion_requests (employee_id, request_type, effective_date, proposed_position, reason_for_promotion, increase_reason, increase_type, increase_value, new_salary, status, finance_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Finance', 'Pending')");
    
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Database prepare error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("issssssdd", $employee_id, $request_type, $effective_date, $proposed_position, $reason_for_promotion, $increase_reason, $increase_type, $increase_value, $new_salary);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "message" => "The Promotion & Salary Adjustment Request has been submitted successfully for Finance review!"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Failed to save promotion request: " . $stmt->error
        ]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_hired_employees') {
    header('Content-Type: application/json');
    
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM employees WHERE status = 'hired' ORDER BY id DESC";
    $result = $conn->query($query);
    $employees = [];
    
    while($row = $result->fetch_assoc()) {
        $row['id'] = isset($row['id']) ? intval($row['id']) : 0;
        $row['display_emp_id'] = isset($row['employee_id']) && !empty($row['employee_id']) ? $row['employee_id'] : 'EMP-' . $row['id'];
        $row['role'] = $row['position_title'] ?? ($row['position'] ?? 'Staff');
        $row['salary'] = isset($row['salary']) ? floatval($row['salary']) : 22000.00;
        $row['date_hired'] = isset($row['date_hired']) && !empty($row['date_hired']) ? $row['date_hired'] : date('Y-m-d');
        $employees[] = $row;
    }
    
    echo json_encode($employees);
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Promotion & Salary Adjustment Portal</title>
  
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
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Employee Promotion & Salary Adjustment</h1>
          <p class="text-sm text-gray-500">Select an employee from the list to begin the form.</p>
        </div>
      </div>

      <div class="mb-4 flex items-center gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="relative flex-1 max-w-md">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
            <i class="bi bi-search"></i>
          </span>
          <input id="searchInput" type="text" class="form-control pl-10 pr-4 py-2 rounded-xl text-sm border-gray-200 focus:border-[#FF8C00] focus:ring-1 focus:ring-[#FF8C00]" placeholder="Search by ID, name, or department...">
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="table-responsive bg-white rounded-xl overflow-hidden">
          <table class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Employee ID</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Current Position</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-end">Current Salary</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="employeeTableBody">
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal fade" id="promotionModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl shadow-xl border-0">
          <div class="modal-header border-0 bg-slate-50 rounded-t-2xl px-6 py-4">
            <h5 class="modal-title font-bold text-gray-800 flex items-center gap-2">
              <i class="bi bi-file-earmark-text text-amber-600"></i> Promotion & Salary Adjustment Form
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <form id="promotionForm" onsubmit="submitPromotionRequest(event)">
            <div class="modal-body p-6 space-y-5 max-h-[75vh] overflow-y-auto text-sm">
              <input type="hidden" id="modalEmployeeId" name="employee_id">

              <!-- A. Employee Information (Auto-filled) -->
              <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h6 class="font-bold text-xs uppercase text-indigo-700 tracking-wider mb-3"><i class="bi bi-person-badge"></i> A. Employee Information (Auto-filled)</h6>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Employee ID</label>
                    <input type="text" id="infoEmpId" readonly class="form-control bg-white text-xs font-mono">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Employee Name</label>
                    <input type="text" id="infoEmpName" readonly class="form-control bg-white text-xs font-semibold">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Department</label>
                    <input type="text" id="infoDepartment" readonly class="form-control bg-white text-xs">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Current Position</label>
                    <input type="text" id="infoCurrentPosition" readonly class="form-control bg-white text-xs">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Employment Type</label>
                    <input type="text" id="infoEmploymentType" readonly class="form-control bg-white text-xs">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Date Hired</label>
                    <input type="text" id="infoDateHired" readonly class="form-control bg-white text-xs">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Years of Service</label>
                    <input type="text" id="infoYearsOfService" readonly class="form-control bg-white text-xs font-bold text-emerald-600">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Current Salary (PHP)</label>
                    <input type="text" id="infoCurrentSalaryDisplay" readonly class="form-control bg-white text-xs font-mono font-bold text-gray-800">
                    <input type="hidden" id="infoCurrentSalaryVal">
                  </div>
                </div>
              </div>

              <!-- B. Request Information -->
              <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h6 class="font-bold text-xs uppercase text-indigo-700 tracking-wider mb-3"><i class="bi bi-list-check"></i> B. Request Information</h6>
                <div class="grid grid-cols-3 gap-3 items-center">
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Request Type</label>
                    <select id="requestType" name="request_type" class="form-control text-xs" required onchange="toggleRequestTypeFields()">
                      <option value="Promotion">Promotion</option>
                      <option value="Salary Increase">Salary Increase</option>
                      <option value="Promotion with Salary Increase" selected>Promotion with Salary Increase</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Request Date (Auto)</label>
                    <input type="text" id="requestDate" readonly class="form-control bg-white text-xs font-mono">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Effective Date</label>
                    <input type="date" id="effectiveDate" name="effective_date" class="form-control text-xs" required>
                  </div>
                </div>
              </div>

              <!-- C. Promotion Details & Increase Reason -->
              <div id="promotionDetailsContainer" class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                <h6 class="font-bold text-xs uppercase text-indigo-700 tracking-wider mb-1"><i class="bi bi-award"></i> C. Promotion Details & Salary Adjustment</h6>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Current Position</label>
                    <input type="text" id="promoCurrentPosition" readonly class="form-control bg-white text-xs">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Proposed Position</label>
                    <input type="text" id="proposedPosition" name="proposed_position" class="form-control text-xs" placeholder="e.g. Senior Staff">
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason for Increase</label>
                    <select id="increaseReason" name="increase_reason" class="form-control text-xs" onchange="updateSuggestedIncrease()">
                      <option value="">-- Select Reason --</option>
                      <option value="Annual Increase">Annual Increase (3%–5%)</option>
                      <option value="Good Performance">Good Performance (5%–10%)</option>
                      <option value="Promotion">Promotion (10%–20% or higher)</option>
                      <option value="Merit Increase">Merit Increase (5%–15%)</option>
                      <option value="Market Adjustment">Market Adjustment (Company dependent)</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Increase Type</label>
                    <select id="increaseType" name="increase_type" class="form-control text-xs" onchange="onIncreaseTypeChange()">
                      <option value="Percentage">Percentage (%)</option>
                      <option value="Fixed">Fixed Amount (₱)</option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1" id="increaseValueLabel">Increase Value (Max 100%)</label>
                    <input type="number" step="0.01" id="increaseValue" name="increase_value" class="form-control text-xs" placeholder="Example: 10" value="10" min="0" max="100" oninput="calculateNewSalary()">
                    <small id="validationMsg" class="text-danger text-[10px] font-semibold" style="display:none;"></small>
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Increase Amount (Auto)</label>
                    <input type="text" id="increaseAmountAuto" readonly class="form-control bg-white text-xs font-mono text-amber-600 font-bold">
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">New Salary (Auto)</label>
                    <input type="text" id="newSalaryDisplay" readonly class="form-control bg-emerald-50 text-emerald-800 text-xs font-mono font-bold">
                    <input type="hidden" id="newSalaryVal" name="new_salary">
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Reason for Promotion / Adjustment (textarea)</label>
                  <textarea id="reasonForPromotion" name="reason_for_promotion" rows="2" class="form-control text-xs" placeholder="Provide details or justification in accordance with Philippine labor standards..."></textarea>
                </div>
              </div>

            </div>

            <div class="modal-footer border-0 bg-slate-50 rounded-b-2xl px-6 py-3 flex justify-end">
              <div class="flex gap-2">
                <button type="button" class="btn btn-light font-semibold border text-gray-600 text-xs px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="submitBtn" class="btn btn-dark font-semibold bg-amber-600 hover:bg-amber-700 border-0 text-xs px-4">
                  <i class="bi bi-send-check"></i> Submit Request
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>

  <script>
    let allEmployees = [];
    let promotionModalInstance = null;

    async function loadEmployees() {
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_hired_employees`);
        allEmployees = response.ok ? await response.json() : [];
        renderTable();
      } catch (err) {
        console.error("Error loading employees:", err);
      }
    }

    function renderTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      const tbody = document.getElementById('employeeTableBody');
      tbody.innerHTML = '';

      const filtered = allEmployees.filter(emp => {
        const customId = String(emp.display_emp_id).toLowerCase();
        const name = String(emp.full_name || '').toLowerCase();
        const role = String(emp.role || '').toLowerCase();
        const dept = String(emp.department || '').toLowerCase();
        return customId.includes(query) || name.includes(query) || role.includes(query) || dept.includes(query);
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No employees found.</td></tr>`;
        return;
      }

      filtered.forEach(emp => {
        const baseSalary = emp.salary ? parseFloat(emp.salary) : 0;
        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-mono font-semibold text-gray-700">${emp.display_emp_id}</td>
          <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name}</td>
          <td class="py-3 px-4"><span class="bg-blue-50 text-blue-700 border-blue-200 px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role}</span></td>
          <td class="py-3 px-4 text-gray-600">${emp.department || 'Unassigned'}</td>
          <td class="py-3 px-4 text-end font-bold text-gray-900">₱${baseSalary.toLocaleString('en-US', {minimumFractionDigits:2})}</td>
          <td class="py-3 px-4 text-center">
            <button onclick="openPromotionModal(${emp.id})" class="btn btn-sm btn-dark py-1.5 px-3 text-xs font-semibold rounded-lg flex items-center gap-1.5 mx-auto bg-amber-600 hover:bg-amber-700 border-0 shadow-sm">
              <i class="bi bi-award-fill"></i> Promote / Adjust
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function openPromotionModal(id) {
      const emp = allEmployees.find(e => e.id == id);
      if (!emp) return;

      document.getElementById('modalEmployeeId').value = emp.id;
      document.getElementById('infoEmpId').value = emp.display_emp_id;
      document.getElementById('infoEmpName').value = emp.full_name || '';
      document.getElementById('infoDepartment').value = emp.department || 'Unassigned';
      document.getElementById('infoCurrentPosition').value = emp.role || 'Staff';
      document.getElementById('infoEmploymentType').value = emp.employment_type || 'Regular';
      
      const dateHired = emp.date_hired || new Date().toISOString().split('T')[0];
      document.getElementById('infoDateHired').value = dateHired;

      const hiredDate = new Date(dateHired);
      const today = new Date();
      let diffYears = (today - hiredDate) / (1000 * 60 * 60 * 24 * 365.25);
      if (diffYears < 0) diffYears = 0;
      document.getElementById('infoYearsOfService').value = diffYears.toFixed(1) + ' Years';

      const currentSalary = emp.salary ? parseFloat(emp.salary) : 22000.00;
      document.getElementById('infoCurrentSalaryDisplay').value = '₱' + currentSalary.toLocaleString('en-US', {minimumFractionDigits: 2});
      document.getElementById('infoCurrentSalaryVal').value = currentSalary;

      const nowStr = new Date().toISOString().split('T')[0];
      document.getElementById('requestDate').value = nowStr;
      document.getElementById('effectiveDate').value = nowStr;
      document.getElementById('requestType').value = 'Promotion with Salary Increase';

      document.getElementById('promoCurrentPosition').value = emp.role || 'Staff';
      document.getElementById('proposedPosition').value = '';
      document.getElementById('increaseReason').value = 'Promotion';
      document.getElementById('increaseType').value = 'Percentage';
      
      const valInput = document.getElementById('increaseValue');
      valInput.max = '100';
      valInput.value = '15';
      document.getElementById('increaseValueLabel').innerText = 'Increase Value (Max 100%)';
      document.getElementById('validationMsg').style.display = 'none';

      document.getElementById('reasonForPromotion').value = '';

      calculateNewSalary();
      toggleRequestTypeFields();

      if (!promotionModalInstance) {
        promotionModalInstance = new bootstrap.Modal(document.getElementById('promotionModal'));
      }
      promotionModalInstance.show();
    }

    function toggleRequestTypeFields() {
      const container = document.getElementById('promotionDetailsContainer');
      if(container) container.style.display = 'block';
    }

    function onIncreaseTypeChange() {
      const increaseType = document.getElementById('increaseType').value;
      const increaseValueInput = document.getElementById('increaseValue');
      const label = document.getElementById('increaseValueLabel');

      if (increaseType === 'Percentage') {
        increaseValueInput.max = '100';
        label.innerText = 'Increase Value (Max 100%)';
        if (parseFloat(increaseValueInput.value) > 100) {
          increaseValueInput.value = '100';
        }
      } else {
        increaseValueInput.removeAttribute('max');
        label.innerText = 'Increase Value (Fixed Amount ₱)';
      }
      calculateNewSalary();
    }

    function updateSuggestedIncrease() {
      const reason = document.getElementById('increaseReason').value;
      const increaseValueInput = document.getElementById('increaseValue');
      const increaseTypeSelect = document.getElementById('increaseType');

      increaseTypeSelect.value = 'Percentage';
      onIncreaseTypeChange();

      if (reason === 'Annual Increase') {
        increaseValueInput.value = '4';
      } else if (reason === 'Good Performance') {
        increaseValueInput.value = '8';
      } else if (reason === 'Promotion') {
        increaseValueInput.value = '15';
      } else if (reason === 'Merit Increase') {
        increaseValueInput.value = '10';
      } else if (reason === 'Market Adjustment') {
        increaseValueInput.value = '10';
      }
      calculateNewSalary();
    }

    function calculateNewSalary() {
      const currentSalary = parseFloat(document.getElementById('infoCurrentSalaryVal').value) || 0;
      const increaseType = document.getElementById('increaseType').value;
      const increaseValInput = document.getElementById('increaseValue');
      const increaseVal = parseFloat(increaseValInput.value) || 0;
      const validationMsg = document.getElementById('validationMsg');
      const submitBtn = document.getElementById('submitBtn');

      let increaseAmount = 0;
      let hasError = false;

      if (increaseType === 'Percentage') {
        if (increaseVal > 100) {
          validationMsg.innerText = 'Maximum limit is 100% only.';
          validationMsg.style.display = 'block';
          hasError = true;
        } else if (increaseVal < 0) {
          validationMsg.innerText = 'Value cannot be negative.';
          validationMsg.style.display = 'block';
          hasError = true;
        } else {
          validationMsg.style.display = 'none';
          increaseAmount = currentSalary * (increaseVal / 100);
        }
      } else {
        if (increaseVal < 0) {
          validationMsg.innerText = 'Value cannot be negative.';
          validationMsg.style.display = 'block';
          hasError = true;
        } else {
          validationMsg.style.display = 'none';
          increaseAmount = increaseVal;
        }
      }

      if (hasError) {
        submitBtn.disabled = true;
        document.getElementById('increaseAmountAuto').value = '₱0.00';
        document.getElementById('newSalaryDisplay').value = '₱' + currentSalary.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('newSalaryVal').value = currentSalary.toFixed(2);
        return;
      } else {
        submitBtn.disabled = false;
      }

      const newSalary = currentSalary + increaseAmount;

      document.getElementById('increaseAmountAuto').value = '₱' + increaseAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      document.getElementById('newSalaryDisplay').value = '₱' + newSalary.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      document.getElementById('newSalaryVal').value = newSalary.toFixed(2);
    }

    async function submitPromotionRequest(event) {
      event.preventDefault();

      const increaseType = document.getElementById('increaseType').value;
      const increaseVal = parseFloat(document.getElementById('increaseValue').value) || 0;

      if (increaseType === 'Percentage' && (increaseVal < 0 || increaseVal > 100)) {
        Swal.fire('Warning', 'The percentage increase cannot exceed 100%.', 'warning');
        return;
      }

      const form = document.getElementById('promotionForm');
      const formData = new FormData(form);
      formData.append('action', 'submit_promotion_request');

      try {
        const res = await fetch(window.location.pathname, {
          method: 'POST',
          body: formData
        });
        const result = await res.json();
        
        if (result.success) {
          promotionModalInstance.hide();
          Swal.fire({
            icon: 'success',
            title: 'Submitted!',
            text: result.message,
            timer: 1800,
            showConfirmButton: false
          });
          loadEmployees();
        } else {
          Swal.fire('Error', result.message, 'error');
        }
      } catch (err) {
        console.error(err);
        Swal.fire('Error', 'Failed to process request.', 'error');
      }
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    window.addEventListener('DOMContentLoaded', loadEmployees);
  </script>
</body>
</html>