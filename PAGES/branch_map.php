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
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar Integration -->
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Branch Location Map
                </h1>
               
            </header>

            <!-- Inner Layout: Branch List Panel + Map View -->
            <div class="flex-1 flex overflow-hidden">
                
                <!-- Sidebar / Select Branches List (Left Inside Main) -->
                <div class="w-96 bg-white border-r border-gray-200 flex flex-col h-full shadow-sm z-10 shrink-0">
                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between shrink-0">
                        <h2 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                            <i class="bi bi-shop-window text-orange-500"></i> Select / Delete Branches
                        </h2>
                    </div>
                    
                    <!-- Alerts inside sidebar -->
                    <div class="p-3">
                        <?php if (!empty($message)): ?>
                            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-2 rounded-lg text-xs mb-2 flex items-center gap-1.5">
                                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg text-xs mb-2 flex items-center gap-1.5">
                                <i class="bi bi-x-circle-fill"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- List of Branches -->
                    <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-2">
                        <?php if (count($branches_list) > 0): ?>
                            <?php foreach ($branches_list as $index => $branch): ?>
                                <?php $encoded_address = urlencode($branch['full_address']); ?>
                                <div class="relative group rounded-xl border border-gray-100 bg-white hover:bg-orange-50/50 hover:border-orange-200 transition-all shadow-sm">
                                    <!-- Clickable area to view map -->
                                    <div onclick="changeMap('<?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES); ?>', '<?php echo $encoded_address; ?>', '<?php echo htmlspecialchars($branch['full_address'], ENT_QUOTES); ?>')" 
                                         class="p-4 cursor-pointer">
                                        <div class="flex justify-between items-start pr-8">
                                            <h3 class="font-bold text-gray-900 text-sm mb-1"><?php echo htmlspecialchars($branch['branch_name']); ?></h3>
                                        </div>
                                        <p class="text-xs text-gray-500 leading-tight mb-2">
                                            <i class="bi bi-geo-alt-fill text-gray-400"></i> <?php echo htmlspecialchars($branch['full_address']); ?>
                                        </p>
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md border border-gray-200">
                                            <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($branch['contact_number'] ?? 'N/A'); ?>
                                        </span>
                                    </div>

                                    <!-- Delete Button -->
                                    <a href="branch_map.php?delete_id=<?php echo $branch['branch_id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($branch['branch_name'], ENT_QUOTES); ?>?');"
                                       class="absolute top-3 right-3 text-gray-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                       title="Delete Branch">
                                        <i class="bi bi-trash-fill text-sm"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-6 text-center text-sm text-gray-400 italic">
                                No branches found. Please add a branch first.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map View Area (Right Side) -->
                <div class="flex-1 flex flex-col bg-gray-100 h-full relative overflow-hidden">
                    <div class="bg-white px-6 py-3 border-b border-gray-200 flex items-center justify-between shrink-0">
                        <div>
                            <h2 id="mapTitle" class="text-base font-bold text-gray-900 tracking-tight flex items-center gap-2">
                                <i class="bi bi-map-fill text-orange-500"></i> <span id="displayBranchName">Select a branch</span>
                            </h2>
                            <p id="displayAddress" class="text-xs text-gray-500 mt-0.5">Click a branch from the list to view its location</p>
                        </div>
                        <!-- Open direct to Google Maps Route -->
                        <a id="externalNavBtn" href="#" target="_blank" class="hidden bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors items-center gap-2 shadow-sm">
                            <i class="bi bi-cursor-fill"></i> Get Directions
                        </a>
                    </div>

                    <!-- Google Maps iframe -->
                    <div class="flex-1 w-full bg-gray-200 relative">
                        <iframe 
                            id="mapIframe"
                            class="w-full h-full border-0"
                            loading="lazy"
                            allowfullscreen
                            src="">
                        </iframe>
                        
                        <!-- Placeholder screen bago may ma-click -->
                        <div id="mapPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100 z-10">
                            <i class="bi bi-geo-alt text-6xl text-gray-300 mb-4"></i>
                            <h2 class="text-xl font-bold text-gray-400">No Branch Selected</h2>
                            <p class="text-sm text-gray-400">Select a branch from the left panel to show the map.</p>
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

        // Auto-load ang unang branch kung meron man
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