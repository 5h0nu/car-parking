<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch all master slots from database configuration
$all_slots = [];
try {
    $all_slots = $pdo->query("SELECT slot_number FROM slots ORDER BY slot_number")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database config query error: " . $e->getMessage());
}

// Fetch active check-ins to map slot details
$occupied_map = [];
$occupied_count = 0;
try {
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'parked'");
    $active_tickets = $stmt->fetchAll();
    
    foreach ($active_tickets as $ticket) {
        $occupied_map[$ticket['slot_number']] = [
            'plate' => $ticket['plate_number'],
            'owner' => $ticket['owner_name'],
            'type' => $ticket['vehicle_type'],
            'time' => $ticket['check_in_time']
        ];
    }
    $occupied_count = count($occupied_map);
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

$available_count = count($all_slots) - $occupied_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Slots - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .slots-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .slot-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px 10px;
            text-align: center;
            position: relative;
            transition: all var(--transition-speed);
        }
        
        .slot-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .slot-id {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
        }
        
        .slot-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 6px;
            letter-spacing: 0.5px;
        }
        
        .slot-box.available {
            border-top: 4px solid var(--success);
        }
        
        .slot-box.available .slot-badge {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .slot-box.occupied {
            border-top: 4px solid var(--danger);
        }
        
        .slot-box.occupied .slot-badge {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .slot-detail-line {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .slot-owner {
            display: block;
            font-size: 11px;
            color: var(--primary-light);
            margin-top: 2px;
            font-style: italic;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 25px;
            font-size: 14px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
        }

        .legend-indicator {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Parking Slots Grid</h1>
            </div>

            <!-- Capacity summary cards -->
            <div class="dashboard-grid" style="margin-bottom: 30px;">
                <div class="metric-card" style="padding: 15px 25px;">
                    <div class="metric-info">
                        <span class="metric-value"><?php echo count($all_slots); ?></span>
                        <span class="metric-label">Total Slots</span>
                    </div>
                </div>
                <div class="metric-card" style="padding: 15px 25px;">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--danger);"><?php echo $occupied_count; ?></span>
                        <span class="metric-label">Occupied</span>
                    </div>
                </div>
                <div class="metric-card" style="padding: 15px 25px;">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--success);"><?php echo $available_count; ?></span>
                        <span class="metric-label">Available</span>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <h2>Operational Slots Status Map</h2>
                <p>Verify active parking slot assignments, plate numbers, and vehicle owner registers below.</p>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-indicator" style="background: var(--success);"></div>
                        <span>Available (Empty)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-indicator" style="background: var(--danger);"></div>
                        <span>Occupied (Parked)</span>
                    </div>
                </div>

                <div class="slots-container">
                    <?php foreach ($all_slots as $slot): ?>
                        <?php if (isset($occupied_map[$slot])): 
                            $info = $occupied_map[$slot];
                        ?>
                            <div class="slot-box occupied">
                                <div style="margin-bottom: 10px;">
                                    <img src="images.png" alt="Car Icon" style="width: 32px; height: 32px; object-fit: contain; filter: drop-shadow(0 0 4px rgba(239, 68, 68, 0.4));">
                                </div>
                                <span class="slot-id"><?php echo htmlspecialchars($slot); ?></span>
                                <span class="slot-badge">Occupied</span>
                                <span class="slot-detail-line" style="color: #fff; font-weight: 600;"><?php echo htmlspecialchars($info['plate']); ?></span>
                                <span class="slot-owner">by <?php echo htmlspecialchars($info['owner']); ?></span>
                                <span class="slot-detail-line" style="font-size: 10px;"><?php echo htmlspecialchars($info['type']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="slot-box available">
                                <div style="margin-bottom: 10px; opacity: 0.15;">
                                    <img src="images.png" alt="Car Icon" style="width: 32px; height: 32px; object-fit: contain; filter: grayscale(100%);">
                                </div>
                                <span class="slot-id"><?php echo htmlspecialchars($slot); ?></span>
                                <span class="slot-badge">Available</span>
                                <span class="slot-detail-line" style="opacity: 0.3;">Empty Slot</span>
                                <span class="slot-owner" style="visibility: hidden;">None</span>
                                <span class="slot-detail-line" style="visibility: hidden;">None</span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
