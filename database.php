<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Helper to format KB sizes
function format_size_kb($kb) {
    $bytes = $kb * 1024;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return number_format($bytes, 2) . ' Bytes';
    }
}

// Configured tables and purposes
$tables_config = [
    'users' => 'System authentication and roles credentials',
    'slots' => 'Slots occupancy registers',
    'tickets' => 'Vehicular checking registries',
    'settings' => 'General pricing and configuration settings'
];

// Fetch data logic
function get_database_stats($pdo, $tables_config) {
    $tables_data = [];
    foreach ($tables_config as $name => $purpose) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$name`");
            $records = $stmt->fetchColumn();
            
            $stmt_size = $pdo->prepare("
                SELECT ROUND(((data_length + index_length) / 1024), 2) AS size_kb
                FROM information_schema.TABLES
                WHERE table_schema = :db_name AND table_name = :table_name
            ");
            $stmt_size->execute([
                'db_name' => DB_NAME,
                'table_name' => $name
            ]);
            $size_kb = (float)$stmt_size->fetchColumn();
            
            $tables_data[] = [
                'name' => $name,
                'records' => $records,
                'size' => format_size_kb($size_kb),
                'purpose' => $purpose
            ];
        } catch (Exception $e) {
            // Handle error case
            $tables_data[] = [
                'name' => $name,
                'records' => 0,
                'size' => '0.00 KB',
                'purpose' => $purpose . ' (Error: ' . $e->getMessage() . ')'
            ];
        }
    }
    return $tables_data;
}

// Handle API Request for real-time updates
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $tables_data = get_database_stats($pdo, $tables_config);
    echo json_encode([
        'status' => 'success',
        'db_name' => DB_NAME,
        'tables' => $tables_data
    ]);
    exit;
}

// Get initial values for server-side rendering
$db_status = "Connected Successfully";
$tables = get_database_stats($pdo, $tables_config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .db-status-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14.5px;
            color: var(--text-muted);
            margin-bottom: 25px;
        }

        .db-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
        }

        .table-list {
            margin-top: 20px;
        }

        .table-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 12px;
            transition: all var(--transition-speed);
        }

        .table-row:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--primary-light);
        }

        .table-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .table-name {
            font-weight: 600;
            color: #ffffff;
            font-size: 16px;
        }

        .table-purpose {
            font-size: 13px;
            color: var(--text-muted);
        }

        .table-stats {
            text-align: right;
        }

        .table-records {
            font-weight: 700;
            color: var(--secondary);
            font-size: 15px;
            display: block;
        }

        .table-size {
            font-size: 12px;
            color: var(--text-muted);
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
                <h1 class="page-title">Database Administration</h1>
            </div>

            <div class="content-card">
                <h2>MySQL Server Registry</h2>
                
                <div class="db-status-bar">
                    <div class="db-status-dot"></div>
                    <span>Connection Status: <strong><?php echo htmlspecialchars($db_status); ?></strong> (car_parking_db)</span>
                </div>

                <p>Monitor active database tables, record directories, physical schemas, and perform backup routines below.</p>
                
                <div class="table-list">
                    <?php foreach ($tables as $tbl): ?>
                        <div class="table-row" id="row-<?php echo htmlspecialchars($tbl['name']); ?>">
                            <div class="table-info">
                                <span class="table-name"><?php echo htmlspecialchars($tbl['name']); ?></span>
                                <span class="table-purpose"><?php echo htmlspecialchars($tbl['purpose']); ?></span>
                            </div>
                            <div class="table-stats">
                                <span class="table-records" data-table="<?php echo htmlspecialchars($tbl['name']); ?>-records"><?php echo htmlspecialchars($tbl['records']); ?> rows</span>
                                <span class="table-size" data-table="<?php echo htmlspecialchars($tbl['name']); ?>-size"><?php echo htmlspecialchars($tbl['size']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 15px;">
                    <button class="btn-primary" onclick="alert('Exporting SQL database...')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Backup Database (.SQL)
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Real-time Database Monitor Script -->
    <script>
        function updateDatabaseStats() {
            fetch('database.php?api=1')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Database connection failed');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Update status indicator to Online
                        const statusDot = document.querySelector('.db-status-dot');
                        const statusText = document.querySelector('.db-status-bar span');
                        
                        if (statusDot && statusText) {
                            statusDot.style.background = 'var(--success)';
                            statusDot.style.boxShadow = '0 0 8px rgba(16, 185, 129, 0.6)';
                            statusText.innerHTML = `Connection Status: <strong>Connected Successfully</strong> (${data.db_name})`;
                        }

                        data.tables.forEach(table => {
                            const recordsEl = document.querySelector(`[data-table="${table.name}-records"]`);
                            const sizeEl = document.querySelector(`[data-table="${table.name}-size"]`);
                            
                            if (recordsEl) {
                                const newText = `${table.records} rows`;
                                if (recordsEl.innerText !== newText) {
                                    // Highlight difference with a dynamic scaling pulse
                                    recordsEl.innerText = newText;
                                    recordsEl.style.transition = 'none';
                                    recordsEl.style.color = 'var(--secondary)';
                                    recordsEl.style.transform = 'scale(1.15)';
                                    recordsEl.style.fontWeight = '800';
                                    setTimeout(() => {
                                        recordsEl.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                                        recordsEl.style.color = 'var(--secondary)';
                                        recordsEl.style.transform = 'scale(1)';
                                        recordsEl.style.fontWeight = '700';
                                    }, 150);
                                }
                            }
                            if (sizeEl) {
                                sizeEl.innerText = table.size;
                            }
                        });
                    }
                })
                .catch(error => {
                    // Update status indicator to Terminated
                    const statusDot = document.querySelector('.db-status-dot');
                    const statusText = document.querySelector('.db-status-bar span');
                    if (statusDot && statusText) {
                        statusDot.style.background = 'var(--danger)';
                        statusDot.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.7)';
                        statusText.innerHTML = `Connection Status: <strong style="color: var(--danger); text-shadow: 0 0 10px rgba(239, 68, 68, 0.2);">Connection Terminated</strong>`;
                    }
                    console.error('Error polling database stats:', error);
                });
        }

        // Poll database session every 3 seconds
        setInterval(updateDatabaseStats, 3000);
    </script>
</body>
</html>
