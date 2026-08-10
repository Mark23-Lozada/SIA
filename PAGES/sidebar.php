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
    /* Custom Scrollbar & Theme Colors */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(126, 34, 206, 0.2); border-radius: 20px; }
    
    .nav-item-container {
        position: relative;
    }
    
    .nav-link-pill {
        position: relative;
        transition: all 0.2s ease-in-out;
    }

    /* Inactive Link Hover State: Text and icon turn purple, FORCE transparent background */
    .nav-link-pill:not(.nav-link-active):hover {
        color: var(--purple-primary) !important;
        background-color: transparent !important;
    }

    .nav-link-pill:not(.nav-link-active):hover i {
        color: var(--purple-primary, #7e22ce) !important;
    }
  
    /* Active Link State: Using var(--purple-primary) with White Text/Icon */
    .nav-link-active {
        background-color: var(--purple-primary, #7e22ce) !important; 
        color: #ffffff !important; 
        font-weight: 600;
        border-top-left-radius: 9999px;
        border-bottom-left-radius: 9999px;
        margin-right: -8px; 
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.05), 0 4px 12px rgba(126,34,206,0.25);
    }

    .nav-link-active i {
        color: #ffffff !important;
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
        background: radial-gradient(circle at 0 0, transparent 70%, var(--purple-primary, #7e22ce) 71%);
    }
    .nav-link-active::after {
        bottom: -12px;
        background: radial-gradient(circle at 0 100%, transparent 70%, var(--purple-primary, #7e22ce) 71%);
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
     class="bg-white text-black pl-3 pr-2 py-4 flex flex-col shadow-2xl flex-shrink-0 z-20 border-r border-slate-200">
   
  <!-- Enterprise Branding Header & Burger Toggle -->
  <div id="sidebarHeader" class="flex items-center justify-between mb-3 pr-1 text-black flex-shrink-0 transition-all duration-300">
    <div class="flex items-center gap-1 overflow-hidden">
      <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--purple-primary,#7e22ce)] to-[#a855f7] flex items-center justify-center shadow-lg shadow-purple-900/20 flex-shrink-0">
         <img src="../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" alt="image" style="width: 40px; height: 40px; border-radius: 20%;">
      </div>
      <div class="hide-on-collapse whitespace-nowrap">
        <span class="font-bold text-xs tracking-wider block text-black uppercase font-mono">Pannakoda</span>
      </div>
    </div>
    <!-- Burger Toggle Button -->
    <button type="button" id="sidebarToggleBtn" class="text-black hover:text-[var(--purple-primary,#7e22ce)] p-1.5 rounded-lg transition flex-shrink-0">
      <i class="bi bi-list text-lg"></i>
    </button>
  </div>
    
  <nav class="flex-grow overflow-y-auto pr-1 space-y-1 custom-scrollbar">
    <style>
        .compact-link { font-size: 11.5px !important; padding-top: 6px !important; padding-bottom: 6px !important; padding-left: 10px !important; }
        .section-title { font-size: 11px !important; margin-top: 14px !important; margin-bottom: 4px !important; font-family: monospace; font-weight: 800 !important; letter-spacing: 0.08em; color: var(--purple-primary, #7e22ce) !important; text-decoration: underline; text-underline-offset: 3px; }
        .section-title i { color: var(--purple-primary, #7e22ce) !important; }
    </style>
    <?php
      function renderCompactLink($url, $label, $icon, $current) {
          $isActive = (basename($url) === $current);
          $activeClass = $isActive ? 'nav-link-active' : 'text-black';
          $iconColor = $isActive ? 'text-white' : 'text-black'; 
          
          echo "<div class='nav-item-container' title='$label'>
                  <a href='$url' class='compact-link nav-link-pill group flex items-center gap-2.5 rounded-l-xl transition-all duration-150 $activeClass'>
                    <i class='$icon $iconColor text-xs'></i> 
                    <span class='truncate tracking-tight hide-on-collapse font-medium'>$label</span>
                  </a>
                </div>";
      }

      // 1. ADMIN MENU
      if ($current_role === 'admin'): 
    ?>
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-shield-check text-[10px]"></i> <span class="hide-on-collapse">AUTHORIZATION</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'admin_home.php', 'Admin Home', 'bi bi-house-door-fill', $exact_current_page);
       renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'all_sales.php', 'Sale Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employee Management', 'bi bi-people-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
        
          renderCompactLink($hrmsPrefix . 'branches_management.php', 'Add Branch', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branch_map.php', 'Branch Map', 'bi bi-map-fill', $exact_current_page);
        ?>
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-gear-fill text-[10px]"></i> <span class="hide-on-collapse">SYSTEM CONTROL</span>
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
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-people text-[10px]"></i> <span class="hide-on-collapse">HUMAN RESOURCES</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'dashboard.php', 'Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'recruitment.php', 'Recruitment', 'bi bi-file-earmark-person-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employees', 'bi bi-people-fill', $exact_current_page);
        ?>
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-check2-square text-[10px]"></i> <span class="hide-on-collapse">HR COMPLIANCE</span>
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
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-box-seam text-[10px]"></i> <span class="hide-on-collapse">INVENTORY ANALYTICS</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
        ?>
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-gear text-[10px]"></i> <span class="hide-on-collapse">PLANT OPERATIONS</span>
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
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-wallet2 text-[10px]"></i> <span class="hide-on-collapse">FINANCIAL LEDGERS</span>
        </div>
        <?php
           renderCompactLink($hrmsPrefix . 'all_sales.php', 'Sale Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'salary_deduction.php', 'Salary Deductions', 'bi bi-dash-circle', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'tax.php', 'Tax Management', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee_salary.php' , 'Employee Salary', 'bi bi-cash-coin', $exact_current_page);
        ?>
        <div class="section-title uppercase px-2 flex items-center gap-1.5">
          <i class="bi bi-file-earmark-medical text-[10px]"></i> <span class="hide-on-collapse">FINANCIAL REVIEW</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'finance_promotion.php', 'Promotion Review', 'bi bi-person-badge', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'finance_cash_review.php', 'Cash Advance Review', 'bi bi-cash-coin', $exact_current_page);
        ?>
    <?php endif; ?>
  </nav>

  <!-- Developer Footer Action -->
  <button type="button" id="sidebarLogoutBtn" title="Log out" class="w-full flex items-center gap-2.5 px-3 py-2 mt-0 rounded-lg text-black hover:text-[var(--purple-primary,#7e22ce)] hover:bg-transparent transition text-[11px] border border-slate-200 flex-shrink-0 group" style="margin-bottom: 10px;">
    <i class="bi bi-box-arrow-right text-black group-hover:text-[var(--purple-primary,#7e22ce)]"></i> 
    <span class="font-mono hide-on-collapse font-semibold">Log out</span>
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
                    confirmButtonColor: 'var(--purple-primary, #7e22ce)', 
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