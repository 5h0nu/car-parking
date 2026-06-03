<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Step 1: Handle Add Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    $slot_number = trim(filter_input(INPUT_POST, 'slot_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $floor = filter_input(INPUT_POST, 'floor', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($slot_number) || empty($floor)) {
        $error_message = "All fields are required to add a slot.";
    } else {
        $slot_number = strtoupper($slot_number);
        try {
            // Check if slot exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM slots WHERE slot_number = :slot");
            $stmt->execute(['slot' => $slot_number]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Slot '{$slot_number}' already exists in the system.";
            } else {
                // Insert new slot
                $stmt = $pdo->prepare("INSERT INTO slots (slot_number, floor) VALUES (:slot, :floor)");
                $stmt->execute(['slot' => $slot_number, 'floor' => $floor]);
                $success_message = "Slot <strong>{$slot_number}</strong> has been added successfully to {$floor}!";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Step 2: Handle Remove Slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_slot'])) {
    $slot_number = filter_input(INPUT_POST, 'slot_number', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($slot_number)) {
        $error_message = "Invalid slot selection.";
    } else {
        try {
            // Safety check: is the slot currently occupied?
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE slot_number = :slot AND status = 'parked'");
            $stmt->execute(['slot' => $slot_number]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Cannot remove slot <strong>{$slot_number}</strong> because a vehicle is currently parked in it.";
            } else {
                // Delete slot configuration
                $stmt = $pdo->prepare("DELETE FROM slots WHERE slot_number = :slot");
                $stmt->execute(['slot' => $slot_number]);
                $success_message = "Slot <strong>{$slot_number}</strong> has been removed from the system.";
            }
        } catch (PDOException $e) {
            $error_message = "Database deletion error: " . $e->getMessage();
        }
    }
}

// Fetch all slots to list them
$slots_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM slots ORDER BY floor, slot_number");
    $slots_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Failed to load slots database list: " . $e->getMessage();
}

// Fetch active occupied slots to display correct status tags
$occupied_slots = [];
try {
    $stmt = $pdo->query("SELECT slot_number FROM tickets WHERE status = 'parked'");
    $occupied_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Slots - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .slots-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14.5px;
            text-align: left;
        }

        .slots-table th {
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 2px solid var(--card-border);
            color: #ffffff;
            font-weight: 600;
        }

        .slots-table td {
            padding: 15px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-muted);
        }

        .slots-table tr:hover td {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.01);
        }

        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12.5px;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
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
                <h1 class="page-title">Manage Parking Slots</h1>
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

            <!-- Add Slot Form Card -->
            <div class="content-card">
                <h2>Configure New Slot</h2>
                <p>Register a new parking bay by setting its designated code name and level mapping (Floor).</p>
                
                <form method="POST" action="add_slots.php" autocomplete="off">
                    <input type="hidden" name="add_slot" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="slot_number">Slot Code Name</label>
                            <input type="text" id="slot_number" name="slot_number" class="form-control" placeholder="e.g. A116, B101" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="floor">Designated Level (Floor)</label>
                            <select id="floor" name="floor" class="form-control" required style="background-color: #111322;">
                                <option value="Ground Floor">Ground Floor</option>
                                <option value="1st Floor">1st Floor</option>
                                <option value="2nd Floor">2nd Floor</option>
                                <option value="Rooftop">Rooftop</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 25px;">
                        <button type="submit" class="btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Add Parking Slot
                        </button>
                    </div>
                </form>
            </div>

            <!-- Slots Listing Ledger Card -->
            <div class="content-card" style="margin-top: 30px;">
                <h2>Master Slots Configuration (<?php echo count($slots_list); ?> Slots)</h2>
                <p>Review registered parking terminals and remove unused bays. Occupied slots cannot be deleted.</p>
                
                <div class="table-responsive">
                    <table class="slots-table">
                        <thead>
                            <tr>
                                <th>Slot Code</th>
                                <th>Floor Level</th>
                                <th>Occupancy Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($slots_list)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 25px; color: var(--text-muted);">
                                        No slot configurations found in database. Configure a slot above.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($slots_list as $sl): ?>
                                    <?php 
                                        $is_occupied = in_array($sl['slot_number'], $occupied_slots);
                                    ?>
                                    <tr>
                                        <td style="color: #fff; font-weight: 700; font-size: 15px;"><?php echo htmlspecialchars($sl['slot_number']); ?></td>
                                        <td><?php echo htmlspecialchars($sl['floor']); ?></td>
                                        <td>
                                            <?php if ($is_occupied): ?>
                                                <span class="badge badge-danger">Occupied</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="add_slots.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to remove slot <?php echo $sl['slot_number']; ?>?');">
                                                <input type="hidden" name="remove_slot" value="1">
                                                <input type="hidden" name="slot_number" value="<?php echo htmlspecialchars($sl['slot_number']); ?>">
                                                <button type="submit" class="btn-danger" <?php echo $is_occupied ? 'disabled style="opacity: 0.5; cursor: not-allowed;" title="Slot occupied"' : ''; ?>>
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
