<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/auth.php';

class ItemController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function createItem($data) {
        $user = getAuthUser();
        
        if(!$user) {
            return ['error' => 'Unauthorized'];
        }

        if(!in_array($user['user_type'], ['seller', 'both'])) {
            return ['error' => 'Only sellers can post items'];
        }

        if(empty($data['name']) || empty($data['price'])) {
            return ['error' => 'Name and price are required'];
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO items (name, description, price, seller_id)
                VALUES (:name, :description, :price, :seller_id)
                RETURNING item_id, name, description, price, status, posted_at
            ");

            $stmt->execute([
                ':name' => $data['name'],
                ':description' => $data['description'] ?? null,
                ':price' => $data['price'],
                ':seller_id' => $user['user_id']
            ]);

            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'message' => 'Item posted successfully',
                'item' => $item
            ];

        } catch(PDOException $e) {
            return ['error' => 'Failed to create item'];
        }
    }

    public function getItems($search = '', $page = 1) {
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $searchTerm = '%' . $search . '%';

        try {
            $stmt = $this->conn->prepare("
                SELECT i.item_id, i.name, i.price, i.status, i.posted_at,
                       u.username as seller_name
                FROM items i
                JOIN users u ON i.seller_id = u.user_id
                WHERE i.name ILIKE :search
                AND i.status = 'available'
                ORDER BY i.posted_at DESC
                LIMIT :limit OFFSET :offset
            ");

            $stmt->bindValue(':search', $searchTerm);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'page' => $page
            ];

        } catch(PDOException $e) {
            return ['error' => 'Failed to fetch items'];
        }
    }

    public function getItemById($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT i.item_id, i.name, i.description, i.price, i.status, 
                       i.posted_at, u.username as seller_name, u.user_id as seller_id,
                       ROUND(AVG(r.score), 1) as avg_rating
                FROM items i
                JOIN users u ON i.seller_id = u.user_id
                LEFT JOIN ratings r ON i.item_id = r.item_id
                WHERE i.item_id = :id
                GROUP BY i.item_id, u.username, u.user_id
            ");

            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$item) {
                return ['error' => 'Item not found'];
            }

            return ['item' => $item];

        } catch(PDOException $e) {
            return ['error' => 'Failed to fetch item'];
        }
    }

    public function markAsSold($itemId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE items SET status = 'sold'
                WHERE item_id = :id
            ");

            $stmt->execute([':id' => $itemId]);
            return ['message' => 'Item marked as sold'];

        } catch(PDOException $e) {
            return ['error' => 'Failed to update item status'];
        }
    }
}
?>