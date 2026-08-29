<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * التحقق من امتلاك المشرف للصلاحية المطلوبة للوصول إلى المسار
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        // 1. التحقق من تسجيل الدخول
        if (!$user) {
            return response()->json([
                'status'  => false,
                'success' => false,
                'message' => 'غير مصرح بالدخول، يرجى تسجيل الدخول أولاً.',
            ], 401);
        }

        // 2. التحقق من أن الحساب نشط
        if (!$user->is_active) {
            return response()->json([
                'status'  => false,
                'success' => false,
                'message' => 'تم تجميد أو إيقاف حسابك من قبل إدارة النظام.',
            ], 403);
        }

        // 3. المدير العام يتجاوز كافة القيود
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // 4. إذا لم يتم تحديد صلاحيات، السماح بالمرور طالما أنه مدير/مشرف مسجل
        if (empty($permissions)) {
            return $next($request);
        }

        // 5. التحقق من امتلاك أي من الصلاحيات المطلوبة
        if ($user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // 6. رفض الوصول عند غياب الصلاحية
        return response()->json([
            'status'             => false,
            'success'            => false,
            'message'            => 'عذراً، ليس لديك الصلاحية الكافية لتنفيذ هذا الإجراء.',
            'required_permission'=> count($permissions) === 1 ? $permissions[0] : $permissions,
        ], 403);
    }
}
