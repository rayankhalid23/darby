<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminAlert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AdminAiAlertsController extends Controller
{
    /**
     * 📋 قائمة تنبيهات الذكاء الاصطناعي للإدارة مع الفلترة والترقيم
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AdminAlert::with(['driver.user'])->latest();

            if ($request->filled('risk_level')) {
                $query->where('risk_level', strtoupper($request->risk_level));
            }

            if ($request->has('is_resolved') && $request->is_resolved !== null && $request->is_resolved !== '') {
                $query->where('is_resolved', filter_var($request->is_resolved, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('driver_id')) {
                $query->where('driver_id', (int) $request->driver_id);
            }

            $perPage = (int) ($request->per_page ?? 15);
            $alerts = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data'   => $alerts->items(),
                'meta'   => [
                    'current_page' => $alerts->currentPage(),
                    'last_page'    => $alerts->lastPage(),
                    'per_page'     => $alerts->perPage(),
                    'total'        => $alerts->total(),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء جلب قائمة التنبيهات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 👁️ عرض كامل التفاصيل (360-Degree Breakdown) لتنبيه محدد
     */
    public function show(int $id): JsonResponse
    {
        try {
            $alert = AdminAlert::with(['driver.user'])->find($id);

            if (!$alert) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، التنبيه المطلوب غير موجود بالسيستم.',
                ], 404);
            }

            $driver = $alert->driver;
            $user   = $driver?->user;
            $meta   = is_array($alert->metadata) ? $alert->metadata : [];

            // تجهيز مصفوفة التعليقات التي تمت مراجعتها وإضافة اسم ولي الأمر
            $evaluatedReviews = is_array($alert->evaluated_reviews) ? $alert->evaluated_reviews : [];
            $parentIds = collect($evaluatedReviews)->pluck('parent_id')->map(function($pid) {
                return (int) str_replace(['p_', 'P_'], '', $pid);
            })->filter()->unique()->toArray();

            $parentsMap = User::whereIn('id', $parentIds)->pluck('full_name', 'id')->toArray();

            $reviewedComments = array_map(function ($rev) use ($parentsMap) {
                $rawPid = $rev['parent_id'] ?? '';
                $cleanId = (int) str_replace(['p_', 'P_'], '', $rawPid);
                return [
                    'parent_id'   => !empty($rawPid) ? (string) $rawPid : "P_{$cleanId}",
                    'parent_name' => $parentsMap[$cleanId] ?? "ولي أمر #{$cleanId}",
                    'text'        => $rev['text'] ?? $rev['comment'] ?? '',
                    'date'        => $rev['date'] ?? date('Y-m-d'),
                ];
            }, $evaluatedReviews);

            // استخراج المقاييس من ai_metrics أو metadata
            $metrics = is_array($alert->ai_metrics) && !empty($alert->ai_metrics)
                ? $alert->ai_metrics
                : ($meta['metrics'] ?? [
                    'total_reviews_analyzed'         => count($evaluatedReviews),
                    'unique_parents_count'           => count($parentIds),
                    'operational_strikes_count'      => 0,
                    'ignored_external_factors_count' => 0,
                ]);

            $ratingChange = (float) ($meta['rating_change'] ?? 0.0);

            return response()->json([
                'status' => true,
                'data'   => [
                    'alert_id'   => $alert->id,
                    'created_at' => $alert->created_at ? $alert->created_at->format('Y-m-d H:i:s') : null,
                    'is_resolved' => (bool) $alert->is_resolved,
                    'driver'     => [
                        'id'             => $driver?->id,
                        'name'           => $user?->full_name ?? 'سائق # ' . $driver?->id,
                        'phone'          => $user?->phone_number ?? '',
                        'current_rating' => (float) ($driver?->rating_avg ?? 5.0),
                        'is_searchable'  => (bool) ($driver?->is_searchable ?? false),
                        'status'         => $driver?->status ?? 'Suspended',
                    ],
                    'ai_decision' => [
                        'risk_level'    => $alert->risk_level ?? 'NONE',
                        'actions_taken' => $alert->actions_taken ?? [],
                        'rating_change' => $ratingChange,
                        'reasoning'     => $alert->reasoning ?? $alert->admin_message ?? '',
                    ],
                    'metrics'           => $metrics,
                    'reviewed_comments' => $reviewedComments,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل التنبيه: ' . $e->getMessage(),
            ], 500);
        }
    }
}
