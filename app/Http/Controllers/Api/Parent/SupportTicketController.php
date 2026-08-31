<?php

namespace App\Http\Controllers\Api\Parent;

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
            'parent',
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
        $ticket = $this->ticketService->getUserTicketDetail($request->user(), 'parent', $id);

        return response()->json([
            'success' => true,
            'data'    => new SupportTicketResource($ticket),
        ]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket(
            $request->user(),
            'parent',
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

        $message = $this->ticketService->addUserMessage($request->user(), 'parent', $id, $request->input('message'));

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
     * الفواتير والمعاملات المالية السابقة (لواجهة اختيار المرجع عند تذكرة "مالية")
     */
    public function financialHistory(Request $request): JsonResponse
    {
        $history = $this->ticketService->financialHistoryForParent($request->user());

        return response()->json([
            'success' => true,
            'data'    => [
                'invoices'     => $history['invoices'],
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
     * رحلات السائقين السابقة (لواجهة اختيار الرحلة عند تذكرة "سائق/رحلات")
     */
    public function driverTrips(Request $request): JsonResponse
    {
        $trips = $this->ticketService->tripsForParent($request->user());

        return response()->json([
            'success' => true,
            'data'    => $trips->map(fn ($trip) => [
                'id'         => $trip->id,
                'trip_date'  => $trip->trip_date,
                'trip_type'  => $trip->trip_type,
                'status'     => $trip->status,
                'driver'     => $trip->driver ? [
                    'id'        => $trip->driver->id,
                    'user_id'   => $trip->driver->user_id,
                    'full_name' => $trip->driver->user?->full_name,
                ] : null,
            ]),
        ]);
    }
}
