<!-- ⚡ كود الربط المباشر مع API التسجيل -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. تبديل رؤية كلمة المرور
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePasswordBtn.addEventListener('click', function() {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        eyeIcon.classList.toggle('fa-eye');
        eyeIcon.classList.toggle('fa-eye-slash');
    });

    // 2. الربط المباشر مع API تسجيل الدخول
    const loginForm = document.getElementById('loginForm');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault(); // منع إعادة تحميل الصفحة التقليدي

        const loginInput = document.getElementById('phone_or_email').value;
        const passwordInputVal = document.getElementById('password').value;
        const submitBtn = document.querySelector('.btn-submit');

        // تغيير شكل الزر أثناء التحميل
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري التحقق...';

        try {
            // إرسال الطلب إلى مسار API الخاص بك (/api/auth/login)
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    email: loginInput,    // أو المسمى المتوقع في LoginController
                    login: loginInput,    // لتغطية الهاتف أو الإيميل
                    password: passwordInputVal
                })
            });

            const data = await response.json();

            if (response.ok && (data.status === true || data.token)) {
                // ✅ النجاح: حفظ التوكن في المتصفح والتحويل للوحة التحكم
                if (data.token) {
                    localStorage.setItem('auth_token', data.token);
                }
                
                // التوجيه للوحة التحكم (أو أي صفحة رئيسية)
                window.location.href = '/dashboard'; 
            } else {
                // ❌ فشل البيانات: إظهار الرسالة القادمة من الـ API
                alertError(data.message || 'بيانات الدخول غير صحيحة، يرجى التأكد وإعادة المحاولة.');
            }
        } catch (error) {
            alertError('حدث خطأ في الاتصال بالسيرفر، يرجى المحاولة لاحقاً.');
        } finally {
            // إعادة الزر لوضعه الطبيعي
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>دخول المنصة</span> <i class="fa-solid fa-arrow-left"></i>';
        }
    });

    // دالة مساعدة لإظهار تنبيه الخطأ فوق النموذج
    function alertError(message) {
        let errorBox = document.querySelector('.alert-error');
        if (!errorBox) {
            errorBox = document.createElement('div');
            errorBox.className = 'alert-error';
            loginForm.insertAdjacentElement('beforebegin', errorBox);
        }
        errorBox.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> <span>${message}</span>`;
    }
});
</script>