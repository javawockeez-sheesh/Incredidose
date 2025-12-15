<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Start session
session_start();

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

function isPatient() {
    if (!isset($_SESSION['userid']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    global $db;
    $stmt = $db->prepare("SELECT role FROM user WHERE userid = ?");
    $stmt->execute([$_SESSION['userid']]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return false;
    
    $row = $result->fetch_assoc();
    return $row['role'] === 'ptnt';
}

function getPrescriptions($patientid) {
    global $db;
    $stmt = $db->prepare((isDoctor()) ? 
    //Query if doctor
    "
        SELECT DISTINCT prescription.*, user.email, user.contactnum 
        FROM prescription 
        INNER JOIN user ON prescription.patientid = user.userid 
        WHERE patientid = ? AND doctorid = ?
    "
    : //Query if other user roles
    "   
        SELECT DISTINCT prescription.*, user.email, user.contactnum 
        FROM prescription 
        INNER JOIN user ON prescription.patientid = user.userid 
        WHERE patientid = ?
    "
    );

    (isDoctor()) ? $stmt->execute([$patientid, $_SESSION['userid']]) : $stmt->execute([$_SESSION['userid']]);
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
        if (empty($patientid)) {
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