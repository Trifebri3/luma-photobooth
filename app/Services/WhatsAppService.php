<?php

namespace App\Services;

use App\Models\VisitorSession;
use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send photo link to visitor via Fonnte WhatsApp gateway.
     */
    public function sendPhotoGalleryLink(VisitorSession $session, string $token, string $galleryUrl): WhatsAppLog
    {
        $visitor = $session->visitor;
        $event = $session->event;

        $phone = $this->formatPhoneNumber($visitor->phone_number);
        $eventName = $event->name;
        $visitorName = $visitor->name;

        $message = "Halo *{$visitorName}*! 🎉\n\n"
            . "Terima kasih telah berfoto di photobooth *{$eventName}*!\n\n"
            . "Foto kenangan Anda sudah berhasil diproses dan dapat dilihat & diunduh melalui tautan privat berikut:\n"
            . "🔗 {$galleryUrl}\n\n"
            . "_Catatan: Tautan ini bersifat privat khusus untuk Anda._\n\n"
            . "✨ *Luma Photobooth* by *YOTA INOVASI NUSANTARA*";

        $log = WhatsAppLog::create([
            'visitor_session_id' => $session->id,
            'phone_number' => $phone,
            'message_content' => $message,
            'gateway_provider' => 'fonnte',
            'status' => 'queued',
        ]);

        $apiKey = config('services.fonnte.token', env('FONNTE_TOKEN', 'Eeo4Pf43HnDcaeSQmSua'));

        if (! $apiKey) {
            $log->update([
                'status' => 'sent',
                'response_payload' => [
                    'mode' => 'simulation',
                    'message' => 'Simulated dispatch. Set FONNTE_TOKEN in .env for live gateway.',
                ],
                'sent_at' => now(),
            ]);

            return $log;
        }

        try {
            // Live Fonnte API Dispatch
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->timeout(12)->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $json = $response->json();

            if ($response->successful() && ($json['status'] ?? false)) {
                $log->update([
                    'status' => 'sent',
                    'response_payload' => $json,
                    'sent_at' => now(),
                ]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'response_payload' => [
                        'status_code' => $response->status(),
                        'response' => $json ?: $response->body(),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Fonnte WhatsApp Dispatch Error: ' . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'response_payload' => ['error' => $e->getMessage()],
            ]);
        }

        return $log;
    }

    /**
     * Normalize Indonesian phone number into international 62 format.
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
