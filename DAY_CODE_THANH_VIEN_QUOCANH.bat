@echo off
chcp 65001 >nul
title ĐẨY MÃ NGUỒN DỰ ÁN 1 - Quốc Anh (dev-quocanh)
color 0B
echo ==============================================================
echo   DỰ ÁN 1 - NHÓM 4: NEXVORA PHONE STORE
echo   Thành viên: Quốc Anh (Thành viên)
echo   Nhánh Git đảm nhiệm: dev-quocanh
echo   Nghiệp vụ: Lập trình Giỏ hàng, Mã giảm giá & Đặt hàng (Checkout)
echo   Kho GitHub mới: https://github.com/abcson20-design/DuAn-1-Nhom-4
echo ==============================================================
echo.

cd /d "%~dp0"

where git >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo [LỖI] Máy tính của bạn chưa cài đặt Git!
    echo Vui lòng tải và cài đặt Git tại: https://git-scm.com/
    pause
    exit /b
)

echo [1/5] Khởi tạo môi trường Git cho phần nghiệp vụ của bạn...
if not exist ".git" (
    git init >nul 2>&1
)

echo [2/5] Thiết lập thông tin tác giả Git (Quốc Anh)...
git config user.name "Quoc Anh"
set /p user_email="Nhập email GitHub của bạn (Nhấn Enter để dùng mặc định quocanh@nexvora.vn): "
if "%user_email%"=="" set user_email=quocanh@nexvora.vn
git config user.email "%user_email%"

echo [3/5] Kết nối tới kho GitHub mới: https://github.com/abcson20-design/DuAn-1-Nhom-4.git ...
git remote remove origin >nul 2>&1
git remote add origin https://github.com/abcson20-design/DuAn-1-Nhom-4.git

echo [4/5] Chuyển sang nhánh cá nhân: dev-quocanh ...
git checkout -B dev-quocanh >nul 2>&1

echo Đang đóng gói các file nghiệp vụ của bạn...
git add .
set /p commit_msg="Nhập nội dung commit (Nhấn Enter để dùng mặc định): "
if "%commit_msg%"=="" set commit_msg=Lập trình Giỏ hàng, Mã giảm giá & Đặt hàng (Checkout) - Quốc Anh
git commit -m "%commit_msg%"

echo.
echo [5/5] Đang đẩy code lên GitHub nhánh dev-quocanh...
git push -u origin dev-quocanh --force

if %errorlevel% equ 0 (
    color 0A
    echo.
    echo ==============================================================
    echo   CHÚC MỪNG BẠN ĐÃ ĐẨY CODE LÊN GITHUB THÀNH CÔNG!
    echo   Nhánh của bạn: https://github.com/abcson20-design/DuAn-1-Nhom-4/tree/dev-quocanh
    echo ==============================================================
) else (
    color 0E
    echo.
    echo ==============================================================
    echo   [LƯU Ý QUAN TRỌNG KHI GẶP LỖI PERMISSION DENIED]
    echo   Nếu báo lỗi quyền đẩy (Permission to DuAn-1-Nhom-4 denied):
    echo   1. Báo Trưởng nhóm Sơn thêm tài khoản GitHub của bạn vào Collaborators:
    echo      (GitHub Repo -> Settings -> Collaborators -> Add people)
    echo   2. Sau khi chấp nhận lời mời qua email, chạy lại file này là thành công 100%!
    echo ==============================================================
)

echo.
pause