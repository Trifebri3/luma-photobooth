<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDevice;
use App\Models\Photo;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WhatsAppLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $totalUsers = User::count();
        $totalOrganizers = User::where('role', 'organizer')->count();
        $totalEvents = Event::count();
        $totalVisitors = Visitor::count();
        $totalPhotos = Photo::count();
        $totalDevices = EventDevice::count();
        $totalWaMessages = WhatsAppLog::count();
        $totalWaSuccess = WhatsAppLog::where('status', 'sent')->orWhere('status', 'delivered')->count();

        $organizers = User::where('role', 'organizer')
            ->withCount('events')
            ->latest()
            ->take(10)
            ->get();

        $allEvents = Event::with('user')
            ->withCount(['visitors', 'devices'])
            ->latest()
            ->take(10)
            ->get();

        $recentWaLogs = WhatsAppLog::with('session.event')
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_organizers' => $totalOrganizers,
                'total_events' => $totalEvents,
                'total_visitors' => $totalVisitors,
                'total_photos' => $totalPhotos,
                'total_devices' => $totalDevices,
                'wa_sent' => $totalWaSuccess,
                'wa_total' => $totalWaMessages,
            ],
            'organizers' => $organizers,
            'events' => $allEvents,
            'recentWaLogs' => $recentWaLogs,
            'fonnteConfig' => [
                'sender' => config('services.fonnte.sender', '6285862319524'),
                'token_masked' => substr(config('services.fonnte.token', 'Eeo4Pf43HnDcaeSQmSua'), 0, 6) . '••••••••••',
            ],
        ]);
    }
}

