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
    if (!isAuthenticated() || $_SESSION['role'] !== 'pharmacist') {
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

// Add purchase item to a purchase
function addPurchaseItem($purchaseitemData) {
    global $db;
    
    // Validate required fields
    $requiredFields = ['purchaseid', 'prescriptionitemid', 'unitprice', 'quantity'];
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
    $prescriptionItemCheck->execute([$purchaseitemData['prescriptionitemid']]);
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
        WHERE purchaseid = ? AND prescriptionitemid = ?
    ");
    $existingCheck->execute([$purchaseitemData['purchaseid'], $purchaseitemData['prescriptionitemid']]);
    if ($existingCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'This medication is already in the purchase'];
    }
    
    // Insert purchase item
    $stmt = $db->prepare("
        INSERT INTO purchaseitem (purchaseid, unitprice, quantity, totalprice, prescriptionitemid) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseitemData['purchaseid'],
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemData['prescriptionitemid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseItemId = $db->insert_id;
        
        return [
            'success' => true,
            'message' => 'Purchase item added successfully',
            'purchaseitemid' => $purchaseItemId,
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add purchase item'];
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

        
    case "addPurchaseItem":

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
        
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>