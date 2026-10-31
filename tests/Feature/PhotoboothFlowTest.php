<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDevice;
use App\Models\EventSetting;
use App\Models\FrameSlot;
use App\Models\FrameTemplate;
use App\Models\Photo;
use App\Models\PhotoAccessToken;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoboothFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_kiosk_booth_screen_renders(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Test Wedding 2026',
            'slug' => 'test-wedding-2026',
            'status' => 'active',
        ]);

        $response = $this->get("/booth/{$event->slug}");
        $response->assertStatus(200);
    }

    public function test_device_pairing_with_activation_code(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Seminar Tech 2026',
            'slug' => 'seminar-tech-2026',
            'status' => 'active',
        ]);

        $device = EventDevice::create([
            'event_id' => $event->id,
            'device_name' => 'Tablet #01',
            'activation_code' => 'TEST99',
            'is_active' => false,
        ]);

        $response = $this->postJson("/booth/{$event->slug}/pair", [
            'activation_code' => 'TEST99',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('event_devices', [
            'id' => $device->id,
            'is_active' => true,
        ]);
    }

    public function test_visitor_registration_and_quota_validation(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Festival Musik 2026',
            'slug' => 'festival-musik-2026',
            'status' => 'active',
        ]);

        EventSetting::create([
            'event_id' => $event->id,
            'max_sessions_per_visitor' => 2,
            'is_unlimited_session' => false,
        ]);

        // First session registration
        $res1 = $this->postJson("/booth/{$event->slug}/visitor", [
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
        ]);
        $res1->assertStatus(200);
        $this->assertEquals(1, $res1->json('remaining_sessions'));

        // Second session registration
        $res2 = $this->postJson("/booth/{$event->slug}/visitor", [
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
        ]);
        $res2->assertStatus(200);
        $this->assertEquals(0, $res2->json('remaining_sessions'));

        // Third session attempt should fail quota
        $res3 = $this->postJson("/booth/{$event->slug}/visitor", [
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
        ]);
        $res3->assertStatus(422);
        $res3->assertJson(['quota_exceeded' => true]);
    }

    public function test_photobooth_finish_session_saves_photo_and_generates_token(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Corporate Party 2026',
            'slug' => 'corporate-party-2026',
            'status' => 'active',
        ]);

        EventSetting::create([
            'event_id' => $event->id,
            'token_expiry_days' => 14,
            'enable_whatsapp_delivery' => true,
        ]);

        $visitor = Visitor::create([
            'event_id' => $event->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Jessica Doe',
            'phone_number' => '081122334455',
        ]);

        $session = VisitorSession::create([
            'event_id' => $event->id,
            'visitor_id' => $visitor->id,
            'session_number' => 1,
            'status' => 'capturing',
        ]);

        // 1x1 transparent PNG data url
        $fakeBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->postJson("/booth/{$event->slug}/sessions/{$session->id}/finish", [
            'final_image' => $fakeBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'token', 'gallery_url', 'download_url']);

        $token = $response->json('token');
        $this->assertDatabaseHas('photo_access_tokens', [
            'token' => $token,
            'visitor_id' => $visitor->id,
        ]);
    }

    public function test_private_gallery_accessible_via_unique_token(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::create([
            'user_id' => $organizer->id,
            'name' => 'Gala Dinner 2026',
            'slug' => 'gala-dinner-2026',
            'status' => 'active',
        ]);

        $visitor = Visitor::create([
            'event_id' => $event->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Rian Hidayat',
            'phone_number' => '085566778899',
        ]);

        $session = VisitorSession::create([
            'event_id' => $event->id,
            'visitor_id' => $visitor->id,
            'session_number' => 1,
        ]);

        $fakePath = 'private/events/' . $event->id . '/photos/photo_test.jpg';
        Storage::disk('local')->put($fakePath, 'fake-jpeg-content');

        $photo = Photo::create([
            'visitor_session_id' => $session->id,
            'final_image_path' => $fakePath,
            'file_size_bytes' => 1024,
        ]);

        $token = 'secure_random_token_12345';
        PhotoAccessToken::create([
            'photo_id' => $photo->id,
            'visitor_id' => $visitor->id,
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);

        // Gallery Show
        $response = $this->get("/p/{$token}");
        $response->assertStatus(200);

        // Invalid Token
        $invalidResponse = $this->get("/p/non-existent-token");
        $invalidResponse->assertStatus(404);
    }
}

