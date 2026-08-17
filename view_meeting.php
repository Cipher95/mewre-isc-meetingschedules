<?php
require 'lang.php';
require 'db.php';

// Check if logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: meeting_rooms.php");
    exit();
}

$id = intval($_GET['id']);
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch the meeting
if ($role == 'User') {
    $sql = "SELECT * FROM meetings WHERE id = $id";
}

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("<h2 style='color:red; text-align:center; margin-top:50px; font-family: sans-serif;'>" . t('unauthorized') . "</h2>");
}

$meeting = $result->fetch_assoc();

// SMART FAILSAFE: If the database ENUM update caused old statuses to become blank, force them to 'pending'
$m_status = !empty($meeting['status']) ? $meeting['status'] : 'pending';
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo t('dir'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('meeting_details'); ?> | MEW ISC</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        * { box-sizing: border-box; font-family: <?php echo t('font'); ?>; }
        body { background: #f8f9fa; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .ticket-card { background: white; max-width: 500px; width: 100%; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; position: relative; }
        .ticket-header { background: #004b87; color: white; padding: 30px 20px; text-align: center; }
        .ticket-header h2 { margin: 0; font-size: 24px; }
        .ticket-header p { margin: 5px 0 0; opacity: 0.8; font-size: 14px; }
        
        .ticket-body { padding: 30px; }
        .info-group { margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .info-group:last-child { border-bottom: none; margin-bottom: 0; }
        .info-label { color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 5px;}
        .info-value { color: #333; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        
        /* New Status Badge Colors */
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: bold; color: white; margin-top: 10px;}
        .status-not_started { background: #6c757d; }
        .status-pending { background: #e5b13a; color: #333; }
        .status-in_progress { background: #004b87; }
        .status-completed, .status-Completed { background: #28a745; }

        .ticket-footer { background: #f1f1f1; padding: 20px; display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .btn { padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; font-size: 14px;}
        .btn-print { background: #333; color: white; }
        .btn-print:hover { background: #000; }
        .btn-pdf { background: #dc3545; color: white; }
        .btn-pdf:hover { background: #c82333; }
        .btn-back { background: #004b87; color: white; }
        .btn-back:hover { background: #e5b13a; color: #333; }

        /* Hide buttons during physical print */
        @media print {
            body { background: white; }
            .ticket-card { box-shadow: none; border: 1px solid #ccc; }
            .ticket-footer { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="ticket-card">
        <div class="ticket-header">
            <i class="fa-solid fa-qrcode" style="font-size: 40px; margin-bottom: 10px; color: #e5b13a;"></i>
            <h2><?php echo t('meeting_details'); ?></h2>
            <p><?php echo t('meeting_id'); ?>: #MEW-<?php echo str_pad($meeting['id'], 5, "0", STR_PAD_LEFT); ?></p>
        </div>
        
        <div class="ticket-body">
            <div class="info-group">
                <div class="info-label"><?php echo t('meeting_title'); ?></div>
                <div class="info-value"><i class="fa-solid fa-briefcase" style="color: #004b87;"></i> <?php echo htmlspecialchars($meeting['title']); ?></div>
            </div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                <div class="info-group" style="width: 48%;">
                    <div class="info-label"><?php echo t('date'); ?></div>
                    <div class="info-value"><i class="fa-regular fa-calendar" style="color: #004b87;"></i> <?php echo $meeting['meeting_date']; ?></div>
                </div>
                
            <div class="info-group" style="width: 48%;">
                    <div class="info-label"><?php echo t('time'); ?></div>
                    <div class="info-value" style="font-size: 15px;">
                        <i class="fa-regular fa-clock" style="color: #004b87;"></i> 
                        <?php 
                        echo date("h:i A", strtotime($meeting['meeting_time'])) . ' <br> ' . date("h:i A", strtotime($meeting['end_time'])); 
                        ?>
                </div>
                </div>
            </div>

            <div class="info-group">
                <div class="info-label"><?php echo t('room'); ?></div>
                <div class="info-value"><i class="fa-solid fa-door-open" style="color: #004b87;"></i> <?php echo htmlspecialchars($meeting['room']); ?></div>
            </div>

            <!-- FIXED STATUS BLOCK -->
            <div class="info-group">
                <div class="info-label"><?php echo t('status'); ?></div>
                <div class="status-badge status-<?php echo $m_status; ?>">
                    <?php 
                        if ($m_status == 'not_started') echo '<i class="fa-solid fa-circle-pause"></i> ' . t('not_started');
                        elseif ($m_status == 'pending') echo '<i class="fa-solid fa-hourglass-half"></i> ' . t('pending');
                        elseif ($m_status == 'in_progress') echo '<i class="fa-solid fa-spinner"></i> ' . t('in_progress');
                        elseif ($m_status == 'completed' || $m_status == 'Completed') echo '<i class="fa-solid fa-check-double"></i> ' . t('completed');
                        else echo htmlspecialchars($m_status);
                    ?>
                </div>
            </div>
        </div>

        <!-- HTML2Canvas Ignore Tag included -->
        <div class="ticket-footer" data-html2canvas-ignore="true">
            <a href="meeting_rooms.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> <?php echo t('back'); ?></a>
            
            <button onclick="saveAsPDF()" class="btn btn-pdf"><i class="fa-solid fa-file-pdf"></i> <?php echo t('save_pdf'); ?></button>
            
        </div>
    </div>

    <script>
        function saveAsPDF() {
            // Grab the main card using its class name (Guaranteed to exist)
            const element = document.querySelector('.ticket-card');
            
            // Failsafe check
            if (!element) {
                console.error("Card element not found!");
                return;
            }
            
            // Configure PDF Options
            const opt = {
                margin:       0.5,
                filename:     'MEW_Meeting_Pass.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, backgroundColor: '#ffffff', useCORS: true }, 
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            // Generate and download the PDF
            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>
</html>
