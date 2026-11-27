<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Product.php';

class CartController extends BaseController {
    // Helper kiểm tra quyền: Khách không có quyền sử dụng giỏ hàng
    private function requireUser(): void {
        if (empty($_SESSION['user']) && empty($_SESSION['admin_user'])) {
            $this->redirect(BASE_URL . 'index.php?url=auth/login&msg=login_required_cart');
        }
    }

    /**
     * Tự động xác thực và làm mới quyền lợi mã giảm giá theo tổng tiền giỏ hàng thực tế
     * Khắc phục triệt để lỗi: Giảm giỏ hàng dưới mức tối thiểu nhưng mã vẫn giảm tiền
     */
    public static function refreshCouponSession(): void {
        if (empty($_SESSION['applied_coupon']['code'])) {
            unset($_SESSION['applied_coupon'], $_SESSION['coupon_code'], $_SESSION['discount_amount'], $_SESSION['coupon_message']);
            return;
        }

        $subtotal = 0;
        if (!empty($_SESSION['is_buy_now']) && !empty($_SESSION['buy_now_item'])) {
            $subtotal = (float)$_SESSION['buy_now_item']['price'] * (int)$_SESSION['buy_now_item']['quantity'];
        } elseif (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                if (!isset($item['selected']) || $item['selected']) {
                    $subtotal += (float)$item['price'] * (int)$item['quantity'];
                }
            }
        }

        if ($subtotal <= 0) {
            unset($_SESSION['applied_coupon'], $_SESSION['coupon_code'], $_SESSION['discount_amount'], $_SESSION['coupon_message']);
            return;
        }

        require_once __DIR__ . '/../models/Coupon.php';
        $couponModel = new Coupon();
        $currUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $userId = !empty($currUser['id']) ? (int)$currUser['id'] : null;
        $userEmail = !empty($currUser['email']) ? (string)$currUser['email'] : null;
        $userPhone = !empty($currUser['phone']) ? (string)$currUser['phone'] : null;
        $cartItems = [];
        if (!empty($_SESSION['is_buy_now']) && !empty($_SESSION['buy_now_item'])) {
            $cartItems = [$_SESSION['buy_now_item']];
        } else {
            foreach ($_SESSION['cart'] ?? [] as $it) {
                if (!isset($it['selected']) || $it['selected']) {
                    $cartItems[] = $it;
                }
            }
        }

        $res = $couponModel->checkAndCalculate($_SESSION['applied_coupon']['code'], $subtotal, $userId, $cartItems, $userEmail, $userPhone);
        if ($res['valid']) {
            $_SESSION['applied_coupon'] = $res['coupon'];
            $_SESSION['discount_amount'] = (float)$res['discount'];
            $_SESSION['coupon_message'] = $res['message'];
        } else {
            // Không còn đủ điều kiện áp dụng mã (VD: Đơn dưới mức tối thiểu)
            $_SESSION['coupon_error'] = 'Mã ' . $_SESSION['applied_coupon']['code'] . ' không thể áp dụng: ' . $res['message'];
            unset($_SESSION['applied_coupon'], $_SESSION['coupon_code'], $_SESSION['discount_amount'], $_SESSION['coupon_message']);
        }
    }

    /**
     * Tự động lưu giỏ hàng vào Database theo tài khoản (Persistent Cart)
     */
    public static function syncUserCartToDb(): void {
        if (!empty($_SESSION['user']['id'])) {
            require_once __DIR__ . '/../models/Cart.php';
            $cartModel = new Cart();
            $cartModel->saveCart((int)$_SESSION['user']['id'], $_SESSION['cart'] ?? []);
        }
    }

    /**
     * Đồng bộ giỏ hàng với Database: Xóa sản phẩm bị soft-delete, cập nhật giá mới nhất và giới hạn tồn kho
     */
    public static function syncCartWithDatabase(): void {
        if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return;
        }

        require_once __DIR__ . '/../models/Product.php';
        $productModel = new Product();
        $updated = false;

        foreach ($_SESSION['cart'] as $k => &$item) {
            $prod = $productModel->getById((int)$item['id']);
            if (!$prod || !empty($prod['deleted_at']) || ($prod['status'] ?? 'active') !== 'active') {
                unset($_SESSION['cart'][$k]);
                $_SESSION['cart_warning'] = 'Sản phẩm "' . htmlspecialchars($item['name']) . '" đã ngừng kinh doanh và được xóa khỏi giỏ hàng.';
                $updated = true;
                continue;
            }

            // Đồng bộ giá mới nhất từ database
            if ((float)$item['price'] !== (float)$prod['price']) {
                $item['price'] = (float)$prod['price'];
                $item['name'] = $prod['name'];
                $updated = true;
            }

            // Giới hạn số lượng theo tồn kho thực tế
            if ((int)$item['quantity'] > (int)$prod['quantity']) {
                $item['quantity'] = max(1, (int)$prod['quantity']);
                $updated = true;
            }
        }
        unset($item);

        if ($updated) {
            self::refreshCouponSession();
            self::syncUserCartToDb();
        }
    }

    public function index(): void {
        // Phân quyền: Khách chỉ được xem giao diện, không có giỏ hàng
        $this->requireUser();

        // Xóa phiên Mua ngay tạm thời khi người dùng chủ động vào xem giỏ hàng
        unset($_SESSION['is_buy_now'], $_SESSION['buy_now_item']);

        // Đồng bộ sản phẩm trong giỏ với database (kiểm tra xóa mềm, giá mới, tồn kho)
        self::syncCartWithDatabase();

        // Đồng bộ lại mã giảm giá theo giỏ hàng
        self::refreshCouponSession();
        self::syncUserCartToDb();

        $cart = $_SESSION['cart'] ?? [];
        $subtotal = 0;
        $selectedSubtotal = 0;
        $selectedCount = 0;

        foreach ($cart as $key => &$item) {
            if (!isset($item['selected'])) {
                $item['selected'] = true;
                $_SESSION['cart'][$key]['selected'] = true;
            }
            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
            $subtotal += $itemTotal;
            if (!empty($item['selected'])) {
                $selectedSubtotal += $itemTotal;
                $selectedCount += (int)$item['quantity'];
            }
        }
        unset($item);

        $discount = $_SESSION['discount_amount'] ?? 0;
        $shipping = 0; // Phí vận chuyển mặc định: Miễn phí (0đ)
        $finalTotal = max(0, $selectedSubtotal - $discount + $shipping);

        require_once __DIR__ . '/../models/Coupon.php';
        $couponModel = new Coupon();
        $currUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $currentUserId = !empty($currUser['id']) ? (int)$currUser['id'] : null;
        $userEmail = !empty($currUser['email']) ? (string)$currUser['email'] : null;
        $userPhone = !empty($currUser['phone']) ? (string)$currUser['phone'] : null;
        $availableCoupons = $couponModel->getAvailableCoupons($selectedSubtotal, $currentUserId, $cart, $userEmail, $userPhone);

        $this->render('cart/index', [
            'pageTitle' => 'Giỏ hàng của bạn',
            'cart' => $cart,
            'subtotal' => $subtotal,
            'selectedSubtotal' => $selectedSubtotal,
            'selectedCount' => $selectedCount,
            'discount' => $discount,
            'shipping' => $shipping,
            'finalTotal' => $finalTotal,
            'couponCode' => $_SESSION['coupon_code'] ?? '',
            'availableCoupons' => $availableCoupons
        ]);
    }

    public function add(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        $this->requireCsrf();

        // Phân quyền: Khách không được thêm vào giỏ hàng
        if (empty($_SESSION['user']) && empty($_SESSION['admin_user'])) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json([
                    'success' => false,
                    'require_login' => true,
                    'login_url' => BASE_URL . 'index.php?url=auth/login&msg=login_required_cart',
                    'message' => 'Bạn đang duyệt với tư cách Khách. Vui lòng đăng nhập tài khoản User để thêm sản phẩm vào giỏ hàng!'
                ], 401);
            }
            $this->redirect(BASE_URL . 'index.php?url=auth/login&msg=login_required_cart');
        }

        $productId = (int)($_POST['product_id'] ?? 0);
        $rawQuantity = (int)($_POST['quantity'] ?? 1);
        $isAjaxReq = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $productModel = new Product();
        $product = $productId > 0 ? $productModel->getById($productId) : null;

        // [SỬA LỖI] Báo lỗi rõ ràng thay vì chuyển trang im lặng (AJAX trước đây nhận về cả trang HTML)
        if (!$product || !empty($product['deleted_at'])) {
            $msg = 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh!';
            if ($isAjaxReq) {
                $this->json(['success' => false, 'message' => $msg], 404);
            }
            $_SESSION['flash_cart_err'] = $msg;
            $this->redirect(BASE_URL . 'index.php?url=products');
        }

        // [SỬA LỖI] Số lượng 0 hoặc âm: báo lỗi thay vì tự đổi thành 1
        if ($rawQuantity <= 0) {
            $msg = 'Số lượng sản phẩm không hợp lệ!';
            if ($isAjaxReq) {
                $this->json(['success' => false, 'message' => $msg], 400);
            }
            $_SESSION['flash_cart_err'] = $msg;
            $this->redirect(BASE_URL . 'index.php?url=cart');
        }
        $quantity = min(10, $rawQuantity);

        // Chốt kiểm tra tồn kho: Không cho thêm quá số lượng tồn
        $stock = (int)($product['quantity'] ?? 0);
        if ($stock <= 0) {
            $msg = 'Sản phẩm "' . $product['name'] . '" hiện đã hết hàng trong kho!';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => false, 'message' => $msg], 400);
            }
            $_SESSION['flash_cart_err'] = $msg;
            $this->redirect(BASE_URL . 'index.php?url=products/detail&id=' . $productId);
            return;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $color = trim($_POST['color'] ?? '');
        $cartKey = $color ? ($productId . '_' . md5($color)) : (string)$productId;

        $curQty = isset($_SESSION['cart'][$cartKey]) ? (int)$_SESSION['cart'][$cartKey]['quantity'] : 0;
        
        // RÀNG BUỘC CHỐNG SPAM: Mỗi sản phẩm tối đa 10 chiếc trong giỏ hàng (tối đa 2 chiếc với sản phẩm giá trị cao >= 15 triệu)
        $maxAllowed = ((float)$product['price'] >= 15000000) ? 2 : 10;
        if ($curQty >= $maxAllowed) {
            $msg = ($maxAllowed === 2)
                ? 'Sản phẩm giá trị cao (từ 15 triệu trở lên) được giới hạn tối đa 2 chiếc trong giỏ hàng để phòng chống đầu cơ/spam!'
                : 'Bạn đã có tối đa 10 chiếc sản phẩm này trong giỏ hàng (Giới hạn tối đa 10 chiếc/món để tránh spam/đầu cơ)!';
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $this->json(['success' => false, 'message' => $msg], 400);
            }
            $_SESSION['flash_cart_err'] = $msg;
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        // [SỬA LỖI] Vượt quá tồn kho: báo lỗi thay vì tự cắt số lượng mà vẫn báo "thêm thành công"
        if ($curQty + $quantity > $stock) {
            $msg = "Rất tiếc, sản phẩm này chỉ còn {$stock} sản phẩm trong kho!";
            if ($isAjaxReq) {
                $this->json(['success' => false, 'message' => $msg], 400);
            }
            $_SESSION['flash_cart_err'] = $msg;
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        $effectiveMax = min($maxAllowed, $stock);
        $newQty = $curQty + $quantity;

        if ($newQty > $effectiveMax) {
            $availableToAdd = max(0, $effectiveMax - $curQty);
            if ($availableToAdd <= 0) {
                $limitReason = ($effectiveMax === 2) ? "Giới hạn tối đa 2 chiếc đối với sản phẩm cao cấp" : (($effectiveMax === 10) ? "Giới hạn tối đa 10 chiếc/sản phẩm" : "Kho chỉ còn {$stock} chiếc");
                $msg = "Bạn đã có {$curQty} chiếc trong giỏ. {$limitReason}!";
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    $this->json(['success' => false, 'message' => $msg], 400);
                }
                $_SESSION['flash_cart_err'] = $msg;
                $this->redirect(BASE_URL . 'index.php?url=cart');
                return;
            }
            $quantity = $availableToAdd;
            $newQty = $effectiveMax;
        }

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] = $newQty;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'color' => $color,
                'slug' => $product['slug'],
                'price' => (float)$product['price'],
                'image' => $product['image'],
                'quantity' => $quantity,
                'category_name' => $product['category_name'] ?? 'Phụ kiện'
            ];
        }

        self::refreshCouponSession();
        self::syncUserCartToDb();

        // Nếu là yêu cầu AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $totalCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
            $this->json([
                'success' => true,
                'message' => 'Đã thêm ' . $product['name'] . ' vào giỏ hàng!',
                'cartCount' => $totalCount
            ]);
        }

        // Nếu có cờ buy_now thì chuyển thẳng tới checkout
        if (!empty($_POST['buy_now']) || !empty($_GET['buy_now'])) {
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        $this->redirect(BASE_URL . 'index.php?url=cart');
    }

    /**
     * Mua ngay: Chuyển thẳng đến trang checkout cho sản phẩm này mà KHÔNG thêm vào giỏ hàng chính
     */
    public function buyNow(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        $this->requireCsrf();
        $this->requireUser(); // [SỬA LỖI] Khách chưa đăng nhập không được tạo phiên Mua ngay

        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if ($productId > 0) {
            $productModel = new Product();
            $product = $productModel->getById($productId);

            if ($product) {
                $stock = (int)($product['quantity'] ?? 0);
                if ($stock <= 0) {
                    $_SESSION['flash_cart_err'] = 'Sản phẩm "' . $product['name'] . '" đã hết hàng, không thể Mua Ngay!';
                    $this->redirect(BASE_URL . 'index.php?url=products/detail&id=' . $productId);
                    return;
                }

                if ($quantity > $stock) {
                    $quantity = $stock;
                }
                // [SỬA LỖI] Giới hạn chống đầu cơ giống giỏ hàng: tối đa 10 chiếc, sản phẩm >= 15 triệu tối đa 2 chiếc
                $maxAllowed = ((float)$product['price'] >= 15000000) ? 2 : 10;
                if ($quantity > $maxAllowed) {
                    $quantity = $maxAllowed;
                    $_SESSION['checkout_error'] = ($maxAllowed === 2)
                        ? 'Sản phẩm giá trị cao (từ 15 triệu trở lên) được giới hạn tối đa 2 chiếc trong cùng 1 lần đặt. Số lượng đã được điều chỉnh về 2.'
                        : 'Mỗi sản phẩm chỉ được mua tối đa 10 chiếc. Số lượng đã được điều chỉnh về 10.';
                }

                $color = trim($_POST['color'] ?? '');
                if (empty($color)) {
                    $productColors = $productModel->getColors($productId);
                    if (!empty($productColors) && !empty($productColors[0]['color_name'])) {
                        $color = $productColors[0]['color_name'];
                    }
                }

                $_SESSION['is_buy_now'] = true;
                $_SESSION['buy_now_item'] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'color' => $color,
                    'slug' => $product['slug'],
                    'price' => (float)$product['price'],
                    'image' => $product['image'],
                    'quantity' => $quantity,
                    'category_name' => $product['category_name'] ?? 'Phụ kiện'
                ];

                self::refreshCouponSession();
            }
        }

        $this->redirect(BASE_URL . 'index.php?url=checkout');
    }

    public function update(): void {
        $this->requireUser();
        $this->requireCsrf(BASE_URL . 'index.php?url=cart');
        $productModel = new Product();

        // 1. Cập nhật hàng loạt từ form giỏ hàng: name="quantities[key]"
        if (!empty($_POST['quantities']) && is_array($_POST['quantities'])) {
            foreach ($_POST['quantities'] as $k => $qty) {
                $qty = (int)$qty;
                if (isset($_SESSION['cart'][$k])) {
                    if ($qty <= 0) {
                        unset($_SESSION['cart'][$k]);
                    } else {
                        $pId = (int)$_SESSION['cart'][$k]['id'];
                        $pData = $productModel->getById($pId);
                        $maxStock = (int)($pData['quantity'] ?? 9999);
                        $maxAllowed = ((float)($pData['price'] ?? 0) >= 15000000) ? 2 : 10;
                        $_SESSION['cart'][$k]['quantity'] = min($maxAllowed, min($qty, $maxStock));
                    }
                }
            }
            self::refreshCouponSession();
            self::syncUserCartToDb();
            $_SESSION['flash_success'] = 'Đã cập nhật số lượng giỏ hàng thành công (Tối đa 10 sản phẩm/món)!';
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        // 2. Cập nhật từng sản phẩm (qua nút bấm hoặc AJAX)
        $key = $_POST['key'] ?? '';
        $productId = (int)($_POST['product_id'] ?? 0);
        $action = $_POST['action_type'] ?? '';

        $targetKey = null;
        if (!empty($key) && isset($_SESSION['cart'][$key])) {
            $targetKey = $key;
        } elseif ($productId > 0) {
            if (isset($_SESSION['cart'][$productId])) {
                $targetKey = $productId;
            } elseif (isset($_SESSION['cart'][(string)$productId])) {
                $targetKey = (string)$productId;
            } else {
                foreach ($_SESSION['cart'] ?? [] as $k => $item) {
                    if ((int)($item['id'] ?? 0) === $productId) {
                        $targetKey = $k;
                        break;
                    }
                }
            }
        }

        if ($targetKey !== null && isset($_SESSION['cart'][$targetKey])) {
            $pId = (int)$_SESSION['cart'][$targetKey]['id'];
            $pData = $productModel->getById($pId);
            $maxStock = (int)($pData['quantity'] ?? 9999);
            $maxAllowed = ((float)($pData['price'] ?? 0) >= 15000000) ? 2 : 10;
            $effectiveMax = min($maxAllowed, $maxStock);

            if ($action === 'increase') {
                if ($_SESSION['cart'][$targetKey]['quantity'] < $effectiveMax) {
                    $_SESSION['cart'][$targetKey]['quantity']++;
                }
            } elseif ($action === 'decrease') {
                $_SESSION['cart'][$targetKey]['quantity']--;
                if ($_SESSION['cart'][$targetKey]['quantity'] <= 0) {
                    unset($_SESSION['cart'][$targetKey]);
                }
            } elseif (isset($_POST['quantity'])) {
                $qty = (int)$_POST['quantity'];
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$targetKey]);
                } else {
                    $_SESSION['cart'][$targetKey]['quantity'] = min($qty, $effectiveMax);
                }
            }
        }

        self::refreshCouponSession();
        self::syncUserCartToDb();

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $subtotal = 0;
            foreach ($_SESSION['cart'] ?? [] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $discount = $_SESSION['discount_amount'] ?? 0;
            $finalTotal = max(0, $subtotal - $discount);
            $itemQty = isset($_SESSION['cart'][$targetKey]) ? $_SESSION['cart'][$targetKey]['quantity'] : 0;
            $itemTotal = isset($_SESSION['cart'][$targetKey]) ? ($_SESSION['cart'][$targetKey]['price'] * $itemQty) : 0;

            $this->json([
                'success' => true,
                'item_quantity' => $itemQty,
                'item_total' => number_format($itemTotal, 0, ',', '.') . 'đ',
                'subtotal' => number_format($subtotal, 0, ',', '.') . 'đ',
                'discount' => number_format($discount, 0, ',', '.') . 'đ',
                'final_total' => number_format($finalTotal, 0, ',', '.') . 'đ'
            ]);
        }

        $this->redirect(BASE_URL . 'index.php?url=cart');
    }

    public function remove(): void {
        $this->requireUser();
        // [SỬA LỖI BẢO MẬT] Xoá / làm trống giỏ hàng bắt buộc CSRF token (trước đây chỉ cần mở 1 đường link GET)
        $this->requireCsrf(BASE_URL . 'index.php?url=cart');

        // 1. Hỗ trợ làm trống toàn bộ giỏ hàng
        if (!empty($_GET['clear_all'])) {
            unset($_SESSION['cart'], $_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code']);
            self::syncUserCartToDb();
            $_SESSION['flash_success'] = 'Đã làm trống giỏ hàng thành công!';
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        $key = $_GET['key'] ?? $_POST['key'] ?? '';
        $id = (int)($_GET['id'] ?? $_POST['product_id'] ?? 0);
        $deleted = false;

        // 2. Xóa theo key chính xác
        if (!empty($key) && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
            $deleted = true;
        }

        // 3. Fallback: duyệt xóa theo product_id
        if (!$deleted && $id > 0) {
            if (isset($_SESSION['cart'][$id])) {
                unset($_SESSION['cart'][$id]);
                $deleted = true;
            }
            if (isset($_SESSION['cart'][(string)$id])) {
                unset($_SESSION['cart'][(string)$id]);
                $deleted = true;
            }
            if (!empty($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $k => $item) {
                    if ((int)($item['id'] ?? 0) === $id) {
                        unset($_SESSION['cart'][$k]);
                        $deleted = true;
                    }
                }
            }
        }

        if (empty($_SESSION['cart'])) {
            unset($_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code']);
            self::syncUserCartToDb();
        } else {
            self::refreshCouponSession();
            self::syncUserCartToDb();
        }

        if ($deleted) {
            $_SESSION['flash_success'] = 'Đã xóa sản phẩm khỏi giỏ hàng thành công!';
        }

        $this->redirect(BASE_URL . 'index.php?url=cart');
    }

    public function applyCoupon(): void {
        $this->requireUser();
        $this->requireCsrf(BASE_URL . 'index.php?url=cart');
        $code = trim($_POST['coupon_code'] ?? '');
        // Chỉ tính các sản phẩm đang được tích chọn thanh toán (đồng bộ với refreshCouponSession)
        $cart = array_filter($_SESSION['cart'] ?? [], fn($it) => !isset($it['selected']) || !empty($it['selected']));
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        if (empty($code)) {
            $_SESSION['coupon_error'] = 'Vui lòng nhập mã khuyến mại!';
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        require_once __DIR__ . '/../models/Coupon.php';
        $couponModel = new Coupon();
        $currUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $userId = !empty($currUser['id']) ? (int)$currUser['id'] : null;
        $userEmail = !empty($currUser['email']) ? (string)$currUser['email'] : null;
        $userPhone = !empty($currUser['phone']) ? (string)$currUser['phone'] : null;
        $res = $couponModel->checkAndCalculate($code, $subtotal, $userId, $cart, $userEmail, $userPhone);

        if ($res['valid']) {
            $_SESSION['coupon_code'] = $res['coupon']['code'];
            $_SESSION['applied_coupon'] = $res['coupon'];
            $_SESSION['discount_amount'] = (float)$res['discount'];
            $_SESSION['coupon_message'] = $res['message'];
            unset($_SESSION['coupon_error']);
        } else {
            $_SESSION['coupon_error'] = $res['message'];
        }
        $this->redirect(BASE_URL . 'index.php?url=cart');
    }
}
