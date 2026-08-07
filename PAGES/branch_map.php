<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

$message = '';
$error = '';

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $del_stmt = $conn->prepare("DELETE FROM branches WHERE branch_id = ?");
    $del_stmt->bind_param("i", $delete_id);
    if ($del_stmt->execute()) {
        $message = "Branch successfully deleted from the database!";
    } else {
        $error = "Failed to delete branch.";
    }
    $del_stmt->close();
}

$branches_query = "SELECT * FROM branches ORDER BY created_at DESC";
$branches_result = $conn->query($branches_query);

$branches_list = [];
if ($branches_result && $branches_result->num_rows > 0) {
    while ($row = $branches_result->fetch_assoc()) {
        $address_parts = [];
        if (!empty($row['block_lot'])) {
            $address_parts[] = $row['block_lot'];
        }
        $address_parts[] = $row['street_name'];
        $address_parts[] = $row['barangay'];
        $address_parts[] = $row['municipality'];
        $address_parts[] = $row['city'];
        
        $row['full_address'] = implode(', ', $address_parts);
        $branches_list[] = $row;
    }
}
$total_branches = count($branches_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Select & Delete Branch Map</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar Integration -->
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 px-6 py-4 flex items-center justify-between shrink-0 shadow-xs">
                <h1 class="text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 bg-orange-500 rounded-full ring-4 ring-orange-500/10"></span> Branch Location Map
                </h1>
            </header>

            <!-- Inner Layout: Branch List Panel + Map View -->
            <div class="flex-1 flex overflow-hidden">
                
                <!-- Sidebar / Select Branches List (Left Inside Main) -->
                <div class="w-96 bg-white border-r border-slate-200/80 flex flex-col h-full shadow-xs z-10 shrink-0">
                    
                    <!-- Header with Total Branches Box -->
                    <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0">
                        <h2 class="font-semibold text-slate-800 text-xs tracking-wider uppercase flex items-center gap-2">
                            <i class="bi bi-shop-window text-orange-500 text-sm"></i> Branch Directory
                        </h2>
                        <!-- Total Branches Badge Counter -->
                        <div class="flex items-center gap-1.5 bg-orange-50 border border-orange-200/60 px-2.5 py-1 rounded-lg text-orange-700 shadow-2xs">
                            <i class="bi bi-layers-fill text-xs text-orange-500"></i>
                            <span class="text-xs font-bold"><?php echo $total_branches; ?></span>
                            <span class="text-[10px] font-medium text-orange-600/80 uppercase tracking-tight">Total</span>
                        </div>
                    </div>
                    
                    <!-- Alerts inside sidebar -->
                    <div class="px-3 pt-3">
                        <?php if (!empty($message)): ?>
                            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-xl text-xs mb-2 flex items-center gap-2 shadow-2xs animate-fade-in">
                                <i class="bi bi-check-circle-fill text-emerald-500 text-sm"></i> 
                                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl text-xs mb-2 flex items-center gap-2 shadow-2xs animate-fade-in">
                                <i class="bi bi-x-circle-fill text-red-500 text-sm"></i> 
                                <span class="font-medium"><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- List of Branches -->
                    <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-2.5 scrollbar-thin">
                        <?php if (count($branches_list) > 0): ?>
                            <?php foreach ($branches_list as $index => $branch): ?>
                                <?php $encoded_address = urlencode($branch['full_address']); ?>
                                <div class="relative group rounded-xl border border-slate-200/70 bg-white hover:bg-orange-50/30 hover:border-orange-200 transition-all duration-200 shadow-2xs hover:shadow-md">
                                    <!-- Clickable area to view map -->
                                    <div onclick="changeMap('<?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES); ?>', '<?php echo $encoded_address; ?>', '<?php echo htmlspecialchars($branch['full_address'], ENT_QUOTES); ?>')" 
                                         class="p-3.5 cursor-pointer">
                                        <div class="flex justify-between items-start pr-8">
                                            <h3 class="font-semibold text-slate-900 text-sm mb-1 group-hover:text-orange-600 transition-colors"><?php echo htmlspecialchars($branch['branch_name']); ?></h3>
                                        </div>
                                        <p class="text-xs text-slate-500 leading-relaxed mb-2.5 flex items-start gap-1.5">
                                            <i class="bi bi-geo-alt-fill text-slate-400 mt-0.5 shrink-0"></i> 
                                            <span><?php echo htmlspecialchars($branch['full_address']); ?></span>
                                        </p>
                                        <div class="inline-flex items-center gap-1.5 text-xs bg-slate-100/80 text-slate-600 px-2.5 py-1 rounded-md border border-slate-200/60 font-medium">
                                            <i class="bi bi-telephone-fill text-slate-400"></i> 
                                            <span><?php echo htmlspecialchars($branch['contact_number'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>

                                    <!-- Delete Button -->
                                    <a href="branch_map.php?delete_id=<?php echo $branch['branch_id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES); ?>?');"
                                       class="absolute top-3 right-3 text-slate-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition-all"
                                       title="Delete Branch">
                                        <i class="bi bi-trash-fill text-xs"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                    <i class="bi bi-shop text-xl"></i>
                                </div>
                                <p class="text-xs font-medium text-slate-600">No branches found</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Please add a branch first to populate the map.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map View Area (Right Side) -->
                <div class="flex-1 flex flex-col bg-slate-100 h-full relative overflow-hidden">
                    <div class="bg-white/90 backdrop-blur-md px-6 py-3.5 border-b border-slate-200/80 flex items-center justify-between shrink-0 shadow-2xs z-10">
                        <div>
                            <h2 id="mapTitle" class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                                <i class="bi bi-map-fill text-orange-500"></i> <span id="displayBranchName">Select a branch</span>
                            </h2>
                            <p id="displayAddress" class="text-xs text-slate-500 mt-0.5">Click a branch from the list to view its precise location details</p>
                        </div>
                        <!-- Open direct to Google Maps Route -->
                        <a id="externalNavBtn" href="#" target="_blank" class="hidden bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition-all items-center gap-2 shadow-sm hover:shadow active:scale-95">
                            <i class="bi bi-cursor-fill"></i> Get Directions
                        </a>
                    </div>

                    <!-- Google Maps iframe -->
                    <div class="flex-1 w-full bg-slate-200 relative">
                        <iframe 
                            id="mapIframe"
                            class="w-full h-full border-0"
                            loading="lazy"
                            allowfullscreen
                            src="">
                        </iframe>
                        
                        <!-- Placeholder screen before selection -->
                        <div id="mapPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-50/90 backdrop-blur-xs z-10">
                            <div class="w-16 h-16 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-500 mb-4 shadow-xs">
                                <i class="bi bi-geo-alt text-2xl"></i>
                            </div>
                            <h2 class="text-base font-bold text-slate-700">No Branch Selected</h2>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs text-center">Select a branch from the left panel to load the layout and geographical map interface.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function changeMap(branchName, encodedAddress, fullAddress) {
            document.getElementById('displayBranchName').innerText = branchName;
            document.getElementById('displayAddress').innerText = fullAddress;
            
            const mapUrl = `https://maps.google.com/maps?q=${encodedAddress}&t=&z=17&ie=UTF8&iwloc=&output=embed`;
            document.getElementById('mapIframe').src = mapUrl;

            const navBtn = document.getElementById('externalNavBtn');
            navBtn.href = `https://www.google.com/maps/dir/?api=1&destination=${encodedAddress}`;
            navBtn.classList.remove('hidden');
            navBtn.classList.add('flex');

            document.getElementById('mapPlaceholder').style.display = 'none';
        }

        // Auto-load the first branch on initialization if available
        <?php if (count($branches_list) > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const firstBranchName = "<?php echo addslashes($branches_list[0]['branch_name']); ?>";
                const firstAddressEncoded = "<?php echo urlencode($branches_list[0]['full_address']); ?>";
                const firstAddress = "<?php echo addslashes($branches_list[0]['full_address']); ?>";
                changeMap(firstBranchName, firstAddressEncoded, firstAddress);
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>