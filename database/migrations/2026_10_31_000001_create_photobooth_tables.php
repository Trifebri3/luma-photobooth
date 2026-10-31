<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Events
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('location')->nullable();
            $table->string('logo_path')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'archived'])->default('active');
            $table->timestamps();
        });

        // 2. Event Settings
        Schema::create('event_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->integer('max_sessions_per_visitor')->default(3);
            $table->integer('photos_per_session')->default(3);
            $table->integer('countdown_seconds')->default(5);
            $table->integer('max_retakes')->default(2);
            $table->boolean('is_unlimited_session')->default(false);
            $table->boolean('require_email')->default(false);
            $table->boolean('require_whatsapp')->default(true);
            $table->boolean('enable_whatsapp_delivery')->default(true);
            $table->integer('token_expiry_days')->default(30);
            $table->string('theme_color', 50)->default('#6366F1');
            $table->boolean('watermark_enabled')->default(false);
            $table->timestamps();
        });

        // 3. Event Devices (Kiosk Pairing)
        Schema::create('event_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('device_name');
            $table->string('activation_code', 16)->unique();
            $table->string('device_token', 64)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        // 4. Frame Templates
        Schema::create('frame_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('canvas_width')->default(1200);
            $table->integer('canvas_height')->default(1800);
            $table->enum('orientation', ['portrait', 'landscape', 'strip'])->default('portrait');
            $table->string('overlay_image_path')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 5. Frame Slots
        Schema::create('frame_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frame_template_id')->constrained()->cascadeOnDelete();
            $table->integer('slot_number')->default(1);
            $table->integer('x_position')->default(0);
            $table->integer('y_position')->default(0);
            $table->integer('width')->default(400);
            $table->integer('height')->default(300);
            $table->decimal('rotation_deg', 5, 2)->default(0);
            $table->string('aspect_ratio', 20)->default('4:3');
            $table->timestamps();
        });

        // 6. Visitors (No user registration needed)
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone_number', 30);
            $table->string('email', 150)->nullable();
            $table->integer('sessions_used')->default(0);
            $table->timestamp('consent_agreed_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'phone_number']);
        });

        // 7. Visitor Sessions
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_device_id')->nullable()->nullOnDelete();
            $table->foreignId('frame_template_id')->nullable()->nullOnDelete();
            $table->integer('session_number')->default(1);
            $table->enum('status', ['pending', 'capturing', 'rendered', 'delivered', 'failed'])->default('pending');
            $table->timestamps();
        });

        // 8. Photo Captures (Raw camera shots)
        Schema::create('photo_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_session_id')->constrained()->cascadeOnDelete();
            $table->integer('slot_number')->default(1);
            $table->string('file_path');
            $table->integer('width')->default(1280);
            $table->integer('height')->default(720);
            $table->integer('retake_count')->default(0);
            $table->timestamps();
        });

        // 9. Photos (Rendered composite images)
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_session_id')->constrained()->cascadeOnDelete();
            $table->string('final_image_path');
            $table->string('thumbnail_path')->nullable();
            $table->bigInteger('file_size_bytes')->default(0);
            $table->timestamps();
        });

        // 10. Photo Access Tokens (Anti-bocor private gallery)
        Schema::create('photo_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->integer('views_count')->default(0);
            $table->integer('downloads_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 11. WhatsApp Delivery Logs
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_session_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 30);
            $table->text('message_content');
            $table->string('gateway_provider', 50)->default('fonnte');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->json('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
        Schema::dropIfExists('photo_access_tokens');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('photo_captures');
        Schema::dropIfExists('visitor_sessions');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('frame_slots');
        Schema::dropIfExists('frame_templates');
        Schema::dropIfExists('event_devices');
        Schema::dropIfExists('event_settings');
        Schema::dropIfExists('events');
    }
};

