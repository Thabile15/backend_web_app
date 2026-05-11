<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/auth.php';

class RatingController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function submitRating($itemId, $data) {
        $user = getAuthUser();

        if(!$user) {
            return ['error' => 'Unauthorized'];
        }

        if(empty($data['score']) || !in_array($data['score'], [1,2,3,4,5])) {
            return ['error' => 'Score must be between 1 and 5'];
        }

        try {
            // Check item exists and get seller info
            $stmt = $this->conn->prepare("
                SELECT seller_id FROM items WHERE item_id = :item_id
            ");
            $stmt->execute([':item_id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$item) {
                return ['error' => 'Item not found'];
            }

            // Prevent seller from rating their own item
            if($item['seller_id'] === $user['user_id']) {
                return ['error' => 'You cannot rate your own item'];
            }

            // Submit the rating
            $stmt = $this->conn->prepare("
                INSERT INTO ratings (item_id, user_id, score)
                VALUES (:item_id, :user_id, :score)
                RETURNING rating_id, score, created_at
            ");

            $stmt->execute([
                ':item_id' => $itemId,
                ':user_id' => $user['user_id'],
                ':score' => $data['score']
            ]);

            $rating = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'message' => 'Rating submitted successfully',
                'rating' => $rating
            ];

        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'unique') !== false) {
                return ['error' => 'You have already rated this item'];
            }
            return ['error' => 'Failed to submit rating'];
        }
    }
}
?>