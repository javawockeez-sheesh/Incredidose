<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function getPrescriptionItems($prescriptionid, $sort) { //Sort: 0 = ASC, 1 = DESC
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

$action = $_GET['action'] ?? '';

switch ($action) {
    case "getPrescriptionItems":
        $prescriptionid = $_GET['prescriptionid'];
        $sort = $_GET['sort'];
        header('Content-Type: application/json');
        echo json_encode(getPrescriptionItems($prescriptionid, $sort));
        break;

    case "getPrescriptionItemsByName":
        $prescriptionid = $_GET['prescriptionid'];
        $prescriptionname = $_GET['prescriptionname'];
        header('Content-Type: application/json');
        echo json_encode(getPrescriptionItemsByName($prescriptionid, $prescriptionname));
        break;

    case "addPrescriptionItem":
        $prescriptionid = $_GET['prescriptionid'];
        $name = $_GET['name'];
        $brand = $_GET['brand'];
        $quantity = $_GET['quantity'];
        $dosage = $_GET['dosage'];
        $frequency = $_GET['frequency'];
        $description = $_GET['description'];
        $substitutions = $_GET['substitutions'];
        $newId = addPrescriptionItem($prescriptionid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
        echo json_encode(['success' => true, 'prescriptionitem_id' => $newId]);
        break;

    case "updatePrescriptionItem":
        $prescriptionitemid = $_GET['prescriptionitemid'];
        $name = $_GET['name'];
        $brand = $_GET['brand'];
        $quantity = $_GET['quantity'];
        $dosage = $_GET['dosage'];
        $frequency = $_GET['frequency'];
        $description = $_GET['description'];
        $substitutions = $_GET['substitutions'];
        $affectedRows = updatePrescriptionItem($prescriptionitemid, $name, $brand, $quantity, $dosage, $frequency, $description, $substitutions);
        echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
        break;

    case "deletePrescriptionItem":
        $prescriptionitemid = $_GET['prescriptionitemid'];
        $affectedRows = deletePrescriptionItem($prescriptionitemid);
        echo json_encode(['success' => true, 'affected_rows' => $affectedRows]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>