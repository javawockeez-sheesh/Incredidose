<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Start session
session_start();

// Check if user is doctor
function isDoctor() {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    // Check if user is practitioner and type is doctor
    global $db;
    $stmt = $db->prepare("SELECT type FROM practitioner WHERE userid = ?");
    $stmt->execute([$_SESSION['userid']]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;
    
    $row = $result->fetch_assoc();
    return $row['type'] === 'doctor';
}

// Check if doctor owns this prescription
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
    $stmt = $db->prepare("SELECT * FROM prescriptionitem WHERE prescriptionid = ?");
    $stmt->execute([$prescriptionid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Search prescription items by name
function getPrescriptionItemsByName($prescriptionid, $prescriptionname) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM prescriptionitem WHERE prescriptionid = ? AND name LIKE ?");
    $stmt->execute([$prescriptionid, "%".$prescriptionname."%"]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Add prescription item
function addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions) {
    global $db;
    $stmt = $db->prepare("INSERT INTO prescriptionitem (prescriptionid, name, brand, quantity, dosage, frequency, description, substitutions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions]);
    return $stmt->insert_id;
}

// Update prescription item
function updatePrescriptionItem($prescriptionitemid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions) {
    global $db;
    $stmt = $db->prepare("UPDATE prescriptionitem SET name = ?, brand = ?, quantity = ?, dosage = ?, frequency = ?, description = ?, substitutions = ? WHERE prescriptionitemid = ?");
    $stmt->execute([$name, $brand, $quantity, $dosage, $frequency, $description, $substitutions, $prescriptionitemid]);
    return $stmt->affected_rows;
}

// Delete prescription item
function deletePrescriptionItem($prescriptionitemid) {
    global $db;
    $stmt = $db->prepare("DELETE FROM prescriptionitem WHERE prescriptionitemid = ?");
    $stmt->execute([$prescriptionitemid]);
    return $stmt->affected_rows;
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// =============== DOCTOR-ONLY ACCESS CHECK ===============
if ($action !== '') {
    if (!isDoctor()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Doctors only.']);
        exit();
    }
}

if ($method === 'GET') {
    switch ($action) {
        case "getPrescriptionItems":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            if (empty($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionid required']);
                break;
            }
            
            if (!doctorOwnsPrescription($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'Not your prescription']);
                break;
            }
            
            echo json_encode(getPrescriptionItems($prescriptionid));
            break;
            
        case "getPrescriptionItemsByName":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            $prescriptionname = $_GET['prescriptionname'] ?? '';
            
            if (empty($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionid required']);
                break;
            }
            
            if (!doctorOwnsPrescription($prescriptionid)) {
                echo json_encode(['success' => false, 'error' => 'Not your prescription']);
                break;
            }
            
            echo json_encode(getPrescriptionItemsByName($prescriptionid, $prescriptionname));
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

         case "updatePrescriptionItem":
            $prescriptionitemid = $input['prescriptionitemid'] ?? '';
            $name = $input['name'] ?? '';
            $brand = $input['brand'] ?? '';
            $quantity = $input['quantity'] ?? '';
            $dosage = $input['dosage'] ?? '';
            $frequency = $input['frequency'] ?? '';
            $description = $input['description'] ?? '';
            $substitutions = $input['substitutions'] ?? '';
            
            if (empty($prescriptionitemid)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionitemid required']);
                break;
            }
            
            // Get prescription ID to check ownership
            global $db;
            $checkStmt = $db->prepare("SELECT prescriptionid FROM prescriptionitem WHERE prescriptionitemid = ?");
            $checkStmt->execute([$prescriptionitemid]);
            $result = $checkStmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
                break;
            }
            $row = $result->fetch_assoc();
            
            if (!doctorOwnsPrescription($row['prescriptionid'])) {
                echo json_encode(['success' => false, 'error' => 'Not your prescription']);
                break;
            }
            
            $affectedRows = updatePrescriptionItem($prescriptionitemid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
            echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
            break;

         case "deletePrescriptionItem":
            $prescriptionitemid = $input['prescriptionitemid'] ?? '';
            
            if (empty($prescriptionitemid)) {
                echo json_encode(['success' => false, 'error' => 'prescriptionitemid required']);
                break;
            }
            
            // Get prescription ID to check ownership
            global $db;
            $checkStmt = $db->prepare("SELECT prescriptionid FROM prescriptionitem WHERE prescriptionitemid = ?");
            $checkStmt->execute([$prescriptionitemid]);
            $result = $checkStmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Item not found']);
                break;
            }
            $row = $result->fetch_assoc();
            
            if (!doctorOwnsPrescription($row['prescriptionid'])) {
                echo json_encode(['success' => false, 'error' => 'Not your prescription']);
                break;
            }
            
            $affectedRows = deletePrescriptionItem($prescriptionitemid);
            echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
}
?>