<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BoothController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\Organizer\DashboardController as OrganizerDashboard;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\Organizer\FrameStudioController;
use App\Http\Controllers\Organizer\PhotoController;
use App\Http\Controllers\ProfileController;
use App\Models\Event;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// 1. Landing Page
Route::get('/', function () {
    $demoEvent = Event::where('slug', 'festival-kreativitas-2026')->first();
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'demoSlug' => $demoEvent?->slug,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

// 2. Google OAuth Routes
Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('/', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
});

// 3. Super Admin Dedicated Portal
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminAuthController::class, 'showLogin']);
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');

    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    });
});

// 4. Public Self-Service Kiosk Photobooth
Route::prefix('booth/{slug}')->name('booth.')->group(function () {
    Route::get('/', [BoothController::class, 'show'])->name('show');
    Route::post('/pair', [BoothController::class, 'pair'])->name('pair');
    Route::post('/visitor', [BoothController::class, 'registerVisitor'])->name('visitor');
    Route::post('/sessions/{session}/finish', [BoothController::class, 'finishSession'])->name('finish');
});

// 5. Public Private Anti-Bocor Photo Gallery
Route::prefix('p/{token}')->name('gallery.')->group(function () {
    Route::get('/', [GalleryController::class, 'show'])->name('show');
    Route::get('/image', [GalleryController::class, 'image'])->name('image');
    Route::get('/download', [GalleryController::class, 'download'])->name('download');
});

// 6. Authenticated Organizer & Admin
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        if ($request->user()->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('organizer.dashboard');
    })->name('dashboard');

    Route::prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/dashboard', [OrganizerDashboard::class, 'index'])->name('dashboard');
        
        // Events Management
        Route::resource('events', OrganizerEventController::class);
        Route::post('events/{event}/devices', [OrganizerEventController::class, 'createDevice'])->name('events.devices.store');
        Route::get('events/{event}/photos/zip', [PhotoController::class, 'downloadEventZip'])->name('events.photos.zip');
        
        // Photos Gallery
        Route::get('photos', [PhotoController::class, 'index'])->name('photos.index');
        Route::get('photos/{photo}/image', [PhotoController::class, 'image'])->name('photos.image');
        Route::get('photos/{photo}/download', [PhotoController::class, 'download'])->name('photos.download');
        Route::delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
        
        // Frame Studio
        Route::get('events/{event}/studio', [FrameStudioController::class, 'index'])->name('events.studio');
        Route::post('events/{event}/templates', [FrameStudioController::class, 'store'])->name('events.templates.store');
        Route::get('events/{event}/templates/{template}/guide', [FrameStudioController::class, 'downloadGuide'])->name('events.templates.guide');
        Route::post('events/{event}/templates/{template}/slots', [FrameStudioController::class, 'updateSlots'])->name('events.templates.slots.update');
        Route::post('events/{event}/templates/{template}/overlay', [FrameStudioController::class, 'updateOverlay'])->name('events.templates.overlay.update');
        Route::delete('events/{event}/templates/{template}', [FrameStudioController::class, 'destroy'])->name('events.templates.destroy');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
