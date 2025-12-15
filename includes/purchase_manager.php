<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

session_start();

//Validate if the user is a doctor
function isDoctor() {
    return $_SESSION['role'] == 'doctor';
}

function isAdmin() {
    return $_SESSION['role'] == 'admn';
}

function isPatient(){
    return $_SESSION['role'] == 'ptnt'; 
}

function isPharmacist(){
    return $_SESSION['role'] == 'pharmacist'; 
}

function getPurchasesByPrescription($prescriptionid) {
    global $db;
    $stmt = $db->prepare("
        SELECT * FROM purchase p
            INNER JOIN purchaseitem i ON p.purchaseid = i.purchaseid 
            INNER JOIN prescriptionitem pi ON i.prescriptionitemid = pi.prescriptionitemid
            WHERE p.prescriptionid = ?

    ");
    $stmt->execute([$prescriptionid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function createPurchase($purchaseData) {
    global $db;

    if (empty($purchaseData['patientid']) || empty($purchaseData['prescriptionid'])) {
        return ['success' => false, 'error' => 'Patient ID and Prescription ID are required'];
    }

    $prescriptionCheck = $db->prepare("
        SELECT prescriptionid
        FROM prescription 
        WHERE prescriptionid = ? AND patientid = ?
    ");

    $prescriptionCheck->execute([$purchaseData['prescriptionid'], $purchaseData['patientid']]);
    $prescription = $prescriptionCheck->get_result()->fetch_assoc();
    
    if (!$prescription) {
        return ['success' => false, 'error' => 'Prescription not found or does not belong to this patient'];
    }
    
    $pharmacistid = $_SESSION['userid'];

    $stmt = $db->prepare("
        INSERT INTO purchase (purchasetimestamp, patientid, pharmacistid, prescriptionid) 
        VALUES (NOW(), ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseData['patientid'],
        $pharmacistid,
        $purchaseData['prescriptionid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseId = $db->insert_id;
        $logStmt = $db->prepare("INSERT INTO log (action, description, timestamp, targetentitytype, targetid, userid) VALUES (?, ?, NOW(), ?, ?, ?)");
        $logStmt->execute([
            'CREATE_PURCHASE',
            'Created purchase ID: ' . $purchaseId . ' for prescription ID: ' . $purchaseData['prescriptionid'],
            'purchase',
            $purchaseId,
            $_SESSION['userid']
        ]);
        
        return [
            'success' => true,
            'message' => 'Purchase created successfully',
            'purchaseid' => $purchaseId
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to create purchase'];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case "getPurchasesByPrescription":
        
        if (!isset($_GET['prescriptionid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'prescriptionid parameter is required']);
            break;
        }
        
        $prescriptionid = $_GET['prescriptionid'];
        
        header('Content-Type: application/json');
        echo json_encode(getPurchasesByPrescription($prescriptionid));
        break;
        
    case "createPurchase":

        if(!isPharmacist()){
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
            break;
        }

        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST']);
            break;
        }
        
        $requiredFields = ['patientid', 'prescriptionid'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(createPurchase($data));
        break;
    }
?>