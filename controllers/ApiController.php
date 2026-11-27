<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/CartController.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Coupon.php';
require_once __DIR__ . '/../services/Csrf.php';

/**
 * ApiController - Cung cấp toàn bộ RESTful JSON Endpoints chuẩn Shopee cho Client & Admin
 * Không reload trang, tốc độ cực nhanh, bảo mật CSRF & Phân quyền chặt chẽ
 */
class ApiController extends BaseController
{
    /**
     * Dispatcher chính cho API
     */
    public function handle(string $resource, string $action): void
    {
        // Thiết lập header JSON & CORS an toàn
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        // [SỬA LỖI BẢO MẬT] Mọi API thay đổi dữ liệu (POST) đều bắt buộc CSRF token
        // (gửi qua header X-CSRF-Token - được tự động gắn trong views/layouts/header.php - hoặc trường _csrf_token)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validateRequest()) {
            $this->json([
                'success' => false,
                'message' => 'Lỗi bảo mật: Token CSRF không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang!'
            ], 403);
        }

        try {
            switch ($resource) {
                case 'cart':
                    $this->handleCart($action);
                    break;
                case 'orders':
                case 'order':
                    $this->handleOrders($action);
                    break;
                case 'admin':
                    $this->handleAdmin($action);
                    break;
                case 'auth':
                    $this->handleAuth($action);
                    break;
                case 'check-phone':
                    $this->checkPhone();
                    break;
                case 'products':
                case 'product':
                    $this->handleProducts($action);
                    break;
                default:
                    $this->json(['success' => false, 'message' => 'API endpoint không tồn tại'], 404);
            }
        } catch (Throwable $e) {
            error_log('[API Error] ' . $e->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Lỗi máy chủ: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // 1. NHÓM API GIỎ HÀNG (SHOPPING CART API)
    // =========================================================================

    private function handleCart(string $action): void
    {
        switch ($action) {
            case 'list':
            case 'get':
                $this->cartList();
                break;
            case 'add':
                $this->cartAdd();
                break;
            case 'update-qty':
            case 'update':
                $this->cartUpdateQty();
                break;
            case 'toggle-select':
            case 'select':
                $this->cartToggleSelect();
                break;
            case 'remove':
            case 'delete':
                $this->cartRemove();
                break;
            case 'apply-coupon':
                $this->cartApplyCoupon();
                break;
            case 'guest-hold':
            case 'hold':
                $this->cartGuestHold();
                break;
            default:
                $this->json(['success' => false, 'message' => 'Hành động giỏ hàng không hợp lệ'], 400);
        }
    }

    private function cartList(): void
    {
        CartController::syncCartWithDatabase();
        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $cart = $_SESSION['cart'] ?? [];
        $items = [];
        $totalSubtotal = 0;
        $selectedSubtotal = 0;
        $selectedCount = 0;
        $allSelected = !empty($cart);

        foreach ($cart as $key => $item) {
            $isSelected = !isset($item['selected']) || !empty($item['selected']);
            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
            $totalSubtotal += $itemTotal;

            if ($isSelected) {
                $selectedSubtotal += $itemTotal;
                $selectedCount += (int)$item['quantity'];
            } else {
                $allSelected = false;
            }

            $items[] = [
                'key' => (string)$key,
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'color' => $item['color'] ?? '',
                'slug' => $item['slug'],
                'price' => (float)$item['price'],
                'price_formatted' => number_format((float)$item['price'], 0, ',', '.') . 'đ',
                'image' => $item['image'],
                'quantity' => (int)$item['quantity'],
                'selected' => $isSelected,
                'total' => $itemTotal,
                'total_formatted' => number_format($itemTotal, 0, ',', '.') . 'đ',
                'category_name' => $item['category_name'] ?? 'Phụ kiện'
            ];
        }

        $discount = (float)($_SESSION['discount_amount'] ?? 0);
        $shipping = 0;
        $finalTotal = max(0, $selectedSubtotal - $discount + $shipping);

        $this->json([
            'success' => true,
            'items' => $items,
            'item_count' => count($cart),
            'selected_count' => $selectedCount,
            'all_selected' => $allSelected,
            'subtotal' => $totalSubtotal,
            'subtotal_formatted' => number_format($totalSubtotal, 0, ',', '.') . 'đ',
            'selected_subtotal' => $selectedSubtotal,
            'selected_subtotal_formatted' => number_format($selectedSubtotal, 0, ',', '.') . 'đ',
            'discount' => $discount,
            'discount_formatted' => number_format($discount, 0, ',', '.') . 'đ',
            'shipping' => $shipping,
            'shipping_formatted' => '0đ (Miễn phí)',
            'final_total' => $finalTotal,
            'final_total_formatted' => number_format($finalTotal, 0, ',', '.') . 'đ',
            'coupon_code' => $_SESSION['coupon_code'] ?? null
        ]);
    }

    private function cartAdd(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        if (empty($_SESSION['user']) && empty($_SESSION['admin_user'])) {
            $this->json([
                'success' => false,
                'require_login' => true,
                'login_url' => BASE_URL . 'index.php?url=auth/login&msg=login_required_cart',
                'message' => 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng!'
            ], 401);
            return;
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $color = trim((string)($_POST['color'] ?? ''));

        if ($productId <= 0) {
            $this->json(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh!'], 400);
            return;
        }
        if ($quantity <= 0) {
            $this->json(['success' => false, 'message' => 'Số lượng sản phẩm không hợp lệ!'], 400);
            return;
        }

        $productModel = new Product();
        $product = $productModel->getById($productId);
        if (!$product || !empty($product['deleted_at'])) {
            $this->json(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh'], 404);
            return;
        }

        $stock = (int)($product['quantity'] ?? 0);
        if ($stock <= 0) {
            $this->json(['success' => false, 'message' => 'Sản phẩm này hiện đã hết hàng trong kho!'], 400);
            return;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $cartKey = $color !== '' ? ($productId . '_' . md5($color)) : (string)$productId;
        $curQty = isset($_SESSION['cart'][$cartKey]) ? (int)$_SESSION['cart'][$cartKey]['quantity'] : 0;

        // RÀNG BUỘC CHỐNG SPAM: Mỗi sản phẩm tối đa 10 chiếc trong giỏ hàng (tối đa 2 chiếc với sản phẩm giá trị cao >= 15 triệu)
        $maxAllowed = ((float)$product['price'] >= 15000000) ? 2 : 10;
        if ($curQty >= $maxAllowed) {
            $msg = ($maxAllowed === 2)
                ? 'Sản phẩm giá trị cao (từ 15 triệu trở lên) được giới hạn tối đa 2 chiếc trong cùng 1 lần đặt để chống đầu cơ/spam đơn hàng!'
                : "Bạn đã có {$curQty} chiếc trong giỏ. Giới hạn tối đa 10 chiếc/sản phẩm!";
            $this->json(['success' => false, 'message' => $msg], 400);
            return;
        }

        $effectiveMax = min($maxAllowed, $stock);
        if ($curQty >= $effectiveMax) {
            $this->json([
                'success' => false,
                'message' => "Bạn đã có {$curQty} chiếc trong giỏ. Kho chỉ còn {$stock} chiếc!"
            ], 400);
            return;
        }

        if ($curQty + $quantity > $stock) {
            $this->json(['success' => false, 'message' => "Rất tiếc, sản phẩm này chỉ còn {$stock} sản phẩm trong kho!"], 400);
            return;
        }
        $newQty = min($effectiveMax, $curQty + $quantity);

        $_SESSION['cart'][$cartKey] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'color' => $color,
            'slug' => $product['slug'],
            'price' => (float)$product['price'],
            'image' => $product['image'],
            'quantity' => $newQty,
            'selected' => true, // Mặc định tự động chọn sản phẩm mới thêm
            'category_name' => $product['category_name'] ?? 'Phụ kiện'
        ];

        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $totalCount = array_sum(array_column($_SESSION['cart'], 'quantity'));

        $this->json([
            'success' => true,
            'message' => 'Đã thêm ' . $product['name'] . ' vào giỏ hàng!',
            'cartCount' => $totalCount
        ]);
    }

    private function cartUpdateQty(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $key = trim((string)($_POST['key'] ?? ''));
        $action = trim((string)($_POST['action_type'] ?? ''));
        $newQuantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;

        if ($key === '' || !isset($_SESSION['cart'][$key])) {
            $this->json(['success' => false, 'message' => 'Sản phẩm không có trong giỏ hàng'], 404);
            return;
        }

        $productModel = new Product();
        $pId = (int)$_SESSION['cart'][$key]['id'];
        $product = $productModel->getById($pId);
        $maxStock = (int)($product['quantity'] ?? 9999);
        // [SỬA LỖI] Áp dụng giới hạn 2 chiếc cho sản phẩm >= 15 triệu cả khi tăng số lượng trong giỏ
        $maxAllowed = ((float)($product['price'] ?? 0) >= 15000000) ? 2 : 10;
        $effectiveMax = min($maxAllowed, $maxStock);

        if ($action === 'increase') {
            if ($_SESSION['cart'][$key]['quantity'] < $effectiveMax) {
                $_SESSION['cart'][$key]['quantity']++;
            } else {
                $limitMsg = ($effectiveMax === $maxAllowed)
                    ? ($maxAllowed === 2 ? 'Sản phẩm giá trị cao (từ 15 triệu trở lên) được giới hạn tối đa 2 chiếc trong cùng 1 lần đặt để chống đầu cơ/spam đơn hàng!' : 'Mỗi sản phẩm chỉ được mua tối đa 10 chiếc để tránh spam!')
                    : "Số lượng đã đạt giới hạn tồn kho ({$maxStock} chiếc)!";
                $this->json([
                    'success' => false,
                    'message' => $limitMsg
                ], 400);
                return;
            }
        } elseif ($action === 'decrease') {
            $_SESSION['cart'][$key]['quantity']--;
            if ($_SESSION['cart'][$key]['quantity'] <= 0) {
                unset($_SESSION['cart'][$key]);
            }
        } elseif ($newQuantity !== null) {
            if ($newQuantity <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['quantity'] = min($newQuantity, $effectiveMax);
            }
        }

        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $this->cartList(); // Trả về toàn bộ dữ liệu giỏ hàng mới nhất để UI cập nhật
    }

    private function cartToggleSelect(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        // 1. Chọn / Bỏ chọn tất cả
        if (isset($_POST['select_all'])) {
            $selectAll = (bool)(int)$_POST['select_all'];
            if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $k => $item) {
                    $_SESSION['cart'][$k]['selected'] = $selectAll;
                }
            }
        }
        // 2. Chọn / Bỏ chọn từng món theo key
        elseif (isset($_POST['key'])) {
            $key = trim((string)$_POST['key']);
            if (isset($_SESSION['cart'][$key])) {
                $selected = isset($_POST['selected']) ? (bool)(int)$_POST['selected'] : !(!isset($_SESSION['cart'][$key]['selected']) || $_SESSION['cart'][$key]['selected']);
                $_SESSION['cart'][$key]['selected'] = $selected;
            }
        }

        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $this->cartList();
    }

    private function cartRemove(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        // 1. Xóa tất cả các món đã tick chọn (Xóa đã chọn kiểu Shopee)
        if (!empty($_POST['clear_selected'])) {
            foreach ($_SESSION['cart'] ?? [] as $k => $item) {
                if (!empty($item['selected'])) {
                    unset($_SESSION['cart'][$k]);
                }
            }
        }
        // 2. Xóa toàn bộ giỏ
        elseif (!empty($_POST['clear_all'])) {
            unset($_SESSION['cart'], $_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code']);
        }
        // 3. Xóa theo key cụ thể
        elseif (!empty($_POST['key'])) {
            $key = trim((string)$_POST['key']);
            unset($_SESSION['cart'][$key]);
        }

        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $this->cartList();
    }

    private function cartApplyCoupon(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $code = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
        if ($code === '') {
            unset($_SESSION['applied_coupon'], $_SESSION['coupon_code'], $_SESSION['discount_amount']);
            $this->json(['success' => true, 'message' => 'Đã gỡ mã giảm giá', 'discount' => 0]);
            return;
        }

        $selectedSubtotal = 0;
        $selectedItems = [];
        foreach ($_SESSION['cart'] ?? [] as $item) {
            if (!isset($item['selected']) || !empty($item['selected'])) {
                $selectedSubtotal += (float)$item['price'] * (int)$item['quantity'];
                $selectedItems[] = $item;
            }
        }

        if ($selectedSubtotal <= 0) {
            $this->json(['success' => false, 'message' => 'Vui lòng chọn ít nhất 1 sản phẩm để áp dụng mã!'], 400);
            return;
        }

        $currUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $userId = !empty($currUser['id']) ? (int)$currUser['id'] : null;
        $userEmail = !empty($currUser['email']) ? (string)$currUser['email'] : null;
        $userPhone = !empty($currUser['phone']) ? (string)$currUser['phone'] : null;
        $couponModel = new Coupon();
        $check = $couponModel->checkAndCalculate($code, $selectedSubtotal, $userId, $selectedItems, $userEmail, $userPhone);

        if ($check['valid']) {
            $_SESSION['applied_coupon'] = $check['coupon'];
            $_SESSION['coupon_code'] = $code;
            $_SESSION['discount_amount'] = (float)$check['discount'];

            $this->json([
                'success' => true,
                'message' => 'Áp dụng mã ' . $code . ' thành công!',
                'discount' => (float)$check['discount'],
                'discount_formatted' => number_format((float)$check['discount'], 0, ',', '.') . 'đ'
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $check['message']
            ], 400);
        }
    }

    // =========================================================================
    // 2. NHÓM API ĐƠN HÀNG (ORDERS API)
    // =========================================================================

    private function handleOrders(string $action): void
    {
        switch ($action) {
            case 'list':
                $this->ordersList();
                break;
            case 'cancel':
                $this->orderCancel();
                break;
            case 'reorder':
                $this->orderReorder();
                break;
            case 'track':
                $this->orderTrack();
                break;
            case 'status':
            case 'status-poll':
                $this->orderStatusPoll();
                break;
            case 'confirm-received':
                $this->orderConfirmReceived();
                break;
            case 'sse':
                $this->orderSse();
                break;
            default:
                $this->json(['success' => false, 'message' => 'Hành động đơn hàng không hợp lệ'], 400);
        }
    }

    private function ordersList(): void
    {
        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        if (empty($currentUser['id'])) {
            $this->json(['success' => false, 'message' => 'Vui lòng đăng nhập để xem đơn hàng'], 401);
            return;
        }

        $status = trim((string)($_GET['status'] ?? 'all'));
        $orderModel = new Order();
        $allOrders = $orderModel->getByUserId((int)$currentUser['id']);

        $filtered = [];
        foreach ($allOrders as $ord) {
            $st = $ord['order_status'];
            if ($status === 'all') {
                $filtered[] = $ord;
            } elseif ($status === 'pending' && $st === 'pending') {
                $filtered[] = $ord;
            } elseif ($status === 'confirmed' && $st === 'confirmed') {
                $filtered[] = $ord;
            } elseif ($status === 'shipping' && $st === 'shipping') {
                $filtered[] = $ord;
            } elseif ($status === 'completed' && $st === 'completed') {
                $filtered[] = $ord;
            } elseif ($status === 'cancelled' && $st === 'cancelled') {
                $filtered[] = $ord;
            } elseif ($status === 'returned' && in_array($st, ['returned', 'return_requested'], true)) {
                $filtered[] = $ord;
            }
        }

        $this->json([
            'success' => true,
            'orders' => $filtered,
            'count' => count($filtered),
            'status' => $status
        ]);
    }

    private function orderCancel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $orderCode = trim((string)($_POST['order_code'] ?? ''));
        $reason = trim((string)($_POST['cancel_reason'] ?? 'Khách hủy đơn'));

        if ($orderCode === '') {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng #' . $orderCode], 404);
            return;
        }

        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $isOwner = !empty($currentUser['id']) && (int)$order['user_id'] === (int)$currentUser['id'];
        $isAdmin = !empty($_SESSION['admin_user']);

        if (!$isOwner && !$isAdmin) {
            $this->json(['success' => false, 'message' => 'Bạn không có quyền thao tác trên đơn hàng này!'], 403);
            return;
        }

        if (!in_array($order['order_status'], ['pending', 'confirmed'], true)) {
            $this->json(['success' => false, 'message' => 'Đơn hàng đang được giao hoặc đã hoàn tất, không thể tự hủy!'], 400);
            return;
        }

        // Hoàn lại tồn kho
        $orderModel->restoreStock((int)$order['id']);

        // Cập nhật trạng thái sang cancelled
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE orders SET order_status = 'cancelled', cancel_reason = ? WHERE id = ?");
        $stmt->execute([$reason, (int)$order['id']]);

        $this->json([
            'success' => true,
            'message' => "Đã hủy đơn hàng #{$orderCode} thành công và hoàn trả sản phẩm vào kho!"
        ]);
    }

    /**
     * Mua lại đơn cũ kiểu Shopee (Re-order)
     * Lấy các sản phẩm trong order_items đưa vào giỏ hàng và dẫn khách vào giỏ
     */
    private function orderReorder(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $orderCode = trim((string)($_POST['order_code'] ?? ''));
        if ($orderCode === '') {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);
        if (!$order || empty($order['items'])) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy thông tin sản phẩm trong đơn'], 404);
            return;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $productModel = new Product();
        $addedCount = 0;

        foreach ($order['items'] as $item) {
            $pId = (int)$item['product_id'];
            $prod = $productModel->getById($pId);
            if (!$prod || !empty($prod['deleted_at']) || ($prod['quantity'] ?? 0) <= 0) {
                continue;
            }

            // Tách màu sắc từ tên nếu có
            $color = '';
            if (preg_match('/\((.*?)\)$/', $item['product_name'], $m)) {
                $color = trim($m[1]);
            }

            $cartKey = $color !== '' ? ($pId . '_' . md5($color)) : (string)$pId;
            $stock = (int)$prod['quantity'];
            $reqQty = min((int)$item['quantity'], $stock);

            $_SESSION['cart'][$cartKey] = [
                'id' => $prod['id'],
                'name' => $prod['name'],
                'color' => $color,
                'slug' => $prod['slug'],
                'price' => (float)$prod['price'],
                'image' => $prod['image'],
                'quantity' => $reqQty,
                'selected' => true,
                'category_name' => $prod['category_name'] ?? 'Phụ kiện'
            ];
            $addedCount++;
        }

        CartController::refreshCouponSession();
        CartController::syncUserCartToDb();

        $this->json([
            'success' => true,
            'added_count' => $addedCount,
            'cart_count' => count($_SESSION['cart']),
            'message' => "Đã thêm {$addedCount} sản phẩm từ đơn #{$orderCode} vào giỏ hàng!",
            'redirect_url' => BASE_URL . 'index.php?url=cart'
        ]);
    }

    private function orderTrack(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($code);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
            return;
        }

        // Tạo timeline 4 bước Shopee
        $timeline = [
            [
                'step' => 1,
                'title' => 'Đơn Hàng Đã Đặt',
                'description' => 'Đơn hàng đã được ghi nhận trên hệ thống',
                'time' => $order['created_at'],
                'completed' => true
            ],
            [
                'step' => 2,
                'title' => 'Đã Xác Nhận Đơn Hàng',
                'description' => in_array($order['order_status'], ['confirmed', 'shipping', 'completed'], true) ? 'Người bán đang chuẩn bị kiện hàng' : 'Đang chờ cửa hàng xác nhận',
                'completed' => in_array($order['order_status'], ['confirmed', 'shipping', 'completed'], true)
            ],
            [
                'step' => 3,
                'title' => 'Đang Vận Chuyển',
                'description' => in_array($order['order_status'], ['shipping', 'completed'], true) ? 'Kiện hàng đã xuất kho và giao cho bưu tá' : 'Chờ xuất kho',
                'completed' => in_array($order['order_status'], ['shipping', 'completed'], true)
            ],
            [
                'step' => 4,
                'title' => 'Giao Hàng Thành Công',
                'description' => $order['order_status'] === 'completed' ? 'Đơn hàng đã được giao an toàn tới người nhận' : 'Chưa hoàn thành',
                'completed' => $order['order_status'] === 'completed'
            ]
        ];

        $this->json([
            'success' => true,
            'order' => $order,
            'timeline' => $timeline
        ]);
    }

    // =========================================================================
    // 3. NHÓM API QUẢN TRỊ ADMIN (ADMIN SELLER API)
    // =========================================================================

    private function handleAdmin(string $action): void
    {
        if (empty($_SESSION['admin_user'])) {
            $this->json(['success' => false, 'message' => 'Yêu cầu quyền Quản trị viên (Admin)'], 403);
            return;
        }

        switch ($action) {
            case 'order-status':
            case 'update-order-status':
                $this->adminOrderStatus();
                break;
            case 'orders-poll':
            case 'poll':
                $this->adminOrdersPoll();
                break;
            case 'invoice-data':
                $this->adminInvoiceData();
                break;
            default:
                $this->json(['success' => false, 'message' => 'Hành động admin không hợp lệ'], 400);
        }
    }

    private function adminOrderStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $newOrderStatus = trim((string)($_POST['order_status'] ?? ''));
        $newPaymentStatus = trim((string)($_POST['payment_status'] ?? ''));

        if ($orderId <= 0) {
            $this->json(['success' => false, 'message' => 'ID đơn hàng không hợp lệ'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getById($orderId);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng #' . $orderId], 404);
            return;
        }

        // RÀNG BUỘC SHIP COD: Admin không được tự ý đổi sang 'completed' với đơn Ship COD
        if ($newOrderStatus === 'completed' && ($order['payment_method'] ?? '') === 'cod' && ($order['order_status'] ?? '') !== 'completed') {
            $this->json([
                'success' => false,
                'message' => 'Với đơn hàng Ship COD, Admin không được tự ý đổi sang Hoàn thành. Đơn hàng chỉ hoàn thành khi Shipper giao tiền hoặc Khách hàng bấm "Đã nhận hàng"!'
            ], 400);
            return;
        }

        // [SỬA LỖI] Dùng chung máy trạng thái 1 chiều với trang quản trị (trước đây API này cho phép lùi trạng thái tuỳ ý)
        $allowedTransitions = [
            'pending' => ['pending', 'confirmed', 'cancelled'],
            'confirmed' => ['confirmed', 'shipping', 'cancelled'],
            'shipping' => ['shipping', 'completed'],
            'completed' => ['completed', 'return_requested', 'returned'],
            'return_requested' => ['return_requested', 'returned', 'completed'],
            'returned' => ['returned'],
            'cancelled' => ['cancelled'],
        ];
        $curStatus = (string)$order['order_status'];
        if ($newOrderStatus === '') {
            $newOrderStatus = $curStatus;
        }
        if (!in_array($newOrderStatus, $allowedTransitions[$curStatus] ?? [], true)) {
            $this->json(['success' => false, 'message' => "Máy trạng thái 1 chiều: Không thể chuyển đơn từ [{$curStatus}] sang [{$newOrderStatus}]!"], 400);
            return;
        }

        $curPay = (string)($order['payment_status'] ?? 'pending');
        if ($newPaymentStatus === 'refunded' && $curPay !== 'paid' && $curPay !== 'refunded') {
            $this->json(['success' => false, 'message' => 'Chỉ có thể cập nhật "Đã hoàn tiền" cho đơn hàng khách đã thanh toán!'], 400);
            return;
        }
        $finalPay = ($newPaymentStatus === 'refunded') ? 'refunded' : null;
        // Order::updateOrderStatus tự xử lý hoàn kho / trừ kho lại / hoàn lượt mã giảm giá / completed_at
        $orderModel->updateOrderStatus($orderId, $newOrderStatus, $finalPay);

        $freshOrder = $orderModel->getById($orderId);

        $this->json([
            'success' => true,
            'message' => 'Cập nhật đơn hàng #' . $order['order_code'] . ' thành công!',
            'order' => $freshOrder
        ]);
    }

    private function adminInvoiceData(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        $id = (int)($_GET['id'] ?? 0);

        $orderModel = new Order();
        $order = $code !== '' ? $orderModel->getByCode($code) : $orderModel->getById($id);

        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'invoice' => $order
        ]);
    }

    private function adminOrdersPoll(): void
    {
        $lastId = (int)($_GET['last_id'] ?? 0);
        $orderModel = new Order();
        $db = $orderModel->getDb();

        $maxId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM orders WHERE deleted_at IS NULL")->fetchColumn();

        $newOrders = [];
        if ($lastId > 0 && $maxId > $lastId) {
            $stmt = $db->prepare("SELECT id, order_code, customer_name, customer_phone, final_amount, order_status, payment_method, payment_status, created_at FROM orders WHERE deleted_at IS NULL AND id > ? ORDER BY id DESC");
            $stmt->execute([$lastId]);
            $newOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $counts = [
            'all' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL")->fetchColumn(),
            'pending' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'pending'")->fetchColumn(),
            'confirmed' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'confirmed'")->fetchColumn(),
            'shipping' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'shipping'")->fetchColumn(),
            'completed' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'completed'")->fetchColumn(),
            'return_requested' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'return_requested'")->fetchColumn(),
            'returned' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'returned'")->fetchColumn(),
            'cancelled' => (int)$db->query("SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND order_status = 'cancelled'")->fetchColumn(),
        ];

        $this->json([
            'success' => true,
            'latest_id' => $maxId,
            'new_count' => count($newOrders),
            'new_orders' => $newOrders,
            'counts' => $counts
        ]);
    }

    private function orderStatusPoll(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($code);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'order_code' => $order['order_code'],
            'order_status' => $order['order_status'],
            'payment_status' => $order['payment_status'],
            'cancel_reason' => $order['cancel_reason'] ?? '',
            'refund_info' => $order['refund_info'] ?? '',
            'updated_at' => $order['updated_at'] ?? $order['created_at']
        ]);
    }

    // =========================================================================
    // 4. MUA HÀNG KHÔNG CẦN ĐĂNG NHẬP (GUEST HOLD) & CHỐNG GHIM HÀNG QUA IP
    // =========================================================================

    private function cartGuestHold(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $color = trim((string)($_POST['color'] ?? ''));
        $quantity = max(1, min(10, (int)($_POST['quantity'] ?? 1)));
        $name = trim((string)($_POST['customer_name'] ?? ''));
        $phone = trim((string)($_POST['customer_phone'] ?? ''));
        $address = trim((string)($_POST['customer_address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($productId <= 0 || empty($name) || empty($phone) || empty($address)) {
            $this->json(['success' => false, 'message' => 'Vui lòng điền đầy đủ Họ tên, Số điện thoại và Địa chỉ nhận hàng!'], 400);
            return;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (!preg_match('/^(0[35789])[0-9]{8}$/', $cleanPhone)) {
            $this->json(['success' => false, 'message' => 'Số điện thoại không đúng định dạng (cần 10 chữ số, bắt đầu bằng 03, 05, 07, 08, 09)!'], 400);
            return;
        }

        $productModel = new Product();
        $product = $productModel->getById($productId);
        if (!$product || !empty($product['deleted_at'])) {
            $this->json(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh'], 404);
            return;
        }

        // Chặn đầu cơ sản phẩm cao cấp (>= 15 triệu tối đa 2 chiếc)
        if ((float)$product['price'] >= 15000000 && $quantity > 2) {
            $this->json(['success' => false, 'message' => 'Sản phẩm giá trị cao (từ 15 triệu trở lên) chỉ được đặt giữ chỗ tối đa 2 chiếc!'], 400);
            return;
        }

        $orderModel = new Order();
        $db = $orderModel->getDb();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // RÀNG BUỘC CHỐNG GHIM HÀNG: Quét IP, mỗi IP chỉ được đặt giữ chỗ tối đa 3 lần trong 24h
        try {
            $holdCount = (int)$db->query("SELECT COUNT(*) FROM guest_reservations WHERE ip_address = " . $db->quote($ip) . " AND created_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
            if ($holdCount >= 3) {
                $this->json([
                    'success' => false,
                    'message' => 'IP của bạn đã thực hiện 3 lần đặt giữ chỗ trong 24 giờ qua. Để phòng ngừa hành vi ghim hàng ảo, quý khách vui lòng Đăng nhập tài khoản User hoặc liên hệ Hotline CSKH 1800.6868!'
                ], 429);
                return;
            }

            $stmt = $db->prepare("INSERT INTO guest_reservations (product_id, product_name, color, quantity, price, customer_name, customer_phone, customer_address, notes, ip_address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $productId,
                $product['name'],
                $color,
                $quantity,
                (float)$product['price'],
                $name,
                $phone,
                $address,
                $notes,
                $ip
            ]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Lỗi lưu thông tin giữ chỗ: ' . $e->getMessage()], 500);
            return;
        }

        $this->json([
            'success' => true,
            'message' => '⚡ Đặt giữ chỗ thành công ' . htmlspecialchars($product['name']) . '! Bộ phận CSKH NEXVORA sẽ liên hệ SĐT ' . htmlspecialchars($phone) . ' trong vòng 15 phút để xác nhận và giao hàng tận nơi cho quý khách.'
        ]);
    }

    // =========================================================================
    // 5. CHECK SỐ ĐIỆN THOẠI TRÙNG DATABASE QUA API (VALIDATE REALTIME)
    // =========================================================================

    public function checkPhone(): void
    {
        $phone = trim((string)($_GET['phone'] ?? $_POST['phone'] ?? ''));
        $phoneClean = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phoneClean) < 10) {
            $this->json([
                'success' => true,
                'valid' => false,
                'exists' => false,
                'message' => 'Số điện thoại phải có đúng 10 chữ số.'
            ]);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE REPLACE(REPLACE(REPLACE(phone, '.', ''), ' ', ''), '-', '') = ? LIMIT 1");
        $stmt->execute([$phoneClean]);
        $user = $stmt->fetch();

        if ($user) {
            $this->json([
                'success' => true,
                'valid' => true,
                'exists' => true,
                'message' => 'Số điện thoại này đã được đăng ký tài khoản trong hệ thống!'
            ]);
        } else {
            $this->json([
                'success' => true,
                'valid' => true,
                'exists' => false,
                'message' => 'Số điện thoại hợp lệ và sẵn sàng sử dụng.'
            ]);
        }
    }

    private function handleAuth(string $action): void
    {
        switch ($action) {
            case 'check-phone':
                $this->checkPhone();
                break;
            default:
                $this->json(['success' => false, 'message' => 'Hành động auth không hợp lệ'], 400);
        }
    }

    // =========================================================================
    // 6. CLIENT XÁC NHẬN ĐÃ NHẬN HÀNG (CHỈ CLIENT MỚI ĐƯỢC XÁC NHẬN HOÀN TẤT)
    // =========================================================================

    private function orderConfirmReceived(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Yêu cầu phương thức POST'], 405);
            return;
        }

        $currentUser = $_SESSION['user'] ?? null;
        if (empty($currentUser['id'])) {
            $this->json(['success' => false, 'message' => 'Vui lòng đăng nhập để xác nhận nhận hàng'], 401);
            return;
        }

        $orderCode = trim((string)($_POST['order_code'] ?? ''));
        if ($orderCode === '') {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng #' . $orderCode], 404);
            return;
        }

        if ((int)$order['user_id'] !== (int)$currentUser['id']) {
            $this->json(['success' => false, 'message' => 'Bạn không sở hữu đơn hàng này'], 403);
            return;
        }

        if ($order['order_status'] !== 'shipping') {
            $this->json(['success' => false, 'message' => 'Chỉ có thể xác nhận khi đơn hàng đang ở trạng thái Đang giao hàng!'], 400);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE orders SET order_status = 'completed', payment_status = 'paid', completed_at = NOW() WHERE id = ?");
        $stmt->execute([(int)$order['id']]);

        $this->json([
            'success' => true,
            'message' => "Bạn đã xác nhận nhận hàng thành công cho đơn #{$orderCode}! Cảm ơn quý khách đã tin tưởng mua sắm tại NEXVORA."
        ]);
    }

    // =========================================================================
    // 7. SSE REALTIME SERVER-SENT EVENTS CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG THEO MILISECONDS
    // =========================================================================

    private function orderSse(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $userId = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;
        $orderCode = trim((string)($_GET['code'] ?? ''));

        // [SỬA LỖI] Giải phóng khóa session: nếu không, kết nối SSE (25 giây) sẽ chặn mọi request khác
        // của cùng người dùng (chuyển trang, thêm giỏ hàng...) cho tới khi SSE kết thúc.
        session_write_close();

        $orderModel = new Order();
        $db = $orderModel->getDb();

        $startTime = time();
        set_time_limit(30);

        while (time() - $startTime < 25) {
            if (connection_aborted()) {
                break;
            }

            if ($orderCode !== '') {
                $stmt = $db->prepare("SELECT id, order_code, order_status, payment_status, updated_at FROM orders WHERE order_code = ? LIMIT 1");
                $stmt->execute([$orderCode]);
                $ord = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ord) {
                    echo "event: order_update\n";
                    echo "data: " . json_encode($ord) . "\n\n";
                }
            } elseif ($userId !== null) {
                $stmt = $db->prepare("SELECT id, order_code, order_status, payment_status, updated_at FROM orders WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 15");
                $stmt->execute([$userId]);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "event: user_orders_update\n";
                echo "data: " . json_encode(['orders' => $orders, 'timestamp' => microtime(true)]) . "\n\n";
            }

            flush();
            usleep(800000); // 800 milliseconds
        }
    }

    // =========================================================================
    // 8. BIẾN THỂ REALTIME CHO CLIENT KHI ADMIN THÊM / CẬP NHẬT
    // =========================================================================

    private function handleProducts(string $action): void
    {
        switch ($action) {
            case 'variants':
                $this->productVariants();
                break;
            default:
                $this->json(['success' => false, 'message' => 'Hành động sản phẩm không hợp lệ'], 400);
        }
    }

    private function productVariants(): void
    {
        $productId = (int)($_GET['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->json(['success' => false, 'message' => 'Thiếu ID sản phẩm'], 400);
            return;
        }

        $productModel = new Product();
        $product = $productModel->getById($productId);
        if (!$product) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm'], 404);
            return;
        }

        $colors = $productModel->getColors($productId);

        $this->json([
            'success' => true,
            'product_id' => $productId,
            'quantity' => (int)($product['quantity'] ?? 0),
            'price' => (float)($product['price'] ?? 0),
            'colors' => $colors
        ]);
    }
}
