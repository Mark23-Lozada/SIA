<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once __DIR__ . '/../BACKEND/db_inventory.php';

// ============================================================
// STATUS IS NEVER STORED -- it's computed live from timestamps:
//   NOW() >= sales.ready_at AND served_at IS NULL  -> shows here (Ready)
//   served_at is set below when staff marks it served, which then
//   removes it from this list on the next refresh.
// ============================================================

// POST: mark an order as served/picked up
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_served'])) {
    header('Content-Type: application/json');
    $sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
    if ($sale_id > 0) {
        $stmt = $conn->prepare("UPDATE sales SET served_at = NOW() WHERE id = ? AND served_at IS NULL");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "success"]);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: text/html');

    $orders_result = $conn->query(
        "SELECT id, ready_at
         FROM sales
         WHERE ready_at IS NOT NULL
           AND ready_at <= NOW()
           AND served_at IS NULL
         ORDER BY ready_at ASC"
    );

    $orders = [];
    if ($orders_result) {
        while ($row = $orders_result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    if (empty($orders)) {
        echo '<div class="col-12"><div class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size: 2.5rem;"></i><p class="mt-2">No orders ready right now.</p></div></div>';
        exit();
    }

    $item_stmt = $conn->prepare(
        "SELECT items.item_name, sales_items.quantity
         FROM sales_items
         JOIN items ON sales_items.item_id = items.id
         WHERE sales_items.sale_id = ?"
    );

    foreach ($orders as $order) {
        $item_stmt->bind_param("i", $order['id']);
        $item_stmt->execute();
        $items_res = $item_stmt->get_result();
        $item_lines = [];
        while ($line = $items_res->fetch_assoc()) {
            $item_lines[] = htmlspecialchars($line['quantity'] . '× ' . $line['item_name']);
        }
        ?>
        <div class="col-md-4 col-sm-6">
            <div class="card p-3 h-100" style="border: 2px solid #198754; background: #f2fbf5;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold" style="color: #198754;">#TXN-<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Ready</span>
                </div>
                <ul class="small mb-3 ps-3">
                    <?php foreach ($item_lines as $line): ?>
                        <li><?php echo $line; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn-success btn-sm w-100 mark-served-btn" data-sale-id="<?php echo $order['id']; ?>">
                    <i class="bi bi-box-arrow-right"></i> Mark as Served
                </button>
            </div>
        </div>
        <?php
    }
    $item_stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Kitchen (Ready / Depart)</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
        <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #eeeed8; }
        .brand-text { color: #911d1d; }
        .kitchen-nav a { color: #911d1d; text-decoration: none; font-weight: 600; }
        .kitchen-nav a.active { border-bottom: 3px solid #911d1d; }
    </style>
</head>

<body>
  <div class="flex h-screen w-full overflow-hidden">
        <?php include '../../PAGES/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Page Header -->
                <div class="mb-8 border-b border-orange-100 pb-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Delivery Display</h1>
                        <p class="text-sm text-gray-500 mt-1">Live delivery monitor</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm bg-white px-3 py-1.5 rounded-lg shadow-sm border border-orange-100">
                        <span class="flex h-2 w-2 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                        </span>
                        <span class="text-gray-600 font-medium">Auto-refreshing</span>
                    </div>
                </div>

    <main class="p-4">
        <div id="orders-container" class="row g-3">
            <div class="col-12 text-center py-5 text-muted">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Loading orders...</p>
            </div>
        </div>
    </main>
</div>
    <script>
        function fetchOrders() {
            fetch('depart.php?ajax=1')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('orders-container').innerHTML = html;
                    attachServedHandlers();
                })
                .catch(err => console.error('Error fetching ready orders:', err));
        }

        function attachServedHandlers() {
            document.querySelectorAll('.mark-served-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const saleId = this.getAttribute('data-sale-id');
                    const formData = new FormData();
                    formData.append('mark_served', '1');
                    formData.append('sale_id', saleId);

                    fetch('depart.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') fetchOrders(); // refresh immediately, don't wait for the 10s cycle
                        })
                        .catch(err => console.error('Error marking order served:', err));
                });
            });
        }

        fetchOrders();
        setInterval(fetchOrders, 10000); // required refresh cadence
    </script>
</body>
</html>
<?php $conn->close(); ?>