<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-Amz-Date, Authorization, X-Api-Key, X-Amz-Security-Token");

session_start();



function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function loginUser($email, $password) {
    global $db;
    
    $stmt = $db->prepare("SELECT userid, firstname, lastname, email, role, password FROM user WHERE email = ?");
    if (!$stmt) return ['success' => false, 'error' => $db->error];
    
    $stmt->execute([$email]);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'error' => 'invalid email or password'];
    }
    
    $row = $result->fetch_assoc();
    
    if ($row['password'] !== $password) {
        return ['success' => false, 'error' => 'invalid email or password'];
    }

    $token = generateToken();
    
    $_SESSION['userid'] = $row['userid'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['firstname'] = $row['firstname'];
    $_SESSION['lastname'] = $row['lastname'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['token'] = $token;
    $_SESSION['login_time'] = time();
    
    return [
        'success' => true,
        'userid' => $row['userid'],
        'firstname' => $row['firstname'],
        'lastname' => $row['lastname'],
        'email' => $row['email'],
        'role' => $row['role'],
        'token' => $token
    ];
}

function logoutUser() {
    session_destroy();
    return ['success' => true, 'message' => 'logged out'];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case 'login':
        // Accept POST only
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'method not allowed, POST required']);
            break;
        }
        
        $data = getJsonBody();
        $email = isset($data['email']) ? trim($data['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
        $password = isset($data['password']) ? $data['password'] : (isset($_POST['password']) ? $_POST['password'] : '');
        
        header('Content-Type: application/json');
        
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'email and password are required']);
            break;
        }
        
        $result = loginUser($email, $password);
        if (!$result['success']) {
            http_response_code(401);
        }
        echo json_encode($result);
        break;

    case 'logout':
        header('Content-Type: application/json');
        $result = logoutUser();
        echo json_encode($result);
        break;

    case 'session':
        // Return current session data if logged in
        header('Content-Type: application/json');
        if (isset($_SESSION['userid'])) {
            echo json_encode([
                'success' => true,
                'userid' => $_SESSION['userid'],
                'firstname' => $_SESSION['firstname'],
                'lastname' => $_SESSION['lastname'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'not authenticated']);
        }
        break;

    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'invalid action']);
        break;
}
?>