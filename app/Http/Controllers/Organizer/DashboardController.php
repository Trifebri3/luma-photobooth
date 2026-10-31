<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDevice;
use App\Models\Photo;
use App\Models\Visitor;
use App\Models\WhatsAppLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $eventsQuery = $user->isSuperAdmin() ? Event::query() : $user->events();

        $totalEvents = (clone $eventsQuery)->count();
        $eventIds = (clone $eventsQuery)->pluck('id');

        $totalVisitors = Visitor::whereIn('event_id', $eventIds)->count();
        $totalDevices = EventDevice::whereIn('event_id', $eventIds)->count();
        $activeDevices = EventDevice::whereIn('event_id', $eventIds)->where('is_active', true)->count();

        $totalPhotos = Photo::whereHas('session', function ($query) use ($eventIds) {
            $query->whereIn('event_id', $eventIds);
        })->count();

        $totalWaDelivered = WhatsAppLog::whereHas('session', function ($query) use ($eventIds) {
            $query->whereIn('event_id', $eventIds);
        })->where('status', 'delivered')->count();

        $recentEvents = (clone $eventsQuery)
            ->withCount(['visitors', 'sessions', 'devices'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Organizer/Dashboard', [
            'stats' => [
                'total_events' => $totalEvents,
                'total_visitors' => $totalVisitors,
                'total_photos' => $totalPhotos,
                'total_devices' => $totalDevices,
                'active_devices' => $activeDevices,
                'wa_delivered' => $totalWaDelivered,
            ],
            'recentEvents' => $recentEvents,
        ]);
    }
}

