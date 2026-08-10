<?php

namespace App\Http\Resources\Api\Admin;

use App\Http\Controllers\Api\Admin\AdminAvatarController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // طلب تغيير بريد معلّق (إن وُجد) حتى تعرض الواجهة شارة "بانتظار التأكيد"
        $pendingEmail = $this->user
            ? (Cache::get("admin_email_change_{$this->user_id}")['new_email'] ?? null)
            : null;

        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'full_name'    => $this->user->full_name ?? null,
            'email'        => $this->user->email ?? null,
            'phone_number' => $this->user->phone_number ?? null,
            // يمر عبر مسار لارافيل ليحصل على ترويسات CORS التي يحتاجها Flutter Web
            'avatar_url'   => AdminAvatarController::urlFor($this->user->avatar_url ?? null),
            'is_active'    => (bool) ($this->user->is_active ?? false),
            'role_id'      => $this->user->role_id ?? null,
            'role_name'    => ((int) ($this->user->role_id ?? 0) === 1) ? 'مدير النظام' : 'مشرف',
            'created_by'   => $this->created_by,
            'creator_name' => $this->creator->full_name ?? null,
            'created_at'   => optional($this->user)->created_at?->toDateTimeString(),
            'last_login_at'=> optional($this->user)->last_login_at?->toDateTimeString(),

            // حالة تغيير البريد الإلكتروني المعلّق
            'email_change_pending' => $pendingEmail !== null,
            'pending_new_email'    => $pendingEmail,
        ];
    }
}
