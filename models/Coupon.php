<?php
require_once __DIR__ . '/BaseModel.php';

class Coupon extends BaseModel {
    private static bool $schemaChecked = false;

    public function __construct() {
        parent::__construct();
        $this->ensureSchema();
    }

    /**
     * Tự động bổ sung các cột phân loại phạm vi áp dụng voucher (all, category, product)
     */
    private function ensureSchema(): void {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;
        try {
            $colScope = $this->db->query("SHOW COLUMNS FROM `coupons` LIKE 'apply_scope'");
            if ($colScope && $colScope->rowCount() === 0) {
                $this->db->exec("ALTER TABLE `coupons` ADD COLUMN `apply_scope` ENUM('all', 'category', 'product') NOT NULL DEFAULT 'all' AFTER `discount_type`");
                $this->db->exec("ALTER TABLE `coupons` ADD COLUMN `category_id` INT(11) DEFAULT NULL AFTER `apply_scope`");
                $this->db->exec("ALTER TABLE `coupons` ADD COLUMN `product_id` INT(11) DEFAULT NULL AFTER `category_id`");
            }
        } catch (Throwable $e) {
            error_log('[Coupon] Schema update: ' . $e->getMessage());
        }
    }

    /**
     * Kiểm tra xem khách hàng này đã từng dùng mã voucher này trong một đơn hàng thành công/đang xử lý hay chưa
     * Quy tắc: Voucher chỉ áp dụng mỗi tài khoản / khách hàng 1 lần thanh toán
     */
    public function hasUserUsedCoupon(string $code, ?int $userId = null, ?string $email = null, ?string $phone = null): bool {
        $userId = ($userId !== null && $userId > 0) ? $userId : null;
        $email = !empty($email) ? trim($email) : null;
        $phone = !empty($phone) ? trim($phone) : null;

        if ($userId === null && $email === null && $phone === null) {
            return false;
        }
        if (empty($code)) {
            return false;
        }

        try {
            $conditions = [];
            $params = [];
            $codeUpper = strtoupper(trim($code));

            if ($userId !== null) {
                $conditions[] = "user_id = ?";
                $params[] = $userId;
            }
            if ($email !== null) {
                $conditions[] = "LOWER(customer_email) = ?";
                $params[] = strtolower($email);
            }
            if ($phone !== null) {
                $conditions[] = "customer_phone = ?";
                $params[] = $phone;
            }

            $userClause = implode(' OR ', $conditions);
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM orders 
                WHERE ({$userClause}) 
                  AND UPPER(coupon_code) = ? 
                  AND order_status != 'cancelled' 
                  AND deleted_at IS NULL
            ");
            $params[] = $codeUpper;
            $stmt->execute($params);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('[Coupon] hasUserUsedCoupon: ' . $e->getMessage());
            return false;
        }
    }

    public function getAll(bool $onlyActive = false): array {
        $sql = "SELECT c.*, cat.name as category_name, p.name as product_name 
                FROM coupons c 
                LEFT JOIN categories cat ON c.category_id = cat.id 
                LEFT JOIN products p ON c.product_id = p.id 
                WHERE c.deleted_at IS NULL";
        if ($onlyActive) {
            $sql .= " AND c.status = 'active' AND (c.end_date IS NULL OR c.end_date >= NOW()) AND c.quantity > 0";
        }
        $sql .= " ORDER BY c.id DESC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM coupons WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM coupons WHERE code = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lấy danh sách các mã khuyến mại có thể sử dụng (hợp lệ cho khách chọn)
     * Tự động xóa (loại bỏ) các mã mà khách hàng đã từng dùng cho 1 đơn hàng trước đó
     */
    public function getAvailableCoupons(float $orderAmount = 0, ?int $userId = null, array $cartItems = [], ?string $email = null, ?string $phone = null): array {
        $stmt = $this->db->query("
            SELECT c.*, 
                   cat.name as category_name, 
                   p.name as product_name
            FROM coupons c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN products p ON c.product_id = p.id
            WHERE c.deleted_at IS NULL 
              AND c.status = 'active' 
              AND (c.start_date IS NULL OR c.start_date <= NOW())
              AND (c.end_date IS NULL OR c.end_date >= NOW()) 
              AND c.quantity > 0
            ORDER BY c.min_order_amount ASC, c.id DESC
        ");
        $list = $stmt->fetchAll();
        $availableList = [];

        foreach ($list as $c) {
            $alreadyUsed = false;
            if (($userId !== null && $userId > 0) || !empty($email) || !empty($phone)) {
                $alreadyUsed = $this->hasUserUsedCoupon($c['code'], $userId, $email, $phone);
            }

            // Tự động xóa (loại bỏ hoàn toàn) mã này khỏi danh sách phía khách hàng nếu đã từng dùng
            if ($alreadyUsed) {
                continue;
            }

            // Kiểm tra tính hợp lệ và ước tính mức giảm
            $check = $this->checkAndCalculate($c['code'], $orderAmount, $userId, $cartItems, $email, $phone);
            $c['already_used'] = false;
            $c['is_eligible'] = $check['valid'];
            $c['eligibility_message'] = $check['message'] ?? '';
            $c['estimated_discount'] = $check['discount'] ?? 0;

            // Tạo nhãn phạm vi áp dụng
            $scope = $c['apply_scope'] ?? 'all';
            if ($scope === 'category') {
                $c['scope_text'] = 'Chỉ áp dụng cho danh mục: ' . ($c['category_name'] ?? 'Danh mục riêng');
            } elseif ($scope === 'product') {
                $c['scope_text'] = 'Chỉ áp dụng cho sản phẩm: ' . ($c['product_name'] ?? 'Sản phẩm riêng');
            } else {
                $c['scope_text'] = 'Áp dụng cho tất cả sản phẩm';
            }

            $availableList[] = $c;
        }

        return $availableList;
    }

    /**
     * Kiểm tra tính hợp lệ và tính toán số tiền giảm
     * Quy tắc 1: Mỗi tài khoản chỉ áp dụng mỗi voucher 1 lần thanh toán
     * Quy tắc 2: Phạm vi áp dụng (Tất cả, 1 Danh mục, hoặc 1 Sản phẩm cụ thể)
     */
    public function checkAndCalculate(string $code, float $orderAmount, ?int $userId = null, array $cartItems = [], ?string $email = null, ?string $phone = null): array {
        $coupon = $this->getByCode($code);
        if (!$coupon) {
            return ['valid' => false, 'message' => 'Mã khuyến mại không tồn tại!'];
        }

        if ($coupon['status'] !== 'active') {
            return ['valid' => false, 'message' => 'Mã khuyến mại hiện đang tạm khóa!'];
        }

        if (!empty($coupon['start_date'])) {
            $startTs = strtotime($coupon['start_date']);
            // Cho phép độ trễ an toàn 5 phút nếu vừa tạo mã hoặc lệch giây hệ thống
            if ($startTs > (time() + 300)) {
                return ['valid' => false, 'message' => 'Mã khuyến mại chưa đến ngày bắt đầu áp dụng!'];
            }
        }

        if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < time()) {
            return ['valid' => false, 'message' => 'Mã khuyến mại này đã hết hạn sử dụng!'];
        }

        if ($coupon['quantity'] <= 0) {
            return ['valid' => false, 'message' => 'Mã khuyến mại này đã hết lượt sử dụng!'];
        }

        // Quy tắc 1: Mỗi tài khoản chỉ được áp dụng mỗi voucher 1 lần thanh toán
        if (($userId !== null && $userId > 0) || !empty($email) || !empty($phone)) {
            if ($this->hasUserUsedCoupon($code, $userId, $email, $phone)) {
                return [
                    'valid' => false,
                    'message' => 'Bạn đã sử dụng mã voucher này rồi! Mỗi tài khoản chỉ được áp dụng 1 lần thanh toán.'
                ];
            }
        }

        // Quy tắc 2: Kiểm tra phạm vi áp dụng (all, category, product)
        $applyScope = $coupon['apply_scope'] ?? 'all';
        $applicableAmount = $orderAmount;

        if ($applyScope === 'category' && !empty($coupon['category_id'])) {
            $catId = (int)$coupon['category_id'];
            $applicableAmount = 0;
            if (!empty($cartItems)) {
                require_once __DIR__ . '/Product.php';
                $productModel = new Product();
                foreach ($cartItems as $item) {
                    $pId = (int)($item['id'] ?? $item['product_id'] ?? 0);
                    $prod = $productModel->getById($pId);
                    if ($prod && (int)$prod['category_id'] === $catId) {
                        $applicableAmount += (float)$item['price'] * (int)$item['quantity'];
                    }
                }
            }

            if ($applicableAmount <= 0) {
                require_once __DIR__ . '/Category.php';
                $cat = (new Category())->getById($catId);
                $catName = $cat['name'] ?? 'danh mục quy định';
                return [
                    'valid' => false,
                    'message' => 'Mã voucher này chỉ áp dụng cho các sản phẩm thuộc danh mục "' . $catName . '" (Giỏ hàng chưa có sản phẩm nào thuộc danh mục này)!'
                ];
            }
        } elseif ($applyScope === 'product' && !empty($coupon['product_id'])) {
            $targetProdId = (int)$coupon['product_id'];
            $applicableAmount = 0;
            if (!empty($cartItems)) {
                foreach ($cartItems as $item) {
                    $pId = (int)($item['id'] ?? $item['product_id'] ?? 0);
                    if ($pId === $targetProdId) {
                        $applicableAmount += (float)$item['price'] * (int)$item['quantity'];
                    }
                }
            }

            if ($applicableAmount <= 0) {
                require_once __DIR__ . '/Product.php';
                $prod = (new Product())->getById($targetProdId);
                $prodName = $prod['name'] ?? 'sản phẩm quy định';
                return [
                    'valid' => false,
                    'message' => 'Mã voucher này chỉ áp dụng cho sản phẩm "' . $prodName . '" (Giỏ hàng chưa có sản phẩm này)!'
                ];
            }
        }

        if ($applicableAmount < (float)$coupon['min_order_amount']) {
            $needMore = (float)$coupon['min_order_amount'] - $applicableAmount;
            return [
                'valid' => false, 
                'message' => 'Sản phẩm áp dụng chưa đạt mức tối thiểu ' . number_format($coupon['min_order_amount'], 0, ',', '.') . 'đ (Cần mua thêm ' . number_format($needMore, 0, ',', '.') . 'đ)!'
            ];
        }

        $discount = 0;
        $codeUpper = strtoupper($coupon['code']);
        $titleLower = mb_strtolower($coupon['title'] ?? '');
        $isShippingCoupon = ($codeUpper === 'FREESHIP' || str_starts_with($codeUpper, 'FREESHIP') || str_starts_with($codeUpper, 'SHIP_') || str_ends_with($codeUpper, '_SHIP'))
                         || str_contains($titleLower, 'vận chuyển') 
                         || str_contains($titleLower, 'freeship')
                         || str_contains($titleLower, 'phí ship');

        if ($isShippingCoupon) {
            $currentShipping = (float)($_SESSION['shipping_fee'] ?? 0);
            if ($currentShipping <= 0) {
                return [
                    'valid' => false,
                    'message' => 'Đơn hàng hiện tại đã được Miễn phí vận chuyển (0đ) mặc định! Mã "' . $coupon['code'] . '" chỉ có hiệu lực khi có phát sinh cước phí vận chuyển.'
                ];
            }
            $discount = min($currentShipping, (float)$coupon['discount_value']);
        } elseif ($coupon['discount_type'] === 'percent') {
            // Tiền là số nguyên VND: nhân trước chia sau bằng intdiv để triệt tiêu sai số float (chuẩn Buổi 2)
            $discount = intdiv((int)$applicableAmount * (int)$coupon['discount_value'], 100);
            if (!empty($coupon['max_discount']) && $discount > (float)$coupon['max_discount']) {
                $discount = (float)$coupon['max_discount'];
            }
        } else {
            $discount = (float)$coupon['discount_value'];
        }

        // Không giảm quá tổng giá trị mặt hàng áp dụng
        if ($discount > $applicableAmount) {
            $discount = $applicableAmount;
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => 'Áp dụng mã "' . $coupon['code'] . '" thành công! Giảm ' . number_format($discount, 0, ',', '.') . 'đ.'
        ];
    }

    public function increaseUsed(string $code): bool {
        $stmt = $this->db->prepare("
            UPDATE coupons 
            SET used_count = used_count + 1, quantity = quantity - 1 
            WHERE code = ? AND quantity > 0
        ");
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->rowCount() > 0;
    }

    public function decreaseUsed(string $code): bool {
        $stmt = $this->db->prepare("
            UPDATE coupons 
            SET used_count = GREATEST(0, used_count - 1), quantity = quantity + 1 
            WHERE code = ?
        ");
        return $stmt->execute([strtoupper(trim($code))]);
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO coupons (code, title, discount_type, apply_scope, category_id, product_id, discount_value, max_discount, min_order_amount, quantity, start_date, end_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['title'],
            $data['discount_type'] ?? 'fixed',
            $data['apply_scope'] ?? 'all',
            !empty($data['category_id']) ? (int)$data['category_id'] : null,
            !empty($data['product_id']) ? (int)$data['product_id'] : null,
            $data['discount_value'] ?? 0,
            !empty($data['max_discount']) ? $data['max_discount'] : null,
            $data['min_order_amount'] ?? 0,
            $data['quantity'] ?? 100,
            !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d H:i:s'),
            !empty($data['end_date']) ? $data['end_date'] : null,
            $data['status'] ?? 'active'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE coupons 
            SET code = ?, title = ?, discount_type = ?, apply_scope = ?, category_id = ?, product_id = ?, discount_value = ?, max_discount = ?, min_order_amount = ?, quantity = ?, start_date = ?, end_date = ?, status = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['title'],
            $data['discount_type'] ?? 'fixed',
            $data['apply_scope'] ?? 'all',
            !empty($data['category_id']) ? (int)$data['category_id'] : null,
            !empty($data['product_id']) ? (int)$data['product_id'] : null,
            $data['discount_value'] ?? 0,
            !empty($data['max_discount']) ? $data['max_discount'] : null,
            $data['min_order_amount'] ?? 0,
            $data['quantity'] ?? 100,
            !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d H:i:s'),
            !empty($data['end_date']) ? $data['end_date'] : null,
            $data['status'] ?? 'active',
            $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("UPDATE coupons SET deleted_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function restore(int $id): bool {
        $stmt = $this->db->prepare("UPDATE coupons SET deleted_at = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getTrash(): array {
        $stmt = $this->db->query("SELECT * FROM coupons WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        return $stmt->fetchAll();
    }

    public function countTrash(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM coupons WHERE deleted_at IS NOT NULL")->fetchColumn();
    }

    public function forceDelete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM coupons WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
