<?php

namespace App\Http\Controllers;

use App\Models\PhotoAccessToken;
use App\Models\PhotoCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GalleryController extends Controller
{
    /**
     * Show the private photo gallery for a specific visitor token.
     */
    public function show(Request $request, string $token): Response
    {
        $accessToken = PhotoAccessToken::with([
            'photo.session.event',
            'photo.session.captures',
            'visitor',
        ])->where('token', $token)->firstOrFail();

        if ($accessToken->isExpired()) {
            abort(403, 'Tautan foto ini telah melewati batas masa berlaku (expired).');
        }

        $accessToken->increment('views_count');

        $photo = $accessToken->photo;
        $session = $photo->session;
        $event = $session->event;
        $visitor = $accessToken->visitor;

        $captures = $session->captures->map(fn ($c) => [
            'id' => $c->id,
            'slot_number' => $c->slot_number,
            'imageUrl' => url("/p/{$token}/capture/{$c->id}/image"),
            'downloadUrl' => url("/p/{$token}/capture/{$c->id}/download"),
        ]);

        return Inertia::render('Gallery/Show', [
            'token' => $token,
            'visitor' => [
                'name' => $visitor->name,
            ],
            'event' => [
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'location' => $event->location,
                'start_date' => $event->start_date?->format('d M Y'),
                'galleryUrl' => url("/e/{$event->slug}"),
            ],
            'photo' => [
                'created_at' => $photo->created_at->format('d M Y, H:i'),
                'file_size' => round($photo->file_size_bytes / 1024) . ' KB',
            ],
            'imageUrl' => url("/p/{$token}/image"),
            'downloadUrl' => url("/p/{$token}/download"),
            'captures' => $captures,
        ]);
    }

    /**
     * Stream a raw capture photo for this visitor token.
     */
    public function captureImage(string $token, PhotoCapture $capture): BinaryFileResponse
    {
        $accessToken = PhotoAccessToken::with('photo.session')->where('token', $token)->firstOrFail();
        if ($accessToken->isExpired() || $capture->visitor_session_id !== $accessToken->photo->visitor_session_id) {
            abort(403, 'Akses foto mentah tidak diizinkan.');
        }

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Download a raw capture photo for this visitor token.
     */
    public function captureDownload(string $token, PhotoCapture $capture): BinaryFileResponse
    {
        $accessToken = PhotoAccessToken::with(['photo.session.event', 'visitor'])->where('token', $token)->firstOrFail();
        if ($accessToken->isExpired() || $capture->visitor_session_id !== $accessToken->photo->visitor_session_id) {
            abort(403, 'Akses foto mentah tidak diizinkan.');
        }

        $path = Storage::disk('local')->path($capture->file_path);
        if (! file_exists($path)) {
            abort(404, 'File foto mentah tidak ditemukan.');
        }

        $eventSlug = Str::slug($accessToken->photo->session->event->name ?? 'event');
        $visitorName = Str::slug($accessToken->visitor->name ?? 'photo');
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $downloadName = "{$eventSlug}_{$visitorName}_Mentah_Pose{$capture->slot_number}.{$ext}";

        return response()->download($path, $downloadName);
    }

    /**
     * Securely stream the image file from private storage.
     */
    public function image(string $token): BinaryFileResponse
    {
        $accessToken = PhotoAccessToken::with('photo')->where('token', $token)->firstOrFail();

        if ($accessToken->isExpired()) {
            abort(403, 'Akses foto kadaluarsa.');
        }

        $path = Storage::disk('local')->path($accessToken->photo->final_image_path);

        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan pada server.');
        }

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Download the high-resolution photo.
     */
    public function download(string $token): BinaryFileResponse
    {
        $accessToken = PhotoAccessToken::with(['photo.session.event', 'visitor'])->where('token', $token)->firstOrFail();

        if ($accessToken->isExpired()) {
            abort(403, 'Akses foto kadaluarsa.');
        }

        $accessToken->increment('downloads_count');

        $path = Storage::disk('local')->path($accessToken->photo->final_image_path);

        if (! file_exists($path)) {
            abort(404, 'File foto tidak ditemukan.');
        }

        $eventSlug = Str::slug($accessToken->photo->session->event->name ?? 'event');
        $visitorName = Str::slug($accessToken->visitor->name ?? 'photo');
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $downloadName = "{$eventSlug}_{$visitorName}_{$accessToken->photo_id}.{$extension}";

        return response()->download($path, $downloadName);
    }
}

