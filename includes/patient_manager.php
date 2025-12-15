<?php
include("db.php");
include("log.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

session_start();

//Validate if user is a doctor
function isDoctor() {
    return $_SESSION['role'] == 'doctor';
}

function isAdmin() {
    return $_SESSION['role'] == 'admn';
}

function isPatient(){
    return $_SESSION['role'] == 'ptnt'; 
}

function getPatients() {
    global $db;
    $stmt = $db->prepare("SELECT u.*, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? GROUP BY u.userid");
    $stmt->execute([$_SESSION['userid']]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getPatientByEmail($email) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM user WHERE user.email = ?");
    $stmt->execute([$email]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getAllPatients() {
    global $db;
    $result = $db->query("SELECT * FROM user");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
} 

function addPatient($patientData) {
    global $db;
    
    // Check if patient already exists with same email
    $checkStmt = $db->prepare("SELECT userid FROM user WHERE email = ?");
    $checkStmt->execute([$patientData['email']]);
    if ($checkStmt->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'Patient with this email already exists'];
    }
    
    // Insert new patient
    $stmt = $db->prepare("INSERT INTO user (firstname, lastname, email, contactnum, birthdate, gender, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'ptnt')");
    
    // Generate a simple default password
    $defaultPassword = "Patient123";
    
    $stmt->execute([
        $patientData['firstname'],
        $patientData['lastname'],
        $patientData['email'],
        $patientData['contactnum'],
        $patientData['birthdate'],
        $patientData['gender'],
        $defaultPassword
    ]);
    
    if ($stmt->affected_rows > 0) {
        $patientId = $db->insert_id;
        return [
            'success' => true,
            'message' => 'Patient added successfully',
            'patientid' => $patientId
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add patient'];
}

function editPatient($patientid, $patientData) {
    global $db;
    
    // Check if patient exists
    $checkStmt = $db->prepare("SELECT userid FROM user WHERE userid = ?");
    $checkStmt->execute([$patientid]);
    if ($checkStmt->get_result()->num_rows === 0) {
        return ['success' => false, 'error' => 'Patient not found'];
    }
    
    // Check if email is already used by another patient
    $emailCheckStmt = $db->prepare("SELECT userid FROM user WHERE email = ? AND userid != ?");
    $emailCheckStmt->execute([$patientData['email'], $patientid]);
    if ($emailCheckStmt->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'Email already in use by another patient'];
    }
    
    // Update patient information
    $stmt = $db->prepare("UPDATE user SET firstname = ?, lastname = ?, email = ?, contactnum = ? WHERE userid = ?");
    $stmt->execute([
        $patientData['firstname'],
        $patientData['lastname'],
        $patientData['email'],
        $patientData['contactnum'],
        $patientid
    ]);
    
    if ($stmt->affected_rows > 0) {
        return [
            'success' => true,
            'message' => 'Patient updated successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'No changes made or failed to update patient'];

}

$action = isset($_GET['action']) ? $_GET['action'] : '';

function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case "getPatients":

        if(!isDoctor()){
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(getPatients());
        break;
    
    case "getPatientByEmail":

        if (!filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            break;
        }

        header('Content-Type: application/json');
        echo json_encode(getPatientByEmail($_GET['email']));
        break;

    case "getAllPatients":
        
        if(isDoctor() || isPatient()){
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
            break;
        }

        header('Content-Type: application/json');
        echo json_encode(getAllPatients());
        break;
        
    case "addPatient":
        
        if (!isDoctor()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
            break;
        }
        
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST']);
            break;
        }

        $requiredFields = ['firstname', 'lastname', 'email', 'contactnum'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            break;
        }
        
        header('Content-Type: application/json');
        logAction('ADD_PATIENT', 'Added patient with email: ' . $data['email'], 'patient', $newId);
        echo json_encode(addPatient($data));
        break;
        
    case "editPatient":

        if (!isDoctor()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only doctors can edit patients.'
            ]);
            break;
        }
        
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use PUT or POST']);
            break;
        }
        
        $patientid = isset($_GET['patientid']) ? $_GET['patientid'] : (isset($data['patientid']) ? $data['patientid'] : null);
        
        if (!$patientid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Patient ID is required']);
            break;
        }
        
        $requiredFields = ['firstname', 'lastname', 'email', 'contactnum'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            break;
        }
        
        header('Content-Type: application/json');
        logAction('EDIT_PATIENT', 'Edited patient ID: ' . $patientid, 'patient', $patientid);
        echo json_encode(editPatient($patientid, $data));
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}