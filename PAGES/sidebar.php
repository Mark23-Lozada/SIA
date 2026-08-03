<?php
// Siguraduhing may session bago basahin ang role nang walang pwedeng mangyaring redirect o pagkawala ng data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$current_uri = $_SERVER['REQUEST_URI'];
$exact_current_page = basename($_SERVER['PHP_SELF']); 

// Dynamic path handling
$inFrontendFolder = (strpos($current_uri, 'FRONTEND') !== false);
$hrmsPrefix = $inFrontendFolder ? '../../PAGES/' : ''; 
$frontendPrefix = $inFrontendFolder ? '' : '../project-test1/FRONTEND/';
$logoutActionUrl = $hrmsPrefix . 'logout.php';
?>

<style>
    /* Custom Scrollbar para sa sidebar */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.2); border-radius: 20px; }
</style>

<div id="sidebar" 
     class="bg-[#212121] text-white/90 px-2 py-3 flex flex-col shadow-xl flex-shrink-0 z-20"
     style="width: 250px; min-width: 250px; max-width: 250px; height: 100vh; overflow: hidden; position: sticky; top: 0;">
  
  <div class="flex items-center gap-2 mb-4 px-2 text-white flex-shrink-0">
    <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center">
      <i class="bi bi-shop text-xs"></i>
    </div>
    <span class="font-bold text-sm tracking-wide">PannaKoda</span>
  </div>
    
  <nav class="flex-grow overflow-y-auto pr-1 space-y-0.5 custom-scrollbar">
    <style>
        .compact-link { font-size: 11.5px !important; padding-top: 4px !important; padding-bottom: 4px !important; }
        .section-title { font-size: 9px !important; margin-top: 8px !important; margin-bottom: 2px !important; }
    </style>
    <?php
      // Helper para sa compact links
      function renderCompactLink($url, $label, $icon, $current) {
          $active = (basename($url) === $current) ? 'bg-[#FF8C00] text-white' : 'hover:bg-white/10';
          echo "<a href='$url' class='compact-link flex items-center gap-2 px-2 py-1 rounded transition $active'>
                  <i class='$icon'></i> $label
                </a>";
      }

      // 1. ADMIN MENU[cite: 3]
      if ($current_role === 'admin'): 
    ?>
        <div class="section-title uppercase tracking-wider text-white/40 font-bold px-2">Admin Control</div>
        <?php
      
          renderCompactLink($frontendPrefix . 'pos_dash.php', 'POS Dashboard', 'bi-speedometer2', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_cash_advance.php', 'Cash Advance Management', 'bi bi-cash-coin', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_liquidation_approve.php', 'Liquidation Approval', 'bi bi-file-earmark-check', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_reimbursement_approve.php', 'Reimbursement Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix .'admin_promotion_approve.php', 'Employee Promotion Approval', 'bi bi-award', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_resignation_approve.php', 'Employee Resignation Approval', 'bi bi-person-x', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_applicants.php', 'Admin Applicants Management', 'bi-person-badge', $exact_current_page);
         renderCompactLink($hrmsPrefix . 'admin_leaves.php', 'Leave Management', 'bi bi-calendar-check', $exact_current_page);
        renderCompactLink($hrmsPrefix . 'admin_budget_approve.php', 'Budget Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branches_management.php', 'Add Branch', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branch_map.php', 'Branch Map', 'bi bi-map-fill', $exact_current_page);
 
      
        ?>
    <?php 
      endif; 

      // 2. HR MENU[cite: 3]
      if ($current_role === 'hr'): 
    ?>
        <div class="section-title uppercase tracking-wider text-white/40 font-bold px-2">Human Resources</div>
        <?php
          renderCompactLink($hrmsPrefix . 'dashboard.php', 'Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'recruitment.php', 'Recruitment', 'bi bi-file-earmark-person-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'Applicant.php', 'HR Applicants', 'bi bi-person-vcard', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employees', 'bi bi-people-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'leave.php', 'HR Leaves', 'bi bi-list-task', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'attendance.php', 'Attendance', 'bi bi-clock-history', $exact_current_page);
        ?>
    <?php 
      endif; 

      // 3. MANAGER MENU[cite: 3]
      if ($current_role === 'manager'): 
    ?>
        <div class="section-title uppercase tracking-wider text-white/40 font-bold px-2">Inventory & Operations</div>
        <?php
       
          renderCompactLink($frontendPrefix . 'add_item.php', 'Add Item', 'bi bi-plus-circle-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'items.php', 'Manage Items', 'bi bi-grid-3x3-gap-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'ingredients.php', 'Ingredients', 'bi bi-basket-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'inventory.php', 'Refill', 'bi bi-arrow-repeat', $exact_current_page);
          renderCompactLink($frontendPrefix . 'recipe.php', 'Recipes', 'bi bi-journal-text', $exact_current_page);
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($frontendPrefix . 'cooking.php', 'Cooking', 'bi bi-egg-fried', $exact_current_page);
          renderCompactLink($frontendPrefix . 'depart.php', 'Departs', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'manager_reimbursement_review.php', 'Reimbursement Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'liquidation_request.php', 'Liquidation Review', 'bi bi-file-earmark-check', $exact_current_page);
      

      
        ?>
    <?php 
      endif; 

      // 4. FINANCE MENU[cite: 3]
      if ($current_role === 'finance'): 
    ?>
        <div class="section-title uppercase tracking-wider text-white/40 font-bold px-2">Finance</div>
        <?php
        renderCompactLink($frontendPrefix . 'pos_dash.php', 'POS Dashboard', 'bi-speedometer2', $exact_current_page);
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
           renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
                    renderCompactLink($hrmsPrefix . 'finance_budget_approve.php', 'Budget Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'tax.php', 'Tax Management', 'bi bi-diagram-3-fill', $exact_current_page);
      
        ?>
    <?php endif; ?>
  </nav>

  <button type="button" id="sidebarLogoutBtn" class="w-full flex items-center gap-2 px-2 py-1.5 mt-2 rounded text-white/70 hover:bg-white/10 hover:text-white transition text-[11px] border border-white/20 flex-shrink-0">
    <i class="bi bi-box-arrow-right"></i> Log out
  </button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
    if (sidebarLogoutBtn) {
        sidebarLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Log out',
                    text: "Are you sure you want to Log out?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#FF8C00', 
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'I-cancel',
                    background: '#ffffff',
                    color: '#212121'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "<?php echo $logoutActionUrl; ?>"; 
                    }
                });
            } else {
                if (confirm("Are you sure you want to Log out?")) {
                    window.location.href = "<?php echo $logoutActionUrl; ?>";
                }
            }
        });
    }
});
</script>