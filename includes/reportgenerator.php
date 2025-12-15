<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

session_start();

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function generatePatientReport() {
    global $db;
    
    $patientid = $_SESSION['userid'];
    // Get patient info
    $stmt = $db->prepare("SELECT * FROM user WHERE userid = ?");
    $stmt->execute([$patientid]);
    $patient = $stmt->get_result()->fetch_assoc();
    
    if (!$patient) {
        return ['error' => 'Patient not found'];
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
    
    // For each prescription, get items and purchases
    foreach ($prescriptions as &$prescription) {
        // Get prescription items
        $stmt = $db->prepare("
            SELECT * FROM prescriptionitem 
            WHERE prescriptionid = ?
        ");
        $stmt->execute([$prescription['prescriptionid']]);
        $prescription['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get purchases for this prescription
        $stmt = $db->prepare("
            SELECT pu.*, u.firstname as pharmacist_firstname, u.lastname as pharmacist_lastname
            FROM purchase pu
            INNER JOIN user u ON pu.pharmacistid = u.userid
            WHERE pu.prescriptionid = ?
            ORDER BY pu.purchasetimestamp DESC
        ");
        $stmt->execute([$prescription['prescriptionid']]);
        $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // For each purchase, get purchase items
        foreach ($purchases as &$purchase) {
            $stmt = $db->prepare("
                SELECT pi.*, pri.name, pri.brand, pri.dosage
                FROM purchaseitem pi
                INNER JOIN prescriptionitem pri ON pi.precriptionitemid = pri.prescriptionitemid
                WHERE pi.purchaseid = ?
            ");
            $stmt->execute([$purchase['purchaseid']]);
            $purchase['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        $prescription['purchases'] = $purchases;
    }
    
    // Build HTML report
    $html = buildHTMLReport($patient, $prescriptions);
    
    // Generate PDF
    $pdf = generatePDF($html);
    
    return [
        'success' => true,
        'pdf_base64' => base64_encode($pdf),
        'filename' => 'patient_report_' . $patientid . '_' . date('Y-m-d') . '.pdf'
    ];
}

function buildHTMLReport($patient, $prescriptions) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Patient Medical Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
            h2 { color: #34495e; margin-top: 30px; }
            h3 { color: #7f8c8d; }
            .patient-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .prescription { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .prescription-items, .purchase-items { margin-left: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .no-data { color: #999; font-style: italic; }
        </style>
    </head>
    <body>
        <h1>Patient Medical Report</h1>
        
        <div class="patient-info">
            <h2>Patient Information</h2>
            <p><strong>Name:</strong> ' . htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($patient['email']) . '</p>
            <p><strong>Contact:</strong> ' . htmlspecialchars($patient['contactnum']) . '</p>
            <p><strong>Birthdate:</strong> ' . htmlspecialchars($patient['birthdate']) . '</p>
            <p><strong>Gender:</strong> ' . htmlspecialchars($patient['gender']) . '</p>
        </div>
        
        <h2>Prescriptions</h2>';
    
    if (empty($prescriptions)) {
        $html .= '<p class="no-data">No prescriptions found</p>';
    } else {
        foreach ($prescriptions as $prescription) {
            $html .= '
            <div class="prescription">
                <h3>Prescription #' . $prescription['prescriptionid'] . '</h3>
                <p><strong>Date Prescribed:</strong> ' . $prescription['dateprescribed'] . '</p>
                <p><strong>Valid Until:</strong> ' . $prescription['validperiod'] . '</p>
                <p><strong>Doctor:</strong> Dr. ' . htmlspecialchars($prescription['doctor_firstname'] . ' ' . $prescription['doctor_lastname']) . '</p>
                
                <h4>Medications:</h4>';
            
            if (empty($prescription['items'])) {
                $html .= '<p class="no-data">No medications prescribed</p>';
            } else {
                $html .= '
                <table class="prescription-items">
                    <tr>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Quantity</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Description</th>
                    </tr>';
                
                foreach ($prescription['items'] as $item) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['name']) . '</td>
                        <td>' . htmlspecialchars($item['brand']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . htmlspecialchars($item['dosage']) . '</td>
                        <td>' . $item['frequency'] . ' times/day</td>
                        <td>' . htmlspecialchars($item['description']) . '</td>
                    </tr>';
                }
                $html .= '</table>';
            }
            
            $html .= '<h4>Purchases:</h4>';
            
            if (empty($prescription['purchases'])) {
                $html .= '<p class="no-data">No purchases recorded</p>';
            } else {
                foreach ($prescription['purchases'] as $purchase) {
                    $html .= '
                    <div class="purchase">
                        <p><strong>Purchase Date:</strong> ' . $purchase['purchasetimestamp'] . '</p>
                        <p><strong>Pharmacist:</strong> ' . htmlspecialchars($purchase['pharmacist_firstname'] . ' ' . $purchase['pharmacist_lastname']) . '</p>
                        
                        <table class="purchase-items">
                            <tr>
                                <th>Medication</th>
                                <th>Brand</th>
                                <th>Dosage</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>';
                    
                    foreach ($purchase['items'] as $item) {
                        $html .= '
                        <tr>
                            <td>' . htmlspecialchars($item['name']) . '</td>
                            <td>' . htmlspecialchars($item['brand']) . '</td>
                            <td>' . htmlspecialchars($item['dosage']) . '</td>
                            <td>' . $item['quantity'] . '</td>
                            <td>$' . number_format($item['unitprice'], 2) . '</td>
                            <td>$' . number_format($item['totalprice'], 2) . '</td>
                        </tr>';
                    }
                    $html .= '</table></div>';
                }
            }
            
            $html .= '</div>';
        }
    }
    
    $html .= '
        <div class="footer">
            <p>Report generated on: ' . date('Y-m-d H:i:s') . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generatePDF($html) {
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    return $dompdf->output();
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'generatePatientReport') {
        
        $result = generatePatientReport($patientid);
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
?>