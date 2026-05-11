<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/auth.php';
require_once __DIR__ . '/../controllers/ItemController.php';

class PurchaseController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function purchaseItem($itemId) {
        $user = getAuthUser();

        if(!$user) {
            return ['error' => 'Unauthorized'];
        }

        if(!in_array($user['user_type'], ['buyer', 'both'])) {
            return ['error' => 'Only buyers can purchase items'];
        }

        try {
            // Check item exists and is available
            $stmt = $this->conn->prepare("
                SELECT * FROM items WHERE item_id = :item_id
            ");
            $stmt->execute([':item_id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$item) {
                return ['error' => 'Item not found'];
            }

            if($item['status'] !== 'available') {
                return ['error' => 'Item is no longer available'];
            }

            // Prevent buying own item
            if($item['seller_id'] == $user['user_id']) {
                return ['error' => 'You cannot buy your own item'];
            }

            // Create the purchase
            $stmt = $this->conn->prepare("
                INSERT INTO purchases (item_id, buyer_id, seller_id, status)
                VALUES (:item_id, :buyer_id, :seller_id, 'pending')
                RETURNING purchase_id, item_id, buyer_id, seller_id, status, purchased_at
            ");

            $stmt->execute([
                ':item_id' => $itemId,
                ':buyer_id' => $user['user_id'],
                ':seller_id' => $item['seller_id']
            ]);

            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            // Mark item as sold
            $itemController = new ItemController();
            $itemController->markAsSold($itemId);

            return [
                'message' => 'Purchase successful',
                'purchase' => $purchase
            ];

        } catch(PDOException $e) {
            return ['error' => 'Purchase failed: ' . $e->getMessage()];
        }
    }

    public function getPurchaseHistory() {
        $user = getAuthUser();

        if(!$user) {
            return ['error' => 'Unauthorized'];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT p.purchase_id, p.status, p.purchased_at,
                       i.name as item_name, i.price,
                       u.username as seller_name
                FROM purchases p
                JOIN items i ON p.item_id = i.item_id
                JOIN users u ON p.seller_id = u.user_id
                WHERE p.buyer_id = :user_id
                ORDER BY p.purchased_at DESC
            ");

            $stmt->execute([':user_id' => $user['user_id']]);

            return [
                'purchases' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch(PDOException $e) {
            return ['error' => 'Failed to fetch purchase history'];
        }
    }

    public function getSalesHistory() {
        $user = getAuthUser();

        if(!$user) {
            return ['error' => 'Unauthorized'];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT p.purchase_id, p.status, p.purchased_at,
                       i.name as item_name, i.price,
                       u.username as buyer_name
                FROM purchases p
                JOIN items i ON p.item_id = i.item_id
                JOIN users u ON p.buyer_id = u.user_id
                WHERE p.seller_id = :user_id
                ORDER BY p.purchased_at DESC
            ");

            $stmt->execute([':user_id' => $user['user_id']]);

            return [
                'sales' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];

        } catch(PDOException $e) {
            return ['error' => 'Failed to fetch sales history'];
        }
    }
}
?>