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

// Get prescriptions for a patient (doctor can view patients they prescribed to)
function getPrescriptions($patientid) {
    global $db;
    $stmt = $db->prepare("
        SELECT DISTINCT prescription.*, user.email, user.contactnum 
        FROM prescription 
        INNER JOIN user ON prescription.patientid = user.userid 
        WHERE patientid = ? AND doctorid = ?
    ");
    $stmt->execute([$patientid, $_SESSION['userid']]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getPrescriptionsPharmacist($patientid) {
    global $db;
    $stmt = $db->prepare("
        SELECT DISTINCT prescription.*, user.email, user.contactnum 
        FROM prescription 
        INNER JOIN user ON prescription.patientid = user.userid 
        WHERE patientid = ?
    ");
    $stmt->execute([$patientid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Add a new prescription
function addPrescription($patientid, $doctorid) {
    global $db;
    
    // Make sure doctor is adding prescription for themselves
    if ($doctorid != $_SESSION['userid']) {
        return ['error' => 'Cannot create prescription for another doctor'];
    }
    
    $stmt = $db->prepare("INSERT INTO prescription (patientid, doctorid) VALUES (?, ?)");
    $stmt->execute([$patientid, $doctorid]);
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

    //TINATAMAD AKONG AYUSING YUNG IBANG CODE KAYA GUMAWA NALANG AKO NG BAGO
    case "getPrescriptionsPharmacist":
        $patientid = $_GET['patientid'] ?? '';
        if (empty($patientid)) {
            echo json_encode(['error' => 'patientid required']);
            break;
        }
        
        echo json_encode(getPrescriptionsPharmacist($patientid));
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