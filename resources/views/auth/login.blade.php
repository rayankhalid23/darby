<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"> 
    <title>تسجيل الدخول - منصة دربي</title>
    
    <!-- FontAwesome للايقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #007A99;
            --primary-dark: #005c73;
            --accent: #F59E0B;
            --accent-hover: #d9820b;
            --bg-color: #f0f7f9;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        
        body { 
            background: linear-gradient(135deg, #e6f2f5 0%, #f0f7f9 100%);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            overflow: hidden;
            position: relative;
        }

        /* خلفية جمالية تفاعلية خفيفة */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(0, 122, 153, 0.05);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            z-index: -1;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(245, 158, 11, 0.05);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
            z-index: -1;
        }

        .login-card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 3rem 2.5rem; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0, 122, 153, 0.08), 0 5px 15px rgba(0, 0, 0, 0.04); 
            width: 100%; 
            max-width: 440px; 
            border-top: 5px solid var(--primary);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header { 
            text-align: center; 
            margin-bottom: 2.2rem; 
        }

        .logo-badge {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 20px rgba(0, 122, 153, 0.25);
            position: relative;
        }

        /* لمسة جمالية بلون الـ accent */
        .logo-badge::after {
            content: '';
            position: absolute;
            bottom: -3px;
            right: -3px;
            width: 16px;
            height: 16px;
            background: var(--accent);
            border-radius: 50%;
            border: 2px solid white;
        }

        .login-header h2 { 
            color: var(--text-main); 
            font-size: 1.75rem; 
            font-weight: 800;
            margin-bottom: 0.4rem; 
        }

        .login-header p { 
            color: var(--text-muted); 
            font-size: 0.92rem; 
            font-weight: 500;
        }

        .form-group { 
            margin-bottom: 1.4rem; 
        }

        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: var(--text-main); 
            font-size: 0.88rem; 
            font-weight: 700; 
        }

        .input-wrapper { 
            position: relative; 
        }

        .input-wrapper i.field-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.2s;
        }

        .input-wrapper input { 
            width: 100%; 
            padding: 0.85rem 2.8rem 0.85rem 1rem; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            outline: none; 
            font-size: 0.95rem; 
            color: var(--text-main);
            background: #f8fafc;
            transition: all 0.3s ease; 
        }

        .input-wrapper input:focus { 
            border-color: var(--primary); 
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 122, 153, 0.1);
        }

        .input-wrapper input:focus + i.field-icon {
            color: var(--primary);
        }

        .toggle-pwd { 
            position: absolute; 
            left: 1rem; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            color: #94a3b8; 
            transition: color 0.2s;
        }

        .toggle-pwd:hover {
            color: var(--primary);
        }

        .btn-submit { 
            width: 100%; 
            padding: 0.9rem; 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 1.05rem; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.6rem; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(0, 122, 153, 0.3);
            margin-top: 1.8rem;
        }

        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 153, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled { 
            opacity: 0.7; 
            cursor: not-allowed; 
            transform: none;
        }

        .alert-error { 
            background: #fef2f2; 
            border: 1px solid #fecaca; 
            color: #dc2626; 
            padding: 0.85rem 1rem; 
            border-radius: 12px; 
            font-size: 0.88rem; 
            margin-bottom: 1.4rem; 
            display: flex; 
            align-items: center; 
            gap: 0.7rem; 
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* حقوق النظام التجميلية أسفل البطاقة */
        .card-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px solid #f1f5f9;
            padding-top: 1rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="logo-badge">
            <i class="fa-solid fa-route"></i>
        </div>
        <h2>منصة دربي</h2>
        <p>إدارة الموظفين والطلبات والمخزون</p>
    </div>

    <!-- مكان إظهار الخطأ -->
    <div id="errorAlert" style="display: none;" class="alert-error">
        <i class="fa-solid fa-circle-exclamation" style="font-size: 1.1rem;"></i>
        <span id="errorMessage"></span>
    </div>

    <form id="loginForm">
        <div class="form-group">
            <label for="phone_or_email">البريد الإلكتروني أو رقم الهاتف</label>
            <div class="input-wrapper">
                <input type="text" id="phone_or_email" required placeholder="09xxxxxxxx أو البريد الإلكتروني">
                <i class="fa-solid fa-user field-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="password">كلمة المرور</label>
            <div class="input-wrapper">
                <input type="password" id="password" required placeholder="••••••••">
                <i class="fa-solid fa-lock field-icon"></i>
                <i class="fa-solid fa-eye toggle-pwd" id="togglePassword"></i>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
            <span>دخول المنصة</span>
            <i class="fa-solid fa-arrow-left-long"></i>
        </button>
    </form>

    <div class="card-footer">
        جميع الحقوق محفوظة &copy; 2026 - دربي
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. إظهار/إخفاء كلمة المرور
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // 2. إرسال البيانات
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // إخفاء التنبيه السابق
        errorAlert.style.display = 'none';

        const loginInput = document.getElementById('phone_or_email').value;
        const passwordInputVal = document.getElementById('password').value;

        // قراءة الـ CSRF Token بشكل آمن
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        // تغيير حالة الزر مع أيقونة التحميل
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';

        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    phone_number: loginInput, // متطابق تماماً مع الـ Backend
                    password: passwordInputVal,
                    platform: 'web'           // المتطلب الأساسي للـ LoginRequest
                })
            });

            const data = await response.json();

            if (response.ok && (data.status === true || data.success === true || data.token)) {
                if (data.token) {
                    localStorage.setItem('auth_token', data.token);
                }
                window.location.href = '/dashboard';
            } else {
                let errorMsg = data.message || 'بيانات الدخول غير صحيحة، يرجى المحاولة مجدداً.';
                if (data.errors) {
                    const firstKey = Object.keys(data.errors)[0];
                    if (firstKey && data.errors[firstKey][0]) {
                        errorMsg = data.errors[firstKey][0];
                    }
                }
                showError(errorMsg);
            }
        } catch (error) {
            showError('تعذر الاتصال بالسيرفر. تأكد من عمل النظام بشكل سليم.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>دخول المنصة</span> <i class="fa-solid fa-arrow-left-long"></i>';
        }
    });

    function showError(msg) {
        errorMessage.textContent = msg;
        errorAlert.style.display = 'flex';
    }
});
</script>

</body>
</html>