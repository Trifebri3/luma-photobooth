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

        // Auto-heal any template with missing, broken overlay paths, or mismatched dimensions
        foreach ($templates as $t) {
            if (empty($t->overlay_image_path) || str_starts_with($t->overlay_image_path, 'public/') || str_starts_with($t->overlay_image_path, '/storage/public/')) {
                $count = $t->slots->count();
                if ($t->orientation === 'strip') {
                    $newPath = ($count === 4) ? '/frames/retro-strip-4.png' : '/frames/kawaii-strip-3.png';
                } elseif ($count === 1) {
                    $newPath = '/frames/polaroid-doodle-1.png';
                } elseif ($count === 2) {
                    $newPath = '/frames/cute-duo-2.png';
                } else {
                    $newPath = '/frames/y2k-grid-4.png';
                }
                $t->update(['overlay_image_path' => $newPath]);
            } elseif (str_starts_with($t->overlay_image_path, '/frames/uploads/')) {
                $filePath = public_path(ltrim($t->overlay_image_path, '/'));
                if (file_exists($filePath)) {
                    $size = @getimagesize($filePath);
                    if ($size && $size[0] > 0 && $size[1] > 0) {
                        $imgW = (int) $size[0];
                        $imgH = (int) $size[1];
                        if ($t->canvas_width !== $imgW || $t->canvas_height !== $imgH) {
                            $oldW = (int) $t->canvas_width ?: $imgW;
                            $oldH = (int) $t->canvas_height ?: $imgH;
                            $scaleX = $imgW / $oldW;
                            $scaleY = $imgH / $oldH;
                            foreach ($t->slots as $s) {
                                $s->update([
                                    'x_position' => (int) round($s->x_position * $scaleX),
                                    'y_position' => (int) round($s->y_position * $scaleY),
                                    'width' => (int) round($s->width * $scaleX),
                                    'height' => (int) round($s->height * $scaleY),
                                ]);
                            }
                            $t->update([
                                'canvas_width' => $imgW,
                                'canvas_height' => $imgH,
                                'orientation' => ($imgH >= $imgW * 2.2) ? 'strip' : (($imgH >= $imgW) ? 'portrait' : 'landscape'),
                            ]);
                        }
                    }
                }
            }
        }

        return Inertia::render('Organizer/Events/Studio', [
            'event' => $event,
            'templates' => $event->frameTemplates()->with('slots')->get(),
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

        $preset = $validated['layout_preset'] ?? null;
        $overlayPath = null;

        if ($request->hasFile('overlay_image')) {
            $file = $request->file('overlay_image');
            $uploadDir = public_path('frames/uploads');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = Str::uuid() . '.png';
            $file->move($uploadDir, $filename);
            $overlayPath = '/frames/uploads/' . $filename;

            // Automatically detect true image resolution so frame is never stretched
            $size = @getimagesize($uploadDir . '/' . $filename);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $validated['canvas_width'] = (int) $size[0];
                $validated['canvas_height'] = (int) $size[1];
                $validated['orientation'] = ($size[1] >= $size[0] * 2.2) ? 'strip' : (($size[1] >= $size[0]) ? 'portrait' : 'landscape');
            }
        } elseif ($preset) {
            $presetFrames = [
                'strip_3' => '/frames/kawaii-strip-3.png',
                'grid_4' => '/frames/y2k-grid-4.png',
                'duo_2' => '/frames/cute-duo-2.png',
                'polaroid_1' => '/frames/polaroid-doodle-1.png',
                'strip_4' => '/frames/retro-strip-4.png',
            ];
            $overlayPath = $presetFrames[$preset] ?? '/frames/kawaii-strip-3.png';
        } else {
            if ($validated['orientation'] === 'strip') {
                $overlayPath = ($validated['slot_count'] == 4) ? '/frames/retro-strip-4.png' : '/frames/kawaii-strip-3.png';
            } elseif ($validated['slot_count'] == 1) {
                $overlayPath = '/frames/polaroid-doodle-1.png';
            } elseif ($validated['slot_count'] == 2) {
                $overlayPath = '/frames/cute-duo-2.png';
            } else {
                $overlayPath = '/frames/y2k-grid-4.png';
            }
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

        $file = $request->file('overlay_image');
        $uploadDir = public_path('frames/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = Str::uuid() . '.png';
        $file->move($uploadDir, $filename);
        $overlayUrl = '/frames/uploads/' . $filename;

        $updateData = [
            'overlay_image_path' => $overlayUrl,
        ];

        // Read real image resolution and adjust canvas & slots proportionally
        $size = @getimagesize($uploadDir . '/' . $filename);
        if ($size && $size[0] > 0 && $size[1] > 0) {
            $newW = (int) $size[0];
            $newH = (int) $size[1];
            $oldW = (int) $template->canvas_width ?: $newW;
            $oldH = (int) $template->canvas_height ?: $newH;

            if ($oldW > 0 && $oldH > 0 && ($oldW !== $newW || $oldH !== $newH)) {
                $scaleX = $newW / $oldW;
                $scaleY = $newH / $oldH;

                foreach ($template->slots as $slot) {
                    $slot->update([
                        'x_position' => (int) round($slot->x_position * $scaleX),
                        'y_position' => (int) round($slot->y_position * $scaleY),
                        'width' => (int) round($slot->width * $scaleX),
                        'height' => (int) round($slot->height * $scaleY),
                    ]);
                }

                $updateData['canvas_width'] = $newW;
                $updateData['canvas_height'] = $newH;
                $updateData['orientation'] = ($newH >= $newW * 2.2) ? 'strip' : (($newH >= $newW) ? 'portrait' : 'landscape');
            }
        }

        $template->update($updateData);

        return back()->with('success', 'Frame PNG transparan berhasil diperbarui!');
    }

    public function downloadGuide(Request $request, Event $event, FrameTemplate $template)
    {
        $this->authorizeEvent($request, $event);

        $w = (int) $template->canvas_width;
        $h = (int) $template->canvas_height;

        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        // Clean white background
        $white = imagecolorallocatealpha($im, 255, 255, 255, 0);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);
        imagealphablending($im, true);

        // Guide Colors
        $teal = imagecolorallocate($im, 13, 148, 136);       // #0D9488
        $softTeal = imagecolorallocatealpha($im, 13, 148, 136, 110);
        $slateDark = imagecolorallocate($im, 15, 23, 42);    // #0F172A
        $slateLight = imagecolorallocate($im, 100, 116, 139); // #64748B

        // Outer border
        imagesetthickness($im, 4);
        imagerectangle($im, 4, 4, $w - 4, $h - 4, $teal);

        // Top Guide Header
        imagestring($im, 5, 20, 20, "ACUAN DESAIN FRAME: " . strtoupper($template->name), $slateDark);
        imagestring($im, 3, 20, 42, "Ukuran Canvas: {$w} x {$h} px | Format Ekspor: PNG 24-bit Transparan", $teal);
        imagestring($im, 2, 20, 60, "PETUNJUK: Area kotak slot foto harus DIBUAT TRANSPARAN / BOLONG saat diekspor.", $slateLight);

        // Draw Slots
        foreach ($template->slots as $slot) {
            $sx = (int) $slot->x_position;
            $sy = (int) $slot->y_position;
            $sw = (int) $slot->width;
            $sh = (int) $slot->height;

            // Fill slot with soft translucent tint
            imagefilledrectangle($im, $sx, $sy, $sx + $sw, $sy + $sh, $softTeal);

            // Border
            imagesetthickness($im, 3);
            imagerectangle($im, $sx, $sy, $sx + $sw, $sy + $sh, $teal);

            // Diagonal lines
            imageline($im, $sx, $sy, $sx + $sw, $sy + $sh, $softTeal);
            imageline($im, $sx, $sy + $sh, $sx + $sw, $sy, $softTeal);

            // Labels
            $label = "AREA FOTO SLOT #" . $slot->slot_number;
            $sizeStr = "{$sw} x {$sh} px ({$slot->aspect_ratio})";
            $note = "[ BUAT TRANSPARAN / BOLONG ]";

            $cx = $sx + ($sw / 2);
            $cy = $sy + ($sh / 2);

            imagestring($im, 5, max($sx + 10, (int)($cx - (strlen($label) * 4.5))), (int)($cy - 25), $label, $teal);
            imagestring($im, 4, max($sx + 10, (int)($cx - (strlen($sizeStr) * 4))), (int)($cy), $sizeStr, $slateDark);
            imagestring($im, 3, max($sx + 10, (int)($cx - (strlen($note) * 3.5))), (int)($cy + 22), $note, $slateLight);
        }

        // Footer Branding
        $footer = "Luma Photobooth by YOTA INOVASI NUSANTARA";
        imagestring($im, 3, (int)(($w - (strlen($footer) * 7)) / 2), $h - 30, $footer, $slateLight);

        ob_start();
        imagepng($im);
        $imageData = ob_get_clean();
        imagedestroy($im);

        $safeName = Str::slug($template->name) . "-{$w}x{$h}-acuan-desain.png";

        return response($imageData, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
        ]);
    }

    public function updateSlots(Request $request, Event $event, FrameTemplate $template): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $request->validate([
            'slots' => ['required', 'array'],
            'slots.*.id' => ['nullable', 'numeric'],
            'slots.*.slot_number' => ['required', 'numeric'],
            'slots.*.x_position' => ['required', 'numeric'],
            'slots.*.y_position' => ['required', 'numeric'],
            'slots.*.width' => ['required', 'numeric'],
            'slots.*.height' => ['required', 'numeric'],
            'slots.*.aspect_ratio' => ['nullable', 'string'],
        ]);

        $slots = $request->input('slots');

        // Delete old slots and re-insert or update
        FrameSlot::where('frame_template_id', $template->id)->delete();

        $savedSlots = [];
        foreach ($slots as $slotData) {
            $savedSlots[] = FrameSlot::create([
                'frame_template_id' => $template->id,
                'slot_number' => (int) round($slotData['slot_number']),
                'x_position' => (int) round($slotData['x_position']),
                'y_position' => (int) round($slotData['y_position']),
                'width' => (int) round($slotData['width']),
                'height' => (int) round($slotData['height']),
                'aspect_ratio' => $slotData['aspect_ratio'] ?? '4:3',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Koordinat slot foto berhasil disimpan!',
            'slots' => $savedSlots,
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

