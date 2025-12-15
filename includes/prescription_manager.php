<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Start session
session_start();

function isDoctor() {
    return $_SESSION['role'] == 'doctor';
}

function isPatient() {
    return $_SESSION['role'] === 'ptnt';
}

function isPharmacist() {
    return $_SESSION['role'] === 'pharmacist';
}

function isAdmin() {
    return $_SESSION['role'] === 'admn';
}

function getPrescriptions($patientid) {
    global $db;
    $stmt = $db->prepare((isDoctor()) ? 
    //Query if doctor
    "
        SELECT DISTINCT prescription.*, user.firstname, user.lastname
        FROM prescription 
        INNER JOIN user ON prescription.doctorid = user.userid 
        WHERE patientid = ? AND doctorid = ?
    "
    : //Query if other user roles
    "   
        SELECT DISTINCT prescription.*, user.firstname, user.lastname
        FROM prescription 
        INNER JOIN user ON prescription.doctorid = user.userid 
        WHERE patientid = ?
    "
    );

    $id = (isPharmacist() || isAdmin()) ? $patientid : $_SESSION['userid'];

    (isDoctor()) ? $stmt->execute([$patientid, $_SESSION['userid']]) : $stmt->execute([$id]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Add a new prescription
function addPrescription($patientid) {
    global $db;
    
    if (!isDoctor()) {
        return ['error' => 'Only doctors can create prescriptions'];
    }
    
    $stmt = $db->prepare("INSERT INTO prescription (patientid, doctorid) VALUES (?, ?)");
    $stmt->execute([$patientid, $_SESSION['userid']]);
    $id = $stmt->insert_id;

    return ["id" => $id];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case "getPrescriptions":
        $patientid = $_GET['patientid'] ?? '';
        if (empty($patientid) && isDoctor()) {
            echo json_encode(['error' => 'patientid required']);
            break;
        }
        
        echo json_encode(getPrescriptions($patientid));
        break;

    case "addPrescription":
        $doctorid = $_GET['doctorid'] ?? '';
        $patientid = $_GET['patientid'] ?? '';
        
        if (empty($doctorid) || empty($patientid)) {
            echo json_encode(['error' => 'doctorid, and patientid required']);
            break;
        }
        
        echo json_encode(addPrescription($patientid, $doctorid));
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>