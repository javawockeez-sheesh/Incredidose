<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true"); // Added for session support

// Start session for authentication (MUST match your login.php)
session_start();

// =============== AUTHENTICATION FUNCTIONS ===============
// These match your login.php session structure

function isAuthenticated() {
    return isset($_SESSION['userid']) && isset($_SESSION['role']) && isset($_SESSION['email']);
}

function hasRequiredRole($requiredRoles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    return in_array($_SESSION['role'], $requiredRoles);
}

function isDoctorOnly() {
    return isAuthenticated() && $_SESSION['role'] === 'doctor';
}

// =============== PATIENT FUNCTIONS ===============

function getPatients($doctorid) {
    global $db;
    $stmt = $db->prepare("SELECT u.*, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? GROUP BY u.userid");
    $stmt->execute([$doctorid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getPatientByName($doctorid, $patientname) {
    global $db;
    $stmt = $db->prepare("SELECT u.*, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? AND (u.firstname LIKE ? OR u.lastname LIKE ?) GROUP BY u.userid");
    $stmt->execute([$doctorid, "%".$patientname."%", "%".$patientname."%"]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getPatientById($patientid) {
    global $db;
    $stmt = $db->prepare("SELECT * from User where userid = ?");
    $stmt->execute([$patientid]);
    $result = $stmt->get_result();
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

// =============== REQUEST HANDLING ===============

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper function to get JSON body
function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case "getPatients":
        // Check authentication - doctor, pharmacist, admin can access
        if (!isAuthenticated() || !hasRequiredRole(['doctor', 'pharmacist', 'admin'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['doctorid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'doctorid parameter is required']);
            break;
        }
        
        $doctorid = $_GET['doctorid'];
        header('Content-Type: application/json');
        echo json_encode(getPatients($doctorid));
        break;

    case "getPatientByName":
        // Check authentication - doctor, pharmacist, admin can access
        if (!isAuthenticated() || !hasRequiredRole(['doctor', 'pharmacist', 'admin'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['doctorid']) || !isset($_GET['patientname'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'doctorid and patientname parameters are required']);
            break;
        }
        
        $doctorid = $_GET['doctorid'];
        $patientname = $_GET['patientname'];
        header('Content-Type: application/json');
        echo json_encode(getPatientByName($doctorid, $patientname));
        break;

    case "getPatientById":
        // Check authentication - doctor, pharmacist, admin can access
        if (!isAuthenticated() || !hasRequiredRole(['pcr', 'pharmacist', 'admin'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['patientid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'patientid parameter is required']);
            break;
        }
        
        $patientid = $_GET['patientid'];
        header('Content-Type: application/json');
        echo json_encode(getPatientById($patientid));
        break;
        
    case "getAllPatients":
        // Check authentication - doctor, pharmacist, admin can access
        if (!isAuthenticated() || !hasRequiredRole(['doctor', 'pharmacist', 'admin'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(getAllPatients());
        break;
        
    case "addPatient":
        // EXCLUSIVE: Only doctors can add patients
        if (!isDoctorOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only doctors can add patients.'
            ]);
            break;
        }
        
        // Get POST data
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST']);
            break;
        }
        
        // Validate required fields
        $requiredFields = ['firstname', 'lastname', 'email', 'contactnum'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(addPatient($data));
        break;
        
    case "editPatient":
        // EXCLUSIVE: Only doctors can edit patients
        if (!isDoctorOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only doctors can edit patients.'
            ]);
            break;
        }
        
        // Get PUT/POST data
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
        
        // Validate required fields
        $requiredFields = ['firstname', 'lastname', 'email', 'contactnum'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid email format']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(editPatient($patientid, $data));
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}