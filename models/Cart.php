<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Product.php';

/**
 * Model Cart - Quản lý giỏ hàng bền vững trong Database (Persistent Cart)
 * Đặc tính:
 * 1. Đóng trình duyệt, hết phiên đăng nhập (session timeout) hoặc đăng xuất -> Giỏ hàng vẫn được lưu trong DB.
 * 2. Khi đăng nhập lại trên bất kỳ thiết bị/trình duyệt nào -> Tự động khôi phục toàn bộ sản phẩm đã chọn.
 * 3. Tự động đồng bộ giá bán hiện tại và giới hạn tồn kho thực tế từ CSDL.
 */
class Cart extends BaseModel
{
    private static bool $schemaChecked = false;

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `user_carts` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `product_id` int(11) NOT NULL,
                    `color` varchar(100) NOT NULL DEFAULT '',
                    `quantity` int(11) NOT NULL DEFAULT 1,
                    `selected` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_product_color_unique` (`user_id`, `product_id`, `color`),
                    KEY `fk_user_carts_product` (`product_id`),
                    CONSTRAINT `fk_user_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_user_carts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Đảm bảo cột selected tồn tại nếu bảng đã được tạo trước đó
            try {
                $checkCol = $this->db->query("SHOW COLUMNS FROM `user_carts` LIKE 'selected'");
                if ($checkCol && $checkCol->rowCount() === 0) {
                    $this->db->exec("ALTER TABLE `user_carts` ADD COLUMN `selected` tinyint(1) NOT NULL DEFAULT 1 AFTER `quantity`");
                }
            } catch (Throwable $e) {
                // Đã tồn tại hoặc không thể thay đổi
            }
        } catch (Throwable $e) {
            error_log('[Cart] Schema init: ' . $e->getMessage());
        }
    }

    /**
     * Lưu toàn bộ giỏ hàng từ session vào database cho user
     */
    public function saveCart(int $userId, array $sessionCart): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->db->beginTransaction();

            $delStmt = $this->db->prepare("DELETE FROM user_carts WHERE user_id = ?");
            $delStmt->execute([$userId]);

            if (!empty($sessionCart)) {
                $insStmt = $this->db->prepare("
                    INSERT INTO user_carts (user_id, product_id, color, quantity, selected, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), selected = VALUES(selected), updated_at = NOW()
                ");

                foreach ($sessionCart as $item) {
                    $pId = (int)($item['id'] ?? 0);
                    $qty = max(1, (int)($item['quantity'] ?? 1));
                    $color = trim((string)($item['color'] ?? ''));
                    $selected = isset($item['selected']) ? ((int)$item['selected'] ? 1 : 0) : 1;

                    if ($pId > 0) {
                        $insStmt->execute([$userId, $pId, $color, $qty, $selected]);
                    }
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Cart] saveCart error: ' . $e->getMessage());
        }
    }

    /**
     * Khôi phục giỏ hàng từ CSDL cho user, đồng bộ thông tin và giá mới nhất từ bảng products
     */
    public function restoreCart(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT c.product_id, c.color, c.quantity, IFNULL(c.selected, 1) as selected,
                       p.id, p.name, p.slug, p.price, p.image, p.quantity as stock, p.deleted_at,
                       cat.name as category_name
                FROM user_carts c
                INNER JOIN products p ON c.product_id = p.id
                LEFT JOIN categories cat ON p.category_id = cat.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();

            $restoredCart = [];
            $hasExpiredItems = false;

            foreach ($rows as $row) {
                // Kiểm tra sản phẩm đã bị xóa mềm (thùng rác)
                if (!empty($row['deleted_at'])) {
                    $hasExpiredItems = true;
                    continue;
                }

                $stock = (int)($row['stock'] ?? 0);
                if ($stock <= 0) {
                    $hasExpiredItems = true;
                    continue;
                }

                $productId = (int)$row['product_id'];
                $color = trim((string)($row['color'] ?? ''));
                $cartKey = !empty($color) ? ($productId . '_' . md5($color)) : (string)$productId;
                $quantity = min((int)$row['quantity'], $stock);
                $selected = isset($row['selected']) ? ((int)$row['selected'] === 1) : true;

                if ($quantity <= 0) {
                    continue;
                }

                $restoredCart[$cartKey] = [
                    'id' => $productId,
                    'name' => $row['name'],
                    'color' => $color,
                    'slug' => $row['slug'],
                    'price' => (float)$row['price'], // Luôn lấy giá mới nhất từ CSDL
                    'image' => $row['image'],
                    'quantity' => $quantity,
                    'selected' => $selected,
                    'category_name' => $row['category_name'] ?? 'Phụ kiện'
                ];
            }

            // Nếu có sản phẩm hết hàng hoặc ngừng bán, cập nhật lại DB
            if ($hasExpiredItems) {
                $this->saveCart($userId, $restoredCart);
            }

            return $restoredCart;
        } catch (Throwable $e) {
            error_log('[Cart] restoreCart error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Gộp giỏ hàng khi người dùng đăng nhập thành công
     */
    public function mergeOnLogin(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $dbCart = $this->restoreCart($userId);
        $sessionCart = $_SESSION['cart'] ?? [];

        if (empty($sessionCart)) {
            $_SESSION['cart'] = $dbCart;
            return;
        }

        // Hợp nhất giữa giỏ hàng đang có trong session và giỏ hàng đã lưu trong DB
        $mergedCart = $dbCart;
        $productModel = new Product();

        foreach ($sessionCart as $key => $item) {
            if (isset($mergedCart[$key])) {
                $prod = $productModel->getById((int)$item['id']);
                $maxStock = (int)($prod['quantity'] ?? 9999);
                $mergedCart[$key]['quantity'] = min($mergedCart[$key]['quantity'] + (int)$item['quantity'], $maxStock);
            } else {
                $mergedCart[$key] = $item;
            }
        }

        $_SESSION['cart'] = $mergedCart;
        $this->saveCart($userId, $mergedCart);
    }

    /**
     * Xóa sạch giỏ hàng trong database (sau khi đặt hàng thành công)
     */
    public function clearUserCart(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $stmt = $this->db->prepare("DELETE FROM user_carts WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (Throwable $e) {
            error_log('[Cart] clearUserCart error: ' . $e->getMessage());
        }
    }
}
