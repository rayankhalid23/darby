<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * 🖼️ تقديم صور المشرفين للوحة التحكم (ويب).
 *
 * سبب وجود هذا الكنترولر:
 * الملفات الموجودة تحت /storage يقدّمها خادم الويب مباشرة دون المرور على لارافيل،
 * فلا تحصل على ترويسات CORS. ولوحة التحكم تعمل على Flutter Web بمحرك CanvasKit
 * الذي يجلب الصور عبر XHR وليس عبر وسم <img>، فيحجبها المتصفح وتظهر فارغة.
 *
 * هذا المسار يمر عبر لارافيل (لأن العنوان لا يقابل ملفاً حقيقياً على القرص)
 * وبالتالي تُطبَّق عليه إعدادات CORS الخاصة بـ api/* تلقائياً.
 *
 * ⚠️ مقصور على صور المشرفين فقط (uploads/admins/avatars) ولا يمس أي وسائط
 *    أخرى يستخدمها تطبيق الهاتف.
 */
class AdminAvatarController extends Controller
{
    /** المجلد الوحيد المسموح بتقديم الملفات منه */
    private const DIRECTORY = 'uploads/admins/avatars';

    /** الصيغ المسموح بها */
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'webp'];

    public function show(string $filename): Response
    {
        // منع أي محاولة للخروج من المجلد المسموح به (path traversal)
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $filename) || str_contains($filename, '..')) {
            abort(404);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED, true)) {
            abort(404);
        }

        $path = self::DIRECTORY . '/' . $filename;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type'                => $disk->mimeType($path) ?: 'image/jpeg',
            'Cache-Control'               => 'public, max-age=604800',
            // ضمان إضافي حتى لو قدّم خادم الويب الرد دون ميدلوير لارافيل
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * تحويل قيمة avatar_url المخزّنة في قاعدة البيانات إلى رابط قابل للعرض في المتصفح.
     * ترجع null إذا لم تكن هناك صورة، وترجع الرابط كما هو إن كان رابطاً خارجياً.
     */
    public static function urlFor(?string $storedPath): ?string
    {
        if (empty($storedPath)) {
            return null;
        }

        // روابط خارجية كاملة تُترك كما هي
        if (str_starts_with($storedPath, 'http://') || str_starts_with($storedPath, 'https://')) {
            return $storedPath;
        }

        $filename = basename($storedPath);

        return url('api/admin/avatars/' . $filename);
    }
}
