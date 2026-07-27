<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Shared\SubscriptionRequestService;
use Exception;

class ChatController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionRequestService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * الـ Endpoint الخاصة بولي الأمر: GET /api/parent/chats
     */
    public function getParentChatList(Request $request): JsonResponse
    {
        try {
            $chats = $this->subscriptionService->getParentChats($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة المحادثات بنجاح.',
                'data'    => $chats
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * الـ Endpoint الخاصة بالسائق: GET /api/driver/chats
     */
    public function getDriverChatList(Request $request): JsonResponse
    {
        try {
            $chats = $this->subscriptionService->getDriverChats($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة المحادثات بنجاح.',
                'data'    => $chats
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 400);
        }
    }
}