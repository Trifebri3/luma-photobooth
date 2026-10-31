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
            'layout_preset' => ['nullable', 'string', 'in:strip_3,grid_4,duo_2,polaroid_1,strip_4,custom'],
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

        // Generate smart default slot placements based on layout preset
        $preset = $validated['layout_preset'] ?? null;
        $count = $validated['slot_count'];
        $w = $validated['canvas_width'];
        $h = $validated['canvas_height'];

        if ($preset === 'grid_4' || ($count === 4 && $validated['orientation'] === 'portrait')) {
            // 2x2 Grid Layout
            $slotW = floor(($w - 180) / 2);
            $slotH = floor(($h - 260) / 2);
            $positions = [
                ['x' => 60, 'y' => 80],
                ['x' => 120 + $slotW, 'y' => 80],
                ['x' => 60, 'y' => 140 + $slotH],
                ['x' => 120 + $slotW, 'y' => 140 + $slotH],
            ];
            foreach ($positions as $idx => $pos) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $idx + 1,
                    'x_position' => (int) $pos['x'],
                    'y_position' => (int) $pos['y'],
                    'width' => (int) $slotW,
                    'height' => (int) $slotH,
                    'aspect_ratio' => '3:4',
                ]);
            }
        } elseif ($preset === 'polaroid_1' || $count === 1) {
            // 1 Large Center Polaroid Slot
            $side = min($w - 160, $h - 350);
            FrameSlot::create([
                'frame_template_id' => $template->id,
                'slot_number' => 1,
                'x_position' => (int) floor(($w - $side) / 2),
                'y_position' => 100,
                'width' => (int) $side,
                'height' => (int) $side,
                'aspect_ratio' => '1:1',
            ]);
        } elseif ($preset === 'duo_2' || $count === 2) {
            // 2 Stacked Slots
            $slotH = floor(($h - 240) / 2);
            $slotW = $w - 140;
            for ($i = 1; $i <= 2; $i++) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $i,
                    'x_position' => 70,
                    'y_position' => 80 + (($i - 1) * ($slotH + 50)),
                    'width' => (int) $slotW,
                    'height' => (int) $slotH,
                    'aspect_ratio' => '16:9',
                ]);
            }
        } elseif ($validated['orientation'] === 'strip' || $preset === 'strip_3' || $preset === 'strip_4') {
            $slotH = floor(($h - 120 - (($count - 1) * 30)) / $count);
            $slotW = $w - 80;
            for ($i = 1; $i <= $count; $i++) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $i,
                    'x_position' => 40,
                    'y_position' => 60 + (($i - 1) * ($slotH + 30)),
                    'width' => (int) $slotW,
                    'height' => (int) $slotH,
                    'aspect_ratio' => '4:3',
                ]);
            }
        } else {
            // Default vertical arrangement
            $slotH = floor(($h - 200 - (($count - 1) * 40)) / $count);
            $slotW = floor($w - 160);
            for ($i = 1; $i <= $count; $i++) {
                FrameSlot::create([
                    'frame_template_id' => $template->id,
                    'slot_number' => $i,
                    'x_position' => 80,
                    'y_position' => 100 + (($i - 1) * ($slotH + 40)),
                    'width' => (int) $slotW,
                    'height' => (int) $slotH,
                    'aspect_ratio' => '16:9',
                ]);
            }
        }

        return back()->with('success', "Template '{$template->name}' dengan frame PNG berhasil ditambahkan.");
    }

    public function updateOverlay(Request $request, Event $event, FrameTemplate $template): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $request->validate([
            'overlay_image' => ['required', 'image', 'mimes:png', 'max:8192'],
        ]);

        $overlayPath = $request->file('overlay_image')->store('public/frames/overlays');
        $overlayUrl = Storage::url($overlayPath);

        $template->update([
            'overlay_image_path' => $overlayUrl,
        ]);

        return back()->with('success', 'Frame PNG transparan berhasil diperbarui!');
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

