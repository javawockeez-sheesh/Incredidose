<?php
include("db.php");
include("error_handler.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

session_start();

//Check if user is logged in
if (!isset($_SESSION['userid'])) {
    sendError(401, "User not logged in.");
    return;
}

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
                                    prescriptionitem pi WHERE pi.prescriptionid = ?");
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
    $logStmt = $db->prepare("INSERT INTO log (action, description, timestamp, targetentitytype, targetid, userid) VALUES (?, ?, NOW(), ?, ?, ?)");
    $logStmt->execute([ 
        'ADD_PRESCRIPTION_ITEM',
        'Added prescription item: ' . $name . ' to prescription ID: ' . $prescriptionid,
        'prescriptionitem',
        $stmt->insert_id,
        $_SESSION['userid']
    ]);
    return $stmt->insert_id;
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    switch ($action) {
        case "getPrescriptionItems":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            if (empty($prescriptionid)) {
                sendError(400, "Prescription id is required");
            }
            
            echo json_encode(getPrescriptionItems($prescriptionid));
            break;
            
        default:

            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}
elseif ($method === 'POST') {

    function getJsonBody() {
        $input = file_get_contents('php://input');
        if (!$input) return [];
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }
    
    switch ($action) {

       case "addPrescriptionItem":

            $data = getJsonBody();
            
            $prescriptionid = $data['prescriptionid'] ?? '';
            $name = $data['name'] ?? '';
            $brand = $data['brand'] ?? '';
            $quantity = $data['quantity'] ?? '';
            $dosage = $data['dosage'] ?? '';
            $frequency = $data['frequency'] ?? '';
            $description = $data['description'] ?? '';
            $substitutions = $data['substitutions'] ?? '';

            if(!isDoctor()){
                sendError(401, "Unauthorized Access");
                break;
            }

            $newId = addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
            echo json_encode(['success' => true, 'prescriptionitem_id' => $newId]);
            break;
    }
} else {
    sendError(400, "Invalid action");
}
?>