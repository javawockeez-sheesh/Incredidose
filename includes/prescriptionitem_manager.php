<?php
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Max-Age: 86400");
    http_response_code(200);
    exit(0);
}

header("Content-Type: application/json");

function getPrescriptionItems($prescriptionid, $sort) { 
    global $db;
    if($sort == 0){
        $stmt = $db->prepare("SELECT * FROM prescriptionitem WHERE prescriptionid = ? ORDER BY name ASC");
    }else{
        $stmt = $db->prepare("SELECT * FROM prescriptionitem WHERE prescriptionid = ? ORDER BY name DESC");
    }
    $stmt->execute([$prescriptionid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

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

function addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions) {
    global $db;
    $stmt = $db->prepare("INSERT INTO prescriptionitem (prescriptionid, name, brand, quantity, dosage, frequency, description, substitutions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions]);
    return $stmt->insert_id;
}

function updatePrescriptionItem($prescriptionitemid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions) {
    global $db;
    $stmt = $db->prepare("UPDATE prescriptionitem SET name = ?, brand = ?, quantity = ?, dosage = ?, frequency = ?, description = ?, substitutions = ? WHERE prescriptionitemid = ?");
    $stmt->execute([$name, $brand, $quantity, $dosage, $frequency, $description, $substitutions, $prescriptionitemid]);
    return $stmt->affected_rows;
}

function deletePrescriptionItem($prescriptionitemid) {
    global $db;
    $stmt = $db->prepare("DELETE FROM prescriptionitem WHERE prescriptionitemid = ?");
    $stmt->execute([$prescriptionitemid]);
    return $stmt->affected_rows;
}

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
        switch ($action) {
        case "getPrescriptionItems":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            $sort = $_GET['sort'] ?? '';
            echo json_encode(getPrescriptionItems($prescriptionid, $sort));
            break;
            
        case "getPrescriptionItemsByName":
            $prescriptionid = $_GET['prescriptionid'] ?? '';
            $prescriptionname = $_GET['prescriptionname'] ?? '';
            echo json_encode(getPrescriptionItemsByName($prescriptionid, $prescriptionname));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid GET action']);
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
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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
                echo json_encode(['success' => false, 'message' => 'Missing prescriptionitemid']);
                break;
            }
            
            $affectedRows = updatePrescriptionItem($prescriptionitemid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
            echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
            break;

         case "deletePrescriptionItem":
            $prescriptionitemid = $input['prescriptionitemid'] ?? '';
            
            if (empty($prescriptionitemid)) {
                echo json_encode(['success' => false, 'message' => 'Missing prescriptionitemid']);
                break;
            }
            
            $affectedRows = deletePrescriptionItem($prescriptionitemid);
            echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid POST action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>