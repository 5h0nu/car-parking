<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Read filter query parameter (default: today)
$filter = filter_input(INPUT_GET, 'filter', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$filter || !in_array($filter, ['today', 'weekly', 'monthly', 'all_time'])) {
    $filter = 'today';
}

$check_ins = 0;
$check_outs = 0;
$total_revenue = 0.00;
$avg_duration_minutes = 0;

// Execute queries based on filter
try {
    if ($filter === 'today') {
        // Today's Checkins
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(check_in_time) = CURDATE()");
        $check_ins = $stmt->fetchColumn();

        // Today's Checkouts
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(check_out_time) = CURDATE() AND status = 'checked_out'");
        $check_outs = $stmt->fetchColumn();

        // Today's Revenue
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount_paid), 0) FROM tickets WHERE DATE(check_out_time) = CURDATE() AND status = 'checked_out'");
        $total_revenue = $stmt->fetchColumn();
        
        // Today's Average Duration
        $stmt = $pdo->query("
            SELECT IFNULL(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
            FROM tickets 
            WHERE DATE(check_out_time) = CURDATE() AND status = 'checked_out'
        ");
        $avg_duration_minutes = round($stmt->fetchColumn());

    } elseif ($filter === 'weekly') {
        // Weekly Checkins (Current ISO Week Mon-Sun)
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE YEARWEEK(check_in_time, 1) = YEARWEEK(CURDATE(), 1)");
        $check_ins = $stmt->fetchColumn();

        // Weekly Checkouts
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE YEARWEEK(check_out_time, 1) = YEARWEEK(CURDATE(), 1) AND status = 'checked_out'");
        $check_outs = $stmt->fetchColumn();

        // Weekly Revenue
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount_paid), 0) FROM tickets WHERE YEARWEEK(check_out_time, 1) = YEARWEEK(CURDATE(), 1) AND status = 'checked_out'");
        $total_revenue = $stmt->fetchColumn();

        // Weekly Avg Duration
        $stmt = $pdo->query("
            SELECT IFNULL(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
            FROM tickets 
            WHERE YEARWEEK(check_out_time, 1) = YEARWEEK(CURDATE(), 1) AND status = 'checked_out'
        ");
        $avg_duration_minutes = round($stmt->fetchColumn());

        // Weekly chart grouping (Monday to Sunday)
        $weekly_earnings = array_fill(0, 7, 0.00); // 0 = Mon, 6 = Sun
        $stmt = $pdo->query("
            SELECT WEEKDAY(check_out_time) as day_idx, SUM(amount_paid) as total 
            FROM tickets 
            WHERE YEARWEEK(check_out_time, 1) = YEARWEEK(CURDATE(), 1) AND status = 'checked_out'
            GROUP BY WEEKDAY(check_out_time)
        ");
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $weekly_earnings[$row['day_idx']] = (float)$row['total'];
        }

    } elseif ($filter === 'monthly') {
        // Monthly Checkins
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())");
        $check_ins = $stmt->fetchColumn();

        // Monthly Checkouts
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE MONTH(check_out_time) = MONTH(CURDATE()) AND YEAR(check_out_time) = YEAR(CURDATE()) AND status = 'checked_out'");
        $check_outs = $stmt->fetchColumn();

        // Monthly Revenue
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount_paid), 0) FROM tickets WHERE MONTH(check_out_time) = MONTH(CURDATE()) AND YEAR(check_out_time) = YEAR(CURDATE()) AND status = 'checked_out'");
        $total_revenue = $stmt->fetchColumn();

        // Monthly Avg Duration
        $stmt = $pdo->query("
            SELECT IFNULL(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
            FROM tickets 
            WHERE MONTH(check_out_time) = MONTH(CURDATE()) AND YEAR(check_out_time) = YEAR(CURDATE()) AND status = 'checked_out'
        ");
        $avg_duration_minutes = round($stmt->fetchColumn());

        // Monthly chart grouping (1 to 30/31)
        $total_days = (int)date('t');
        $monthly_earnings = array_fill(1, $total_days, 0.00);
        $stmt = $pdo->query("
            SELECT DAY(check_out_time) as day_num, SUM(amount_paid) as total 
            FROM tickets 
            WHERE MONTH(check_out_time) = MONTH(CURDATE()) AND YEAR(check_out_time) = YEAR(CURDATE()) AND status = 'checked_out'
            GROUP BY DAY(check_out_time)
        ");
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            $monthly_earnings[$row['day_num']] = (float)$row['total'];
        }

    } elseif ($filter === 'all_time') {
        // All Time Checkins
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
        $check_ins = $stmt->fetchColumn();

        // All Time Checkouts
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'checked_out'");
        $check_outs = $stmt->fetchColumn();

        // All Time Revenue
        $stmt = $pdo->query("SELECT IFNULL(SUM(amount_paid), 0) FROM tickets WHERE status = 'checked_out'");
        $total_revenue = $stmt->fetchColumn();

        // All Time Avg Duration
        $stmt = $pdo->query("
            SELECT IFNULL(AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)), 0) 
            FROM tickets 
            WHERE status = 'checked_out'
        ");
        $avg_duration_minutes = round($stmt->fetchColumn());
    }
} catch (PDOException $e) {
    die("Database report aggregation failed: " . $e->getMessage());
}

// Convert average duration minutes into readable hour/minute format
$duration_text = "N/A";
if ($avg_duration_minutes > 0) {
    $hours = floor($avg_duration_minutes / 60);
    $mins = $avg_duration_minutes % 60;
    $duration_text = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Report Filter Buttons styling */
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.02);
            padding: 8px;
            border-radius: 14px;
            border: 1px solid var(--card-border);
            width: fit-content;
        }

        .btn-filter {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 10px 22px;
            font-size: 14.5px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-speed);
        }

        .btn-filter:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.03);
        }

        .btn-filter.active {
            color: #ffffff;
            background: var(--primary);
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* CSS chart styling */
        .chart-card {
            margin-top: 30px;
        }

        .chart-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-top: 25px;
        }

        .chart-scroll-wrapper::-webkit-scrollbar {
            height: 6px;
        }

        .chart-scroll-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 3px;
        }

        .chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 250px;
            border-bottom: 2px solid var(--card-border);
            padding-bottom: 8px;
            min-width: 600px;
        }

        .chart-container.monthly {
            min-width: 950px;
            justify-content: space-between;
            padding: 0 10px 8px 10px;
        }

        .chart-bar-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-grow: 1;
            height: 100%;
            justify-content: flex-end;
            position: relative;
        }

        .chart-bar {
            background: linear-gradient(180deg, var(--secondary) 0%, var(--primary) 100%);
            width: 32px;
            border-radius: 6px 6px 0 0;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .chart-container.monthly .chart-bar {
            width: 16px;
            border-radius: 3px 3px 0 0;
        }

        .chart-bar:hover {
            filter: brightness(1.2);
            box-shadow: 0 0 15px var(--secondary-glow);
        }

        .chart-bar-label {
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Tooltip style */
        .chart-bar-wrapper .tooltip {
            position: absolute;
            background: #090a10;
            border: 1px solid var(--card-border);
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            bottom: 100%;
            margin-bottom: 8px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            transform: translateY(4px);
            white-space: nowrap;
            z-index: 10;
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
        }

        .chart-bar-wrapper:hover .tooltip {
            opacity: 1;
            transform: translateY(0);
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
                <h1 class="page-title">Reports & Analytics</h1>
            </div>

            <!-- Tab Filters Bar -->
            <div class="filter-bar">
                <a href="reports.php?filter=today" class="btn-filter <?php echo $filter === 'today' ? 'active' : ''; ?>">Today</a>
                <a href="reports.php?filter=weekly" class="btn-filter <?php echo $filter === 'weekly' ? 'active' : ''; ?>">This Week</a>
                <a href="reports.php?filter=monthly" class="btn-filter <?php echo $filter === 'monthly' ? 'active' : ''; ?>">This Month</a>
                <a href="reports.php?filter=all_time" class="btn-filter <?php echo $filter === 'all_time' ? 'active' : ''; ?>">All Time</a>
            </div>

            <!-- Dynamic Metrics Grid -->
            <div class="dashboard-grid">
                <!-- Check-ins -->
                <div class="metric-card">
                    <div class="metric-info">
                        <span class="metric-value"><?php echo $check_ins; ?></span>
                        <span class="metric-label">Check-Ins</span>
                    </div>
                    <div class="metric-icon-wrapper indigo">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>

                <!-- Check-outs -->
                <div class="metric-card">
                    <div class="metric-info">
                        <span class="metric-value"><?php echo $check_outs; ?></span>
                        <span class="metric-label">Check-Outs</span>
                    </div>
                    <div class="metric-icon-wrapper cyan">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="metric-card">
                    <div class="metric-info">
                        <span class="metric-value" style="color: var(--success);">₹<?php echo number_format($total_revenue, 2); ?></span>
                        <span class="metric-label">Revenue Collected</span>
                    </div>
                    <div class="metric-icon-wrapper emerald">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125H3.75m0-12c-.621 0-1.125.504-1.125 1.125v9.75c0 .621.504 1.125 1.125 1.125m0-12v12m0 0h16.5" />
                        </svg>
                    </div>
                </div>

                <!-- Avg Parking Duration -->
                <div class="metric-card">
                    <div class="metric-info">
                        <span class="metric-value"><?php echo $duration_text; ?></span>
                        <span class="metric-label">Avg Park Time</span>
                    </div>
                    <div class="metric-icon-wrapper indigo" style="color: var(--primary-light); background: rgba(79, 70, 229, 0.15);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Chart Visualizations -->
            <?php if ($filter === 'weekly'): ?>
                <div class="content-card chart-card">
                    <h2>Weekly Revenue Log (Mon - Sun)</h2>
                    <p>Bar graph representation showing transactional earnings collected across each weekday.</p>
                    
                    <?php 
                        $max_earning = max($weekly_earnings);
                        if ($max_earning <= 0) $max_earning = 1; // avoid division by zero
                        $days_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    ?>
                    
                    <div class="chart-scroll-wrapper">
                        <div class="chart-container">
                            <?php for ($d = 0; $d < 7; $d++): 
                                $earn = $weekly_earnings[$d];
                                $percent = ($earn / $max_earning) * 100;
                            ?>
                                <div class="chart-bar-wrapper">
                                    <div class="tooltip">₹<?php echo number_format($earn, 2); ?></div>
                                    <div class="chart-bar" style="height: <?php echo max(5, $percent); ?>%;"></div>
                                    <span class="chart-bar-label"><?php echo $days_labels[$d]; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($filter === 'monthly'): ?>
                <div class="content-card chart-card">
                    <h2>Monthly Revenue Log (Days 1 - <?php echo $total_days; ?>)</h2>
                    <p>Detailed capacity earnings record grouped daily across the current calendar month. Scroll horizontally to view all dates.</p>
                    
                    <?php 
                        $max_earning = max($monthly_earnings);
                        if ($max_earning <= 0) $max_earning = 1;
                    ?>
                    
                    <div class="chart-scroll-wrapper">
                        <div class="chart-container monthly">
                            <?php for ($d = 1; $d <= $total_days; $d++): 
                                $earn = $monthly_earnings[$d];
                                $percent = ($earn / $max_earning) * 100;
                            ?>
                                <div class="chart-bar-wrapper">
                                    <div class="tooltip">Day <?php echo $d; ?>: ₹<?php echo number_format($earn, 2); ?></div>
                                    <div class="chart-bar" style="height: <?php echo max(5, $percent); ?>%;"></div>
                                    <span class="chart-bar-label" style="font-size: 10px;"><?php echo $d; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Details for Today or All Time -->
                <div class="content-card" style="margin-top: 30px;">
                    <h2>Operational Reports Directory</h2>
                    <p>
                        Currently displaying metrics aggregated for <strong><?php echo strtoupper($filter); ?></strong>. 
                        To view detailed graphs, click on the **This Week** or **This Month** filter buttons above.
                    </p>
                    <p>
                        This report compiles checking records, parking receipts, total cars checks, and logs calculations generated by the administration panel.
                    </p>
                </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>
