<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

session_start();

// =============== AUTHENTICATION FUNCTIONS ===============
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

function isPharmacistOnly() {
    return isAuthenticated() && $_SESSION['role'] === 'pcr' && isset($_SESSION['practitioner_type']) && $_SESSION['practitioner_type'] === 'pharmacist';
}

function isAdminOnly() {
    return isAuthenticated() && $_SESSION['role'] === 'admn';
}

// =============== PURCHASE FUNCTIONS ===============

// Get all purchases (for pharmacists and admins)
function getAllPurchases() {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            p.purchaseid,
            p.purchasetimestamp,
            p.patientid,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname,
            p.pharmacistid,
            pharm.firstname as pharmacist_firstname,
            pharm.lastname as pharmacist_lastname,
            p.prescriptionid,
            pres.dateprescribed,
            pres.doctorid,
            doc.firstname as doctor_firstname,
            doc.lastname as doctor_lastname,
            SUM(pi.totalprice) as total_amount,
            pay.status as payment_status
        FROM purchase p
        JOIN user pat ON p.patientid = pat.userid
        JOIN user pharm ON p.pharmacistid = pharm.userid
        JOIN prescription pres ON p.prescriptionid = pres.prescriptionid
        JOIN user doc ON pres.doctorid = doc.userid
        LEFT JOIN purchaseitem pi ON p.purchaseid = pi.purchaseid
        LEFT JOIN payment pay ON p.purchaseid = pay.purchaseid
        GROUP BY p.purchaseid
        ORDER BY p.purchasetimestamp DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Get purchases by patient ID
function getPurchasesByPatient($patientid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            p.purchaseid,
            p.purchasetimestamp,
            p.patientid,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname,
            p.pharmacistid,
            pharm.firstname as pharmacist_firstname,
            pharm.lastname as pharmacist_lastname,
            p.prescriptionid,
            pres.dateprescribed,
            pres.doctorid,
            doc.firstname as doctor_firstname,
            doc.lastname as doctor_lastname,
            SUM(pi.totalprice) as total_amount,
            pay.status as payment_status
        FROM purchase p
        JOIN user pat ON p.patientid = pat.userid
        JOIN user pharm ON p.pharmacistid = pharm.userid
        JOIN prescription pres ON p.prescriptionid = pres.prescriptionid
        JOIN user doc ON pres.doctorid = doc.userid
        LEFT JOIN purchaseitem pi ON p.purchaseid = pi.purchaseid
        LEFT JOIN payment pay ON p.purchaseid = pay.purchaseid
        WHERE p.patientid = ?
        GROUP BY p.purchaseid
        ORDER BY p.purchasetimestamp DESC
    ");
    $stmt->execute([$patientid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Get purchases by pharmacist ID
function getPurchasesByPharmacist($pharmacistid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            p.purchaseid,
            p.purchasetimestamp,
            p.patientid,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname,
            p.pharmacistid,
            pharm.firstname as pharmacist_firstname,
            pharm.lastname as pharmacist_lastname,
            p.prescriptionid,
            pres.dateprescribed,
            pres.doctorid,
            doc.firstname as doctor_firstname,
            doc.lastname as doctor_lastname,
            SUM(pi.totalprice) as total_amount,
            pay.status as payment_status
        FROM purchase p
        JOIN user pat ON p.patientid = pat.userid
        JOIN user pharm ON p.pharmacistid = pharm.userid
        JOIN prescription pres ON p.prescriptionid = pres.prescriptionid
        JOIN user doc ON pres.doctorid = doc.userid
        LEFT JOIN purchaseitem pi ON p.purchaseid = pi.purchaseid
        LEFT JOIN payment pay ON p.purchaseid = pay.purchaseid
        WHERE p.pharmacistid = ?
        GROUP BY p.purchaseid
        ORDER BY p.purchasetimestamp DESC
    ");
    $stmt->execute([$pharmacistid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Get purchase by ID
function getPurchaseById($purchaseid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            p.*,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname,
            pat.email as patient_email,
            pat.contactnum as patient_contact,
            pharm.firstname as pharmacist_firstname,
            pharm.lastname as pharmacist_lastname,
            pres.dateprescribed,
            pres.validperiod,
            doc.firstname as doctor_firstname,
            doc.lastname as doctor_lastname
        FROM purchase p
        JOIN user pat ON p.patientid = pat.userid
        JOIN user pharm ON p.pharmacistid = pharm.userid
        JOIN prescription pres ON p.prescriptionid = pres.prescriptionid
        JOIN user doc ON pres.doctorid = doc.userid
        WHERE p.purchaseid = ?
    ");
    $stmt->execute([$purchaseid]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }
    return $result->fetch_assoc();
}

// Create a new purchase (pharmacist only)
function createPurchase($purchaseData) {
    global $db;
    
    // Validate required fields
    if (empty($purchaseData['patientid']) || empty($purchaseData['prescriptionid'])) {
        return ['success' => false, 'error' => 'Patient ID and Prescription ID are required'];
    }
    
    // Check if prescription exists and is valid
    $prescriptionCheck = $db->prepare("
        SELECT prescriptionid, validperiod 
        FROM prescription 
        WHERE prescriptionid = ? AND patientid = ?
    ");
    $prescriptionCheck->execute([$purchaseData['prescriptionid'], $purchaseData['patientid']]);
    $prescription = $prescriptionCheck->get_result()->fetch_assoc();
    
    if (!$prescription) {
        return ['success' => false, 'error' => 'Prescription not found or does not belong to this patient'];
    }
    
    // Check if prescription is still valid
    if ($prescription['validperiod'] && strtotime($prescription['validperiod']) < time()) {
        return ['success' => false, 'error' => 'Prescription has expired'];
    }
    
    // Check if purchase already exists for this prescription
    $existingCheck = $db->prepare("SELECT purchaseid FROM purchase WHERE prescriptionid = ?");
    $existingCheck->execute([$purchaseData['prescriptionid']]);
    if ($existingCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'Purchase already exists for this prescription'];
    }
    
    // Get pharmacist ID from session
    $pharmacistid = $_SESSION['userid'];
    
    // Insert new purchase
    $stmt = $db->prepare("
        INSERT INTO purchase (purchasetimestamp, patientid, pharmacistid, prescriptionid) 
        VALUES (NOW(), ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseData['patientid'],
        $pharmacistid,
        $purchaseData['prescriptionid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseId = $db->insert_id;
        return [
            'success' => true,
            'message' => 'Purchase created successfully',
            'purchaseid' => $purchaseId
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to create purchase'];
}

// Update purchase (admin only - for corrections)
function updatePurchase($purchaseid, $purchaseData) {
    global $db;
    
    // Check if purchase exists
    $checkStmt = $db->prepare("SELECT purchaseid FROM purchase WHERE purchaseid = ?");
    $checkStmt->execute([$purchaseid]);
    if ($checkStmt->get_result()->num_rows === 0) {
        return ['success' => false, 'error' => 'Purchase not found'];
    }
    
    // Update purchase information
    $stmt = $db->prepare("
        UPDATE purchase 
        SET patientid = ?, pharmacistid = ?, prescriptionid = ? 
        WHERE purchaseid = ?
    ");
    
    $stmt->execute([
        $purchaseData['patientid'],
        $purchaseData['pharmacistid'],
        $purchaseData['prescriptionid'],
        $purchaseid
    ]);
    
    if ($stmt->affected_rows > 0) {
        return [
            'success' => true,
            'message' => 'Purchase updated successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'No changes made or failed to update purchase'];
}

// Delete purchase (admin only)
function deletePurchase($purchaseid) {
    global $db;
    
    // Check if purchase exists
    $checkStmt = $db->prepare("SELECT purchaseid FROM purchase WHERE purchaseid = ?");
    $checkStmt->execute([$purchaseid]);
    if ($checkStmt->get_result()->num_rows === 0) {
        return ['success' => false, 'error' => 'Purchase not found'];
    }
    
    // Check if purchase has payment records
    $paymentCheck = $db->prepare("SELECT paymentid FROM payment WHERE purchaseid = ?");
    $paymentCheck->execute([$purchaseid]);
    if ($paymentCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'Cannot delete purchase with existing payment records'];
    }
    
    // Delete purchase items first
    $deleteItems = $db->prepare("DELETE FROM purchaseitem WHERE purchaseid = ?");
    $deleteItems->execute([$purchaseid]);
    
    // Delete purchase
    $stmt = $db->prepare("DELETE FROM purchase WHERE purchaseid = ?");
    $stmt->execute([$purchaseid]);
    
    if ($stmt->affected_rows > 0) {
        return [
            'success' => true,
            'message' => 'Purchase deleted successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to delete purchase'];
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
    case "getAllPurchases":
        // Check authentication - pharmacist and admin can access
        if (!isAuthenticated() || !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(getAllPurchases());
        break;

    case "getPurchasesByPatient":
        // Check authentication - pharmacist, admin, and patient can access
        if (!isAuthenticated()) {
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
        
        // Check if user is patient viewing their own records, or has appropriate role
        if ($_SESSION['role'] === 'ptnt' && $_SESSION['userid'] != $patientid && !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(getPurchasesByPatient($patientid));
        break;

    case "getPurchasesByPharmacist":
        // Check authentication - pharmacist and admin can access
        if (!isAuthenticated() || !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['pharmacistid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'pharmacistid parameter is required']);
            break;
        }
        
        $pharmacistid = $_GET['pharmacistid'];
        
        // Pharmacists can only view their own purchases unless they're admin
        if ($_SESSION['role'] === 'pcr' && $_SESSION['userid'] != $pharmacistid && !isAdminOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied. Can only view your own purchases.']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(getPurchasesByPharmacist($pharmacistid));
        break;

    case "getPurchaseById":
        // Check authentication - pharmacist, admin, and patient can access
        if (!isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['purchaseid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'purchaseid parameter is required']);
            break;
        }
        
        $purchaseid = $_GET['purchaseid'];
        $purchase = getPurchaseById($purchaseid);
        
        if (!$purchase) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase not found']);
            break;
        }
        
        // Check if user has permission to view this purchase
        if ($_SESSION['role'] === 'ptnt' && $_SESSION['userid'] != $purchase['patientid'] && 
            !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($purchase);
        break;
        
    case "createPurchase":
        // EXCLUSIVE: Only pharmacists can create purchases
        if (!isPharmacistOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can create purchases.'
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
        $requiredFields = ['patientid', 'prescriptionid'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(createPurchase($data));
        break;
        
    case "updatePurchase":
        // EXCLUSIVE: Only admins can update purchases
        if (!isAdminOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only admins can update purchases.'
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
        
        $purchaseid = isset($_GET['purchaseid']) ? $_GET['purchaseid'] : (isset($data['purchaseid']) ? $data['purchaseid'] : null);
        
        if (!$purchaseid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase ID is required']);
            break;
        }
        
        // Validate required fields
        $requiredFields = ['patientid', 'pharmacistid', 'prescriptionid'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
                break 2;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(updatePurchase($purchaseid, $data));
        break;
        
    case "deletePurchase":
        // EXCLUSIVE: Only admins can delete purchases
        if (!isAdminOnly()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only admins can delete purchases.'
            ]);
            break;
        }
        
        // Get DELETE data
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use DELETE or POST']);
            break;
        }
        
        $purchaseid = isset($_GET['purchaseid']) ? $_GET['purchaseid'] : (isset($data['purchaseid']) ? $data['purchaseid'] : null);
        
        if (!$purchaseid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase ID is required']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(deletePurchase($purchaseid));
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>