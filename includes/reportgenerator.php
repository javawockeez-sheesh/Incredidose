<?php
include("db.php");

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;


session_start();
    

function generatePatientReport() {
    global $db;
    $patientid = $_SESSION['userid'];
    // Get patient info
    $stmt = $db->prepare("SELECT * FROM user WHERE userid = ?");
    $stmt->execute([$patientid]);
    $patient = $stmt->get_result()->fetch_assoc();
    
    if (!$patient) {
        die('Patient not found');
    }
    
    // Get prescriptions for this patient
    $stmt = $db->prepare("
        SELECT p.*, u.firstname as doctor_firstname, u.lastname as doctor_lastname 
        FROM prescription p
        INNER JOIN practitioner pr ON p.doctorid = pr.userid
        INNER JOIN user u ON pr.userid = u.userid
        WHERE p.patientid = ?
        ORDER BY p.dateprescribed DESC
    ");
    $stmt->execute([$patientid]);
    $prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // For each prescription, get items
    foreach ($prescriptions as &$prescription) {
        $stmt = $db->prepare("
            SELECT * FROM prescriptionitem 
            WHERE prescriptionid = ?
        ");
        $stmt->execute([$prescription['prescriptionid']]);
        $prescription['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Generate HTML directly
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Patient Prescription Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>Patient Prescription Report</h1>
        
        <h2>Patient Information</h2>
        <p><strong>Name:</strong> ' . htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($patient['email']) . '</p>
        <p><strong>Contact:</strong> ' . htmlspecialchars($patient['contactnum']) . '</p>
        
        <h2>Prescriptions</h2>';
    
    if (empty($prescriptions)) {
        $html .= '<p>No prescriptions found</p>';
    } else {
        foreach ($prescriptions as $prescription) {
            $html .= '
            <div style="margin-bottom: 30px;">
                <h3>Prescription #' . $prescription['prescriptionid'] . '</h3>
                <p><strong>Date:</strong> ' . $prescription['dateprescribed'] . '</p>
                <p><strong>Doctor:</strong> Dr. ' . htmlspecialchars($prescription['doctor_firstname'] . ' ' . $prescription['doctor_lastname']) . '</p>
                
                <h4>Medications:</h4>';
            
            if (empty($prescription['items'])) {
                $html .= '<p>No medications</p>';
            } else {
                $html .= '
                <table>
                    <tr>
                        <th>Medication</th>
                        <th>Brand</th>
                        <th>Quantity</th>
                        <th>Dosage</th>
                        <th>Instructions</th>
                    </tr>';
                
                foreach ($prescription['items'] as $item) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['name']) . '</td>
                        <td>' . htmlspecialchars($item['brand']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . htmlspecialchars($item['dosage']) . '</td>
                        <td>' . htmlspecialchars($item['description']) . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }
            $html .= '</div>';
        }
    }
    
    $html .= '
        <p style="margin-top: 30px;"><em>Generated on: ' . date('Y-m-d H:i:s') . '</em></p>
    </body>
    </html>';
    
    return $html;
}

// Check if it's a report request
if (isset($_GET['patientid'])) {
    $html = generatePatientReport($_GET['patientid']);
    
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output the PDF as a download
    $filename = 'patient_report_' . $_GET['patientid'] . '_' . date('Y-m-d') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $dompdf->output();
    exit;
}
?>