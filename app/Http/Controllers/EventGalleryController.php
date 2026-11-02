<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoCapture;
use App\Models\VisitorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventGalleryController extends Controller
{
    /**
     * Display public shareable gallery of all booth & raw photos for an event.
     */
    public function show(Request $request, string $slug): Response
    {
        $event = Event::where('slug', $slug)->where('status', '!=', 'archived')->firstOrFail();

        $boothCount = Photo::whereHas('session', fn ($q) => $q->where('event_id', $event->id))->count();
        $rawCount = PhotoCapture::whereHas('session', fn ($q) => $q->where('event_id', $event->id))->count();
        $visitorsCount = $event->visitors()->count();

        $sessions = VisitorSession::where('event_id', $event->id)
            ->where(fn ($q) => $q->has('photo')->orHas('captures'))
            ->with([
                'visitor:id,name,phone_number',
                'photo',
                'captures',
                'frameTemplate:id,name,orientation',
            ])
            ->latest()
            ->get();

        $items = [];

        foreach ($sessions as $session) {
            $visitorName = $session->visitor?->name ?: 'Pengunjung Anonim';
            $sessionDate = $session->created_at->format('d M Y, H:i');

            // 1. Final Framed Booth Photo
            if ($session->photo) {
                $items[] = [
                    'id' => $session->photo->id,
                    'type' => 'booth',
                    'session_id' => $session->id,
                    'session_number' => $session->session_number,
                    'visitor_name' => $visitorName,
                    'created_at' => $sessionDate,
                    'timestamp' => $session->created_at->timestamp,
                    'file_size_bytes' => $session->photo->file_size_bytes,
                    'template_name' => $session->frameTemplate?->name,
                    'imageUrl' => route('events.gallery.photo.image', ['slug' => $event->slug, 'photo' => $session->photo->id]),
                    'downloadUrl' => route('events.gallery.photo.download', ['slug' => $event->slug, 'photo' => $session->photo->id]),
                ];
            }

            // 2. Raw Camera Captures (Foto Mentah)
            foreach ($session->captures as $capture) {
                $items[] = [
                    'id' => $capture->id,
                    'type' => 'raw',
                    'slot_number' => $capture->slot_number,
                    'session_id' => $session->id,
                    'session_number' => $session->session_number,
                    'visitor_name' => $visitorName,
                    'created_at' => $sessionDate,
                    'timestamp' => $capture->created_at->timestamp,
                    'file_size_bytes' => 0,
                    'template_name' => null,
                    'imageUrl' => route('events.gallery.capture.image', ['slug' => $event->slug, 'capture' => $capture->id]),
                    'downloadUrl' => route('events.gallery.capture.download', ['slug' => $event->slug, 'capture' => $capture->id]),
                ];
            }
        }

        // Sort all items newest first
        usort($items, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return Inertia::render('Gallery/Event', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'location' => $event->location,
                'start_date' => $event->start_date?->format('d M Y'),
                'share_url' => url("/e/{$event->slug}"),
                'booth_url' => url("/booth/{$event->slug}"),
            ],
            'stats' => [
                'booth_count' => $boothCount,
                'raw_count' => $rawCount,
                'total_count' => $boothCount + $rawCount,
                'visitors_count' => $visitorsCount,
            ],
            'items' => $items,
            'downloadZipUrls' => [
                'all' => route('events.gallery.zip', ['slug' => $event->slug, 'type' => 'all']),
                'booth' => route('events.gallery.zip', ['slug' => $event->slug, 'type' => 'booth']),
                'raw' => route('events.gallery.zip', ['slug' => $event->slug, 'type' => 'raw']),
            ],
        ]);
    }

    /**
     * Stream booth composite photo securely.
     */
    public function photoImage(string $slug, Photo $photo): BinaryFileResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        if ($photo->session?->event_id !== $event->id) {
            abort(404, 'Foto tidak ditemukan pada event ini.');
        }

        $path = Storage::disk('local')->path($photo->final_image_path);
        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Download booth composite photo.
     */
    public function photoDownload(string $slug, Photo $photo): BinaryFileResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        if ($photo->session?->event_id !== $event->id) {
            abort(404, 'Foto tidak ditemukan pada event ini.');
        }

        $path = Storage::disk('local')->path($photo->final_image_path);
        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan.');
        }

        $eventSlug = Str::slug($event->name);
        $visitorName = Str::slug($photo->session?->visitor?->name ?: 'visitor');
        $sessionNum = sprintf('%03d', $photo->session?->session_number ?: 1);
        $date = $photo->created_at->format('Ymd_His');
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $downloadName = "{$eventSlug}_{$sessionNum}_{$visitorName}_Booth_{$date}.{$ext}";

        return response()->download($path, $downloadName);
    }

    /**
     * Stream raw capture photo securely.
     */
    public function captureImage(string $slug, PhotoCapture $capture): BinaryFileResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        if ($capture->session?->event_id !== $event->id) {
            abort(404, 'Foto mentah tidak ditemukan pada event ini.');
        }

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Download raw capture photo.
     */
    public function captureDownload(string $slug, PhotoCapture $capture): BinaryFileResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        if ($capture->session?->event_id !== $event->id) {
            abort(404, 'Foto mentah tidak ditemukan pada event ini.');
        }

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan.');
        }

        $eventSlug = Str::slug($event->name);
        $visitorName = Str::slug($capture->session?->visitor?->name ?: 'visitor');
        $sessionNum = sprintf('%03d', $capture->session?->session_number ?: 1);
        $date = $capture->created_at->format('Ymd_His');
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $downloadName = "{$eventSlug}_{$sessionNum}_{$visitorName}_Mentah_Pose{$capture->slot_number}_{$date}.{$ext}";

        return response()->download($path, $downloadName);
    }

    /**
     * Download all event photos as ZIP (both booth, raw, or combined).
     */
    public function downloadZip(Request $request, string $slug): BinaryFileResponse|RedirectResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $type = $request->query('type', 'all'); // 'all', 'booth', 'raw'

        $sessions = VisitorSession::where('event_id', $event->id)
            ->with(['visitor', 'photo', 'captures'])
            ->get();

        if ($sessions->isEmpty()) {
            return back()->with('error', 'Belum ada foto yang tersedia untuk diunduh.');
        }

        $zipDir = storage_path('app/temp');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $dateTag = date('Ymd_His');
        $typeLabel = match ($type) {
            'booth' => 'Foto_Booth',
            'raw' => 'Foto_Mentah',
            default => 'Semua_Foto_Booth_Dan_Mentah',
        };
        $zipFileName = Str::slug($event->name) . "_{$typeLabel}_{$dateTag}.zip";
        $zipPath = $zipDir . '/' . $zipFileName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat file arsip ZIP.');
        }

        $addedCount = 0;

        foreach ($sessions as $index => $session) {
            $visitorName = Str::slug($session->visitor?->name ?: 'visitor');
            $sessionNum = sprintf('%03d', $session->session_number ?: ($index + 1));
            $dateStr = $session->created_at->format('Ymd_His');

            // Add booth photo
            if (in_array($type, ['all', 'booth']) && $session->photo) {
                $filePath = Storage::disk('local')->path($session->photo->final_image_path);
                if (file_exists($filePath)) {
                    $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
                    $folder = ($type === 'all') ? 'Foto_Booth/' : '';
                    $entryName = "{$folder}{$sessionNum}_{$visitorName}_Booth_{$dateStr}.{$ext}";
                    $zip->addFile($filePath, $entryName);
                    $addedCount++;
                }
            }

            // Add raw captures
            if (in_array($type, ['all', 'raw'])) {
                foreach ($session->captures as $capture) {
                    $rawPath = Storage::disk('local')->path($capture->file_path);
                    if (file_exists($rawPath)) {
                        $ext = pathinfo($rawPath, PATHINFO_EXTENSION) ?: 'jpg';
                        $folder = ($type === 'all') ? 'Foto_Mentah/' : '';
                        $entryName = "{$folder}{$sessionNum}_{$visitorName}_Pose{$capture->slot_number}_{$dateStr}.{$ext}";
                        $zip->addFile($rawPath, $entryName);
                        $addedCount++;
                    }
                }
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return back()->with('error', 'Tidak ada file fisik foto yang ditemukan pada server.');
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
