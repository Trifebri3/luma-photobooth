<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FrameSlot;
use App\Models\FrameTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FrameStudioController extends Controller
{
    public function index(Request $request, Event $event): Response
    {
        $this->authorizeEvent($request, $event);

        $templates = $event->frameTemplates()->with('slots')->get();

        return Inertia::render('Organizer/Events/Studio', [
            'event' => $event,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'orientation' => ['required', 'in:portrait,landscape,strip'],
            'canvas_width' => ['required', 'integer', 'min:400', 'max:3840'],
            'canvas_height' => ['required', 'integer', 'min:400', 'max:3840'],
            'overlay_image' => ['nullable', 'image', 'mimes:png', 'max:8192'], // PNG overlay max 8MB
            'slot_count' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $overlayPath = null;
        if ($request->hasFile('overlay_image')) {
            $overlayPath = $request->file('overlay_image')->store('public/frames/overlays');
            $overlayPath = Storage::url($overlayPath);
        }

        $template = FrameTemplate::create([
            'event_id' => $event->id,
            'name' => $validated['name'],
            'orientation' => $validated['orientation'],
            'canvas_width' => $validated['canvas_width'],
            'canvas_height' => $validated['canvas_height'],
            'overlay_image_path' => $overlayPath,
            'is_active' => true,
            'sort_order' => $event->frameTemplates()->count() + 1,
        ]);

        // Generate smart default slot placements
        $count = $validated['slot_count'];
        $w = $validated['canvas_width'];
        $h = $validated['canvas_height'];

        if ($validated['orientation'] === 'strip') {
            $slotH = floor(($h - 120 - (($count - 1) * 30)) / $count);
            $slotW = $w - 80;
            for ($i = 1; $i <= $count; $i++) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $i,
                    'x_position' => 40,
                    'y_position' => 60 + (($i - 1) * ($slotH + 30)),
                    'width' => $slotW,
                    'height' => $slotH,
                    'aspect_ratio' => '4:3',
                ]);
            }
        } else {
            // Grid or vertical arrangement
            $slotH = floor(($h - 200 - (($count - 1) * 40)) / $count);
            $slotW = floor($w - 160);
            for ($i = 1; $i <= $count; $i++) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $i,
                    'x_position' => 80,
                    'y_position' => 100 + (($i - 1) * ($slotH + 40)),
                    'width' => $slotW,
                    'height' => $slotH,
                    'aspect_ratio' => '16:9',
                ]);
            }
        }

        return back()->with('success', "Template '{$template->name}' berhasil ditambahkan.");
    }

    public function updateSlots(Request $request, Event $event, FrameTemplate $template): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $request->validate([
            'slots' => ['required', 'array'],
            'slots.*.id' => ['nullable', 'integer'],
            'slots.*.slot_number' => ['required', 'integer'],
            'slots.*.x_position' => ['required', 'integer'],
            'slots.*.y_position' => ['required', 'integer'],
            'slots.*.width' => ['required', 'integer'],
            'slots.*.height' => ['required', 'integer'],
            'slots.*.aspect_ratio' => ['nullable', 'string'],
        ]);

        $slots = $request->input('slots');

        // Delete old slots and re-insert or update
        FrameSlot::where('frame_template_id', $template->id)->delete();

        foreach ($slots as $slotData) {
            FrameSlot::create([
                'frame_template_id' => $template->id,
                'slot_number' => $slotData['slot_number'],
                'x_position' => (int) $slotData['x_position'],
                'y_position' => (int) $slotData['y_position'],
                'width' => (int) $slotData['width'],
                'height' => (int) $slotData['height'],
                'aspect_ratio' => $slotData['aspect_ratio'] ?? '4:3',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Koordinat slot foto berhasil disimpan!',
        ]);
    }

    public function destroy(Request $request, Event $event, FrameTemplate $template): RedirectResponse
    {
        $this->authorizeEvent($request, $event);
        $template->delete();

        return back()->with('success', 'Template frame berhasil dihapus.');
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $event->user_id !== $user->id) {
            abort(403, 'Akses tidak diizinkan.');
        }
    }
}

