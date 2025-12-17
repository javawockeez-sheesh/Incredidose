<?php
include("db.php");
include("error_handler.php");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");


// Start session
session_start();

//Check if user is logged in
if (!isset($_SESSION['userid'])) {
    sendError(401, "User not logged in.");
    return;
}

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
    
    $stmt = $db->prepare("INSERT INTO prescription (patientid, doctorid) VALUES (?, ?)");
    $stmt->execute([$patientid, $_SESSION['userid']]);
    $id = $stmt->insert_id;

    $logStmt = $db->prepare("INSERT INTO log (action, description, timestamp, targetentitytype, targetid, userid) VALUES (?, ?, NOW(), ?, ?, ?)");
    $logStmt->execute([
        'ADD_PRESCRIPTION',
        'Added prescription ID: ' . $id . ' for patient ID: ' . $patientid,
        'prescription',
        $id,
        $_SESSION['userid']
    ]);

    return ["id" => $id];
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case "getPrescriptions":
        $patientid = $_GET['patientid'] ?? '';
        if (empty($patientid) && isDoctor()) {
            sendError(401, "Unauthorized Access");
            break;
        }
        
        echo json_encode(getPrescriptions($patientid));
        break;

    case "addPrescription":
        $patientid = $_GET['patientid'] ?? '';
        
        if (empty($patientid)) {
            sendError(400, "Missing field/s");
            break;
        }

        if (!isDoctor()) {
            sendError(401, "Unauthorized Access");
            break;
        }
        
        echo json_encode(addPrescription($patientid));
        break;
        
    default:
        sendError(400, "Invalid Action");
        break;
}
?>