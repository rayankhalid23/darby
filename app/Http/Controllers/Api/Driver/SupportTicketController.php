<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shared\StoreSupportTicketRequest;
use App\Http\Resources\Api\Shared\SupportTicketResource;
use App\Services\Shared\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    protected SupportTicketService $ticketService;

    public function __construct(SupportTicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function index(Request $request): JsonResponse
    {
        $tickets = $this->ticketService->getUserTickets(
            $request->user(),
            'driver',
            $request->only(['status', 'category', 'incoming', 'type', 'scope'])
        );

        return response()->json([
            'success'    => true,
            'data'       => SupportTicketResource::collection($tickets),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
                'per_page'     => $tickets->perPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->getUserTicketDetail($request->user(), 'driver', $id);

        return response()->json([
            'success' => true,
            'data'    => new SupportTicketResource($ticket),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket(
            $request->user(),
            'driver',
            $request->validated(),
            $request->file('attachments', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم التذكرة بنجاح، سيتم التواصل معك قريباً.',
            'data'    => new SupportTicketResource($ticket),
        ], 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|min:1|max:5000']);

        $message = $this->ticketService->addUserMessage($request->user(), 'driver', $id, $request->input('message'));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $message->id,
                'message'    => $message->message,
                'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    /**
     * المعاملات المالية السابقة (لواجهة اختيار المرجع عند تذكرة "مالية")
     */
    public function financialHistory(Request $request): JsonResponse
    {
        $history = $this->ticketService->financialHistoryForDriver($request->user());

        return response()->json([
            'success' => true,
            'data'    => [
                'transactions' => $history['transactions']->map(fn ($t) => [
                    'id'         => $t->id,
                    'type'       => $t->type,
                    'amount'     => (float) $t->amount / 100,
                    'created_at' => $t->created_at?->format('Y-m-d H:i:s'),
                ]),
            ],
        ]);
    }

    /**
     * رحلات أولياء الأمور/الأطفال السابقة (لواجهة اختيار الرحلة عند تذكرة "ولي أمر/طفل")
     */
    public function parentTrips(Request $request): JsonResponse
    {
        $trips = $this->ticketService->tripsForDriver($request->user());

        return response()->json([
            'success' => true,
            'data'    => $trips->map(function ($trip) {
                $subscription = $trip->activeSubscriptions->first();

                return [
                    'id'        => $trip->id,
                    'trip_date' => $trip->trip_date,
                    'trip_type' => $trip->trip_type,
                    'status'    => $trip->status,
                    'parent'    => $subscription?->parent ? [
                        'id'        => $subscription->parent->id,
                        'full_name' => $subscription->parent->full_name,
                    ] : null,
                    'child' => $subscription?->child ? [
                        'id'   => $subscription->child->id,
                        'name' => $subscription->child->full_name,
                    ] : null,
                ];
            }),
        ]);
    }
}
