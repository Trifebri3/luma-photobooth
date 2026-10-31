<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDevice;
use App\Models\EventSetting;
use App\Models\FrameSlot;
use App\Models\FrameTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = $user->isSuperAdmin() ? Event::query() : $user->events();

        $events = $query
            ->withCount(['visitors', 'sessions', 'devices', 'frameTemplates'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Organizer/Events/Index', [
            'events' => $events,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Organizer/Events/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // Settings
            'max_sessions_per_visitor' => ['required', 'integer', 'min:1', 'max:10'],
            'photos_per_session' => ['required', 'integer', 'min:1', 'max:6'],
            'countdown_seconds' => ['required', 'integer', 'min:3', 'max:10'],
            'max_retakes' => ['required', 'integer', 'min:0', 'max:5'],
            'is_unlimited_session' => ['boolean'],
            'enable_whatsapp_delivery' => ['boolean'],
        ]);

        $slugBase = Str::slug($validated['name']);
        $slug = $slugBase;
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$counter}";
            $counter++;
        }

        $event = $request->user()->events()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => 'active',
        ]);

        // Create Settings
        EventSetting::create([
            'event_id' => $event->id,
            'max_sessions_per_visitor' => $validated['max_sessions_per_visitor'],
            'photos_per_session' => $validated['photos_per_session'],
            'countdown_seconds' => $validated['countdown_seconds'],
            'max_retakes' => $validated['max_retakes'],
            'is_unlimited_session' => (bool) ($validated['is_unlimited_session'] ?? false),
            'enable_whatsapp_delivery' => (bool) ($validated['enable_whatsapp_delivery'] ?? true),
        ]);

        // Create default first station
        EventDevice::create([
            'event_id' => $event->id,
            'device_name' => 'Photobooth Station #01',
            'activation_code' => strtoupper(Str::random(6)),
            'is_active' => false,
        ]);

        // Create starter frame template
        $template = FrameTemplate::create([
            'event_id' => $event->id,
            'name' => 'Standard 3-Photo Frame',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'orientation' => 'portrait',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Default 3 slots
        for ($i = 1; $i <= 3; $i++) {
            FrameSlot::create([
                'frame_template_id' => $template->id,
                'slot_number' => $i,
                'x_position' => 100,
                'y_position' => 100 + (($i - 1) * 520),
                'width' => 1000,
                'height' => 480,
                'aspect_ratio' => '16:9',
            ]);
        }

        return redirect()->route('organizer.events.show', $event->id)
            ->with('success', 'Event berhasil dibuat beserta pengaturan awal dan perangkat booth!');
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorizeEvent($request, $event);

        $event->load([
            'settings',
            'devices',
            'frameTemplates.slots',
            'visitors' => fn ($q) => $q->latest()->take(20),
            'sessions' => fn ($q) => $q->with(['visitor', 'photo.accessTokens'])->latest()->take(15),
        ]);

        return Inertia::render('Organizer/Events/Show', [
            'event' => $event,
            'boothUrl' => url("/booth/{$event->slug}"),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,active,completed,archived'],
            // Settings
            'max_sessions_per_visitor' => ['required', 'integer', 'min:1'],
            'photos_per_session' => ['required', 'integer', 'min:1'],
            'countdown_seconds' => ['required', 'integer', 'min:3'],
            'max_retakes' => ['required', 'integer', 'min:0'],
            'is_unlimited_session' => ['boolean'],
            'enable_whatsapp_delivery' => ['boolean'],
            'token_expiry_days' => ['required', 'integer', 'min:1'],
        ]);

        $event->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
        ]);

        $event->settings()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'max_sessions_per_visitor' => $validated['max_sessions_per_visitor'],
                'photos_per_session' => $validated['photos_per_session'],
                'countdown_seconds' => $validated['countdown_seconds'],
                'max_retakes' => $validated['max_retakes'],
                'is_unlimited_session' => (bool) ($validated['is_unlimited_session'] ?? false),
                'enable_whatsapp_delivery' => (bool) ($validated['enable_whatsapp_delivery'] ?? true),
                'token_expiry_days' => $validated['token_expiry_days'],
            ]
        );

        return back()->with('success', 'Pengaturan event berhasil diperbarui.');
    }

    public function createDevice(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        EventDevice::create([
            'event_id' => $event->id,
            'device_name' => $request->input('device_name'),
            'activation_code' => strtoupper(Str::random(6)),
            'is_active' => false,
        ]);

        return back()->with('success', 'Perangkat photobooth baru berhasil ditambahkan.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);
        $event->delete();

        return redirect()->route('organizer.events.index')
            ->with('success', 'Event berhasil dihapus.');
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $event->user_id !== $user->id) {
            abort(403, 'Akses tidak diizinkan untuk event ini.');
        }
    }
}

