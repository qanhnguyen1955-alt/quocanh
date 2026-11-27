<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center">
            <i class="fa-solid fa-shield-halved text-emerald-400 mr-3"></i> Thanh Toán Đơn Hàng An Toàn
        </h2>
        <p class="text-xs text-slate-400 mt-1">Hoàn tất thông tin nhận hàng nhanh và chọn phương thức thanh toán</p>
    </div>

    <?php if (!empty($_SESSION['checkout_error'])): ?>
        <div class="p-4 rounded-2xl bg-red-950/60 border border-red-500/40 text-red-300 text-xs flex items-center">
            <i class="fa-solid fa-triangle-exclamation text-red-400 mr-2 text-sm"></i> <?= $_SESSION['checkout_error'] ?>
        </div>
        <?php unset($_SESSION['checkout_error']); ?>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>index.php?url=checkout/process" method="POST">
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Delivery Form & Payment Method Selection -->
            <div class="lg:col-span-2 space-y-6">
                <!-- 1. Customer Delivery Details -->
                <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-4">
                    <div class="border-b border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center">
                            <i class="fa-solid fa-location-dot text-rose-400 mr-2"></i> 1. Thông Tin Nhận Hàng
                        </h3>
                        <?php if (!empty($currentUser)): ?>
                            <span class="inline-flex items-center gap-1.5 bg-emerald-950 text-emerald-300 border border-emerald-500/40 text-[11px] px-2.5 py-1 rounded-xl font-semibold self-start sm:self-auto">
                                <i class="fa-solid fa-circle-check text-emerald-400"></i> Đã tự động điền từ tài khoản
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($currentUser)): ?>
                        <div class="bg-cyan-950/30 border border-cyan-500/30 rounded-2xl p-3.5 text-xs text-slate-300 flex items-start gap-3">
                            <i class="fa-solid fa-wand-magic-sparkles text-cyan-400 text-sm mt-0.5 shrink-0"></i>
                            <div class="flex-1 text-[11px] leading-relaxed">
                                Đã tự động điền thông tin của <strong class="text-cyan-300"><?= htmlspecialchars($currentUser['fullname'] ?? '') ?></strong>. Bạn có thể thoải mái chỉnh sửa lại Họ tên, SĐT hoặc Địa chỉ nhận hàng bên dưới nếu muốn nhận hàng tại nơi khác!
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Họ và tên người nhận <span class="text-red-400">*</span></label>
                            <input type="text" name="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? ($currentUser['fullname'] ?? '')) ?>" placeholder="Ví dụ: Nguyễn Văn A" required class="w-full text-xs p-3 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Số điện thoại liên hệ <span class="text-red-400">*</span></label>
                            <input type="tel" name="customer_phone" value="<?= htmlspecialchars($_POST['customer_phone'] ?? ($currentUser['phone'] ?? '')) ?>" placeholder="Ví dụ: 0988888888" required class="w-full text-xs p-3 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-300">Địa chỉ Email (Nhận hóa đơn điện tử & tra cứu đơn) <span class="text-red-400">*</span></label>
                                <span id="emailValidationBadge" class="text-[11px] hidden font-semibold transition-all"></span>
                            </div>
                            <div class="relative">
                                <input type="email" id="customer_email" name="customer_email" 
                                       value="<?= htmlspecialchars($_POST['customer_email'] ?? ($currentUser['email'] ?? '')) ?>" 
                                       placeholder="email@gmail.com" 
                                       required
                                       oninput="checkEmailRealtime(this)" 
                                       class="w-full text-xs p-3 pr-10 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500 transition">
                                <span id="emailStatusIcon" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs hidden"></span>
                            </div>
                            <p id="emailHelpText" class="text-[11px] text-slate-500 mt-1">Hệ thống gửi mã tra cứu tiến trình đơn hàng và biên lai điện tử tức thì qua email này.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-300">Địa chỉ nhận hàng chi tiết <span class="text-red-400">*</span></label>
                                <span class="text-[10px] text-cyan-400 font-semibold"><i class="fa-solid fa-pen mr-1"></i> Có thể đổi địa chỉ khác</span>
                            </div>
                            <textarea name="customer_address" rows="2" placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố..." required class="w-full text-xs p-3 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500"><?= htmlspecialchars($_POST['customer_address'] ?? ($currentUser['address'] ?? '')) ?></textarea>
                            <?php if (!empty($currentUser)): ?>
                                <label class="flex items-center gap-2 cursor-pointer mt-2 text-[11px] text-slate-400 hover:text-slate-200 select-none">
                                    <input type="checkbox" name="save_address_to_profile" value="1" class="rounded border-slate-700 bg-slate-950 text-cyan-500 focus:ring-cyan-500">
                                    <span>Lưu địa chỉ mới này vào hồ sơ tài khoản của tôi cho các lần mua sau</span>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Ghi chú giao hàng</label>
                            <input type="text" name="notes" value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>" placeholder="Ví dụ: Giao giờ hành chính, gọi trước khi giao 15 phút..." class="w-full text-xs p-3 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        </div>
                    </div>
                </div>

                <!-- 2. Payment Method Selector -->
                <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-4">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center">
                        <i class="fa-solid fa-credit-card text-cyan-400 mr-2"></i> 2. Phương Thức Thanh Toán
                    </h3>

                    <div class="space-y-3">
                        <!-- Cổng SePay Trực Tuyến -->
                        <label class="block relative p-4 rounded-2xl border border-slate-700 bg-slate-950/60 cursor-pointer hover:border-cyan-400 transition has-[:checked]:border-cyan-400 has-[:checked]:bg-cyan-950/20">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_method" value="sepay" checked class="text-cyan-400 focus:ring-cyan-400 bg-slate-900 border-slate-700">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div class="font-bold text-xs text-white flex items-center">
                                            <i class="fa-solid fa-bolt text-amber-400 mr-2 text-sm"></i> Cổng SePay Trực Tuyến (VietQR Tự Động, Thẻ &amp; Napas 24/7)
                                        </div>
                                        <span class="text-[10px] bg-gradient-to-r from-cyan-500 to-primary-600 text-white px-2.5 py-0.5 rounded-full font-bold shadow-sm">Xác nhận tự động</span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">Quét mã QR qua cổng SePay, hệ thống tự động xác nhận đơn hàng thành công ngay trong 3 giây.</p>
                                </div>
                            </div>
                            <input type="hidden" name="sepay_method" value="BANK_TRANSFER">
                        </label>

                        <!-- Thanh toán khi nhận hàng (Ship COD) -->
                        <label class="block relative p-4 rounded-2xl border border-slate-700 bg-slate-950/60 cursor-pointer hover:border-emerald-400 transition has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-950/20">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_method" value="cod" class="text-emerald-400 focus:ring-emerald-400 bg-slate-900 border-slate-700">
                                <div class="flex-1">
                                    <div class="font-bold text-xs text-white flex items-center">
                                        <i class="fa-solid fa-money-bill-wave text-emerald-400 mr-2 text-sm"></i> Thanh Toán Khi Nhận Hàng (Ship COD)
                                    </div>
                                    <p class="text-[11px] text-slate-400 mt-1">Được mở hộp cắm sạc thử / nghe thử tai nghe trước khi thanh toán cho shipper.</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right: Order Items & Submit -->
            <div class="space-y-4">
                <div class="glass-card rounded-3xl p-6 space-y-4 sticky top-28">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider border-b border-slate-800 pb-3 flex items-center justify-between">
                        <span>Đơn Hàng</span>
                        <div class="flex items-center gap-2">
                            <?php if (!empty($_SESSION['is_buy_now'])): ?>
                                <span class="text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-full font-bold flex items-center gap-1">
                                    <i class="fa-solid fa-bolt text-[9px]"></i> Mua Ngay
                                </span>
                            <?php endif; ?>
                            <span class="text-xs text-slate-400"><?= count($cart) ?> món</span>
                        </div>
                    </h3>

                    <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                        <?php foreach ($cart as $item): ?>
                            <div class="flex items-center justify-between text-xs py-2 border-b border-slate-800/80">
                                <div class="flex items-center space-x-2.5">
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="" class="w-10 h-10 object-contain rounded-lg bg-slate-950 p-1 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-slate-200 line-clamp-1 w-36"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-[10px] text-slate-400">
                                            <?php if (!empty($item['color'])): ?>
                                                <span class="text-cyan-300 font-medium mr-1">[<?= htmlspecialchars($item['color']) ?>]</span>
                                            <?php endif; ?>
                                            SL: <?= $item['quantity'] ?> x <?= number_format($item['price'], 0, ',', '.') ?>đ
                                        </div>
                                    </div>
                                </div>
                                <span class="font-bold text-cyan-400"><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- MÃ KHUYẾN MẠI / VOUCHER (CHỌN TỪ DANH SÁCH DATABASE HOẶC NHẬP TAY) -->
                    <div class="pt-3 border-t border-slate-800 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                <i class="fa-solid fa-ticket text-amber-400"></i> Mã Khuyến Mại
                            </span>
                            <button type="button" onclick="openCouponModal()" class="text-[11px] text-cyan-400 hover:text-cyan-300 font-semibold flex items-center gap-1 transition">
                                <i class="fa-solid fa-gift"></i> Xem tất cả (<?= count($availableCoupons ?? []) ?>)
                            </button>
                        </div>

                        <!-- 1. Dropdown chọn nhanh mã ưu đãi: TỰ ĐỘNG ÁP DỤNG NGAY KHI CHỌN -->
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 font-medium">Chọn mã để tự động áp dụng ngay:</label>
                            <div class="relative">
                                <select id="quickCouponSelect" onchange="if(this.value) autoApplyCoupon(this.value);" class="w-full text-xs py-2.5 px-3 rounded-xl border border-amber-500/40 bg-slate-950 text-slate-100 font-medium focus:outline-none focus:border-amber-400 cursor-pointer">
                                    <option value="">🎁 -- Chọn mã khuyến mại (Có <?= count($availableCoupons ?? []) ?> mã) --</option>
                                    <?php if (!empty($availableCoupons)): ?>
                                        <?php foreach ($availableCoupons as $ac): 
                                            $isSelected = (!empty($appliedCoupon) && $appliedCoupon['code'] === $ac['code']);
                                            $isEligible = !empty($ac['is_eligible']);
                                            $discountTxt = ($ac['discount_type'] === 'percent') ? '-' . (int)$ac['discount_value'] . '%' : '-' . number_format($ac['discount_value'], 0, ',', '.') . 'đ';
                                        ?>
                                            <option value="<?= htmlspecialchars($ac['code']) ?>" <?= $isSelected ? 'selected' : '' ?> <?= !$isEligible ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($ac['code']) ?>: <?= $discountTxt ?> (Đơn từ <?= number_format($ac['min_order_amount'], 0, ',', '.') ?>đ) <?= !$isEligible ? ' [Chưa đủ điều kiện]' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Thẻ hiển thị mã đang áp dụng -->
                        <div id="appliedCouponBox" class="<?= empty($appliedCoupon) ? 'hidden' : '' ?> bg-gradient-to-r from-emerald-950/70 to-slate-900 border border-emerald-500/50 rounded-2xl p-2.5 flex items-center justify-between shadow-sm animate-in fade-in duration-200">
                            <div class="flex items-center space-x-2">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0 text-xs">
                                    <i class="fa-solid fa-tags"></i>
                                </div>
                                <div>
                                    <div class="font-extrabold text-xs text-white tracking-wider font-mono flex items-center gap-1.5">
                                        <span id="appliedCouponCode"><?= htmlspecialchars($appliedCoupon['code'] ?? '') ?></span>
                                        <span class="text-[9px] text-emerald-400 bg-emerald-500/20 px-1.5 py-0.5 rounded font-bold">Đã áp dụng</span>
                                    </div>
                                    <div id="appliedCouponDiscount" class="text-[10px] text-emerald-300 font-medium">
                                        -<?= number_format($discount, 0, ',', '.') ?>đ
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="removeCouponDirectly()" class="text-[11px] text-rose-400 hover:text-rose-300 font-bold px-2.5 py-1 rounded-lg hover:bg-rose-500/10 transition flex items-center gap-1" title="Hủy mã này">
                                <i class="fa-solid fa-xmark"></i> Hủy
                            </button>
                        </div>

                        <!-- 3. Ô nhập mã thủ công nếu khách có mã riêng -->
                        <div id="manualCouponBox" class="<?= !empty($appliedCoupon) ? 'hidden' : '' ?> flex gap-2">
                            <input type="text" id="couponCodeInput" placeholder="Hoặc nhập mã riêng..." class="flex-1 text-xs px-3 py-2 rounded-xl border border-slate-700 bg-slate-950 text-slate-100 uppercase font-mono tracking-wider placeholder:normal-case placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-amber-400">
                            <button type="button" onclick="autoApplyCoupon(document.getElementById('couponCodeInput').value)" class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs px-3 py-2 rounded-xl transition shadow-md shadow-amber-500/20 shrink-0 flex items-center gap-1">
                                <span>Áp dụng</span>
                            </button>
                        </div>

                        <!-- Thông báo / Toast trạng thái áp dụng mã -->
                        <div id="couponStatusMessage" class="hidden text-[11px] px-3 py-2 rounded-xl flex items-center gap-2 animate-in fade-in duration-200"></div>

                        <?php if (!empty($_SESSION['coupon_message'])): ?>
                            <div class="text-[11px] text-emerald-300 bg-emerald-950/60 border border-emerald-500/40 px-3 py-1.5 rounded-xl flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-400"></i> <?= htmlspecialchars($_SESSION['coupon_message']) ?>
                            </div>
                            <?php unset($_SESSION['coupon_message']); ?>
                        <?php endif; ?>

                        <!-- Nút mở modal danh sách chi tiết -->
                        <button type="button" onclick="openCouponModal()" class="w-full py-2 px-3 rounded-xl border border-dashed border-amber-500/40 bg-amber-950/20 hover:bg-amber-950/40 text-amber-300 text-xs font-semibold flex items-center justify-between transition">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-ticket-simple text-amber-400"></i> Xem danh sách mã giảm giá
                            </span>
                            <span class="text-[10px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded font-mono font-bold">
                                <?= count($availableCoupons ?? []) ?> mã
                            </span>
                        </button>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-slate-800 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Tạm tính:</span>
                            <span id="summarySubtotal" class="text-slate-200 font-bold"><?= number_format($subtotal, 0, ',', '.') ?>đ</span>
                        </div>
                        <div id="summaryDiscountRow" class="flex justify-between text-emerald-400 font-bold <?= ($discount > 0) ? '' : 'hidden' ?>">
                            <span>Giảm giá (Voucher):</span>
                            <span id="summaryDiscountValue">-<?= number_format($discount, 0, ',', '.') ?>đ</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Phí vận chuyển:</span>
                            <span class="text-slate-200 font-medium"><?= ($shipping == 0) ? '<span class="text-emerald-400 font-bold"><i class="fa-solid fa-truck-fast mr-1"></i> Miễn phí (0đ)</span>' : number_format($shipping, 0, ',', '.') . 'đ' ?></span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-between items-baseline">
                        <span class="text-sm font-bold text-white">Tổng cộng:</span>
                        <span id="summaryFinalTotal" class="text-2xl font-black text-cyan-400"><?= number_format($finalTotal, 0, ',', '.') ?>đ</span>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-primary-600 via-indigo-600 to-cyan-500 hover:from-primary-500 hover:to-cyan-400 text-white font-extrabold text-xs py-4 px-6 rounded-2xl shadow-xl shadow-primary-600/30 transition transform hover:-translate-y-0.5 flex items-center justify-center">
                        <i class="fa-solid fa-lock mr-2 text-sm"></i> Xác Nhận Đặt Hàng
                    </button>
                    <p class="text-[10px] text-slate-500 text-center">Bằng cách đặt hàng, bạn đồng ý với các điều khoản mua sắm của NEXVORA.</p>

                    <div class="pt-2 text-center">
                        <?php if (!empty($_SESSION['is_buy_now'])): ?>
                            <a href="<?= BASE_URL ?>index.php?url=products" class="text-xs text-slate-400 hover:text-cyan-400 transition inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-arrow-left text-[10px]"></i> Quay lại chọn sản phẩm khác
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>index.php?url=cart" class="text-xs text-slate-400 hover:text-cyan-400 transition inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-cart-shopping text-[10px]"></i> Chỉnh sửa giỏ hàng
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- FORM DỰ PHÒNG ÁP DỤNG & HỦY MÃ (ĐỘC LẬP HOÀN TOÀN KHỎI FORM ĐẶT HÀNG) -->
<form id="standaloneCouponForm" action="<?= BASE_URL ?>index.php?url=checkout/apply-coupon" method="POST" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="coupon_code" id="standaloneCouponInput" value="">
</form>
<form id="standaloneRemoveCouponForm" action="<?= BASE_URL ?>index.php?url=checkout/remove-coupon" method="POST" class="hidden">
    <?= csrf_field() ?>
</form>

<!-- MODAL CHỌN MÃ KHUYẾN MẠI (DANH SÁCH TỰ ĐỘNG CẬP NHẬT TỪ DATABASE) -->
<div id="couponModal" class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-200">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Chọn Mã Khuyến Mại</h3>
                    <p class="text-[11px] text-slate-400">Nhấp chọn mã để tự động áp dụng ngay vào đơn hàng</p>
                </div>
            </div>
            <button type="button" onclick="closeCouponModal()" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <div class="p-5 max-h-[60vh] overflow-y-auto space-y-3">
            <?php if (empty($availableCoupons)): ?>
                <div class="py-8 text-center text-slate-500">
                    <i class="fa-solid fa-ticket-simple text-3xl mb-2 text-slate-600 block"></i>
                    Hiện chưa có mã khuyến mại nào khả dụng. Vui lòng quay lại sau!
                </div>
            <?php else: ?>
                <?php foreach ($availableCoupons as $c): 
                    $isApplied = (!empty($appliedCoupon) && $appliedCoupon['code'] === $c['code']);
                    $isEligible = !empty($c['is_eligible']);
                ?>
                    <div data-coupon-code="<?= htmlspecialchars($c['code']) ?>" 
                         data-eligible="<?= $isEligible ? '1' : '0' ?>"
                         onclick="<?= ($isEligible && !$isApplied) ? "autoApplyCoupon('" . htmlspecialchars($c['code']) . "')" : '' ?>"
                         class="rounded-2xl border p-4 transition relative flex flex-col sm:flex-row sm:items-center justify-between gap-3 <?= $isApplied ? 'border-emerald-500 bg-emerald-950/20' : ($isEligible ? 'border-slate-700 bg-slate-950/60 hover:border-amber-400/60 cursor-pointer' : 'border-slate-800 bg-slate-950/30 opacity-60') ?>">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/30 flex flex-col items-center justify-center text-amber-400 shrink-0 font-bold">
                                <?php if ($c['discount_type'] === 'percent'): ?>
                                    <span class="text-sm leading-none"><?= (int)$c['discount_value'] ?>%</span>
                                    <span class="text-[9px] uppercase tracking-tighter">GIẢM</span>
                                <?php else: ?>
                                    <span class="text-xs leading-none"><?= (int)($c['discount_value'] / 1000) ?>K</span>
                                    <span class="text-[9px] uppercase tracking-tighter">GIẢM</span>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-xs text-white bg-slate-800 px-2 py-0.5 rounded border border-slate-700 tracking-wider">
                                        <?= htmlspecialchars($c['code']) ?>
                                    </span>
                                    <?php if ($isApplied): ?>
                                        <span class="text-[10px] text-emerald-400 font-bold"><i class="fa-solid fa-circle-check"></i> Đang dùng</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs font-bold text-slate-200">
                                    <?= htmlspecialchars($c['title']) ?>
                                </div>
                                <div class="text-[11px] text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <span><i class="fa-solid fa-basket-shopping text-[10px] text-slate-500 mr-1"></i> Đơn tối thiểu: <strong class="text-slate-300"><?= number_format($c['min_order_amount'], 0, ',', '.') ?>đ</strong></span>
                                    <?php if (!empty($c['end_date'])): ?>
                                        <span><i class="fa-solid fa-clock text-[10px] text-slate-500 mr-1"></i> HSD: <?= date('d/m/Y', strtotime($c['end_date'])) ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa-solid fa-fire text-[10px] text-amber-400 mr-1"></i> Còn <?= (int)$c['quantity'] ?> lượt</span>
                                </div>
                                <div class="pt-1">
                                    <span class="inline-flex items-center gap-1 text-[10px] bg-slate-900 border border-slate-700/80 text-cyan-300 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-crosshairs text-[9px]"></i> <?= htmlspecialchars($c['scope_text'] ?? 'Tất cả sản phẩm') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="sm:shrink-0 flex items-center justify-end coupon-action-box" onclick="event.stopPropagation()">
                            <?php if ($isApplied): ?>
                                <button type="button" class="text-xs font-bold px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 cursor-default flex items-center gap-1">
                                    <i class="fa-solid fa-check"></i> Đang Áp Dụng
                                </button>
                            <?php elseif (!empty($c['already_used'])): ?>
                                <span class="text-[10px] font-bold text-rose-400 bg-rose-950/60 border border-rose-800/80 px-2.5 py-1.5 rounded-xl text-center">
                                    <i class="fa-solid fa-ban mr-1"></i> Đã Dùng (1 lần/TK)
                                </span>
                            <?php elseif ($isEligible): ?>
                                <button type="button" onclick="autoApplyCoupon('<?= htmlspecialchars($c['code']) ?>')" class="text-xs font-bold px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md shadow-amber-500/20 flex items-center gap-1">
                                    <i class="fa-solid fa-check"></i> Áp Dụng
                                </button>
                            <?php else: ?>
                                <span class="text-[10px] text-slate-400 bg-slate-900 border border-slate-800 px-2.5 py-1.5 rounded-xl text-center" title="<?= htmlspecialchars($c['eligibility_message'] ?? '') ?>">
                                    Chưa đủ điều kiện
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="p-4 bg-slate-950/60 border-t border-slate-800 text-center">
            <button type="button" onclick="closeCouponModal()" class="text-xs text-slate-400 hover:text-white font-semibold">
                Đóng danh sách
            </button>
        </div>
    </div>
</div>

<script>
function openCouponModal() {
    const modal = document.getElementById('couponModal');
    if (modal) modal.classList.remove('hidden');
}
function closeCouponModal() {
    const modal = document.getElementById('couponModal');
    if (modal) modal.classList.add('hidden');
}
window.addEventListener('click', function(e) {
    const modal = document.getElementById('couponModal');
    if (e.target === modal) {
        closeCouponModal();
    }
});

function getCheckoutCsrfToken() {
    return (typeof window.getCsrfToken === 'function')
        ? window.getCsrfToken()
        : (document.querySelector('meta[name="csrf-token"]')?.content 
           || document.querySelector('input[name="_csrf_token"]')?.value 
           || '<?= csrf_token() ?>');
}

function autoApplyCoupon(code) {
    code = (code || '').trim();
    if (!code) {
        alert('Vui lòng chọn hoặc nhập mã khuyến mại!');
        return;
    }

    const msgEl = document.getElementById('couponStatusMessage');
    if (msgEl) {
        msgEl.className = 'text-[11px] px-3 py-2 rounded-xl flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 text-amber-300';
        msgEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Đang áp dụng mã ' + escapeHtml(code) + '...</span>';
        msgEl.classList.remove('hidden');
    }

    const csrfToken = getCheckoutCsrfToken();
    const formData = new FormData();
    formData.append('coupon_code', code);
    formData.append('_csrf_token', csrfToken);

    fetch('index.php?url=checkout/apply-coupon&ajax=1', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCouponUI(data);
            if (msgEl) {
                msgEl.className = 'text-[11px] px-3 py-2 rounded-xl flex items-center gap-2 bg-emerald-950/70 border border-emerald-500/40 text-emerald-300';
                msgEl.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-400"></i> <span>' + escapeHtml(data.message) + '</span>';
                msgEl.classList.remove('hidden');
            }
            closeCouponModal();
        } else {
            if (msgEl) {
                msgEl.className = 'text-[11px] px-3 py-2 rounded-xl flex items-center gap-2 bg-rose-950/70 border border-rose-500/40 text-rose-300';
                msgEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-400"></i> <span>' + escapeHtml(data.message) + '</span>';
                msgEl.classList.remove('hidden');
            }
        }
    })
    .catch(err => {
        // Fallback sang form submit độc lập (không kích hoạt validate form nhận hàng)
        const f = document.getElementById('standaloneCouponForm');
        document.getElementById('standaloneCouponInput').value = code;
        f.submit();
    });
}

function removeCouponDirectly() {
    const msgEl = document.getElementById('couponStatusMessage');
    const csrfToken = getCheckoutCsrfToken();
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);

    fetch('index.php?url=checkout/remove-coupon&ajax=1', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resetCouponUI(data);
            if (msgEl) {
                msgEl.className = 'text-[11px] px-3 py-2 rounded-xl flex items-center gap-2 bg-slate-800 text-slate-300 border border-slate-700';
                msgEl.innerHTML = '<i class="fa-solid fa-circle-info text-cyan-400"></i> <span>' + escapeHtml(data.message) + '</span>';
                msgEl.classList.remove('hidden');
            }
        } else {
            const f = document.getElementById('standaloneRemoveCouponForm');
            f.submit();
        }
    })
    .catch(err => {
        const f = document.getElementById('standaloneRemoveCouponForm');
        f.submit();
    });
}

function updateCouponUI(data) {
    // 1. Cập nhật thẻ hiển thị mã đang áp dụng
    const box = document.getElementById('appliedCouponBox');
    const manualBox = document.getElementById('manualCouponBox');
    const codeSpan = document.getElementById('appliedCouponCode');
    const discSpan = document.getElementById('appliedCouponDiscount');
    const selectEl = document.getElementById('quickCouponSelect');

    if (box) box.classList.remove('hidden');
    if (manualBox) manualBox.classList.add('hidden');
    if (codeSpan) codeSpan.textContent = data.code;
    if (discSpan) discSpan.textContent = '-' + data.discount_formatted;
    if (selectEl) selectEl.value = data.code;

    // 2. Cập nhật dòng Giảm giá (Voucher) và Tổng thanh toán
    const discRow = document.getElementById('summaryDiscountRow');
    const discVal = document.getElementById('summaryDiscountValue');
    const finalVal = document.getElementById('summaryFinalTotal');
    const subtotalEl = document.getElementById('summarySubtotal');

    if (discRow) discRow.classList.remove('hidden');
    if (discVal) discVal.textContent = '-' + data.discount_formatted;
    if (finalVal) finalVal.textContent = data.final_total_formatted;
    if (subtotalEl && data.subtotal_formatted) subtotalEl.textContent = data.subtotal_formatted;

    // 3. Cập nhật nút trạng thái trong modal
    const modalItems = document.querySelectorAll('[data-coupon-code]');
    modalItems.forEach(item => {
        const cCode = item.getAttribute('data-coupon-code');
        const btnBox = item.querySelector('.coupon-action-box');
        if (cCode === data.code) {
            item.className = 'rounded-2xl border p-4 transition relative flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-emerald-500 bg-emerald-950/20';
            if (btnBox) {
                btnBox.innerHTML = '<button type="button" class="text-xs font-bold px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 cursor-default flex items-center gap-1"><i class="fa-solid fa-check"></i> Đang Áp Dụng</button>';
            }
        } else if (item.getAttribute('data-eligible') === '1') {
            item.className = 'rounded-2xl border p-4 transition relative flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-slate-700 bg-slate-950/60 hover:border-amber-400/60 cursor-pointer';
            if (btnBox) {
                btnBox.innerHTML = '<button type="button" onclick="autoApplyCoupon(\'' + cCode + '\')" class="text-xs font-bold px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md shadow-amber-500/20 flex items-center gap-1"><i class="fa-solid fa-check"></i> Áp Dụng</button>';
            }
        }
    });
}

function resetCouponUI(data) {
    const box = document.getElementById('appliedCouponBox');
    const manualBox = document.getElementById('manualCouponBox');
    const selectEl = document.getElementById('quickCouponSelect');
    const discRow = document.getElementById('summaryDiscountRow');
    const finalVal = document.getElementById('summaryFinalTotal');

    if (box) box.classList.add('hidden');
    if (manualBox) manualBox.classList.remove('hidden');
    if (selectEl) selectEl.value = '';
    if (discRow) discRow.classList.add('hidden');
    if (finalVal) finalVal.textContent = data.final_total_formatted;
    const subtotalEl = document.getElementById('summarySubtotal');
    if (subtotalEl && data.subtotal_formatted) subtotalEl.textContent = data.subtotal_formatted;

    // Reset các nút trong modal
    const modalItems = document.querySelectorAll('[data-coupon-code]');
    modalItems.forEach(item => {
        const cCode = item.getAttribute('data-coupon-code');
        const isEligible = item.getAttribute('data-eligible') === '1';
        const btnBox = item.querySelector('.coupon-action-box');
        if (isEligible) {
            item.className = 'rounded-2xl border p-4 transition relative flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-slate-700 bg-slate-950/60 hover:border-amber-400/60 cursor-pointer';
            if (btnBox) {
                btnBox.innerHTML = '<button type="button" onclick="autoApplyCoupon(\'' + cCode + '\')" class="text-xs font-bold px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md shadow-amber-500/20 flex items-center gap-1"><i class="fa-solid fa-check"></i> Áp Dụng</button>';
            }
        }
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? String(text).replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
}

// KIỂM TRA EMAIL REALTIME TỨC THÌ (Theo yêu cầu)
function checkEmailRealtime(input) {
    if (!input) return;
    const val = input.value.trim();
    const badge = document.getElementById('emailValidationBadge');
    const icon = document.getElementById('emailStatusIcon');
    const help = document.getElementById('emailHelpText');
    
    if (!val) {
        if (badge) badge.classList.add('hidden');
        if (icon) icon.classList.add('hidden');
        input.classList.remove('border-emerald-500', 'border-rose-500');
        input.classList.add('border-slate-700');
        if (help) help.textContent = 'Hệ thống gửi mã tra cứu tiến trình đơn hàng và biên lai điện tử tức thì qua email này.';
        return;
    }
    
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (re.test(val)) {
        if (badge) {
            badge.className = 'text-[11px] text-emerald-400 font-semibold';
            badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Email hợp lệ';
            badge.classList.remove('hidden');
        }
        if (icon) {
            icon.className = 'absolute right-3.5 top-1/2 -translate-y-1/2 text-emerald-400 text-sm';
            icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            icon.classList.remove('hidden');
        }
        input.classList.remove('border-slate-700', 'border-rose-500');
        input.classList.add('border-emerald-500');
        if (help) help.innerHTML = '<span class="text-emerald-400">✓ Đã xác nhận định dạng email hợp lệ. Thông tin đơn hàng sẽ gửi về hòm thư này.</span>';
    } else {
        if (badge) {
            badge.className = 'text-[11px] text-rose-400 font-semibold';
            badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Email chưa đúng định dạng';
            badge.classList.remove('hidden');
        }
        if (icon) {
            icon.className = 'absolute right-3.5 top-1/2 -translate-y-1/2 text-rose-400 text-sm';
            icon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
            icon.classList.remove('hidden');
        }
        input.classList.remove('border-slate-700', 'border-emerald-500');
        input.classList.add('border-rose-500');
        if (help) help.innerHTML = '<span class="text-rose-400">Vui lòng nhập đúng cấu trúc email, ví dụ: tenban@gmail.com</span>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const emInput = document.getElementById('customer_email');
    if (emInput && emInput.value) checkEmailRealtime(emInput);
});
</script>
