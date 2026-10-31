<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventDevice;
use App\Models\EventSetting;
use App\Models\FrameSlot;
use App\Models\FrameTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@luma.test'],
            [
                'name' => 'Super Admin Luma',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'phone' => '081234567890',
            ]
        );

        // 2. Demo Organizer
        $organizer = User::updateOrCreate(
            ['email' => 'organizer@luma.test'],
            [
                'name' => 'John Doe Organizer',
                'password' => Hash::make('password'),
                'role' => 'organizer',
                'phone' => '089876543210',
            ]
        );

        // 3. Demo Event
        $event = Event::updateOrCreate(
            ['slug' => 'festival-kreativitas-2026'],
            [
                'user_id' => $organizer->id,
                'name' => 'Festival Kreativitas 2026',
                'description' => 'Ajang pameran seni & teknologi digital se-Indonesia.',
                'start_date' => now()->startOfDay(),
                'end_date' => now()->addDays(7)->endOfDay(),
                'location' => 'Grand City Convention Center, Surabaya',
                'status' => 'active',
            ]
        );

        // 4. Event Settings
        EventSetting::updateOrCreate(
            ['event_id' => $event->id],
            [
                'max_sessions_per_visitor' => 3,
                'photos_per_session' => 3,
                'countdown_seconds' => 5,
                'max_retakes' => 2,
                'is_unlimited_session' => false,
                'require_email' => false,
                'require_whatsapp' => true,
                'enable_whatsapp_delivery' => true,
                'token_expiry_days' => 30,
                'theme_color' => '#6366F1',
                'watermark_enabled' => false,
            ]
        );

        // 5. Event Device Pairing
        EventDevice::updateOrCreate(
            ['activation_code' => 'LUMA26'],
            [
                'event_id' => $event->id,
                'device_name' => 'Booth Utama - Touchscreen #01',
                'is_active' => true,
                'last_heartbeat_at' => now(),
            ]
        );

        // 6. Frame Templates
        // Template 1: Strip 3 Photos
        $stripTemplate = FrameTemplate::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Vintage 3-Photo Strip'],
            [
                'canvas_width' => 600,
                'canvas_height' => 1800,
                'orientation' => 'strip',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        FrameSlot::where('frame_template_id', $stripTemplate->id)->delete();
        // 3 vertical slots
        FrameSlot::create([
            'frame_template_id' => $stripTemplate->id,
            'slot_number' => 1,
            'x_position' => 50,
            'y_position' => 60,
            'width' => 500,
            'height' => 450,
            'aspect_ratio' => '4:3',
        ]);
        FrameSlot::create([
            'frame_template_id' => $stripTemplate->id,
            'slot_number' => 2,
            'x_position' => 50,
            'y_position' => 560,
            'width' => 500,
            'height' => 450,
            'aspect_ratio' => '4:3',
        ]);
        FrameSlot::create([
            'frame_template_id' => $stripTemplate->id,
            'slot_number' => 3,
            'x_position' => 50,
            'y_position' => 1060,
            'width' => 500,
            'height' => 450,
            'aspect_ratio' => '4:3',
        ]);

        // Template 2: Duo Landscape / Portrait Collage
        $duoTemplate = FrameTemplate::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Modern Duo Clean Frame'],
            [
                'canvas_width' => 1200,
                'canvas_height' => 1600,
                'orientation' => 'portrait',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        FrameSlot::where('frame_template_id', $duoTemplate->id)->delete();
        FrameSlot::create([
            'frame_template_id' => $duoTemplate->id,
            'slot_number' => 1,
            'x_position' => 80,
            'y_position' => 100,
            'width' => 1040,
            'height' => 650,
            'aspect_ratio' => '16:9',
        ]);
        FrameSlot::create([
            'frame_template_id' => $duoTemplate->id,
            'slot_number' => 2,
            'x_position' => 80,
            'y_position' => 800,
            'width' => 1040,
            'height' => 650,
            'aspect_ratio' => '16:9',
        ]);

        // Template 3: Polaroid Single Shot
        $polaroidTemplate = FrameTemplate::updateOrCreate(
            ['event_id' => $event->id, 'name' => 'Polaroid Signature (1 Shot)'],
            [
                'canvas_width' => 1200,
                'canvas_height' => 1500,
                'orientation' => 'portrait',
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        FrameSlot::where('frame_template_id', $polaroidTemplate->id)->delete();
        FrameSlot::create([
            'frame_template_id' => $polaroidTemplate->id,
            'slot_number' => 1,
            'x_position' => 100,
            'y_position' => 120,
            'width' => 1000,
            'height' => 1000,
            'aspect_ratio' => '1:1',
        ]);
    }
}
