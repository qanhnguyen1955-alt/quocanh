<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Order.php';

class CheckoutController extends BaseController {
    private function requireUser(): void {
        if (empty($_SESSION['user']) && empty($_SESSION['admin_user'])) {
            $this->redirect(BASE_URL . 'index.php?url=auth/login&msg=login_required_checkout');
        }
    }

    /**
     * Lấy danh sách sản phẩm thực tế cần thanh toán:
     * - Nếu là phiên Mua ngay: chỉ lấy món mua ngay.
     * - Nếu từ Giỏ hàng chính: CHỈ lấy các món được tích chọn (selected = true), không lấy toàn bộ giỏ!
     */
    private function getCheckoutCart(): array {
        $isBuyNow = !empty($_SESSION['is_buy_now']) && !empty($_SESSION['buy_now_item']);
        if ($isBuyNow) {
            return [$_SESSION['buy_now_item']];
        }

        $cart = [];
        foreach ($_SESSION['cart'] ?? [] as $k => $item) {
            if (!isset($item['selected']) || !empty($item['selected'])) {
                $cart[$k] = $item;
            }
        }
        return $cart;
    }

    public function index(): void {
        // Phân quyền: Khách không được đặt hàng, yêu cầu đăng nhập User hoặc Admin
        $this->requireUser();

        require_once __DIR__ . '/CartController.php';
        CartController::syncCartWithDatabase();

        $cart = $this->getCheckoutCart();

        if (empty($cart)) {
            $_SESSION['cart_error'] = 'Vui lòng chọn ít nhất 1 sản phẩm trong giỏ hàng để tiến hành thanh toán!';
            $this->redirect(BASE_URL . 'index.php?url=cart');
        }

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Tải danh sách mã khuyến mại từ database & kiểm tra mã đã áp dụng
        require_once __DIR__ . '/../models/Coupon.php';
        $couponModel = new Coupon();
        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $currentUserId = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;
        $userEmail = !empty($currentUser['email']) ? (string)$currentUser['email'] : null;
        $userPhone = !empty($currentUser['phone']) ? (string)$currentUser['phone'] : null;

        if (!empty($_SESSION['applied_coupon']['code'])) {
            $check = $couponModel->checkAndCalculate($_SESSION['applied_coupon']['code'], $subtotal, $currentUserId, $cart, $userEmail, $userPhone);
            if ($check['valid']) {
                $discount = $check['discount'];
                $_SESSION['discount_amount'] = $discount;
                $_SESSION['applied_coupon'] = $check['coupon'];
            } else {
                unset($_SESSION['applied_coupon'], $_SESSION['discount_amount']);
                $_SESSION['checkout_error'] = 'Mã giảm giá đã bị hủy: ' . $check['message'];
                $discount = 0;
            }
        } else {
            $discount = $_SESSION['discount_amount'] ?? 0;
        }

        $shipping = 0; // Phí vận chuyển mặc định: Miễn phí (0đ)
        $finalTotal = max(0, $subtotal - $discount + $shipping);
        $availableCoupons = $couponModel->getAvailableCoupons($subtotal, $currentUserId, $cart, $userEmail, $userPhone);
        $appliedCoupon = $_SESSION['applied_coupon'] ?? null;

        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        if (!empty($currentUser['id'])) {
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            $freshUser = $userModel->getById((int)$currentUser['id']);
            if ($freshUser) {
                $currentUser = array_merge($currentUser, $freshUser);
                if (!empty($_SESSION['user'])) {
                    $_SESSION['user'] = $currentUser;
                }
            }
        }

        $this->render('checkout/index', [
            'pageTitle' => 'Thanh toán đơn hàng an toàn',
            'cart' => $cart,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'finalTotal' => $finalTotal,
            'currentUser' => $currentUser,
            'availableCoupons' => $availableCoupons,
            'appliedCoupon' => $appliedCoupon
        ]);
    }

    /**
     * Áp dụng mã khuyến mại tại trang thanh toán
     */
    public function applyCoupon(): void {
        $this->requireUser();

        $isAjax = isset($_GET['ajax']) 
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        // [SỬA LỖI BẢO MẬT] Bắt buộc POST và CSRF token
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Yêu cầu phương thức POST hợp lệ!'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        if (!Csrf::validateRequest()) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Token CSRF không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang!'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['checkout_error'] = 'Token CSRF không hợp lệ hoặc đã hết hạn!';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        $code = trim($_POST['coupon_code'] ?? '');

        // Lấy đúng các sản phẩm đang được chọn thanh toán
        $cart = $this->getCheckoutCart();

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += (float)$item['price'] * (int)$item['quantity'];
        }

        if (empty($code)) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập hoặc chọn mã khuyến mại!'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['checkout_error'] = 'Vui lòng nhập hoặc chọn mã khuyến mại!';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        require_once __DIR__ . '/../models/Coupon.php';
        $couponModel = new Coupon();
        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $currentUserId = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;
        $userEmail = !empty($currentUser['email']) ? (string)$currentUser['email'] : null;
        $userPhone = !empty($currentUser['phone']) ? (string)$currentUser['phone'] : null;
        $result = $couponModel->checkAndCalculate($code, $subtotal, $currentUserId, $cart, $userEmail, $userPhone);

        if ($result['valid']) {
            $_SESSION['applied_coupon'] = $result['coupon'];
            $_SESSION['discount_amount'] = (float)$result['discount'];
            $_SESSION['coupon_message'] = $result['message'];
            unset($_SESSION['checkout_error']);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $shipping = 0;
                $finalTotal = max(0, $subtotal - (float)$result['discount'] + $shipping);
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'discount' => (float)$result['discount'],
                    'discount_formatted' => number_format((float)$result['discount'], 0, ',', '.') . 'đ',
                    'subtotal' => $subtotal,
                    'subtotal_formatted' => number_format($subtotal, 0, ',', '.') . 'đ',
                    'shipping' => $shipping,
                    'final_total' => $finalTotal,
                    'final_total_formatted' => number_format($finalTotal, 0, ',', '.') . 'đ',
                    'code' => $result['coupon']['code'],
                    'title' => $result['coupon']['title'],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $_SESSION['checkout_error'] = $result['message'];
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => $result['message']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $this->redirect(BASE_URL . 'index.php?url=checkout');
    }

    /**
     * Hủy áp dụng mã khuyến mại
     */
    public function removeCoupon(): void {
        $this->requireUser();
        unset($_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code']);
        $_SESSION['coupon_message'] = 'Đã hủy áp dụng mã khuyến mại.';

        $isAjax = isset($_GET['ajax']) 
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $cart = $this->getCheckoutCart();
            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $shipping = 0;
            $finalTotal = max(0, $subtotal + $shipping);
            echo json_encode([
                'success' => true,
                'message' => 'Đã hủy áp dụng mã khuyến mại.',
                'discount' => 0,
                'discount_formatted' => '0đ',
                'subtotal' => $subtotal,
                'subtotal_formatted' => number_format($subtotal, 0, ',', '.') . 'đ',
                'final_total' => $finalTotal,
                'final_total_formatted' => number_format($finalTotal, 0, ',', '.') . 'đ'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->redirect(BASE_URL . 'index.php?url=checkout');
    }

    public function process(): void {
        $this->requireUser();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        $this->requireCsrf(BASE_URL . 'index.php?url=checkout');

        $isBuyNow = !empty($_SESSION['is_buy_now']) && !empty($_SESSION['buy_now_item']);
        $checkoutKeys = [];
        if ($isBuyNow) {
            $cart = [$_SESSION['buy_now_item']];
        } else {
            $cart = [];
            foreach ($_SESSION['cart'] ?? [] as $k => $item) {
                if (!isset($item['selected']) || !empty($item['selected'])) {
                    $cart[$k] = $item;
                    $checkoutKeys[] = $k;
                }
            }
        }

        if (empty($cart)) {
            $_SESSION['cart_error'] = 'Vui lòng chọn ít nhất 1 sản phẩm trong giỏ hàng để tiến hành thanh toán!';
            $this->redirect(BASE_URL . 'index.php?url=cart');
            return;
        }

        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $address = trim($_POST['customer_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cod';

        if (empty($name) || empty($phone) || empty($address)) {
            $_SESSION['checkout_error'] = 'Vui lòng điền đầy đủ Họ tên, Số điện thoại và Địa chỉ nhận hàng!';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        if (!preg_match('/^0[35789][0-9]{8}$/', $phone)) {
            $_SESSION['checkout_error'] = 'Số điện thoại nhận hàng không hợp lệ! Vui lòng nhập số điện thoại Việt Nam (10 chữ số, bắt đầu bằng 03, 05, 07, 08, 09).';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['checkout_error'] = 'Địa chỉ email không đúng định dạng!';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        // Tính toán tổng tiền
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $discount = 0;
        // [SỬA LỖI] Kiểm tra lại mã giảm giá theo đúng các sản phẩm đang thanh toán ngay trước khi tạo đơn
        // (trước đây lấy thẳng số tiền giảm lưu trong session, có thể đã cũ nếu giỏ hàng thay đổi ở tab khác)
        if (!empty($_SESSION['applied_coupon']['code'])) {
            require_once __DIR__ . '/../models/Coupon.php';
            $currUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
            $chkUid = !empty($currUser['id']) ? (int)$currUser['id'] : null;
            $recheck = (new Coupon())->checkAndCalculate($_SESSION['applied_coupon']['code'], (float)$subtotal, $chkUid, array_values($cart), $email, $phone);
            if (!empty($recheck['valid'])) {
                $discount = (float)$recheck['discount'];
            } else {
                unset($_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code']);
                $_SESSION['checkout_error'] = 'Mã giảm giá đã bị hủy: ' . ($recheck['message'] ?? 'không còn đủ điều kiện') . ' Vui lòng kiểm tra lại đơn hàng.';
                $this->redirect(BASE_URL . 'index.php?url=checkout');
                return;
            }
        }
        $shipping = 0; // Phí vận chuyển mặc định: Miễn phí (0đ)
        $finalTotal = max(0, $subtotal - $discount + $shipping);

        if (!empty($_SESSION['applied_coupon']['code'])) {
            $couponNote = "[Áp dụng mã: " . $_SESSION['applied_coupon']['code'] . " (-" . number_format($discount, 0, ',', '.') . "đ)]";
            $notes = !empty($notes) ? ($notes . " | " . $couponNote) : $couponNote;
        }

        // Lưu user_id vào đơn hàng
        $userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

        // Tự động cập nhật địa chỉ/SĐT mới vào hồ sơ tài khoản nếu khách chọn hoặc tài khoản chưa có địa chỉ
        $saveToProfile = !empty($_POST['save_address_to_profile']);
        if ($userId && ($saveToProfile || empty($_SESSION['user']['address']))) {
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            $freshUser = $userModel->getById($userId);
            if ($freshUser) {
                $userModel->updateProfile($userId, [
                    'fullname' => !empty($name) ? $name : ($freshUser['fullname'] ?? ''),
                    'phone' => !empty($phone) ? $phone : ($freshUser['phone'] ?? null),
                    'address' => $address
                ]);
                $_SESSION['user']['address'] = $address;
                if (!empty($phone)) $_SESSION['user']['phone'] = $phone;
            }
        }

        $orderData = [
            'user_id' => $userId,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $email,
            'customer_address' => $address,
            'notes' => $notes,
            'total_amount' => $subtotal,
            'discount_amount' => $discount,
            'coupon_code' => $_SESSION['applied_coupon']['code'] ?? null,
            'shipping_fee' => $shipping,
            'final_amount' => $finalTotal,
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending'
        ];

        $orderModel = new Order();
        try {
            $orderCode = $orderModel->createOrder($orderData, $cart);
        } catch (Exception $e) {
            $_SESSION['checkout_error'] = 'Lỗi xử lý đơn hàng: ' . $e->getMessage();
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        if (!$orderCode) {
            $_SESSION['checkout_error'] = 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại!';
            $this->redirect(BASE_URL . 'index.php?url=checkout');
            return;
        }

        // Lưu đơn hàng gần nhất vào session
        $_SESSION['last_order_code'] = $orderCode;
        $_SESSION['recent_orders'] = array_unique(array_merge($_SESSION['recent_orders'] ?? [], [$orderCode]));
        $_SESSION['last_order_data'] = [
            'order_code' => $orderCode,
            'final_amount' => $finalTotal,
            'payment_method' => $paymentMethod
        ];

        // Dọn dẹp mã khuyến mại sau khi đã trừ thành công trong createOrder
        unset($_SESSION['applied_coupon'], $_SESSION['discount_amount'], $_SESSION['coupon_code'], $_SESSION['coupon_message']);

        // Dọn dẹp phiên mua hàng sau khi tạo đơn thành công
        if ($isBuyNow) {
            // Đặt mua ngay thành công: chỉ xóa phiên mua ngay, giữ nguyên giỏ hàng cũ của khách
            unset($_SESSION['is_buy_now'], $_SESSION['buy_now_item']);
        } else {
            // Shopee logic: CHỈ XÓA CÁC SẢN PHẨM ĐÃ ĐƯỢC CHỌN VÀ ĐẶT HÀNG, giữ nguyên các sản phẩm khác!
            foreach ($checkoutKeys as $k) {
                unset($_SESSION['cart'][$k]);
            }
            if (!empty($_SESSION['user']['id'])) {
                require_once __DIR__ . '/../models/Cart.php';
                $cartModel = new Cart();
                $cartModel->saveCart((int)$_SESSION['user']['id'], $_SESSION['cart'] ?? []);
            }
        }
        unset($_SESSION['discount_amount']);
        unset($_SESSION['coupon_code']);

        // Chuyển hướng theo phương thức thanh toán
        if ($paymentMethod === 'sepay') {
            require_once __DIR__ . '/../services/PaymentService.php';
            $paymentService = new PaymentService();
            $order = $orderModel->getByCode($orderCode);
            $sepayMethod = trim((string)($_POST['sepay_method'] ?? 'BANK_TRANSFER'));

            try {
                $checkout = $paymentService->start($order, $sepayMethod);
            } catch (Throwable $e) {
                error_log('Không tạo được thanh toán SePay: ' . $e->getMessage());
                // Fallback mô phỏng nếu chưa cấu hình Merchant Key
                $txnRef = date('ymdHis') . random_int(1000, 9999);
                $checkout = [
                    'action' => SepayGateway::getCheckoutUrl(), // [SỬA LỖI] checkoutUrl() không phải hàm static -> Fatal Error
                    'fields' => [
                        'order_invoice_number' => $txnRef,
                        'order_amount' => (string)(int)$finalTotal,
                        'order_description' => 'Thanh toan don ' . $orderCode,
                        'merchant' => 'DEMO_MERCHANT',
                    ]
                ];
            }

            $this->render('payment/redirect', [
                'pageTitle' => 'Chuyển sang Cổng SePay - NEXVORA',
                'checkout' => $checkout,
                'orderCode' => $orderCode,
                'amount' => (int)$finalTotal,
                'isSandbox' => SepayGateway::env() === 'sandbox'
            ]);
            return;
        } else {
            // Thanh toán khi nhận hàng (Ship COD)
            $this->redirect(BASE_URL . 'index.php?url=order/success&code=' . $orderCode);
        }
    }

    public function paymentQr(): void {
        $orderCode = $_GET['code'] ?? $_SESSION['last_order_code'] ?? '';
        if (!empty($orderCode)) {
            $this->redirect(BASE_URL . 'index.php?url=order/track&code=' . urlencode($orderCode));
        } else {
            $this->redirect(BASE_URL);
        }
    }

    public function paymentBank(): void {
        $orderCode = $_GET['code'] ?? $_SESSION['last_order_code'] ?? '';
        if (!empty($orderCode)) {
            $this->redirect(BASE_URL . 'index.php?url=order/track&code=' . urlencode($orderCode));
        } else {
            $this->redirect(BASE_URL);
        }
    }

    public function confirmPayment(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL);
            return;
        }

        $this->requireCsrf();

        $orderCode = trim($_POST['order_code'] ?? '');
        $senderBank = trim($_POST['sender_bank'] ?? '');
        $transactionId = trim($_POST['transaction_id'] ?? '');

        if (empty($orderCode)) {
            $this->redirect(BASE_URL);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);
        if (!$order) {
            $this->redirect(BASE_URL);
            return;
        }

        // Quyền thao tác: Chỉ chủ đơn hàng, khách vừa đặt đơn trong phiên này hoặc Admin
        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        $isOwner = false;
        if (!empty($currentUser['id']) && (int)$order['user_id'] === (int)$currentUser['id']) {
            $isOwner = true;
        } elseif (!empty($_SESSION['admin_user'])) {
            $isOwner = true;
        } elseif (!empty($_SESSION['recent_orders']) && in_array($orderCode, (array)$_SESSION['recent_orders'], true)) {
            $isOwner = true;
        } elseif (!empty($_SESSION['last_order_code']) && $_SESSION['last_order_code'] === $orderCode) {
            $isOwner = true;
        }

        if (!$isOwner) {
            http_response_code(403);
            die('Lỗi bảo mật 403: Bạn không có quyền thao tác gửi thông tin thanh toán cho đơn hàng này!');
        }

        // Không tự ý chuyển paid từ client; lưu thông tin để Admin đối soát thủ công hoặc đợi Webhook
        $note = "\n[Khách báo chuyển tiền " . date('d/m/Y H:i') . "]: Ngân hàng: {$senderBank} | Mã GD: {$transactionId}";
        $stmt = $orderModel->getDb()->prepare("UPDATE orders SET bank_transaction_id = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?");
        $stmt->execute([$transactionId, $note, $order['id']]);

        $_SESSION['flash_user_order_msg'] = "Đã gửi thông tin thanh toán cho đơn #{$orderCode}! Shop sẽ kiểm tra và xác nhận đơn của bạn sớm nhất.";
        $this->redirect(BASE_URL . 'index.php?url=order/success&code=' . urlencode($orderCode));
    }

    public function checkStatus(): void {
        $orderCode = $_GET['code'] ?? '';
        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);

        $this->json([
            'order_code' => $orderCode,
            'payment_status' => $order['payment_status'] ?? 'pending',
            'order_status' => $order['order_status'] ?? 'pending',
            'is_paid' => ($order['payment_status'] ?? '') === 'paid'
        ]);
    }

    /**
     * Giả lập giao dịch thanh toán thành công (Chỉ dành cho Admin / Demo đồ án)
     */
    public function simulatePaid(): void {
        // Ràng buộc bảo mật: Chỉ cho phép Quản trị viên (Admin)
        if (empty($_SESSION['admin_user'])) {
            $this->json(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
            return;
        }

        $orderCode = $_GET['code'] ?? $_POST['code'] ?? '';
        if (empty($orderCode)) {
            $this->json(['success' => false, 'message' => 'Thiếu mã đơn hàng'], 400);
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);
        if (!$order) {
            $this->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng'], 404);
            return;
        }

        if ($order['order_status'] === 'cancelled') {
            $this->json(['success' => false, 'message' => 'Đơn hàng đã bị hủy, không thể kích hoạt thanh toán!'], 400);
            return;
        }

        $txId = 'MB_' . strtoupper(substr(md5(uniqid()), 0, 10));
        $res = $orderModel->updatePaymentStatus($orderCode, 'paid', $txId);

        $this->json([
            'success' => $res,
            'message' => 'Tài khoản chủ đã nhận được tiền đơn hàng ' . $orderCode,
            'transaction_id' => $txId,
            'order_code' => $orderCode
        ]);
    }

    /**
     * Giả lập thanh toán SePay thành công (Chuyên dùng cho Demo đồ án & Sandbox testing)
     */
    public function sepaySimulate(): void {
        require_once __DIR__ . '/../models/Payment.php';
        $ref = (string)($_GET['ref'] ?? $_POST['ref'] ?? '');
        if (empty($ref)) {
            $this->redirect(BASE_URL);
            return;
        }

        $paymentModel = new Payment();
        $orderModel = new Order();
        $payment = $paymentModel->findByTxnRef($ref);
        if (!$payment) {
            $this->redirect(BASE_URL);
            return;
        }

        $order = $orderModel->getByCode($payment['order_code']);
        if (!$order) {
            $this->redirect(BASE_URL);
            return;
        }

        // [CHỐT BẢO MẬT] Chống khách hàng tự ý giả lập thanh toán đơn của mình
        $isProduction = (defined('APP_ENV') && APP_ENV === 'production');
        $isAdmin = !empty($_SESSION['admin_user']);
        if ($isProduction || SepayGateway::env() !== 'sandbox' || !$isAdmin || !Csrf::verify($_GET['csrf_token'] ?? $_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $_SESSION['flash_err'] = 'Tính năng giả lập thanh toán bị khóa!';
            $this->redirect(BASE_URL);
            return;
        }

        if ($order['order_status'] === 'cancelled') {
            $_SESSION['flash_user_order_err'] = 'Đơn hàng này đã bị hủy, không thể kích hoạt thanh toán!';
            $this->redirect(BASE_URL . 'index.php?url=payment/sepay/error&ref=' . urlencode($ref));
            return;
        }

        // Tạo mã giao dịch giả lập chuẩn SePay
        $simulatedTxId = 'SEPAY_SIM_' . strtoupper(bin2hex(random_bytes(5)));
        $mockResponse = [
            'simulated' => true,
            'source' => 'NEXVORA SePay Sandbox Simulator',
            'amount' => (int)$payment['amount'],
            'order_status' => 'CAPTURED',
            'transaction_id' => $simulatedTxId,
            'paid_at' => date('Y-m-d H:i:s')
        ];

        $paymentModel->markSuccess((int)$payment['id'], $simulatedTxId, 'sepay', 'CAPTURED', $mockResponse);
        $orderModel->updatePaymentStatus($payment['order_code'], 'paid', $simulatedTxId);

        // Chuyển thẳng về trang kết quả thành công của SePay
        $this->redirect(BASE_URL . 'index.php?url=payment/sepay/success&ref=' . urlencode($ref));
    }

    /**
     * Khách hàng được SePay redirect về success_url
     * Tự động gọi API SePay đối soát nếu chạy localhost (Kế thừa từ Base Shop)
     */
    public function sepaySuccess(): void {
        require_once __DIR__ . '/../models/Payment.php';
        $ref = (string)($_GET['ref'] ?? '');

        $paymentModel = new Payment();
        $orderModel = new Order();

        $payment = $ref !== '' ? $paymentModel->findByTxnRef($ref) : null;
        $order = $payment ? $orderModel->getByCode($payment['order_code']) : null;

        $isSuccess = ($payment && $payment['status'] === Payment::STATUS_SUCCESS);

        // Đối soát tự động (Reconcile on Return) khi chạy Localhost
        if ($payment && $payment['status'] === Payment::STATUS_PENDING && defined('SEPAY_RECONCILE_ON_RETURN') && SEPAY_RECONCILE_ON_RETURN) {
            try {
                $response = SepayGateway::retrieveOrder($payment['txn_ref']);
                $sepayOrder = SepayGateway::orderFromResponse($response);
                $status = (string)($sepayOrder['order_status'] ?? '');
                $amount = SepayGateway::parseAmount($sepayOrder['order_amount'] ?? null);

                if ($status === SepayGateway::PAID_ORDER_STATUS && $amount === (int)$payment['amount']) {
                    $paymentModel->markSuccess((int)$payment['id'], (string)($sepayOrder['id'] ?? ''), 'sepay', $status, $response);
                    $orderModel->updatePaymentStatus($payment['order_code'], 'paid', (string)($sepayOrder['id'] ?? 'SEPAY_' . $ref));
                    $isSuccess = true;
                    $order = $orderModel->getByCode($payment['order_code']);
                }
            } catch (Throwable $e) {
                // Tiếp tục hiển thị trạng thái chờ xác nhận
            }
        }

        $this->render('checkout/sepay_result', [
            'pageTitle' => $isSuccess ? 'Thanh toán SePay thành công' : 'Kết quả thanh toán SePay',
            'isSuccess' => $isSuccess,
            'type' => 'success',
            'message' => $isSuccess 
                ? 'Hệ thống SePay đã xác nhận thanh toán thành công cho đơn hàng của bạn!' 
                : 'Đơn hàng đang chờ hệ thống SePay xác nhận tự động (IPN). Trạng thái sẽ được cập nhật trong ít phút.',
            'order' => $order,
            'payment' => $payment
        ]);
    }

    public function sepayCancel(): void {
        require_once __DIR__ . '/../models/Payment.php';
        $ref = (string)($_GET['ref'] ?? '');
        $paymentModel = new Payment();
        $orderModel = new Order();
        $payment = $ref !== '' ? $paymentModel->findByTxnRef($ref) : null;
        $order = $payment ? $orderModel->getByCode($payment['order_code']) : null;

        $this->render('checkout/sepay_result', [
            'pageTitle' => 'Đã hủy thanh toán SePay',
            'isSuccess' => false,
            'type' => 'cancel',
            'message' => 'Bạn đã hủy quá trình thanh toán trên cổng SePay. Đơn hàng của bạn vẫn được lưu giữ và bạn có thể thanh toán lại bất kỳ lúc nào.',
            'order' => $order,
            'payment' => $payment
        ]);
    }

    public function sepayError(): void {
        require_once __DIR__ . '/../models/Payment.php';
        $ref = (string)($_GET['ref'] ?? '');
        $paymentModel = new Payment();
        $orderModel = new Order();
        $payment = $ref !== '' ? $paymentModel->findByTxnRef($ref) : null;
        $order = $payment ? $orderModel->getByCode($payment['order_code']) : null;

        $this->render('checkout/sepay_result', [
            'pageTitle' => 'Thanh toán SePay không thành công',
            'isSuccess' => false,
            'type' => 'error',
            'message' => 'Giao dịch thanh toán SePay không thành công hoặc đã bị từ chối. Vui lòng kiểm tra lại số dư hoặc thử lại.',
            'order' => $order,
            'payment' => $payment
        ]);
    }

    /**
     * Khách hàng đổi phương thức thanh toán cho đơn hàng chưa thanh toán
     */
    public function changePaymentMethod(): void {
        $this->requireUser();
        $orderCode = trim($_POST['order_code'] ?? $_GET['code'] ?? '');
        $newMethod = trim($_POST['new_method'] ?? $_GET['method'] ?? '');

        if (empty($orderCode) || empty($newMethod)) {
            $this->redirect(BASE_URL);
        }

        $validMethods = ['cod', 'sepay'];
        if (!in_array($newMethod, $validMethods)) {
            $this->redirect(BASE_URL . 'index.php?url=order/track&q=' . urlencode($orderCode));
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);

        if (!$order) {
            $this->redirect(BASE_URL);
        }

        // Quyền thao tác
        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        if (!empty($currentUser['id']) && (int)$order['user_id'] !== (int)$currentUser['id'] && empty($_SESSION['admin_user'])) {
            $this->redirect(BASE_URL);
        }

        // Chỉ cho phép đổi khi đơn hàng chưa thanh toán
        if ($order['payment_status'] === 'paid' || in_array($order['order_status'], ['completed', 'cancelled'])) {
            $_SESSION['flash_user_order_err'] = 'Đơn hàng này đã thanh toán hoặc đã hoàn tất, không thể thay đổi phương thức!';
            $this->redirect(BASE_URL . 'index.php?url=order/track&q=' . urlencode($orderCode));
        }

        // Cập nhật phương thức thanh toán
        $stmt = $orderModel->getDb()->prepare("UPDATE orders SET payment_method = ? WHERE id = ?");
        $stmt->execute([$newMethod, $order['id']]);

        // Điều hướng tương ứng với phương thức mới
        if ($newMethod === 'cod') {
            $_SESSION['flash_user_order_msg'] = 'Đã chuyển đổi phương thức thành công sang "Thanh toán khi nhận hàng (Ship COD)" cho đơn #' . $orderCode . '! Shop sẽ sớm giao hàng đến bạn.';
            $this->redirect(BASE_URL . 'index.php?url=order/success&code=' . urlencode($orderCode));
        } elseif ($newMethod === 'sepay') {
            require_once __DIR__ . '/../services/PaymentService.php';
            $paymentService = new PaymentService();
            $sepayMethod = trim((string)($_POST['sepay_method'] ?? 'BANK_TRANSFER'));

            try {
                $checkoutData = $paymentService->start($order, $sepayMethod);
            } catch (Throwable $e) {
                error_log('Không tạo được thanh toán SePay: ' . $e->getMessage());
                $txnRef = date('ymdHis') . random_int(1000, 9999);
                $checkoutData = [
                    'action' => SepayGateway::getCheckoutUrl(), // [SỬA LỖI] checkoutUrl() không phải hàm static -> Fatal Error
                    'fields' => [
                        'order_invoice_number' => $txnRef,
                        'order_amount' => (string)(int)$order['final_amount'],
                        'order_description' => 'Thanh toan don ' . $orderCode,
                        'merchant' => 'DEMO_MERCHANT',
                    ]
                ];
            }

            $this->render('payment/redirect', [
                'pageTitle' => 'Chuyển sang Cổng SePay - NEXVORA',
                'checkout' => $checkoutData,
                'orderCode' => $orderCode,
                'amount' => $order['final_amount'],
                'isSandbox' => SepayGateway::env() === 'sandbox'
            ]);
            return;
        }
    }

    /**
     * Khách hàng muốn đổi sản phẩm: Hủy đơn tạm tính, khôi phục sản phẩm vào giỏ hàng và chuyển về trang giỏ hàng
     */
    public function rollbackToCart(): void {
        $this->requireUser();
        $orderCode = trim($_POST['order_code'] ?? $_GET['code'] ?? '');
        if (empty($orderCode)) {
            $this->redirect(BASE_URL . 'index.php?url=cart');
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);

        if (!$order) {
            $this->redirect(BASE_URL . 'index.php?url=cart');
        }

        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        if (!empty($currentUser['id']) && (int)$order['user_id'] !== (int)$currentUser['id'] && empty($_SESSION['admin_user'])) {
            $this->redirect(BASE_URL . 'index.php?url=cart');
        }

        // Chỉ cho phép hoàn về giỏ khi đơn chưa thanh toán
        if ($order['payment_status'] === 'paid' || in_array($order['order_status'], ['shipping', 'completed'])) {
            $_SESSION['cart_error'] = 'Đơn hàng này đã thanh toán hoặc đang được giao, không thể hoàn về giỏ!';
            $this->redirect(BASE_URL . 'index.php?url=order/track&q=' . urlencode($orderCode));
        }

        // Khôi phục các sản phẩm trong đơn vào $_SESSION['cart']
        require_once __DIR__ . '/../models/Product.php';
        $productModel = new Product();
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (!empty($order['items'])) {
            foreach ($order['items'] as $it) {
                $p = $productModel->getById((int)$it['product_id']);
                $color = '';
                $cleanName = $it['product_name'];
                if (preg_match('/\((.+)\)$/', $it['product_name'], $m)) {
                    $color = trim($m[1]);
                    $cleanName = trim(preg_replace('/\((.+)\)$/', '', $it['product_name']));
                }

                $cartKey = $color ? ($it['product_id'] . '_' . md5($color)) : (string)$it['product_id'];
                $_SESSION['cart'][$cartKey] = [
                    'id' => (int)$it['product_id'],
                    'name' => $p ? $p['name'] : $cleanName,
                    'color' => $color,
                    'slug' => $p ? $p['slug'] : '',
                    'price' => (float)$it['price'],
                    'image' => $p ? $p['image'] : $it['product_image'],
                    'quantity' => (int)$it['quantity'],
                    'category_name' => $p['category_name'] ?? 'Sản phẩm'
                ];
            }
        }

        // Hủy đơn tạm tính, hoàn tồn kho vào database và ẩn đơn khỏi danh sách cá nhân
        $orderModel->cancelOrder((int)$order['id'], 'Khách quay lại sửa giỏ hàng / đổi sản phẩm');
        if (!empty($order['user_id'])) {
            $orderModel->hideOrderByUser((int)$order['id'], (int)$order['user_id']);
        }

        unset($_SESSION['is_buy_now'], $_SESSION['buy_now_item']);
        $_SESSION['flash_success'] = 'Đã khôi phục toàn bộ sản phẩm của đơn #' . $orderCode . ' vào giỏ hàng. Bạn có thể thoải mái thêm, bớt hoặc đổi sang sản phẩm khác!';
        $this->redirect(BASE_URL . 'index.php?url=cart');
    }

    /**
     * Khách hàng muốn sửa lại thông tin nhận hàng / đổi voucher: Hủy đơn tạm tính, khôi phục giỏ và chuyển về checkout
     */
    public function rollbackToCheckout(): void {
        $this->requireUser();
        $orderCode = trim($_POST['order_code'] ?? $_GET['code'] ?? '');
        if (empty($orderCode)) {
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        $orderModel = new Order();
        $order = $orderModel->getByCode($orderCode);

        if (!$order) {
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        $currentUser = $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null;
        if (!empty($currentUser['id']) && (int)$order['user_id'] !== (int)$currentUser['id'] && empty($_SESSION['admin_user'])) {
            $this->redirect(BASE_URL . 'index.php?url=checkout');
        }

        if ($order['payment_status'] === 'paid' || in_array($order['order_status'], ['shipping', 'completed'])) {
            $this->redirect(BASE_URL . 'index.php?url=order/track&q=' . urlencode($orderCode));
        }

        // Khôi phục các sản phẩm trong đơn vào $_SESSION['cart']
        require_once __DIR__ . '/../models/Product.php';
        $productModel = new Product();
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (!empty($order['items'])) {
            foreach ($order['items'] as $it) {
                $p = $productModel->getById((int)$it['product_id']);
                $color = '';
                $cleanName = $it['product_name'];
                if (preg_match('/\((.+)\)$/', $it['product_name'], $m)) {
                    $color = trim($m[1]);
                    $cleanName = trim(preg_replace('/\((.+)\)$/', '', $it['product_name']));
                }

                $cartKey = $color ? ($it['product_id'] . '_' . md5($color)) : (string)$it['product_id'];
                $_SESSION['cart'][$cartKey] = [
                    'id' => (int)$it['product_id'],
                    'name' => $p ? $p['name'] : $cleanName,
                    'color' => $color,
                    'slug' => $p ? $p['slug'] : '',
                    'price' => (float)$it['price'],
                    'image' => $p ? $p['image'] : $it['product_image'],
                    'quantity' => (int)$it['quantity'],
                    'category_name' => $p['category_name'] ?? 'Sản phẩm'
                ];
            }
        }

        // Hủy đơn tạm tính và ẩn
        $orderModel->cancelOrder((int)$order['id'], 'Khách quay lại sửa thông tin nhận hàng');
        if (!empty($order['user_id'])) {
            $orderModel->hideOrderByUser((int)$order['id'], (int)$order['user_id']);
        }

        unset($_SESSION['is_buy_now'], $_SESSION['buy_now_item']);
        $this->redirect(BASE_URL . 'index.php?url=checkout');
    }
}
