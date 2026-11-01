<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventDevice;
use App\Models\Photo;
use App\Models\PhotoAccessToken;
use App\Models\PhotoCapture;
use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BoothController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Show General Universal Kiosk Photobooth Activation interface.
     */
    public function general(Request $request): Response
    {
        $lastPairedSlug = $request->session()->get('luma_last_paired_slug');
        $lastPairedEvent = null;

        if ($lastPairedSlug) {
            $event = Event::where('slug', $lastPairedSlug)->where('status', '!=', 'archived')->first();
            if ($event) {
                $token = $request->cookie("luma_device_{$event->slug}")
                    ?? $request->session()->get("luma_device_{$event->slug}");
                $device = $token ? EventDevice::where('event_id', $event->id)->where('device_token', $token)->first() : null;
                $lastPairedEvent = [
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'device_name' => $device?->device_name,
                ];
            }
        }

        return Inertia::render('Booth/General', [
            'lastPairedEvent' => $lastPairedEvent,
        ]);
    }

    /**
     * Universal device pairing by activation code:
     * Identifies the event from code, pairs the device, and returns redirect URL.
     */
    public function pairGeneral(Request $request): JsonResponse
    {
        $request->validate([
            'activation_code' => ['required', 'string'],
        ]);

        $code = strtoupper(trim($request->input('activation_code')));

        $device = EventDevice::with('event')->where('activation_code', $code)->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Kode aktivasi tidak valid atau tidak ditemukan.',
            ], 422);
        }

        $event = $device->event;

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event terkait perangkat ini tidak ditemukan.',
            ], 404);
        }

        if ($event->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => "Event '{$event->name}' sudah diarsipkan dan tidak dapat digunakan.",
            ], 422);
        }

        $token = Str::random(60);

        $device->update([
            'device_token' => $token,
            'is_active' => true,
            'ip_address' => $request->ip(),
            'last_heartbeat_at' => now(),
        ]);

        $cookie = cookie("luma_device_{$event->slug}", $token, 60 * 24 * 30); // 30 days
        $request->session()->put("luma_device_{$event->slug}", $token);
        $request->session()->put("luma_last_paired_slug", $event->slug);

        return response()->json([
            'success' => true,
            'message' => "Perangkat berhasil dihubungkan ke event '{$event->name}'!",
            'event' => [
                'name' => $event->name,
                'slug' => $event->slug,
                'device_name' => $device->device_name,
            ],
            'redirect_url' => url("/booth/{$event->slug}"),
        ])->withCookie($cookie);
    }

    /**
     * Show Kiosk Photobooth interface.
     */
    public function show(Request $request, string $slug): Response
    {
        $event = Event::with([
            'settings',
            'frameTemplates' => fn ($query) => $query->where('is_active', true)->with('slots'),
        ])->where('slug', $slug)->firstOrFail();

        // Auto-heal templates: sync real uploaded frame overlay image dimensions to template canvas
        foreach ($event->frameTemplates as $template) {
            if (!empty($template->overlay_image_path) && str_starts_with($template->overlay_image_path, '/frames/uploads/')) {
                $filePath = public_path(ltrim($template->overlay_image_path, '/'));
                if (file_exists($filePath)) {
                    $size = @getimagesize($filePath);
                    if ($size && $size[0] > 0 && $size[1] > 0) {
                        $imgW = (int) $size[0];
                        $imgH = (int) $size[1];
                        if ($template->canvas_width !== $imgW || $template->canvas_height !== $imgH) {
                            $oldW = (int) $template->canvas_width ?: $imgW;
                            $oldH = (int) $template->canvas_height ?: $imgH;
                            $scaleX = $imgW / $oldW;
                            $scaleY = $imgH / $oldH;
                            foreach ($template->slots as $slot) {
                                $slot->update([
                                    'x_position' => (int) round($slot->x_position * $scaleX),
                                    'y_position' => (int) round($slot->y_position * $scaleY),
                                    'width' => (int) round($slot->width * $scaleX),
                                    'height' => (int) round($slot->height * $scaleY),
                                ]);
                            }
                            $template->update([
                                'canvas_width' => $imgW,
                                'canvas_height' => $imgH,
                                'orientation' => ($imgH >= $imgW * 2.2) ? 'strip' : (($imgH >= $imgW) ? 'portrait' : 'landscape'),
                            ]);
                            $template->refresh();
                        }
                    }
                }
            }
        }

        // Check Device Pairing
        $deviceToken = $request->header('X-Device-Token') 
            ?? $request->cookie("luma_device_{$event->slug}")
            ?? $request->session()->get("luma_device_{$event->slug}");

        $device = null;
        $isPaired = false;

        if ($deviceToken) {
            $device = EventDevice::where('event_id', $event->id)
                ->where('device_token', $deviceToken)
                ->first();

            if ($device) {
                $isPaired = true;
                $device->update([
                    'last_heartbeat_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        return Inertia::render('Booth/Index', [
            'event' => $event,
            'isPaired' => $isPaired,
            'device' => $device,
        ]);
    }

    /**
     * Pair kiosk device with activation code.
     */
    public function pair(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'activation_code' => ['required', 'string'],
        ]);

        $event = Event::where('slug', $slug)->firstOrFail();

        $code = strtoupper(trim($request->input('activation_code')));

        $device = EventDevice::where('event_id', $event->id)
            ->where('activation_code', $code)
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Kode aktivasi tidak valid untuk event ini.',
            ], 422);
        }

        $token = Str::random(60);

        $device->update([
            'device_token' => $token,
            'is_active' => true,
            'ip_address' => $request->ip(),
            'last_heartbeat_at' => now(),
        ]);

        $cookie = cookie("luma_device_{$event->slug}", $token, 60 * 24 * 30); // 30 days
        $request->session()->put("luma_device_{$event->slug}", $token);

        return response()->json([
            'success' => true,
            'message' => 'Perangkat berhasil terhubung!',
            'device_token' => $token,
            'device_name' => $device->device_name,
        ])->withCookie($cookie);
    }

    /**
     * Register visitor and initialize photobooth session.
     */
    public function registerVisitor(Request $request, string $slug): JsonResponse
    {
        $event = Event::with('settings')->where('slug', $slug)->firstOrFail();
        $settings = $event->settings;

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'phone_number' => [$settings?->require_whatsapp ? 'required' : 'nullable', 'string', 'max:30'],
            'email' => [$settings?->require_email ? 'required' : 'nullable', 'email', 'max:150'],
        ];

        $request->validate($rules);

        $phone = preg_replace('/[^0-9]/', '', (string) $request->input('phone_number'));

        // Check if visitor already exists in this event
        $visitor = Visitor::where('event_id', $event->id)
            ->where(function ($query) use ($phone, $request) {
                if (! empty($phone)) {
                    $query->where('phone_number', $phone);
                } elseif ($request->filled('email')) {
                    $query->where('email', $request->input('email'));
                }
            })
            ->first();

        if (! $visitor) {
            $visitor = Visitor::create([
                'event_id' => $event->id,
                'uuid' => (string) Str::uuid(),
                'name' => $request->input('name'),
                'phone_number' => $phone ?: '-',
                'email' => $request->input('email'),
                'sessions_used' => 0,
                'consent_agreed_at' => now(),
            ]);
        }

        // Validate quota
        $maxSessions = $settings?->max_sessions_per_visitor ?? 3;
        $isUnlimited = (bool) ($settings?->is_unlimited_session ?? false);

        if (! $isUnlimited && $visitor->sessions_used >= $maxSessions) {
            return response()->json([
                'success' => false,
                'message' => "Kuota sesi untuk nomor ini telah habis ({$visitor->sessions_used}/{$maxSessions} sesi terpakai).",
                'quota_exceeded' => true,
            ], 422);
        }

        // Get device id if paired
        $deviceToken = $request->cookie("luma_device_{$event->slug}")
            ?? $request->session()->get("luma_device_{$event->slug}");

        $device = $deviceToken
            ? EventDevice::where('event_id', $event->id)->where('device_token', $deviceToken)->first()
            : null;

        // Create new session
        $sessionNumber = $visitor->sessions_used + 1;
        $session = VisitorSession::create([
            'event_id' => $event->id,
            'visitor_id' => $visitor->id,
            'event_device_id' => $device?->id,
            'session_number' => $sessionNumber,
            'status' => 'capturing',
        ]);

        $visitor->increment('sessions_used');

        return response()->json([
            'success' => true,
            'visitor' => $visitor,
            'session_id' => $session->id,
            'session_number' => $sessionNumber,
            'total_allowed_sessions' => $isUnlimited ? 'Unlimited' : $maxSessions,
            'remaining_sessions' => $isUnlimited ? 999 : max(0, $maxSessions - $visitor->sessions_used),
        ]);
    }

    /**
     * Finish session: save rendered composite image & generate private access token.
     */
    public function finishSession(Request $request, string $slug, VisitorSession $session): JsonResponse
    {
        $event = Event::with('settings')->where('slug', $slug)->firstOrFail();

        $request->validate([
            'final_image' => ['required', 'string'], // base64 data URL
            'frame_template_id' => ['nullable', 'exists:frame_templates,id'],
            'raw_captures' => ['nullable', 'array'],
        ]);

        $finalImageData = $request->input('final_image');

        if (preg_match('/^data:image\/(\w+);base64,/', $finalImageData, $type)) {
            $finalImageData = substr($finalImageData, strpos($finalImageData, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, etc.
        } else {
            $type = 'jpg';
        }

        $decodedImage = base64_decode($finalImageData);

        if ($decodedImage === false) {
            return response()->json(['success' => false, 'message' => 'Format gambar tidak valid.'], 422);
        }

        // Save into private storage (Anti-Bocor)
        $yearMonth = date('Y/m');
        $fileName = 'photo_' . Str::random(32) . '.' . ($type === 'png' ? 'png' : 'jpg');
        $relativePath = "private/events/{$event->id}/photos/{$yearMonth}/{$fileName}";

        Storage::disk('local')->put($relativePath, $decodedImage);

        // Record Photo
        $photo = Photo::create([
            'visitor_session_id' => $session->id,
            'final_image_path' => $relativePath,
            'file_size_bytes' => strlen($decodedImage),
        ]);

        // Save raw captures if provided
        $rawCaptures = $request->input('raw_captures', []);
        foreach ($rawCaptures as $index => $rawBase64) {
            if (preg_match('/^data:image\/(\w+);base64,/', $rawBase64)) {
                $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
            }
            $rawDecoded = base64_decode($rawBase64);
            if ($rawDecoded) {
                $rawFileName = 'raw_s' . ($index + 1) . '_' . Str::random(24) . '.jpg';
                $rawPath = "private/events/{$event->id}/raw/{$yearMonth}/{$rawFileName}";
                Storage::disk('local')->put($rawPath, $rawDecoded);

                PhotoCapture::create([
                    'visitor_session_id' => $session->id,
                    'slot_number' => $index + 1,
                    'file_path' => $rawPath,
                    'width' => 1280,
                    'height' => 720,
                ]);
            }
        }

        // Update session
        $session->update([
            'frame_template_id' => $request->input('frame_template_id'),
            'status' => 'rendered',
        ]);

        // Generate Private Security Token (Anti-Bocor)
        $token = Str::random(48);
        $expiryDays = $event->settings?->token_expiry_days ?? 30;

        $accessToken = PhotoAccessToken::create([
            'photo_id' => $photo->id,
            'visitor_id' => $session->visitor_id,
            'token' => $token,
            'expires_at' => now()->addDays($expiryDays),
        ]);

        $galleryUrl = url("/p/{$token}");

        // Dispatch WhatsApp notification
        if ($event->settings?->enable_whatsapp_delivery && $session->visitor?->phone_number) {
            $this->whatsAppService->sendPhotoGalleryLink($session, $token, $galleryUrl);
            $session->update(['status' => 'delivered']);
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'gallery_url' => $galleryUrl,
            'download_url' => url("/p/{$token}/download"),
        ]);
    }
}

