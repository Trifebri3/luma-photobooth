<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoCapture;
use App\Models\VisitorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PhotoController extends Controller
{
    /**
     * Display a listing of all photos taken across organizer's events.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $eventsQuery = $user->isSuperAdmin() ? Event::query() : $user->events();
        $eventIds = (clone $eventsQuery)->pluck('id');
        $eventsList = (clone $eventsQuery)->select('id', 'name', 'slug')->orderBy('name')->get();

        $query = Photo::query()
            ->whereHas('session', function ($q) use ($eventIds, $request) {
                $q->whereIn('event_id', $eventIds);

                if ($request->filled('event_id')) {
                    $q->where('event_id', $request->input('event_id'));
                }

                if ($request->filled('search')) {
                    $search = trim($request->input('search'));
                    $q->whereHas('visitor', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            })
            ->with([
                'session.event:id,name,slug',
                'session.visitor:id,name,phone_number',
                'session.device:id,device_name',
                'session.frameTemplate:id,name',
                'accessTokens:id,photo_id,token,views_count,downloads_count,expires_at',
            ])
            ->latest();

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $photos = $query->paginate(24)->withQueryString();

        $totalPhotosCount = Photo::whereHas('session', fn ($q) => $q->whereIn('event_id', $eventIds))->count();

        return Inertia::render('Organizer/Photos/Index', [
            'photos' => $photos,
            'eventsList' => $eventsList,
            'filters' => $request->only(['event_id', 'search', 'date']),
            'totalPhotosCount' => $totalPhotosCount,
        ]);
    }

    /**
     * Securely stream image file for authenticated organizer view.
     */
    public function image(Request $request, Photo $photo): BinaryFileResponse
    {
        $this->authorizePhoto($request, $photo);

        $path = Storage::disk('local')->path($photo->final_image_path);
        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Download original composite photo.
     */
    public function download(Request $request, Photo $photo): BinaryFileResponse
    {
        $this->authorizePhoto($request, $photo);

        $path = Storage::disk('local')->path($photo->final_image_path);
        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan pada server.');
        }

        $eventSlug = Str::slug($photo->session?->event?->name ?? 'event');
        $visitorName = Str::slug($photo->session?->visitor?->name ?? 'visitor');
        $date = $photo->created_at->format('Ymd_His');
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = "{$eventSlug}_{$visitorName}_{$date}.{$ext}";

        return response()->download($path, $fileName);
    }

    /**
     * Securely stream raw capture image for authenticated organizer view.
     */
    public function captureImage(Request $request, PhotoCapture $capture): BinaryFileResponse
    {
        $this->authorizeCapture($request, $capture);

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Download original raw capture photo.
     */
    public function captureDownload(Request $request, PhotoCapture $capture): BinaryFileResponse
    {
        $this->authorizeCapture($request, $capture);

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan pada server.');
        }

        $eventSlug = Str::slug($capture->session?->event?->name ?? 'event');
        $visitorName = Str::slug($capture->session?->visitor?->name ?? 'visitor');
        $date = $capture->created_at->format('Ymd_His');
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = "{$eventSlug}_{$visitorName}_Mentah_Pose{$capture->slot_number}_{$date}.{$ext}";

        return response()->download($path, $fileName);
    }

    /**
     * Download all photos of a specific event as a ZIP archive (both booth, raw, or combined).
     */
    public function downloadEventZip(Request $request, Event $event): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $event->user_id !== $user->id) {
            abort(403, 'Akses tidak diizinkan untuk event ini.');
        }

        $type = $request->query('type', 'all'); // 'all', 'booth', 'raw'

        $sessions = VisitorSession::where('event_id', $event->id)
            ->with(['visitor', 'photo', 'captures'])
            ->get();

        if ($sessions->isEmpty()) {
            return back()->with('error', 'Belum ada foto yang tersedia untuk diunduh pada event ini.');
        }

        $dateTag = date('Ymd_His');
        $typeLabel = match ($type) {
            'booth' => 'Foto_Booth',
            'raw' => 'Foto_Mentah',
            default => 'Semua_Foto_Booth_Dan_Mentah',
        };
        $zipFileName = Str::slug($event->name) . "_{$typeLabel}_{$dateTag}.zip";
        $zipDir = storage_path('app/temp');
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }
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

            // 1. Add booth composite photo
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

            // 2. Add raw captures (foto mentah)
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
            return back()->with('error', 'File fisik foto belum tersimpan di server.');
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Delete a photo record and file.
     */
    public function destroy(Request $request, Photo $photo): RedirectResponse
    {
        $this->authorizePhoto($request, $photo);

        if ($photo->final_image_path && Storage::disk('local')->exists($photo->final_image_path)) {
            Storage::disk('local')->delete($photo->final_image_path);
        }

        $photo->delete();

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    /**
     * Authorize that the photo belongs to the authenticated user's event.
     */
    private function authorizePhoto(Request $request, Photo $photo): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $eventOwnerId = $photo->session?->event?->user_id;
        if ($eventOwnerId !== $user->id) {
            abort(403, 'Akses tidak diizinkan untuk foto ini.');
        }
    }

    /**
     * Authorize that the raw capture belongs to the authenticated user's event.
     */
    private function authorizeCapture(Request $request, PhotoCapture $capture): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $eventOwnerId = $capture->session?->event?->user_id;
        if ($eventOwnerId !== $user->id) {
            abort(403, 'Akses tidak diizinkan untuk foto mentah ini.');
        }
    }
}

