<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

session_start();

function isDoctor() {
    return $_SESSION['role'] == 'doctor';
}

function isAdmin() {
    return $_SESSION['role'] == 'admn';
}

function isPatient(){
    return $_SESSION['role'] == 'ptnt'; 
}

function doctorOwnsPrescription($prescriptionid) {
    global $db;
    $stmt = $db->prepare("SELECT doctorid FROM prescription WHERE prescriptionid = ?");
    $stmt->execute([$prescriptionid]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;
    
    $row = $result->fetch_assoc();
    return $row['doctorid'] == $_SESSION['userid'];
}

// Get prescription items
function getPrescriptionItems($prescriptionid) { 
    global $db;
    $stmt = $db->prepare("SELECT pi.*, GREATEST(0, pi.quantity - COALESCE((SELECT SUM(pui.quantity) FROM purchase pu JOIN purchaseitem pui 
                                    ON pu.purchaseid = pui.purchaseid WHERE pu.prescriptionid = pi.prescriptionid AND 
                                    pui.prescriptionitemid = pi.prescriptionitemid), 0)) as available FROM 
                                    prescriptionitem pi WHERE pi.presAcriptionid = ?");
    $stmt->execute([$prescriptionid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions) {
    global $db;
    $stmt = $db->prepare("INSERT INTO prescriptionitem (prescriptionid, name, brand, quantity, dosage, frequency, description, substitutions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions]);
    return $stmt->insert_id;
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    switch ($action) {
        case "getPrescriptionItems":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            if (empty($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionid required']);
                break;
            }
            
            echo json_encode(getPrescriptionItems($prescriptionid));
            break;
            
        default:

            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}
elseif ($method === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
       case "addPrescriptionItem":
            $prescriptionid = $input['prescriptionid'] ?? '';
            $name = $input['name'] ?? '';
            $brand = $input['brand'] ?? '';
            $quantity = $input['quantity'] ?? '';
            $dosage = $input['dosage'] ?? '';
            $frequency = $input['frequency'] ?? '';
            $description = $input['description'] ?? '';
            $substitutions = $input['substitutions'] ?? '';

            if(!isDoctor()){
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
                break;
            }
            
            if (!doctorOwnsPrescription($prescriptionid)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
                break;
            }

            if (empty($prescriptionid) || empty($name)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Incomplete form fields.']);
                break;
            }

            $newId = addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
            echo json_encode(['success' => true, 'prescriptionitem_id' => $newId]);
            break;
    }
} else {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
}
?>