<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';
require_once 'security/auth.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/ItemController.php';
require_once 'controllers/RatingController.php';
require_once 'controllers/PurchaseController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', trim($uri, '/'));
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

$auth = new AuthController();
$items = new ItemController();
$ratings = new RatingController();
$purchases = new PurchaseController();


if($uri[0] === 'auth') {
    if($uri[1] === 'register' && $method === 'POST') {
        echo json_encode($auth->register($data));
    } elseif($uri[1] === 'login' && $method === 'POST') {
        echo json_encode($auth->login($data));
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}


elseif($uri[0] === 'items') {
    if(!isset($uri[1])) {
        if($method === 'GET') {
            $search = $_GET['search'] ?? '';
            $page = $_GET['page'] ?? 1;
            echo json_encode($items->getItems($search, $page));
        } elseif($method === 'POST') {
            echo json_encode($items->createItem($data));
        }
    } elseif(isset($uri[1]) && !isset($uri[2])) {
        if($method === 'GET') {
            echo json_encode($items->getItemById($uri[1]));
        }
    } elseif(isset($uri[2]) && $uri[2] === 'ratings' && $method === 'POST') {
        echo json_encode($ratings->submitRating($uri[1], $data));
    } elseif(isset($uri[2]) && $uri[2] === 'purchase' && $method === 'POST') {
        echo json_encode($purchases->purchaseItem($uri[1]));
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}


elseif($uri[0] === 'purchases') {
    if($uri[1] === 'history' && $method === 'GET') {
        echo json_encode($purchases->getPurchaseHistory());
    } elseif($uri[1] === 'sales' && $method === 'GET') {
        echo json_encode($purchases->getSalesHistory());
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}

else {
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}
?>