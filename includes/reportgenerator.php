<?php
include("db.php");

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

function generateWeeklyFrequencyReport() {
    global $db;
    $patientid = $_SESSION['userid'];
    
    // Get patient info
    $stmt = $db->prepare("SELECT * FROM user WHERE userid = ?");
    $stmt->execute([$patientid]);
    $patient = $stmt->get_result()->fetch_assoc();
    
    if (!$patient) {
        die('Patient not found');
    }
    
    // Get prescription items for this patient
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            p.dateprescribed,
            d.firstname as doctor_firstname,
            d.lastname as doctor_lastname
        FROM prescriptionitem pi
        JOIN prescription p ON pi.prescriptionid = p.prescriptionid
        JOIN user d ON p.doctorid = d.userid
        WHERE p.patientid = ?
        ORDER BY p.dateprescribed DESC, pi.prescriptionitemid ASC
    ");
    $stmt->execute([$patientid]);
    $prescriptionItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Generate HTML report
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Medication Frequency Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2, h3, h4 { color: #333; }
            .report-period { color: #666; font-size: 14px; }
            .patient-info { margin-bottom: 30px; padding: 15px; background-color: #f9f9f9; border-radius: 5px; }
            .section { margin: 25px 0; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #4CAF50; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .frequency-highlight { 
                background-color: #e8f5e8; 
                padding: 15px; 
                margin: 15px 0; 
                border-left: 4px solid #4CAF50;
                border-radius: 0 5px 5px 0;
            }
            .frequency-badge {
                display: inline-block;
                background-color: #2196F3;
                color: white;
                padding: 4px 12px;
                border-radius: 15px;
                font-size: 14px;
                font-weight: bold;
                margin: 5px;
            }
            .frequency-explanation {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
                font-size: 14px;
            }
            .no-data { 
                color: #999; 
                font-style: italic; 
                padding: 20px; 
                text-align: center; 
                font-size: 16px;
            }
            .summary-stats {
                display: flex;
                justify-content: space-around;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            .stat-box {
                background-color: #f0f7ff;
                border: 1px solid #cce5ff;
                border-radius: 8px;
                padding: 15px;
                text-align: center;
                min-width: 150px;
                margin: 10px;
            }
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                color: #2196F3;
            }
            .stat-label {
                font-size: 14px;
                color: #666;
                margin-top: 5px;
            }
            .week-schedule {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 10px;
                margin: 20px 0;
            }
            .day-column {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px;
                min-height: 150px;
            }
            .day-header {
                background-color: #f0f0f0;
                padding: 8px;
                text-align: center;
                font-weight: bold;
                margin-bottom: 10px;
                border-radius: 3px;
            }
            .med-on-day {
                background-color: #e3f2fd;
                padding: 5px;
                margin: 3px 0;
                border-radius: 3px;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <h1>Medication Frequency Report</h1>
        <p class="report-period"><strong>Report Generated:</strong> ' . date('F j, Y') . '</p>
        
        <div class="patient-info">
            <h2>Patient Information</h2>
            <p><strong>Name:</strong> ' . htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($patient['email']) . '</p>
            <p><strong>Contact:</strong> ' . htmlspecialchars($patient['contactnum']) . '</p>
        </div>';
    
    if (empty($prescriptionItems)) {
        $html .= '
        <div class="no-data">
            <h3>No Prescription Data Found</h3>
            <p>You do not have any prescription records in the system.</p>
        </div>';
    } else {
        // Calculate statistics
        $totalMeds = count($prescriptionItems);
        $dailyMeds = 0; // Frequency = 1 (daily)
        $alternateDayMeds = 0; // Frequency = 2 (every other day)
        $weeklyMeds = 0; // Frequency = 7 (weekly)
        $customFrequencyMeds = 0; // Other frequencies
        $asNeededMeds = 0; // Frequency = 0
        
        // Create weekly schedule
        $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $weeklySchedule = array_fill(0, 7, []); // 0=Sunday, 1=Monday, etc.
        
        foreach ($prescriptionItems as $item) {
            $frequency = intval($item['frequency']);
            
            // Count by frequency type
            if ($frequency == 0) {
                $asNeededMeds++;
            } elseif ($frequency == 1) {
                $dailyMeds++;
                // Add to all days
                for ($i = 0; $i < 7; $i++) {
                    $weeklySchedule[$i][] = [
                        'name' => $item['name'],
                        'dosage' => $item['dosage'],
                        'brand' => $item['brand']
                    ];
                }
            } elseif ($frequency == 2) {
                $alternateDayMeds++;
                // Add to alternate days (even days: 0, 2, 4, 6)
                for ($i = 0; $i < 7; $i += 2) {
                    $weeklySchedule[$i][] = [
                        'name' => $item['name'],
                        'dosage' => $item['dosage'],
                        'brand' => $item['brand']
                    ];
                }
            } elseif ($frequency == 7) {
                $weeklyMeds++;
                // Add to Sunday only (or choose a specific day)
                $weeklySchedule[0][] = [
                    'name' => $item['name'],
                    'dosage' => $item['dosage'],
                    'brand' => $item['brand']
                ];
            } elseif ($frequency > 0) {
                $customFrequencyMeds++;
                // For custom frequencies (e.g., every 3 days), calculate schedule
                $daysInWeek = 7;
                $dosesPerWeek = floor($daysInWeek / $frequency);
                
                for ($i = 0; $i < $dosesPerWeek; $i++) {
                    $dayIndex = ($i * $frequency) % 7;
                    $weeklySchedule[$dayIndex][] = [
                        'name' => $item['name'],
                        'dosage' => $item['dosage'],
                        'brand' => $item['brand']
                    ];
                }
            }
        }
        
        // Statistics Section
        $html .= '
        <div class="section">
            <h2>Medication Statistics</h2>
            <div class="summary-stats">
                <div class="stat-box">
                    <div class="stat-number">' . $totalMeds . '</div>
                    <div class="stat-label">Total Medications</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">' . $dailyMeds . '</div>
                    <div class="stat-label">Daily Medications</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">' . $alternateDayMeds . '</div>
                    <div class="stat-label">Every Other Day</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">' . $asNeededMeds . '</div>
                    <div class="stat-label">As Needed</div>
                </div>
            </div>
        </div>';
        
        // Frequency Explanation
        $html .= '
        <div class="frequency-explanation">
            <h4>Understanding Frequency Codes:</h4>
            <p><strong>Frequency indicates how often to take 1 dose:</strong></p>
            <ul>
                <li><strong>1</strong> = Once daily (every day)</li>
                <li><strong>2</strong> = Once every 2 days</li>
                <li><strong>3</strong> = Once every 3 days</li>
                <li><strong>7</strong> = Once weekly</li>
                <li><strong>0</strong> = As needed (PRN)</li>
            </ul>
            <p>Example: Frequency "3" means take this medication once every 3 days.</p>
        </div>';
        
        // Main Medication Table
        $html .= '
        <div class="section">
            <h2>Medication Frequency Details</h2>
            <table>
                <tr>
                    <th>Medication</th>
                    <th>Brand</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Schedule</th>
                    <th>Instructions</th>
                    <th>Prescribed By</th>
                    <th>Date Prescribed</th>
                </tr>';
        
        foreach ($prescriptionItems as $item) {
            $frequency = intval($item['frequency']);
            $frequencyText = '';
            $scheduleText = '';
            
            switch($frequency) {
                case 0:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #ff9800;">As Needed</span>';
                    $scheduleText = 'Take when needed';
                    break;
                case 1:
                    $frequencyText = '<span class="frequency-badge">Daily</span>';
                    $scheduleText = 'Every day';
                    break;
                case 2:
                    $frequencyText = '<span class="frequency-badge">Every 2 Days</span>';
                    $scheduleText = 'Alternate days';
                    break;
                case 3:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #4CAF50;">Every 3 Days</span>';
                    $scheduleText = 'Once every 3 days';
                    break;
                case 4:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #9c27b0;">Every 4 Days</span>';
                    $scheduleText = 'Once every 4 days';
                    break;
                case 5:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #795548;">Every 5 Days</span>';
                    $scheduleText = 'Once every 5 days';
                    break;
                case 6:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #607d8b;">Every 6 Days</span>';
                    $scheduleText = 'Once every 6 days';
                    break;
                case 7:
                    $frequencyText = '<span class="frequency-badge" style="background-color: #f44336;">Weekly</span>';
                    $scheduleText = 'Once a week';
                    break;
                default:
                    $frequencyText = '<span class="frequency-badge">Every ' . $frequency . ' Days</span>';
                    $scheduleText = 'Once every ' . $frequency . ' days';
            }
            
            $html .= '
            <tr>
                <td><strong>' . htmlspecialchars($item['name']) . '</strong></td>
                <td>' . htmlspecialchars($item['brand']) . '</td>
                <td>' . htmlspecialchars($item['dosage']) . '</td>
                <td>' . $frequencyText . '</td>
                <td>' . $scheduleText . '</td>
                <td>' . htmlspecialchars($item['description']) . '</td>
                <td>Dr. ' . htmlspecialchars($item['doctor_firstname'] . ' ' . $item['doctor_lastname']) . '</td>
                <td>' . date('M d, Y', strtotime($item['dateprescribed'])) . '</td>
            </tr>';
        }
    }
    
    $html .= '
        <p style="margin-top: 30px; font-size: 12px; color: #666;">
            <em>Report generated by Incredidose System on ' . date('Y-m-d H:i:s') . '</em><br>
            <em>For patient: ' . htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) . ' (ID: ' . $patientid . ')</em>
        </p>
    </body>
    </html>';
    
    return $html;
}

// Main logic
if (isset($_SESSION['userid']) && $_SESSION['role'] === 'ptnt') {
    // Check if download is requested
    if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
        $html = generateWeeklyFrequencyReport();
        
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output the PDF as a download
        $filename = 'medication_frequency_report_' . date('Y-m-d') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    } else {
        // Display HTML preview
        $html = generateWeeklyFrequencyReport();
        echo $html;
    }
} else {
    echo '<p>Please log in as a patient to view your medication frequency report.</p>';
}
?>