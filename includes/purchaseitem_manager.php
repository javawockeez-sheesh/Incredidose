<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
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

// Check if user is pharmacist (type = 'pharmacist' in practitioner table)
function isPharmacist() {
    if (!isAuthenticated() || $_SESSION['role'] !== 'pcr') {
        return false;
    }
    
    // Check practitioner type if not already in session
    if (!isset($_SESSION['practitioner_type'])) {
        global $db;
        $stmt = $db->prepare("SELECT type FROM practitioner WHERE userid = ?");
        $stmt->execute([$_SESSION['userid']]);
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['practitioner_type'] = $row['type'];
        } else {
            return false;
        }
    }
    
    return $_SESSION['practitioner_type'] === 'pharmacist';
}

function isAdminOnly() {
    return isAuthenticated() && $_SESSION['role'] === 'admn';
}

// =============== PURCHASE ITEM FUNCTIONS ===============

// Get all purchase items for a purchase
function getPurchaseItemsByPurchase($purchaseid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            presi.name as medication_name,
            presi.brand as medication_brand,
            presi.dosage,
            presi.quantity as prescribed_quantity,
            presi.description
        FROM purchaseitem pi
        JOIN prescriptionitem presi ON pi.precriptionitemid = presi.prescriptionitemid
        WHERE pi.purchaseid = ?
        ORDER BY pi.purchaseitemid
    ");
    $stmt->execute([$purchaseid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

// Get purchase item by ID
function getPurchaseItemById($purchaseitemid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            presi.name as medication_name,
            presi.brand as medication_brand,
            presi.dosage,
            presi.quantity as prescribed_quantity,
            presi.description,
            p.patientid,
            p.pharmacistid,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname
        FROM purchaseitem pi
        JOIN prescriptionitem presi ON pi.precriptionitemid = presi.prescriptionitemid
        JOIN purchase p ON pi.purchaseid = p.purchaseid
        JOIN user pat ON p.patientid = pat.userid
        WHERE pi.purchaseitemid = ?
    ");
    $stmt->execute([$purchaseitemid]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }
    return $result->fetch_assoc();
}

// Add purchase item to a purchase
function addPurchaseItem($purchaseitemData) {
    global $db;
    
    // Validate required fields
    $requiredFields = ['purchaseid', 'precriptionitemid', 'unitprice', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($purchaseitemData[$field]) || $purchaseitemData[$field] === '') {
            return ['success' => false, 'error' => "Missing required field: $field"];
        }
    }
    
    // Check if purchase exists and get pharmacist ID
    $purchaseCheck = $db->prepare("SELECT purchaseid, pharmacistid FROM purchase WHERE purchaseid = ?");
    $purchaseCheck->execute([$purchaseitemData['purchaseid']]);
    $purchase = $purchaseCheck->get_result()->fetch_assoc();
    
    if (!$purchase) {
        return ['success' => false, 'error' => 'Purchase not found'];
    }
    
    // Check if current user is the pharmacist who created this purchase
    if ($purchase['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only add items to your own purchases'];
    }
    
    // Check if prescription item exists
    $prescriptionItemCheck = $db->prepare("
        SELECT prescriptionitemid, quantity as max_quantity 
        FROM prescriptionitem 
        WHERE prescriptionitemid = ?
    ");
    $prescriptionItemCheck->execute([$purchaseitemData['precriptionitemid']]);
    $prescriptionItem = $prescriptionItemCheck->get_result()->fetch_assoc();
    
    if (!$prescriptionItem) {
        return ['success' => false, 'error' => 'Prescription item not found'];
    }
    
    // Check if quantity exceeds prescribed quantity
    if ($purchaseitemData['quantity'] > $prescriptionItem['max_quantity']) {
        return ['success' => false, 'error' => 'Quantity exceeds prescribed amount'];
    }
    
    // Calculate total price
    $totalprice = $purchaseitemData['unitprice'] * $purchaseitemData['quantity'];
    
    // Check if this prescription item is already in the purchase
    $existingCheck = $db->prepare("
        SELECT purchaseitemid 
        FROM purchaseitem 
        WHERE purchaseid = ? AND precriptionitemid = ?
    ");
    $existingCheck->execute([$purchaseitemData['purchaseid'], $purchaseitemData['precriptionitemid']]);
    if ($existingCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'This medication is already in the purchase'];
    }
    
    // Insert purchase item
    $stmt = $db->prepare("
        INSERT INTO purchaseitem (purchaseid, unitprice, quantity, totalprice, precriptionitemid) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseitemData['purchaseid'],
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemData['precriptionitemid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseItemId = $db->insert_id;
        
        // Update prescription item quantity (reduce available quantity)
        $updatePrescription = $db->prepare("
            UPDATE prescriptionitem 
            SET quantity = quantity - ? 
            WHERE prescriptionitemid = ?
        ");
        $updatePrescription->execute([$purchaseitemData['quantity'], $purchaseitemData['precriptionitemid']]);
        
        return [
            'success' => true,
            'message' => 'Purchase item added successfully',
            'purchaseitemid' => $purchaseItemId,
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add purchase item'];
}

// Update purchase item
function updatePurchaseItem($purchaseitemid, $purchaseitemData) {
    global $db;
    
    // Get current purchase item data
    $currentItem = getPurchaseItemById($purchaseitemid);
    if (!$currentItem) {
        return ['success' => false, 'error' => 'Purchase item not found'];
    }
    
    // Check if current user is the pharmacist who owns this purchase
    if ($currentItem['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only update items in your own purchases'];
    }
    
    // Validate required fields
    $requiredFields = ['unitprice', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($purchaseitemData[$field]) || $purchaseitemData[$field] === '') {
            return ['success' => false, 'error' => "Missing required field: $field"];
        }
    }
    
    // Calculate quantity difference
    $quantityDiff = $purchaseitemData['quantity'] - $currentItem['quantity'];
    
    // Check prescription item quantity limits
    $prescriptionItemCheck = $db->prepare("
        SELECT quantity as available_quantity 
        FROM prescriptionitem 
        WHERE prescriptionitemid = ?
    ");
    $prescriptionItemCheck->execute([$currentItem['precriptionitemid']]);
    $prescriptionItem = $prescriptionItemCheck->get_result()->fetch_assoc();
    
    // If increasing quantity, check if enough is available
    if ($quantityDiff > 0 && $quantityDiff > $prescriptionItem['available_quantity']) {
        return ['success' => false, 'error' => 'Insufficient available medication quantity'];
    }
    
    // Calculate new total price
    $totalprice = $purchaseitemData['unitprice'] * $purchaseitemData['quantity'];
    
    // Update purchase item
    $stmt = $db->prepare("
        UPDATE purchaseitem 
        SET unitprice = ?, quantity = ?, totalprice = ? 
        WHERE purchaseitemid = ?
    ");
    
    $stmt->execute([
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemid
    ]);
    
    if ($stmt->affected_rows > 0) {
        // Update prescription item quantity
        if ($quantityDiff != 0) {
            $updatePrescription = $db->prepare("
                UPDATE prescriptionitem 
                SET quantity = quantity - ? 
                WHERE prescriptionitemid = ?
            ");
            $updatePrescription->execute([$quantityDiff, $currentItem['precriptionitemid']]);
        }
        
        return [
            'success' => true,
            'message' => 'Purchase item updated successfully',
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'No changes made or failed to update purchase item'];
}

// Delete purchase item
function deletePurchaseItem($purchaseitemid) {
    global $db;
    
    // Get current purchase item data
    $currentItem = getPurchaseItemById($purchaseitemid);
    if (!$currentItem) {
        return ['success' => false, 'error' => 'Purchase item not found'];
    }
    
    // Check if current user is the pharmacist who owns this purchase
    if ($currentItem['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only delete items from your own purchases'];
    }
    
    // Check if purchase is already paid
    $paymentCheck = $db->prepare("
        SELECT status FROM payment WHERE purchaseid = ?
    ");
    $paymentCheck->execute([$currentItem['purchaseid']]);
    $payment = $paymentCheck->get_result()->fetch_assoc();
    
    if ($payment && $payment['status'] === 'Completed') {
        return ['success' => false, 'error' => 'Cannot delete items from a completed purchase'];
    }
    
    // Delete purchase item
    $stmt = $db->prepare("DELETE FROM purchaseitem WHERE purchaseitemid = ?");
    $stmt->execute([$purchaseitemid]);
    
    if ($stmt->affected_rows > 0) {
        // Return quantity to prescription item
        $updatePrescription = $db->prepare("
            UPDATE prescriptionitem 
            SET quantity = quantity + ? 
            WHERE prescriptionitemid = ?
        ");
        $updatePrescription->execute([$currentItem['quantity'], $currentItem['precriptionitemid']]);
        
        return [
            'success' => true,
            'message' => 'Purchase item deleted successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to delete purchase item'];
}

// Calculate purchase total
function calculatePurchaseTotal($purchaseid) {
    global $db;
    $stmt = $db->prepare("
        SELECT SUM(totalprice) as total_amount 
        FROM purchaseitem 
        WHERE purchaseid = ?
    ");
    $stmt->execute([$purchaseid]);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total_amount'] ?: 0;
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
    case "getPurchaseItemsByPurchase":
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
        $items = getPurchaseItemsByPurchase($purchaseid);
        
        // Get purchase info for permission check
        $purchaseCheck = $db->prepare("SELECT patientid FROM purchase WHERE purchaseid = ?");
        $purchaseCheck->execute([$purchaseid]);
        $purchase = $purchaseCheck->get_result()->fetch_assoc();
        
        if (!$purchase) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase not found']);
            break;
        }
        
        // Check if user has permission to view these items
        if ($_SESSION['role'] === 'ptnt' && $_SESSION['userid'] != $purchase['patientid'] && 
            !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($items);
        break;

    case "getPurchaseItemById":
        // Check authentication - pharmacist and admin can access
        if (!isAuthenticated() || !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['purchaseitemid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'purchaseitemid parameter is required']);
            break;
        }
        
        $purchaseitemid = $_GET['purchaseitemid'];
        $item = getPurchaseItemById($purchaseitemid);
        
        if (!$item) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase item not found']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($item);
        break;
        
    case "addPurchaseItem":
        // EXCLUSIVE: Only pharmacists can add purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can add purchase items.'
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
        
        header('Content-Type: application/json');
        echo json_encode(addPurchaseItem($data));
        break;
        
    case "updatePurchaseItem":
        // EXCLUSIVE: Only pharmacists can update purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can update purchase items.'
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
        
        $purchaseitemid = isset($_GET['purchaseitemid']) ? $_GET['purchaseitemid'] : (isset($data['purchaseitemid']) ? $data['purchaseitemid'] : null);
        
        if (!$purchaseitemid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase Item ID is required']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(updatePurchaseItem($purchaseitemid, $data));
        break;
        
    case "deletePurchaseItem":
        // EXCLUSIVE: Only pharmacists can delete purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can delete purchase items.'
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
        
        $purchaseitemid = isset($_GET['purchaseitemid']) ? $_GET['purchaseitemid'] : (isset($data['purchaseitemid']) ? $data['purchaseitemid'] : null);
        
        if (!$purchaseitemid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase Item ID is required']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(deletePurchaseItem($purchaseitemid));
        break;
        
    case "calculatePurchaseTotal":
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
        $total = calculatePurchaseTotal($purchaseid);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total_amount' => $total]);
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?><?php
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