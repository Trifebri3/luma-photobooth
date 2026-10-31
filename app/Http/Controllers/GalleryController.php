<?php

namespace App\Http\Controllers;

use App\Models\PhotoAccessToken;
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
            ],
            'photo' => [
                'created_at' => $photo->created_at->format('d M Y, H:i'),
                'file_size' => round($photo->file_size_bytes / 1024) . ' KB',
            ],
            'imageUrl' => url("/p/{$token}/image"),
            'downloadUrl' => url("/p/{$token}/download"),
        ]);
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

