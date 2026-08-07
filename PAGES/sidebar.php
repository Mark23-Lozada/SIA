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

<!-- AGAD NA SCRIPT PARA SA STATE BAGO MAG-RENDER ANG PAGE -->
<script>
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed-mode', 'preload-no-transition');
    }
</script>

<style>
    /* Modern 50-Year Enterprise Software Developer Aesthetics (White Sidebar / Orange Theme) */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(249, 115, 22, 0.2); border-radius: 20px; }
    
    .nav-item-container {
        position: relative;
    }
    
    .nav-link-pill {
        position: relative;
        transition: all 0.2s ease-in-out;
    }
  
    .nav-link-active {
        background-color: #f8fafc !important; 
        color: #f97316 !important; 
        font-weight: 600;
        border-top-left-radius: 9999px;
        border-bottom-left-radius: 9999px;
        margin-right: -8px; 
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.05);
    }

    .nav-link-active::before,
    .nav-link-active::after {
        content: "";
        position: absolute;
        right: 0;
        width: 12px;
        height: 12px;
        pointer-events: none;
    }
    .nav-link-active::before {
        top: -12px;
        background: radial-gradient(circle at 0 0, transparent 70%, #f8fafc 71%);
    }
    .nav-link-active::after {
        bottom: -12px;
        background: radial-gradient(circle at 0 100%, transparent 70%, #f8fafc 71%);
    }

    /* HIGH-PERFORMANCE SMOOTH ANIMATION STYLES */
    #sidebar {
        width: 250px;
        min-width: 70px;
        max-width: 270px;
        height: 102vh;
        overflow: hidden;
        position: sticky;
        top: 0;
        will-change: width;
        transition: width 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    }

    /* Pigilan ang animation kapag naglo-load para walang lag/flash */
    .preload-no-transition #sidebar {
        transition: none !important;
    }

    /* Collapse States */
    .sidebar-collapsed-mode #sidebar {
        width: 70px !important;
    }
    
    /* Ayusin ang header kapag naka-collapse para hindi mag-siksikan at maputol */
    .sidebar-collapsed-mode #sidebarHeader {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding-right: 0 !important;
        margin-bottom: 12px !important;
    }

    .sidebar-collapsed-mode .hide-on-collapse {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.15s ease;
        display: none !important;
    }
    .sidebar-collapsed-mode .section-title span,
    .sidebar-collapsed-mode .section-title i {
        display: none !important;
    }
    .sidebar-collapsed-mode .section-title {
        border-top: 1px solid #e2e8f0;
        margin-top: 10px !important;
        padding-top: 10px !important;
    }
    .sidebar-collapsed-mode nav a {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-right: 0 !important;
        border-radius: 0.5rem !important;
    }
    .sidebar-collapsed-mode .nav-link-active {
        margin-right: 0 !important;
        border-radius: 0.5rem !important;
    }
    .sidebar-collapsed-mode .nav-link-active::before,
    .sidebar-collapsed-mode .nav-link-active::after {
        display: none !important;
    }
</style>

<div id="sidebar" 
     class="bg-white text-slate-600 pl-3 pr-2 py-4 flex flex-col shadow-2xl flex-shrink-0 z-20 border-r border-slate-200">
   
  <!-- Enterprise Branding Header & Burger Toggle -->
  <div id="sidebarHeader" class="flex items-center justify-between mb-3 pr-1 text-slate-800 flex-shrink-0 transition-all duration-300">
    <div class="flex items-center gap-1 overflow-hidden">
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg shadow-orange-500/20 flex-shrink-0">
        <i class="bi bi-cpu-fill text-sm text-white"></i>
      </div>
      <div class="hide-on-collapse whitespace-nowrap">
        <span class="font-bold text-xs tracking-wider block text-slate-900 uppercase font-mono">PannaKoda</span>
      </div>
    </div>
    <!-- Burger Toggle Button -->
    <button type="button" id="sidebarToggleBtn" class="text-slate-400 hover:text-orange-600 p-1.5 rounded-lg hover:bg-slate-100 transition flex-shrink-0">
      <i class="bi bi-list text-lg"></i>
    </button>
  </div>
    
  <nav class="flex-grow overflow-y-auto pr-1 space-y-1 custom-scrollbar">
    <style>
        .compact-link { font-size: 11.5px !important; padding-top: 6px !important; padding-bottom: 6px !important; padding-left: 10px !important; }
        .section-title { font-size: 9.5px !important; margin-top: 14px !important; margin-bottom: 4px !important; font-family: monospace; letter-spacing: 0.05em; }
    </style>
    <?php
      function renderCompactLink($url, $label, $icon, $current) {
          $isActive = (basename($url) === $current);
          $activeClass = $isActive ? 'nav-link-active' : 'hover:bg-slate-100 hover:text-slate-900 text-slate-500';
          $iconColor = $isActive ? 'text-orange-500' : 'text-slate-400 group-hover:text-orange-500';
          
          echo "<div class='nav-item-container' title='$label'>
                  <a href='$url' class='compact-link nav-link-pill group flex items-center gap-2.5 rounded-l-xl transition-all duration-150 $activeClass'>
                    <i class='$icon $iconColor text-xs transition-colors'></i> 
                    <span class='truncate tracking-tight hide-on-collapse'>$label</span>
                  </a>
                </div>";
      }

      // 1. ADMIN MENU
      if ($current_role === 'admin'): 
    ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-terminal text-[8px]"></i> <span class="hide-on-collapse">System Control</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix. 'dashboard.php', 'Applicant Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($frontendPrefix . 'pos_dash.php', 'POS Dashboard', 'bi-speedometer2', $exact_current_page);
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employee Management', 'bi bi-people-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branches_management.php', 'Add Branch', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branch_map.php', 'Branch Map', 'bi bi-map-fill', $exact_current_page);
        ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-shield-check text-[8px]"></i> <span class="hide-on-collapse">Authorization</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'admin_budget_approve.php', 'Budget Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_applicants.php', 'Applicants Management', 'bi-person-badge', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_leaves.php', 'Leave Management', 'bi bi-calendar-check', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_cash_advance.php', 'Cash Advance Management', 'bi bi-cash-coin', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_promotion.php', 'Promotion Management', 'bi bi-person-badge', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_reimbursement_approve.php', 'Reimbursement Approval', 'bi bi-cash-stack', $exact_current_page);
        ?>
    <?php 
      endif; 

      // 2. HR MENU
      if ($current_role === 'hr'): 
    ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-people text-[8px]"></i> <span class="hide-on-collapse">Human Resources</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'dashboard.php', 'Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'recruitment.php', 'Recruitment', 'bi bi-file-earmark-person-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employees', 'bi bi-people-fill', $exact_current_page);
        ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-check2-square text-[8px]"></i> <span class="hide-on-collapse">HR Compliance</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'Applicant.php', 'HR Applicants', 'bi bi-person-vcard', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'leave.php', 'HR Leaves', 'bi bi-list-task', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'attendance.php', 'Attendance', 'bi bi-clock-history', $exact_current_page);
        ?>
    <?php 
      endif; 

      // 3. MANAGER MENU
      if ($current_role === 'manager'): 
    ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-box-seam text-[8px]"></i> <span class="hide-on-collapse">Inventory Analytics</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
        ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-gear text-[8px]"></i> <span class="hide-on-collapse">Plant Operations</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'cooking.php', 'Cooking', 'bi bi-egg-fried', $exact_current_page);
          renderCompactLink($frontendPrefix . 'depart.php', 'Departs', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'recipe.php', 'Recipes', 'bi bi-journal-text', $exact_current_page);
          renderCompactLink($frontendPrefix . 'add_item.php', 'Add Item', 'bi bi-plus-circle-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'items.php', 'Manage Items', 'bi bi-grid-3x3-gap-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'inventory.php', 'Refill', 'bi bi-arrow-repeat', $exact_current_page);
          renderCompactLink($frontendPrefix . 'ingredients.php', 'Ingredients', 'bi bi-basket-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'manager_reimbursement_review.php', 'Reimbursement Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'manager_promotion.php', 'Employee Promotion', 'bi bi-person-badge', $exact_current_page);
        ?>
    <?php 
      endif; 

      // 4. FINANCE MENU
      if ($current_role === 'finance'): 
    ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-wallet2 text-[8px]"></i> <span class="hide-on-collapse">Financial Ledgers</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'pos_dash.php', 'POS Dashboard', 'bi-speedometer2', $exact_current_page);
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'salary_deduction.php', 'Salary Deductions', 'bi bi-dash-circle', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'tax.php', 'Tax Management', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee_salary.php' , 'Employee Salary', 'bi bi-cash-coin', $exact_current_page);
        ?>
        <div class="section-title uppercase text-orange-600 font-bold px-2 flex items-center gap-1">
          <i class="bi bi-file-earmark-medical text-[8px]"></i> <span class="hide-on-collapse">Financial Review</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'finance_promotion.php', 'Promotion Review', 'bi bi-person-badge', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'finance_cash_review.php', 'Cash Advance Review', 'bi bi-cash-coin', $exact_current_page);
        ?>
    <?php endif; ?>
  </nav>

  <!-- Developer Footer Action -->
  <button type="button" id="sidebarLogoutBtn" title="Log out" class="w-full flex items-center gap-2.5 px-3 py-2 mt-0 rounded-lg text-slate-500 hover:bg-rose-500/10 hover:text-rose-600 transition text-[11px] border border-slate-200 flex-shrink-0 group">
    <i class="bi bi-box-arrow-right text-slate-400 group-hover:text-rose-600 transition-colors"></i> 
    <span class="font-mono hide-on-collapse">Log out</span>
  </button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    setTimeout(function () {
        document.documentElement.classList.remove('preload-no-transition');
    }, 50);

    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            document.documentElement.classList.toggle('sidebar-collapsed-mode');
            if (document.documentElement.classList.contains('sidebar-collapsed-mode')) {
                localStorage.setItem('sidebar_collapsed', 'true');
            } else {
                localStorage.setItem('sidebar_collapsed', 'false');
            }
        });
    }

    const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
    if (sidebarLogoutBtn) {
        sidebarLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Log out',
                    text: "Are you sure you want to log out?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f97316', 
                    cancelButtonColor: '#e11d48',
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel',
                    background: '#ffffff',
                    color: '#0f172a'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "<?php echo $logoutActionUrl; ?>"; 
                    }
                });
            } else {
                if (confirm("Are you sure you want to log out?")) {
                    window.location.href = "<?php echo $logoutActionUrl; ?>";
                }
            }
        });
    }
});
</script>