<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6" id="shopeeCartApp">
    <?= csrf_field() ?>
    <!-- Header Title -->
    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <div class="flex items-center space-x-3">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white flex items-center">
                <i class="fa-solid fa-cart-shopping text-cyan-400 mr-3"></i> Giỏ Hàng Shopee
            </h1>
            <span id="badgeCartTotalItems" class="bg-primary-500/20 text-cyan-300 border border-primary-500/40 text-xs font-bold px-3 py-1 rounded-full">
                <?= count($cart) ?> mặt hàng
            </span>
        </div>
        <a href="<?= BASE_URL ?>index.php?url=phones" class="text-xs text-slate-400 hover:text-cyan-400 transition flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Tiếp tục chọn mua
        </a>
    </div>

    <!-- Thông báo Flash message nếu có -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="p-4 rounded-2xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs flex items-center justify-between">
            <div class="flex items-center"><i class="fa-solid fa-circle-check text-emerald-400 mr-2 text-sm"></i> <?= $_SESSION['flash_success'] ?></div>
            <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['cart_error'])): ?>
        <div class="p-4 rounded-2xl bg-amber-950/70 border border-amber-500/50 text-amber-200 text-xs flex items-center justify-between">
            <div class="flex items-center">
                <i class="fa-solid fa-triangle-exclamation text-amber-400 mr-2.5 text-base"></i> 
                <span><?= htmlspecialchars($_SESSION['cart_error']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

    <?php // [SỬA LỖI] Các thông báo này trước đây được ghi vào session nhưng không trang nào hiển thị ?>
    <?php foreach (['flash_cart_err', 'cart_warning', 'coupon_error'] as $__flashKey): ?>
        <?php if (!empty($_SESSION[$__flashKey])): ?>
            <div class="p-4 rounded-2xl bg-amber-950/70 border border-amber-500/50 text-amber-200 text-xs flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fa-solid fa-triangle-exclamation text-amber-400 mr-2.5 text-base"></i> 
                    <span><?= htmlspecialchars(html_entity_decode((string)$_SESSION[$__flashKey], ENT_QUOTES, 'UTF-8')) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <?php unset($_SESSION[$__flashKey]); ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($cart)): ?>
        <div class="glass-card rounded-3xl p-16 text-center space-y-5 border border-slate-800">
            <div class="w-20 h-20 rounded-3xl bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto text-slate-500 text-3xl shadow-inner">
                <i class="fa-solid fa-cart-arrow-down text-cyan-400/60"></i>
            </div>
            <h3 class="text-xl font-bold text-white">Giỏ hàng của bạn đang trống</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto">Chưa có sản phẩm nào được chọn. Hãy khám phá ngay hàng trăm mẫu smartphone và phụ kiện công nghệ đỉnh cao!</p>
            <div class="pt-3 flex justify-center gap-3">
                <a href="<?= BASE_URL ?>index.php?url=phones" class="bg-gradient-to-r from-primary-600 to-cyan-500 hover:from-primary-500 hover:to-cyan-400 text-white text-xs font-bold px-6 py-3.5 rounded-xl shadow-lg shadow-primary-600/30 transition">
                    <i class="fa-solid fa-mobile-screen mr-1.5"></i> Mua Điện Thoại
                </a>
                <a href="<?= BASE_URL ?>index.php?url=accessories" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold px-6 py-3.5 rounded-xl border border-slate-700 transition">
                    <i class="fa-solid fa-headphones mr-1.5"></i> Mua Phụ Kiện
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Free Shipping Banner Kiểu Shopee -->
        <div class="p-3.5 rounded-2xl border border-emerald-500/30 bg-emerald-950/30 text-xs text-emerald-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-truck-fast text-xs"></i>
                </span>
                <span>Freeship Xtra: Đơn hàng của bạn đủ điều kiện được <strong>MIỄN PHÍ VẬN CHUYỂN TOÀN QUỐC (0đ)</strong></span>
            </span>
            <span class="bg-emerald-500/20 text-emerald-300 px-3 py-1 rounded-full text-[11px] font-bold">100% Free</span>
        </div>

        <!-- Bảng Sản Phẩm Trong Giỏ Chuẩn Shopee -->
        <div class="glass-card rounded-3xl overflow-hidden border border-slate-800 shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-[11px] text-slate-400 uppercase tracking-wider bg-slate-950/80">
                            <th class="py-4 px-4 sm:px-6 w-12 text-center">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="checkSelectAllHeader" class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-cyan-500 focus:ring-cyan-500 transition cursor-pointer" onchange="cartToggleSelectAll(this.checked)">
                                </label>
                            </th>
                            <th class="py-4 px-2">Sản phẩm</th>
                            <th class="py-4 px-4 text-center">Đơn giá</th>
                            <th class="py-4 px-4 text-center">Số lượng</th>
                            <th class="py-4 px-4 text-right">Số tiền</th>
                            <th class="py-4 px-4 text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80" id="cartTableBody">
                        <?php 
                        $allChecked = true;
                        foreach ($cart as $key => $item): 
                            $isSelected = !isset($item['selected']) || !empty($item['selected']);
                            if (!$isSelected) {
                                $allChecked = false;
                            }
                            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                        ?>
                            <tr class="hover:bg-slate-900/50 transition <?= $isSelected ? 'bg-cyan-950/10' : '' ?>" id="cart-row-<?= htmlspecialchars((string)$key) ?>">
                                <!-- Checkbox chọn món -->
                                <td class="py-4 px-4 sm:px-6 text-center">
                                    <input type="checkbox" 
                                           class="cart-item-checkbox w-4 h-4 rounded border-slate-700 bg-slate-900 text-cyan-500 focus:ring-cyan-500 transition cursor-pointer" 
                                           data-key="<?= htmlspecialchars((string)$key) ?>" 
                                           <?= $isSelected ? 'checked' : '' ?>
                                           onchange="cartToggleSelectItem('<?= htmlspecialchars((string)$key) ?>', this.checked)">
                                </td>

                                <!-- Chi tiết sản phẩm -->
                                <td class="py-4 px-2">
                                    <div class="flex items-center space-x-3.5">
                                        <a href="<?= BASE_URL ?>index.php?url=products/detail&slug=<?= urlencode($item['slug']) ?>">
                                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="" class="w-16 h-16 object-contain rounded-2xl bg-slate-950 p-1.5 border border-slate-800 hover:border-cyan-500 transition">
                                        </a>
                                        <div>
                                            <a href="<?= BASE_URL ?>index.php?url=products/detail&slug=<?= urlencode($item['slug']) ?>" class="font-bold text-slate-200 hover:text-cyan-400 transition line-clamp-1 text-sm">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span class="text-[10px] text-slate-500 bg-slate-900 px-2 py-0.5 rounded-md border border-slate-800">
                                                    <?= htmlspecialchars($item['category_name']) ?>
                                                </span>
                                                <?php if (!empty($item['color'])): ?>
                                                    <span class="text-[10px] bg-cyan-950/60 text-cyan-300 border border-cyan-800/60 px-2 py-0.5 rounded-md font-medium">
                                                        Phân loại: <?= htmlspecialchars($item['color']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Đơn giá -->
                                <td class="py-4 px-4 text-center font-semibold text-slate-300">
                                    <?= number_format($item['price'], 0, ',', '.') ?>đ
                                </td>

                                <!-- Tăng giảm số lượng Shopee AJAX -->
                                <td class="py-4 px-4 text-center">
                                    <div class="inline-flex items-center border border-slate-700 rounded-xl bg-slate-950 overflow-hidden shadow-sm">
                                        <button type="button" 
                                                onclick="cartChangeQty('<?= htmlspecialchars((string)$key) ?>', 'decrease')" 
                                                class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-95">
                                            <i class="fa-solid fa-minus text-[10px]"></i>
                                        </button>
                                        <input type="number" 
                                               id="qty-input-<?= htmlspecialchars((string)$key) ?>"
                                               value="<?= (int)$item['quantity'] ?>" 
                                               min="1" max="10" 
                                               class="w-12 text-center text-xs font-bold bg-transparent text-white border-0 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                               onchange="cartSetQty('<?= htmlspecialchars((string)$key) ?>', this.value)">
                                        <button type="button" 
                                                onclick="cartChangeQty('<?= htmlspecialchars((string)$key) ?>', 'increase')" 
                                                class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition active:scale-95">
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                        </button>
                                    </div>
                                </td>

                                <!-- Tạm tính từng dòng -->
                                <td class="py-4 px-4 text-right font-extrabold text-cyan-400 text-sm" id="item-total-<?= htmlspecialchars((string)$key) ?>">
                                    <?= number_format($itemTotal, 0, ',', '.') ?>đ
                                </td>

                                <!-- Xóa món -->
                                <td class="py-4 px-4 text-center">
                                    <button type="button" 
                                            onclick="cartDeleteItem('<?= htmlspecialchars((string)$key) ?>')" 
                                            class="text-slate-500 hover:text-rose-400 p-2 inline-flex items-center justify-center transition active:scale-90" 
                                            title="Xóa khỏi giỏ hàng">
                                        <i class="fa-regular fa-trash-can text-sm"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Khối Voucher Khuyến Mại kiểu Shopee (Chọn từ danh sách hoặc nhập tay) -->
            <div class="p-4 bg-slate-950/70 border-t border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 shrink-0">
                        <i class="fa-solid fa-ticket text-sm"></i>
                    </span>
                    <div>
                        <span class="text-xs font-bold text-white block">Shopee Voucher / Mã Giảm Giá NEXVORA</span>
                        <span class="text-[11px] text-slate-400">Chọn mã ưu đãi có sẵn bên dưới hoặc gõ trực tiếp mã của bạn</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                    <!-- 1. Select Option Chọn Mã Sẵn (Theo yêu cầu) -->
                    <div class="relative">
                        <select id="cartCouponSelect" onchange="onSelectCouponDropdown(this.value)" class="w-full sm:w-auto text-xs py-2.5 px-3 rounded-xl border border-amber-500/40 bg-slate-900 text-amber-300 font-semibold focus:outline-none focus:border-amber-400 cursor-pointer shadow-sm">
                            <option value="">🎁 -- Chọn mã giảm giá có sẵn (<?= count($availableCoupons ?? []) ?> mã) --</option>
                            <?php if (!empty($availableCoupons)): ?>
                                <?php foreach ($availableCoupons as $ac): ?>
                                    <option value="<?= htmlspecialchars($ac['code']) ?>" <?= (!empty($couponCode) && $couponCode === $ac['code']) ? 'selected' : '' ?>>
                                        🎟️ <?= htmlspecialchars($ac['code']) ?>: Giảm <?= $ac['discount_type'] === 'percent' ? $ac['discount_value'].'%' : number_format($ac['discount_value'], 0, ',', '.').'đ' ?> - <?= htmlspecialchars($ac['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 2. Nhập tay nếu có mã riêng -->
                    <div class="flex items-center gap-2">
                        <input type="text" 
                               id="cartCouponInput" 
                               value="<?= htmlspecialchars($couponCode ?? '') ?>" 
                               placeholder="Mã voucher..." 
                               class="w-full sm:w-32 text-xs p-2.5 rounded-xl border border-slate-700 bg-slate-900 text-white uppercase focus:outline-none focus:border-cyan-400 font-mono">
                        <button type="button" 
                                onclick="cartApplyCoupon()" 
                                class="bg-gradient-to-r from-primary-600 to-cyan-500 hover:from-primary-500 hover:to-cyan-400 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-md shadow-primary-500/20 whitespace-nowrap">
                            Áp dụng
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- THANH THANH TOÁN CỐ ĐỊNH PHÍA DƯỚI CHUẨN SHOPEE (Sticky Checkout Bar) -->
        <div class="sticky bottom-4 z-30 glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-cyan-500/30 bg-slate-900/95 shadow-2xl backdrop-blur-md">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- Bên trái: Chọn tất cả & Xóa đã chọn -->
                <div class="flex items-center gap-4 sm:gap-6 text-xs text-slate-300">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none font-bold hover:text-white transition">
                        <input type="checkbox" id="checkSelectAllBottom" class="w-4 h-4 rounded border-slate-700 bg-slate-900 text-cyan-500 focus:ring-cyan-500 transition cursor-pointer" onchange="cartToggleSelectAll(this.checked)">
                        <span>Chọn Tất Cả (<span id="countTotalItems"><?= count($cart) ?></span>)</span>
                    </label>
                    <span class="text-slate-700">|</span>
                    <button type="button" onclick="cartDeleteSelected()" class="text-rose-400 hover:text-rose-300 transition font-semibold flex items-center gap-1.5 active:scale-95" id="btnDeleteSelected">
                        <i class="fa-regular fa-trash-can"></i> Xóa Đã Chọn
                    </button>
                    <span class="text-slate-700">|</span>
                    <button type="button" onclick="cartClearAll()" class="text-slate-400 hover:text-white transition text-[11px]">
                        Xóa tất cả
                    </button>
                </div>

                <!-- Bên phải: Tổng tiền & Nút Mua Hàng -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 lg:gap-6 justify-end">
                    <div class="text-right space-y-0.5">
                        <div class="flex items-center justify-end gap-2 text-xs text-slate-400">
                            <span>Tổng thanh toán (<span id="countSelectedItems" class="text-cyan-400 font-extrabold text-sm"><?= $selectedCount ?></span> Sản phẩm):</span>
                            <span id="finalTotalDisplay" class="text-xl sm:text-2xl font-black text-cyan-400 tracking-tight">
                                <?= number_format($finalTotal, 0, ',', '.') ?>đ
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center justify-end gap-2">
                            <span>Tiết kiệm: <strong id="discountDisplay" class="text-emerald-400"><?= number_format($discount, 0, ',', '.') ?>đ</strong></span>
                            <span>•</span>
                            <span class="text-emerald-400"><i class="fa-solid fa-truck-fast"></i> Miễn phí vận chuyển</span>
                        </div>
                    </div>

                    <?php 
                    $isAdmin = !empty($_SESSION['admin_user']) || (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');
                    if ($isAdmin): 
                    ?>
                        <button type="button" disabled class="opacity-60 cursor-not-allowed bg-slate-800 text-slate-400 font-bold text-xs py-3.5 px-6 rounded-xl border border-slate-700">
                            <i class="fa-solid fa-ban mr-1.5"></i> Admin Không Thể Đặt Hàng
                        </button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>index.php?url=checkout" 
                           id="btnProceedCheckout"
                           class="inline-flex items-center justify-center bg-gradient-to-r from-primary-600 to-cyan-500 hover:from-primary-500 hover:to-cyan-400 text-white font-extrabold text-sm py-3.5 px-8 rounded-2xl shadow-xl shadow-cyan-500/25 transition transform hover:-translate-y-0.5 active:scale-95 whitespace-nowrap <?= ($selectedCount <= 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                            <i class="fa-solid fa-bag-shopping mr-2"></i> Mua Hàng (<span id="btnCheckoutCount"><?= $selectedCount ?></span>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SweetAlert2 Toast Thông Báo Real-time -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    background: '#0f172a',
    color: '#fff',
    iconColor: '#06b6d4'
});

// Đồng bộ checkbox Select All ban đầu
document.addEventListener('DOMContentLoaded', () => {
    syncSelectAllState();
});

function syncSelectAllState() {
    const checkboxes = document.querySelectorAll('.cart-item-checkbox');
    if (checkboxes.length === 0) return;
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const headerCb = document.getElementById('checkSelectAllHeader');
    const bottomCb = document.getElementById('checkSelectAllBottom');
    if (headerCb) headerCb.checked = allChecked;
    if (bottomCb) bottomCb.checked = allChecked;
}

function getCartCsrfToken() {
    return (typeof window.getCsrfToken === 'function') 
        ? window.getCsrfToken() 
        : (document.querySelector('meta[name="csrf-token"]')?.content 
           || document.querySelector('input[name="_csrf_token"]')?.value 
           || '');
}

// 1. Checkbox Chọn Tất Cả
async function cartToggleSelectAll(isChecked) {
    // Phản hồi giao diện tức thì (Optimistic UI)
    const headerCb = document.getElementById('checkSelectAllHeader');
    const bottomCb = document.getElementById('checkSelectAllBottom');
    if (headerCb) headerCb.checked = isChecked;
    if (bottomCb) bottomCb.checked = isChecked;

    document.querySelectorAll('.cart-item-checkbox').forEach(cb => {
        cb.checked = isChecked;
        const itemKey = cb.getAttribute('data-key');
        const row = document.getElementById('cart-row-' + itemKey);
        if (row) {
            if (isChecked) row.classList.add('bg-cyan-950/10');
            else row.classList.remove('bg-cyan-950/10');
        }
    });

    try {
        const token = getCartCsrfToken();
        const formData = new FormData();
        formData.append('select_all', isChecked ? '1' : '0');
        formData.append('_csrf_token', token);

        const res = await fetch('index.php?url=api/cart/toggle-select', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data);
            Toast.fire({
                icon: 'success',
                title: isChecked ? 'Đã chọn tất cả sản phẩm' : 'Đã bỏ chọn tất cả'
            });
        } else {
            syncSelectAllState();
            Toast.fire({
                icon: 'warning',
                title: data.message || 'Không thể cập nhật trạng thái chọn tất cả'
            });
        }
    } catch (e) {
        syncSelectAllState();
        console.error(e);
    }
}

// 2. Checkbox Chọn Từng Món
async function cartToggleSelectItem(key, isChecked) {
    // Phản hồi giao diện tức thì (Optimistic UI)
    const row = document.getElementById('cart-row-' + key);
    if (row) {
        if (isChecked) row.classList.add('bg-cyan-950/10');
        else row.classList.remove('bg-cyan-950/10');
    }
    syncSelectAllState(); // Tự động chọn hoặc bỏ chọn checkbox "Chọn tất cả"

    try {
        const token = getCartCsrfToken();
        const formData = new FormData();
        formData.append('key', key);
        formData.append('selected', isChecked ? '1' : '0');
        formData.append('_csrf_token', token);

        const res = await fetch('index.php?url=api/cart/toggle-select', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data);
        } else {
            syncSelectAllState();
            Toast.fire({
                icon: 'warning',
                title: data.message || 'Không thể cập nhật sản phẩm'
            });
        }
    } catch (e) {
        syncSelectAllState();
        console.error(e);
    }
}

// 3. Tăng / Giảm Số Lượng AJAX
async function cartChangeQty(key, actionType) {
    try {
        const token = getCartCsrfToken();
        const formData = new FormData();
        formData.append('key', key);
        formData.append('action_type', actionType);
        formData.append('_csrf_token', token);

        const res = await fetch('index.php?url=api/cart/update-qty', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data);
        } else {
            Toast.fire({
                icon: 'warning',
                title: data.message || 'Không thể cập nhật số lượng'
            });
        }
    } catch (e) {
        console.error(e);
    }
}

// 4. Nhập Số Lượng Trực Tiếp
async function cartSetQty(key, value) {
    const qty = parseInt(value);
    if (isNaN(qty) || qty <= 0) return;
    try {
        const token = getCartCsrfToken();
        const formData = new FormData();
        formData.append('key', key);
        formData.append('quantity', qty);
        formData.append('_csrf_token', token);

        const res = await fetch('index.php?url=api/cart/update-qty', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            updateCartUI(data);
        } else {
            Toast.fire({
                icon: 'warning',
                title: data.message || 'Số lượng không hợp lệ'
            });
        }
    } catch (e) {
        console.error(e);
    }
}

// 5. Xóa 1 Món
async function cartDeleteItem(key) {
    const result = await Swal.fire({
        title: 'Xóa sản phẩm?',
        text: 'Bạn có chắc chắn muốn bỏ sản phẩm này khỏi giỏ hàng?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Giữ lại',
        background: '#0f172a',
        color: '#fff'
    });

    if (result.isConfirmed) {
        try {
            const token = getCartCsrfToken();
            const formData = new FormData();
            formData.append('key', key);
            formData.append('_csrf_token', token);

            const res = await fetch('index.php?url=api/cart/remove', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                // Xóa dòng HTML tương ứng
                const row = document.getElementById('cart-row-' + key);
                if (row) row.remove();

                updateCartUI(data);

                Toast.fire({
                    icon: 'success',
                    title: 'Đã xóa sản phẩm khỏi giỏ'
                });

                if (data.items.length === 0) {
                    location.reload();
                }
            } else {
                Toast.fire({
                    icon: 'warning',
                    title: data.message || 'Không thể xóa sản phẩm'
                });
            }
        } catch (e) {
            console.error(e);
        }
    }
}

// 6. Xóa Các Món Đã Chọn
async function cartDeleteSelected() {
    const checkedBoxes = document.querySelectorAll('.cart-item-checkbox:checked');
    if (checkedBoxes.length === 0) {
        Toast.fire({
            icon: 'info',
            title: 'Vui lòng chọn ít nhất 1 sản phẩm để xóa'
        });
        return;
    }

    const result = await Swal.fire({
        title: 'Xóa các sản phẩm đã chọn?',
        text: `Bạn có chắc muốn xóa ${checkedBoxes.length} sản phẩm đang được tick chọn?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy',
        background: '#0f172a',
        color: '#fff'
    });

    if (result.isConfirmed) {
        try {
            const token = getCartCsrfToken();
            const formData = new FormData();
            formData.append('clear_selected', '1');
            formData.append('_csrf_token', token);

            const res = await fetch('index.php?url=api/cart/remove', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                Toast.fire({
                    icon: 'warning',
                    title: data.message || 'Không thể xóa sản phẩm đã chọn'
                });
            }
        } catch (e) {
            console.error(e);
        }
    }
}

// 7. Xóa Toàn Bộ Giỏ Hàng
async function cartClearAll() {
    const result = await Swal.fire({
        title: 'Làm trống giỏ hàng?',
        text: 'Toàn bộ sản phẩm trong giỏ hàng sẽ bị xóa!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Xóa tất cả',
        cancelButtonText: 'Hủy',
        background: '#0f172a',
        color: '#fff'
    });

    if (result.isConfirmed) {
        try {
            const token = getCartCsrfToken();
            const formData = new FormData();
            formData.append('clear_all', '1');
            formData.append('_csrf_token', token);

            const res = await fetch('index.php?url=api/cart/remove', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                Toast.fire({
                    icon: 'warning',
                    title: data.message || 'Không thể làm trống giỏ hàng'
                });
            }
        } catch (e) {
            console.error(e);
        }
    }
}

// 8. Áp Dụng Mã Giảm Giá Shopee Voucher (Hỗ trợ chọn Select Option hoặc gõ tay)
function onSelectCouponDropdown(code) {
    if (!code) return;
    const input = document.getElementById('cartCouponInput');
    if (input) input.value = code;
    cartApplyCoupon();
}

async function cartApplyCoupon() {
    const code = document.getElementById('cartCouponInput').value.trim();
    if (!code) {
        Toast.fire({
            icon: 'info',
            title: 'Vui lòng nhập mã giảm giá'
        });
        return;
    }

    try {
        const token = getCartCsrfToken();
        const formData = new FormData();
        formData.append('coupon_code', code);
        formData.append('_csrf_token', token);

        const res = await fetch('index.php?url=api/cart/apply-coupon', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            Toast.fire({
                icon: 'success',
                title: data.message
            });
            // Tải lại dữ liệu giỏ để cập nhật tổng tiền
            const listRes = await fetch('index.php?url=api/cart/list');
            const listData = await listRes.json();
            if (listData.success) {
                updateCartUI(listData);
            }
        } else {
            Toast.fire({
                icon: 'error',
                title: data.message
            });
        }
    } catch (e) {
        console.error(e);
    }
}

// Hàm cập nhật toàn bộ DOM trên màn hình dựa vào JSON từ API
function updateCartUI(data) {
    if (!data) return;

    // Cập nhật từng dòng sản phẩm
    if (data.items) {
        data.items.forEach(item => {
            const cb = document.querySelector(`.cart-item-checkbox[data-key="${item.key}"]`);
            if (cb) cb.checked = item.selected;

            const qtyInput = document.getElementById(`qty-input-${item.key}`);
            if (qtyInput) qtyInput.value = item.quantity;

            const lineTotal = document.getElementById(`item-total-${item.key}`);
            if (lineTotal) lineTotal.textContent = item.total_formatted;

            const row = document.getElementById(`cart-row-${item.key}`);
            if (row) {
                if (item.selected) {
                    row.classList.add('bg-cyan-950/10');
                } else {
                    row.classList.remove('bg-cyan-950/10');
                }
            }
        });
    }

    // Cập nhật các badge số lượng
    const badgeTotal = document.getElementById('badgeCartTotalItems');
    if (badgeTotal) badgeTotal.textContent = `${data.item_count} mặt hàng`;

    const countTotal = document.getElementById('countTotalItems');
    if (countTotal) countTotal.textContent = data.item_count;

    const countSel = document.getElementById('countSelectedItems');
    if (countSel) countSel.textContent = data.selected_count;

    const btnCount = document.getElementById('btnCheckoutCount');
    if (btnCount) btnCount.textContent = data.selected_count;

    // Cập nhật tổng tiền
    const finalTotalEl = document.getElementById('finalTotalDisplay');
    if (finalTotalEl) finalTotalEl.textContent = data.final_total_formatted;

    const discountEl = document.getElementById('discountDisplay');
    if (discountEl) discountEl.textContent = data.discount_formatted;

    // Cập nhật nút Mua Hàng: Nếu không chọn món nào thì vô hiệu hóa
    const btnCheckout = document.getElementById('btnProceedCheckout');
    if (btnCheckout) {
        if (data.selected_count <= 0) {
            btnCheckout.classList.add('opacity-50', 'pointer-events-none');
        } else {
            btnCheckout.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    // Cập nhật trạng thái checkbox Select All
    const headerCb = document.getElementById('checkSelectAllHeader');
    const bottomCb = document.getElementById('checkSelectAllBottom');
    if (headerCb) headerCb.checked = data.all_selected;
    if (bottomCb) bottomCb.checked = data.all_selected;
}
</script>
