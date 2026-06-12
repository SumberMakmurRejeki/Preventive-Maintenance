<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarEventsRequest;
use App\Services\Auth\PrimeAuthService;
use App\Services\Calendar\CalendarEventService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected CalendarEventService $calendarEventService,
    ) {
    }

    public function index(Request $request): View
    {
        $role = $this->primeAuth->role($request);

        return view('pages.calendar.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $role,
            'showOperatorBottomActions' => false,
            'filterOptions' => $this->calendarEventService->filterOptions(),
        ]);
    }

    public function events(CalendarEventsRequest $request): JsonResponse
    {
        $events = $this->calendarEventService->events(
            role: (string) $this->primeAuth->role($request),
            filters: $request->validated(),
        );

        return response()->json($events);
    }
}
