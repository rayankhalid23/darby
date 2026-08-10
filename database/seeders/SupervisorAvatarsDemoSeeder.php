<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 🖼️ توليد صور شخصية وهمية للمشرفين لاختبار عرض الصور في لوحة التحكم (ويب).
 *
 * التشغيل: php artisan db:seed --class=SupervisorAvatarsDemoSeeder
 *
 * ينشئ صورة PNG حقيقية لكل مشرف بلا صورة: خلفية ملوّنة ثابتة مشتقة من المعرّف
 * مع أول حرف من اسمه، ويخزّنها في نفس مسار الصور المرفوعة فعلياً
 * (uploads/admins/avatars) حتى يكون الاختبار مطابقاً للواقع تماماً.
 *
 * ⚠️ لا يمس أي وسائط أخرى في المشروع — صور المشرفين فقط.
 */
class SupervisorAvatarsDemoSeeder extends Seeder
{
    /** ألوان خلفيات هادئة تُوزَّع على المشرفين */
    private const PALETTE = [
        [ 41, 128, 185], // أزرق
        [ 39, 174,  96], // أخضر
        [211,  84,   0], // برتقالي
        [142,  68, 173], // بنفسجي
        [192,  57,  43], // أحمر
        [ 22, 160, 133], // تركوازي
        [ 52,  73,  94], // كحلي
        [230, 126,  34], // عنبري
    ];

    public function run(): void
    {
        if (! extension_loaded('gd')) {
            $this->command?->error('❌ إضافة GD غير مفعّلة في PHP، تعذّر توليد الصور.');
            return;
        }

        Storage::disk('public')->makeDirectory('uploads/admins/avatars');

        $created = 0;
        $skipped = 0;

        foreach (Admin::with('user')->get() as $admin) {
            if (! $admin->user) {
                continue;
            }

            // عدم استبدال صورة موجودة فعلاً على القرص
            if ($admin->user->avatar_url) {
                $existing = str_replace('storage/', '', $admin->user->avatar_url);
                if (Storage::disk('public')->exists($existing)) {
                    $skipped++;
                    continue;
                }
            }

            $letter = mb_substr(trim($admin->user->full_name ?? '؟'), 0, 1, 'UTF-8');
            $color  = self::PALETTE[$admin->id % count(self::PALETTE)];

            $filename = Str::random(40) . '.png';
            $path     = 'uploads/admins/avatars/' . $filename;

            Storage::disk('public')->put($path, $this->makeAvatar($letter, $color));

            $admin->user->update(['avatar_url' => 'storage/' . $path]);
            $created++;
        }

        $this->command?->info("✅ تم توليد {$created} صورة للمشرفين (تم تخطي {$skipped} لديهم صور بالفعل).");
    }

    /**
     * رسم صورة مربعة 256×256 بخلفية ملوّنة وحرف أبيض في المنتصف.
     */
    private function makeAvatar(string $letter, array $rgb): string
    {
        $size  = 256;
        $image = imagecreatetruecolor($size, $size);

        $background = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $white      = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $size, $size, $background);

        // الخط المدمج في GD لا يدعم العربية، لذا نرسم الحرف اللاتيني إن توفر
        // وإلا نرسم دائرة بيضاء بسيطة كعنصر بصري مميّز.
        $ascii = preg_match('/^[A-Za-z0-9]$/', $letter) ? $letter : null;

        if ($ascii !== null) {
            $font  = 5;
            $x     = (int) (($size - imagefontwidth($font) * 1) / 2);
            $y     = (int) (($size - imagefontheight($font)) / 2);
            imagestring($image, $font, $x, $y, $ascii, $white);
        } else {
            imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2.6), 90, 90, $white);
            imagefilledellipse($image, (int) ($size / 2), (int) ($size / 1.1), 160, 120, $white);
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary;
    }
}
