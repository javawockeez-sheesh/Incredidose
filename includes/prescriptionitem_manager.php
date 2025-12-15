<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

session_start();

//Validate if the user is a doctor
function isDoctor() {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT type FROM practitioner WHERE userid = ?");
    $stmt->execute([$_SESSION['userid']]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;
    
    $row = $result->fetch_assoc();
    return $row['type'] === 'doctor';
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
            
            if (!doctorOwnsPrescription($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'only doctors can add prescriptions']);
                break;
            }

            if (empty($prescriptionid) || empty($name)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionid and name required']);
                break;
            }
            
            if (!doctorOwnsPrescription($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'Not your prescription']);
                break;
            }

            $newId = addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
            echo json_encode(['success' => true, 'prescriptionitem_id' => $newId]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
}
?>