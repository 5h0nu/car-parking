<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

$success_message = '';
$error_message = '';

// Variables for checkout flow
$ticket = null;
$amount_due = 0;
$duration_hours = 0;
$duration_text = '';
$completed_ticket = null;

// Fetch settings for pricing rates
$rates = [];
try {
    $stmt = $pdo->query("SELECT key_name, val_value FROM settings");
    $rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {}
$rate_2_wheel = (int)($rates['rate_2_wheel'] ?? 50);
$rate_4_wheel = (int)($rates['rate_4_wheel'] ?? 100);
$rate_truck_heavy = (int)($rates['rate_truck_heavy'] ?? 150);

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Check-In Form Submit
    if (isset($_POST['action']) && $_POST['action'] === 'check_in') {
        $owner_name = trim(filter_input(INPUT_POST, 'owner_name', FILTER_SANITIZE_SPECIAL_CHARS));
        $plate_number = trim(filter_input(INPUT_POST, 'plate_number', FILTER_SANITIZE_SPECIAL_CHARS));
        $vehicle_type = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $slot_number = filter_input(INPUT_POST, 'slot_number', FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($owner_name) || empty($plate_number) || empty($vehicle_type) || empty($slot_number)) {
            $error_message = "All check-in fields are required.";
        } else {
            try {
                // Verify slot availability
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE slot_number = :slot AND status = 'parked'");
                $stmt->execute(['slot' => $slot_number]);
                $occupied = $stmt->fetchColumn();
                
                if ($occupied) {
                    $error_message = "Slot {$slot_number} is already occupied.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO tickets (owner_name, plate_number, vehicle_type, slot_number, check_in_time, status)
                        VALUES (:owner_name, :plate_number, :vehicle_type, :slot_number, NOW(), 'parked')
                    ");
                    $stmt->execute([
                        'owner_name' => $owner_name,
                        'plate_number' => strtoupper($plate_number),
                        'vehicle_type' => $vehicle_type,
                        'slot_number' => $slot_number
                    ]);
                    $success_message = "Vehicle <strong>" . htmlspecialchars(strtoupper($plate_number)) . "</strong> checked in successfully to slot <strong>" . htmlspecialchars($slot_number) . "</strong>!";
                }
            } catch (PDOException $e) {
                $error_message = "Database error during check-in: " . $e->getMessage();
            }
        }
    }

    // 2. Check-Out Search Submit
    if (isset($_POST['action']) && $_POST['action'] === 'search_exit') {
        $search_plate = trim(filter_input(INPUT_POST, 'plate_number', FILTER_SANITIZE_SPECIAL_CHARS));
        if (empty($search_plate)) {
            $error_message = "Please enter a license plate or slot number.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM tickets 
                    WHERE (plate_number = :search1 OR slot_number = :search2) 
                      AND status = 'parked' 
                    LIMIT 1
                ");
                $stmt->execute([
                    'search1' => strtoupper($search_plate),
                    'search2' => strtoupper($search_plate)
                ]);
                $ticket = $stmt->fetch();
                
                if (!$ticket) {
                    $error_message = "No active check-in found for plate or slot: " . htmlspecialchars($search_plate);
                } else {
                    // Calculation logic (timezone aligned)
                    $in_time = new DateTime($ticket['check_in_time']);
                    $out_time = new DateTime();
                    $interval = $in_time->diff($out_time);
                    
                    $duration_seconds = $out_time->getTimestamp() - $in_time->getTimestamp();
                    $duration_hours = ceil($duration_seconds / 3600);
                    if ($duration_hours <= 0) {
                        $duration_hours = 1;
                    }
                    
                    $days = $interval->d;
                    $hours = $interval->h + ($days * 24);
                    $minutes = $interval->i;
                    $duration_text = "{$hours} hr(s) {$minutes} min(s)";
                    
                    $rate_per_hour = $rate_4_wheel;
                    if ($ticket['vehicle_type'] === '2-Wheel') {
                        $rate_per_hour = $rate_2_wheel;
                    } elseif ($ticket['vehicle_type'] === 'Truck/Heavy') {
                        $rate_per_hour = $rate_truck_heavy;
                    }
                    
                    $amount_due = $duration_hours * $rate_per_hour;
                }
            } catch (PDOException $e) {
                $error_message = "Search error: " . $e->getMessage();
            }
        }
    }

    // 3. Check-Out Confirm Submit
    if (isset($_POST['action']) && $_POST['action'] === 'confirm_checkout') {
        $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
        $final_amount = filter_input(INPUT_POST, 'amount_due', FILTER_VALIDATE_FLOAT);
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (!$ticket_id || $final_amount === false || empty($payment_method)) {
            $error_message = "Invalid checkout parameters.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tickets 
                    SET status = 'checked_out', check_out_time = NOW(), amount_paid = :amount, payment_method = :method
                    WHERE id = :id AND status = 'parked'
                ");
                $stmt->execute([
                    'amount' => $final_amount,
                    'method' => $payment_method,
                    'id' => $ticket_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = :id");
                    $stmt->execute(['id' => $ticket_id]);
                    $completed_ticket = $stmt->fetch();
                    $success_message = "Checkout completed successfully!";
                } else {
                    $error_message = "Failed to update checkout. The ticket might already be checked out.";
                }
            } catch (PDOException $e) {
                $error_message = "Checkout database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch stats dynamically
$total_slots = 0;
$occupied_slots = 0;
$empty_left = 0;
$remaining_ratio = "0/0";
$todays_revenue = 0.00;

try {
    $total_slots = (int)$pdo->query("SELECT COUNT(*) FROM slots")->fetchColumn();
    $occupied_slots = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'parked'")->fetchColumn();
    $empty_left = max(0, $total_slots - $occupied_slots);
    $remaining_ratio = $total_slots . "/" . $empty_left;
    $todays_revenue = (float)$pdo->query("
        SELECT IFNULL(SUM(amount_paid), 0) 
        FROM tickets 
        WHERE DATE(check_out_time) = CURDATE() AND status = 'checked_out'
    ")->fetchColumn();
    
    // Calculate percentages for pie chart
    $occupied_percent = $total_slots > 0 ? round(($occupied_slots / $total_slots) * 100, 1) : 0;
    $empty_percent = 100 - $occupied_percent;
} catch (PDOException $e) {
    $occupied_percent = 0;
    $empty_percent = 100;
}

// Fetch all slots for the grid map
$all_slots = [];
$occupied_map = [];
try {
    $all_slots = $pdo->query("SELECT slot_number FROM slots ORDER BY slot_number")->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'parked'");
    $active_tickets = $stmt->fetchAll();
    foreach ($active_tickets as $tick) {
        $occupied_map[$tick['slot_number']] = [
            'plate' => $tick['plate_number'],
            'owner' => $tick['owner_name'],
            'type' => $tick['vehicle_type'],
            'time' => $tick['check_in_time']
        ];
    }
} catch (PDOException $e) {}

// Available slots for Check-In dropdown
$available_slots_dropdown = array_diff($all_slots, array_keys($occupied_map));

// Query currently parked vehicles
$parked_vehicles = [];
try {
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'parked' ORDER BY check_in_time DESC");
    $parked_vehicles = $stmt->fetchAll();
} catch (PDOException $e) {}

// Query recently exited vehicles
$exited_vehicles = [];
try {
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'checked_out' ORDER BY check_out_time DESC LIMIT 10");
    $exited_vehicles = $stmt->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard portal for ParkMaster.">
    <title>Dashboard - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Clickable Metric Cards */
        .metric-card {
            cursor: pointer;
            transition: all var(--transition-speed);
        }
        .metric-card:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35) !important;
            border-color: var(--primary-light) !important;
        }

        /* Modal styling for Pie Chart */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 11, 22, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .modal-content-box {
            background: rgba(17, 19, 36, 0.9);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .modal-overlay.active .modal-content-box {
            transform: scale(1);
        }
        .pie-chart {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5), 0 8px 24px rgba(0,0,0,0.4);
            margin: 0 auto;
        }

        /* Visual Slots Grid */
        .slots-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .slot-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 15px 10px;
            text-align: center;
            transition: all var(--transition-speed);
        }
        
        .slot-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
            background: rgba(255, 255, 255, 0.04);
        }
        
        .slot-id {
            font-size: 14.5px;
            font-weight: 700;
            margin-bottom: 5px;
            display: block;
            color: #fff;
        }
        
        .slot-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 2.5px 6px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .slot-box.available {
            border-top: 3px solid var(--success);
        }
        
        .slot-box.available .slot-badge {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .slot-box.occupied {
            border-top: 3px solid var(--danger);
        }
        
        .slot-box.occupied .slot-badge {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .slot-detail-line {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
        }

        .slot-owner {
            display: block;
            font-size: 10px;
            color: var(--primary-light);
            margin-top: 1px;
            font-style: italic;
        }

        /* 2-Column Split Panels (Forms and Lists) */
        .dashboard-split-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        /* Custom Table aesthetics */
        .custom-table-wrapper {
            overflow-x: auto;
            margin-top: 12px;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table th {
            padding: 10px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--card-border);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .custom-table td {
            padding: 12px 10px;
            font-size: 13px;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
        }

        .custom-table tr:hover td {
            background: rgba(255, 255, 255, 0.01);
        }

        .badge-type {
            font-size: 9px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            body::before, body::after, .sidebar, .page-header, .content-card:not(.printable-receipt), .alert, .dashboard-grid, .slots-container, .legend, .dashboard-split-grid {
                display: none !important;
            }
            .main-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .printable-receipt {
                border: 2px dashed #000 !important;
                box-shadow: none !important;
                background: white !important;
                color: black !important;
                width: 100% !important;
                max-width: 420px !important;
                margin: 0 auto !important;
                padding: 25px !important;
                border-radius: 8px !important;
            }
            .receipt-title {
                color: black !important;
            }
            .receipt-subtitle {
                color: #555 !important;
            }
            .receipt-value {
                color: black !important;
            }
            .receipt-label {
                color: #444 !important;
            }
            .receipt-total-val {
                color: black !important;
                font-size: 22px !important;
            }
            .receipt-barcode {
                color: black !important;
            }
            .receipt-barcode-txt, .receipt-thankyou {
                color: #444 !important;
            }
            .receipt-header-divider, .receipt-footer-divider {
                border-bottom-color: #000 !important;
                border-top-color: #000 !important;
            }
            .receipt-row-divider {
                border-bottom-color: #ddd !important;
            }
            .receipt-total-divider {
                border-top-color: #000 !important;
            }
            .print-actions {
                display: none !important;
            }
            .receipt-logo {
                box-shadow: none !important;
                border: 1px solid #333 !important;
                filter: grayscale(100%);
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Dashboard Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard Overview</h1>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert" style="margin-bottom: 25px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom: 25px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Metric Grid (Click to view Pie Chart distribution) -->
            <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <!-- Total Parking Slots -->
                <div class="metric-card" onclick="openChartModal()" title="Click to view distribution chart">
                    <div class="metric-info">
                        <span class="metric-value"><?php echo $total_slots; ?></span>
                        <span class="metric-label">Total Slots</span>
                    </div>
                    <div class="metric-icon-wrapper indigo">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21h8.25M12 3v18m0 0v-4.5m0 4.5H8.25m3.75 0h3.75M3.75 6h16.5M3.75 12h16.5M3.75 18h16.5" />
                        </svg>
                    </div>
                </div>

                <!-- Occupied Slots -->
                <div class="metric-card" onclick="openChartModal()" title="Click to view distribution chart">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--danger);"><?php echo $occupied_slots; ?></span>
                        <span class="metric-label">Occupied</span>
                    </div>
                    <div class="metric-icon-wrapper rose" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.321-5.128a3.375 3.375 0 0 0-3.06-3.148l-9.223-.71a3.375 3.375 0 0 0-3.518 2.89L2.25 14.25m17.25 4.5h-1.125m-12.75 0h1.125m-1.125 0V14.25m0 0h12.75V14.25" />
                        </svg>
                    </div>
                </div>

                <!-- Empty Left -->
                <div class="metric-card" onclick="openChartModal()" title="Click to view distribution chart">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--success);"><?php echo $empty_left; ?></span>
                        <span class="metric-label">Empty Left</span>
                    </div>
                    <div class="metric-icon-wrapper emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>

                <!-- Remaining Slots Ratio -->
                <div class="metric-card" onclick="openChartModal()" title="Click to view distribution chart">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--secondary);"><?php echo $remaining_ratio; ?></span>
                        <span class="metric-label">Slots (Total/Empty)</span>
                    </div>
                    <div class="metric-icon-wrapper cyan" style="background: rgba(6, 182, 212, 0.1); color: var(--secondary);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                    </div>
                </div>

                <!-- Today's Revenue -->
                <div class="metric-card" onclick="openChartModal()" title="Click to view distribution chart">
                    <div class="metric-info">
                        <span class="metric-value" style="color: #fbbf24;">₹<?php echo number_format($todays_revenue, 0); ?></span>
                        <span class="metric-label">Today's Revenue</span>
                    </div>
                    <div class="metric-icon-wrapper amber" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 8.25H9m6 3H9m3 6.75a9.09 9.09 0 1 1 0-18.18H12v18.18Z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Completed Checkout Receipt Display Modal/Block -->
            <?php if (isset($completed_ticket) && $completed_ticket): ?>
                <div class="content-card printable-receipt" style="border: 2px dashed rgba(255, 255, 255, 0.15); max-width: 460px; margin: 0 auto 25px auto; padding: 30px; border-radius: 16px; background: rgba(17, 19, 36, 0.65); backdrop-filter: blur(12px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);">
                    <div style="text-align: center; margin-bottom: 25px; border-bottom: 1px dashed rgba(255, 255, 255, 0.15); padding-bottom: 20px;" class="receipt-header-divider">
                        <img src="logo.jpg" alt="ParkMaster Logo" class="receipt-logo" style="width: 64px; height: 64px; border-radius: 14px; object-fit: cover; margin-bottom: 12px; border: 2px solid rgba(255, 255, 255, 0.1);">
                        <h2 style="margin: 0; color: #fff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;" class="receipt-title">PARKMASTER</h2>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;" class="receipt-subtitle">Official Parking Receipt</span>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px; margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Ticket ID:</span>
                            <strong style="color: #fff;" class="receipt-value">#PM-<?php echo str_pad($completed_ticket['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Owner Name:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['owner_name']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">License Plate:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['plate_number']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Vehicle Type:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['vehicle_type']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Slot Number:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['slot_number']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Check-In Time:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['check_in_time']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Check-Out Time:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['check_out_time']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Payment Mode:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['payment_method']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 2px dashed rgba(255, 255, 255, 0.15); padding-top: 15px; margin-top: 10px;" class="receipt-total-divider">
                            <span style="color: var(--text-muted); font-weight: 600;" class="receipt-label">Amount Paid:</span>
                            <strong style="color: var(--success); font-size: 22px; font-weight: 800;" class="receipt-total-val">₹<?php echo htmlspecialchars($completed_ticket['amount_paid']); ?></strong>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 20px; border-top: 1px dashed rgba(255, 255, 255, 0.15); padding-top: 15px;" class="receipt-footer-divider">
                        <div class="receipt-barcode" style="display: inline-flex; height: 35px; gap: 2px; opacity: 0.85; margin-bottom: 8px; color: #fff;">
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 4px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 4px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                        </div>
                        <div style="font-family: monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 2px; margin-bottom: 15px;" class="receipt-barcode-txt">
                            *PM-<?php echo str_pad($completed_ticket['id'], 5, '0', STR_PAD_LEFT); ?>*
                        </div>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin: 0; line-height: 1.4;" class="receipt-thankyou">
                            Thank you for parking with us!<br>
                            Please keep this invoice for your records.
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: center; margin-top: 30px;" class="print-actions">
                        <button onclick="window.print()" class="btn-primary" style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);">
                            Print Invoice
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Live Slots Map Section -->
            <div class="content-card">
                <h2>Live Slots Status Board</h2>
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-indicator" style="background: var(--success); box-shadow: 0 0 6px rgba(16,185,129,0.3);"></div>
                        <span>Available (Empty)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-indicator" style="background: var(--danger); box-shadow: 0 0 6px rgba(239,68,68,0.3);"></div>
                        <span>Occupied (Parked)</span>
                    </div>
                </div>

                <div class="slots-container">
                    <?php foreach ($all_slots as $slot): ?>
                        <?php if (isset($occupied_map[$slot])): 
                            $info = $occupied_map[$slot];
                        ?>
                            <div class="slot-box occupied" style="border-top: 3px solid var(--danger);">
                                <div style="margin-bottom: 8px;">
                                    <img src="images.png" alt="Car Icon" style="width: 24px; height: 24px; object-fit: contain; filter: drop-shadow(0 0 4px rgba(239, 68, 68, 0.4));">
                                </div>
                                <span class="slot-id"><?php echo htmlspecialchars($slot); ?></span>
                                <span class="slot-badge">Occupied</span>
                                <span class="slot-detail-line" style="color: #fff; font-weight: 600; font-size: 11px;"><?php echo htmlspecialchars($info['plate']); ?></span>
                                <span class="slot-owner">by <?php echo htmlspecialchars($info['owner']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="slot-box available" style="border-top: 3px solid var(--success);">
                                <div style="margin-bottom: 8px; opacity: 0.12;">
                                    <img src="images.png" alt="Car Icon" style="width: 24px; height: 24px; object-fit: contain; filter: grayscale(100%);">
                                </div>
                                <span class="slot-id"><?php echo htmlspecialchars($slot); ?></span>
                                <span class="slot-badge">Available</span>
                                <span class="slot-detail-line" style="opacity: 0.3; font-size: 10px;">Empty</span>
                                <span class="slot-owner" style="visibility: hidden; font-size: 9px;">None</span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Vehicle Entry (Check-In) and Exit (Check-Out) Section -->
            <div class="dashboard-split-grid">
                <!-- Vehicle Entry Card -->
                <div class="content-card" style="margin-top: 0;">
                    <h2>Vehicle Check-In Form</h2>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Fill out the owner details, plate number, and assign an available slot.</p>
                    
                    <form method="POST" action="dashboard.php" autocomplete="off">
                        <input type="hidden" name="action" value="check_in">
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="owner_name">Owner Name</label>
                            <input type="text" id="owner_name" name="owner_name" class="form-control" placeholder="e.g. John Doe" required style="background: rgba(17,19,36,0.5);">
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="plate_number">License Plate Number</label>
                            <input type="text" id="plate_number" name="plate_number" class="form-control" placeholder="e.g. MH 12 AB 1234" required style="background: rgba(17,19,36,0.5);">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="vehicle_type">Vehicle Category</label>
                            <select id="vehicle_type" name="vehicle_type" class="form-control" required style="background-color: #111322;">
                                <option value="4-Wheel">4-Wheeler (Car/SUV) - ₹<?php echo htmlspecialchars($rate_4_wheel); ?>/hr</option>
                                <option value="2-Wheel">2-Wheeler (Bike/Scooter) - ₹<?php echo htmlspecialchars($rate_2_wheel); ?>/hr</option>
                                <option value="Truck/Heavy">Truck / Heavy - ₹<?php echo htmlspecialchars($rate_truck_heavy); ?>/hr</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="slot_number">Assign Slot</label>
                            <select id="slot_number" name="slot_number" class="form-control" required style="background-color: #111322;">
                                <?php if (empty($available_slots_dropdown)): ?>
                                    <option value="" disabled selected>No Slots Available (Full)</option>
                                <?php else: ?>
                                    <option value="" disabled selected>Select Available Slot</option>
                                    <?php foreach ($available_slots_dropdown as $sl): ?>
                                        <option value="<?php echo htmlspecialchars($sl); ?>"><?php echo htmlspecialchars($sl); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-primary" <?php echo empty($available_slots_dropdown) ? 'disabled' : ''; ?>>
                            Register Check-In
                        </button>
                    </form>
                </div>

                <!-- Vehicle Exit Card -->
                <div class="content-card" style="margin-top: 0; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <h2>Vehicle Check-Out Form</h2>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Search by license plate or slot number to finalize check-out and compute billing charges.</p>
                        
                        <form method="POST" action="dashboard.php" style="margin-bottom: 20px;">
                            <input type="hidden" name="action" value="search_exit">
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="search_plate_num">License Plate or Slot Number</label>
                                <input type="text" id="search_plate_num" name="plate_number" class="form-control" placeholder="e.g. MH12AB1234 or A105" required style="background: rgba(17,19,36,0.5);">
                            </div>
                            <button type="submit" class="btn-primary">
                                Find & Calculate Bill
                            </button>
                        </form>

                        <!-- Pending Checkout Receipt Summary -->
                        <?php if (isset($ticket) && $ticket): ?>
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--primary); border-radius: 12px; padding: 20px; margin-top: 15px;">
                                <h3 style="color: var(--secondary); margin-bottom: 15px;">🧾 Pending Checkout Details</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; margin-bottom: 20px;">
                                    <div>
                                        <span style="color: var(--text-muted);">Plate:</span>
                                        <strong style="color: #fff; display: block;"><?php echo htmlspecialchars($ticket['plate_number']); ?></strong>
                                    </div>
                                    <div>
                                        <span style="color: var(--text-muted);">Slot:</span>
                                        <strong style="color: #fff; display: block;"><?php echo htmlspecialchars($ticket['slot_number']); ?></strong>
                                    </div>
                                    <div>
                                        <span style="color: var(--text-muted);">Duration:</span>
                                        <strong style="color: #fff; display: block;"><?php echo $duration_text; ?></strong>
                                    </div>
                                    <div>
                                        <span style="color: var(--text-muted);">Total Charges:</span>
                                        <strong style="color: var(--success); font-size: 16px; display: block;">₹<?php echo $amount_due; ?></strong>
                                    </div>
                                </div>
                                
                                <form method="POST" action="dashboard.php">
                                    <input type="hidden" name="action" value="confirm_checkout">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <input type="hidden" name="amount_due" value="<?php echo $amount_due; ?>">
                                    
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label for="payment_method" style="font-size: 12px; color: var(--text-muted);">Payment Mode</label>
                                        <select id="payment_method" name="payment_method" class="form-control" required style="background-color: #111322;">
                                            <option value="UPI">UPI</option>
                                            <option value="CARD">CARD</option>
                                            <option value="CASH" selected>CASH</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%); width: 100%;">
                                        Confirm Payment & Release Slot
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Parked Vehicles and Recently Exited Section -->
            <div class="dashboard-split-grid" style="margin-top: 25px;">
                <!-- Currently Parked Vehicles Table -->
                <div class="content-card" style="margin-top: 0;">
                    <h2>Currently Parked Vehicles</h2>
                    <p style="font-size: 13px; color: var(--text-muted);">Listing of all active vehicles inside the parking space.</p>
                    
                    <div class="custom-table-wrapper">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Slot</th>
                                    <th>Category</th>
                                    <th>Check-in Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($parked_vehicles)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); opacity: 0.5;">No active vehicles parked.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($parked_vehicles as $veh): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($veh['plate_number']); ?></td>
                                            <td><span style="font-weight: 600; color: var(--secondary);"><?php echo htmlspecialchars($veh['slot_number']); ?></span></td>
                                            <td><span class="badge-type"><?php echo htmlspecialchars($veh['vehicle_type']); ?></span></td>
                                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($veh['check_in_time']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recently Exited Vehicles Table -->
                <div class="content-card" style="margin-top: 0;">
                    <h2>Recently Exited Vehicles</h2>
                    <p style="font-size: 13px; color: var(--text-muted);">Audit log of the last 10 vehicles that checked out.</p>
                    
                    <div class="custom-table-wrapper">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Plate Number</th>
                                    <th>Slot</th>
                                    <th>Check-out Time</th>
                                    <th>Settled Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($exited_vehicles)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); opacity: 0.5;">No records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($exited_vehicles as $veh): ?>
                                        <tr>
                                            <td style="font-weight: 700; color: #fff; opacity: 0.8;"><?php echo htmlspecialchars($veh['plate_number']); ?></td>
                                            <td><span style="font-weight: 600; color: var(--text-muted);"><?php echo htmlspecialchars($veh['slot_number']); ?></span></td>
                                            <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($veh['check_out_time']); ?></td>
                                            <td style="font-weight: 700; color: var(--success);">₹<?php echo htmlspecialchars($veh['amount_paid']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Allocation Pie Chart Modal -->
    <div id="chartModal" class="modal-overlay" onclick="closeChartModalOnOverlay(event)">
        <div class="modal-content-box">
            <h2 style="color: #fff; margin-bottom: 5px; font-size: 19px; font-weight: 700;">📊 Slot Allocation Chart</h2>
            <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 20px;">Current parking space occupancy ratio</p>
            
            <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 25px;">
                <div class="pie-chart" style="background: conic-gradient(var(--danger) 0% <?php echo $occupied_percent; ?>%, var(--success) <?php echo $occupied_percent; ?>% 100%);"></div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 10px; text-align: left; max-width: 260px; margin: 0 auto 25px auto; padding: 15px; background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 10px; height: 10px; border-radius: 2.5px; background: var(--danger); box-shadow: 0 0 6px var(--danger);"></div>
                    <span style="color: #fff; font-size: 13px; font-weight: 500;">Occupied: <strong><?php echo $occupied_slots; ?></strong> (<?php echo $occupied_percent; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 10px; height: 10px; border-radius: 2.5px; background: var(--success); box-shadow: 0 0 6px var(--success);"></div>
                    <span style="color: #fff; font-size: 13px; font-weight: 500;">Available: <strong><?php echo $empty_left; ?></strong> (<?php echo $empty_percent; ?>%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; border-top: 1px dashed rgba(255,255,255,0.08); padding-top: 8px; margin-top: 5px;">
                    <div style="width: 10px; height: 10px; border-radius: 2.5px; background: var(--primary); box-shadow: 0 0 6px var(--primary);"></div>
                    <span style="color: var(--text-muted); font-size: 13px;">Total Slots: <strong><?php echo $total_slots; ?></strong></span>
                </div>
            </div>
            
            <button onclick="closeChartModal()" class="btn-secondary" style="width: 100%; padding: 12px; border-radius: 10px; font-weight: 600;">Close Chart</button>
        </div>
    </div>

    <!-- Modal Trigger Script -->
    <script>
        function openChartModal() {
            const modal = document.getElementById('chartModal');
            modal.style.display = 'flex';
            // Force reflow
            modal.offsetHeight;
            modal.classList.add('active');
        }

        function closeChartModal() {
            const modal = document.getElementById('chartModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function closeChartModalOnOverlay(event) {
            if (event.target.id === 'chartModal') {
                closeChartModal();
            }
        }
    </script>
</body>
</html>
