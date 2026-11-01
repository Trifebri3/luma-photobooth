<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Camera, RefreshCw, Check, ArrowRight, ShieldCheck, Heart, Sparkles,
    Smartphone, QrCode, RotateCcw, AlertCircle, Lock, Maximize2, Minimize2
} from 'lucide-vue-next';
import confetti from 'canvas-confetti';
import QRCode from 'qrcode';
import axios from 'axios';
import PwaInstallButton from '@/Components/PwaInstallButton.vue';

const props = defineProps({
    event: Object,
    isPaired: Boolean,
    device: Object,
});

// State Machine Steps: 'pair' | 'welcome' | 'register' | 'select_frame' | 'camera' | 'review' | 'finish'
const currentStep = ref(props.isPaired ? 'welcome' : 'pair');

// Pairing state
const pairingCode = ref('');
const pairingError = ref('');
const isPairingLoading = ref(false);

// Fullscreen
const isFullscreen = ref(false);
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(err => console.log(err));
    } else {
        document.exitFullscreen().then(() => {
            isFullscreen.value = false;
        }).catch(err => console.log(err));
    }
}

// Visitor Form
const visitorForm = reactive({
    name: '',
    phone_number: '',
    email: '',
    consent: true,
});
const formErrors = ref({});
const isSubmittingVisitor = ref(false);
const activeVisitor = ref(null);
const currentSessionId = ref(null);
const sessionInfo = reactive({
    number: 1,
    remaining: 0,
});

// Frame Selection
const selectedTemplate = ref(props.event.frame_templates?.[0] || null);

// Camera State
const videoRef = ref(null);
const mediaStream = ref(null);
const availableDevices = ref([]);
const selectedDeviceId = ref('');
const isCameraReady = ref(false);
const cameraError = ref('');
const countdown = ref(0);
const isCountingDown = ref(false);
const isFlashing = ref(false);

// Shots state
const currentSlotIndex = ref(0);
const capturedShots = ref([]); // array of base64 dataUrls
const retakesUsedPerSlot = ref({});

// Final Result & Delivery
const finalPhotoUrl = ref('');
const qrDataUrl = ref('');
const privateGalleryUrl = ref('');
const autoResetTimer = ref(15);
let resetInterval = null;

// Audio Context for Beeps
let audioCtx = null;
function playBeep(freq = 800, duration = 0.15) {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + duration);
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    } catch (e) {
        // audio might be blocked without gesture
    }
}

// Pairing handler
async function handlePairDevice() {
    if (!pairingCode.value) {
        pairingError.value = 'Silakan masukkan kode aktivasi perangkat.';
        return;
    }
    isPairingLoading.value = true;
    pairingError.value = '';

    try {
        const res = await axios.post(`/booth/${props.event.slug}/pair`, {
            activation_code: pairingCode.value,
        });
        if (res.data.success) {
            currentStep.value = 'welcome';
        }
    } catch (err) {
        pairingError.value = err.response?.data?.message || 'Kode aktivasi salah atau tidak ditemukan.';
    } finally {
        isPairingLoading.value = false;
    }
}

// Helper for robust overlay image loading
function formatOverlayUrl(path) {
    if (!path) return '/frames/kawaii-strip-3.png';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path.startsWith('/')) return path;
    return '/' + path;
}

function handleOverlayError(e) {
    if (!e.target.src.endsWith('kawaii-strip-3.png')) {
        e.target.src = '/frames/kawaii-strip-3.png';
    }
}

// Calculate responsive inner canvas style so it strictly preserves aspect ratio without stretching
function getPreviewCanvasStyle(tmpl) {
    const w = Number(tmpl.canvas_width) || 1200;
    const h = Number(tmpl.canvas_height) || 1800;
    const ratio = w / h;
    const containerRatio = 3 / 4; // 0.75 for aspect-[3/4] card preview box

    if (ratio <= containerRatio) {
        // Taller/narrower than 3:4 (e.g. photostrips 1:3 or 2:3) -> fit height 100%, width scales naturally
        return {
            aspectRatio: `${w} / ${h}`,
            height: '100%',
            width: 'auto',
            maxWidth: '100%',
        };
    } else {
        // Wider than 3:4 (e.g. polaroid 1200x1500, square 1:1, or landscape) -> fit width 100%, height scales naturally
        return {
            aspectRatio: `${w} / ${h}`,
            width: '100%',
            height: 'auto',
            maxHeight: '100%',
        };
    }
}

// Step Transitions: Visitor picks frame first, then takes photos!
function startPhotobooth() {
    currentStep.value = 'select_frame';
}

function selectFrameAndProceed(template) {
    selectedTemplate.value = template;
    capturedShots.value = [];
    currentSlotIndex.value = 0;
    retakesUsedPerSlot.value = {};

    if (!activeVisitor.value) {
        currentStep.value = 'register';
    } else {
        initCameraAndStart();
    }
}

async function submitVisitorRegistration() {
    formErrors.value = {};
    if (!visitorForm.name.trim()) {
        formErrors.value.name = 'Nama lengkap wajib diisi.';
        return;
    }
    if (props.event.settings?.require_whatsapp && !visitorForm.phone_number.trim()) {
        formErrors.value.phone_number = 'Nomor WhatsApp wajib diisi untuk pengiriman foto.';
        return;
    }

    isSubmittingVisitor.value = true;
    try {
        const res = await axios.post(`/booth/${props.event.slug}/visitor`, {
            name: visitorForm.name,
            phone_number: visitorForm.phone_number,
            email: visitorForm.email,
        });

        if (res.data.success) {
            activeVisitor.value = res.data.visitor;
            currentSessionId.value = res.data.session_id;
            sessionInfo.number = res.data.session_number;
            sessionInfo.remaining = res.data.remaining_sessions;
            
            // Frame is already selected -> go directly to Camera!
            initCameraAndStart();
        }
    } catch (err) {
        if (err.response?.data?.quota_exceeded) {
            formErrors.value.general = err.response.data.message;
        } else {
            formErrors.value.general = err.response?.data?.message || 'Gagal memulai sesi. Silakan coba lagi.';
        }
    } finally {
        isSubmittingVisitor.value = false;
    }
}

async function initCameraAndStart() {
    currentStep.value = 'camera';
    capturedShots.value = [];
    currentSlotIndex.value = 0;
    retakesUsedPerSlot.value = {};
    await startCamera();
}

// Camera WebRTC Engine
async function startCamera() {
    cameraError.value = '';
    isCameraReady.value = false;

    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach(t => t.stop());
    }

    try {
        const constraints = {
            video: {
                width: { ideal: 1920 },
                height: { ideal: 1080 },
                deviceId: selectedDeviceId.value ? { exact: selectedDeviceId.value } : undefined,
                facingMode: selectedDeviceId.value ? undefined : 'user',
            },
            audio: false,
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        mediaStream.value = stream;

        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            videoRef.value.onloadedmetadata = () => {
                videoRef.value.play();
                isCameraReady.value = true;
            };
        }

        // List video devices for switching
        const devices = await navigator.mediaDevices.enumerateDevices();
        availableDevices.value = devices.filter(d => d.kind === 'videoinput');
    } catch (err) {
        console.error('Camera access error:', err);
        cameraError.value = 'Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.';
    }
}

async function switchCamera() {
    if (availableDevices.value.length < 2) return;
    const currentIndex = availableDevices.value.findIndex(d => d.deviceId === selectedDeviceId.value);
    const nextIndex = (currentIndex + 1) % availableDevices.value.length;
    selectedDeviceId.value = availableDevices.value[nextIndex].deviceId;
    await startCamera();
}

// Capture Engine with Countdown & Flash
const totalSlots = computed(() => {
    return selectedTemplate.value?.slots?.length || props.event.settings?.photos_per_session || 3;
});

function triggerCountdown() {
    if (isCountingDown.value) return;
    isCountingDown.value = true;
    const duration = props.event.settings?.countdown_seconds || 5;
    countdown.value = duration;

    playBeep(600, 0.1);

    const timer = setInterval(() => {
        countdown.value--;
        if (countdown.value > 0) {
            playBeep(600, 0.1);
        } else {
            clearInterval(timer);
            playBeep(1200, 0.3); // High capture beep
            snapPhoto();
        }
    }, 1000);
}

function snapPhoto() {
    isFlashing.value = true;
    setTimeout(() => { isFlashing.value = false; }, 350);

    const video = videoRef.value;
    if (!video) return;

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 1280;
    canvas.height = video.videoHeight || 720;
    const ctx = canvas.getContext('2d');

    // Flip horizontal if front-facing camera
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
    capturedShots.value[currentSlotIndex.value] = dataUrl;

    isCountingDown.value = false;

    // Check if more slots needed
    if (currentSlotIndex.value < totalSlots.value - 1) {
        currentSlotIndex.value++;
        setTimeout(() => {
            triggerCountdown();
        }, 1200);
    } else {
        // All shots captured! Go to review
        currentStep.value = 'review';
        stopCamera();
    }
}

function retakeShot(index) {
    const maxRetakes = props.event.settings?.max_retakes ?? 2;
    const currentRetakes = retakesUsedPerSlot.value[index] || 0;
    if (currentRetakes >= maxRetakes) {
        alert(`Batas maksimal retake untuk foto ini (${maxRetakes}x) telah tercapai.`);
        return;
    }

    retakesUsedPerSlot.value[index] = currentRetakes + 1;
    currentSlotIndex.value = index;
    currentStep.value = 'camera';
    startCamera();
}

function stopCamera() {
    if (mediaStream.value) {
        mediaStream.value.getTracks().forEach(t => t.stop());
        mediaStream.value = null;
    }
}

// Canvas Frame Composer Engine
const isRendering = ref(false);
async function compositeAndFinish() {
    isRendering.value = true;

    try {
        const template = selectedTemplate.value;
        const canvasW = template?.canvas_width || 1200;
        const canvasH = template?.canvas_height || 1800;

        const canvas = document.createElement('canvas');
        canvas.width = canvasW;
        canvas.height = canvasH;
        const ctx = canvas.getContext('2d');

        // 1. Draw elegant crisp clean white background
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvasW, canvasH);

        // Subtle decorative border frame in Teal
        ctx.strokeStyle = '#0D9488'; // Teal 600
        ctx.lineWidth = 14;
        ctx.strokeRect(20, 20, canvasW - 40, canvasH - 40);

        // 2. Draw captured photos into defined slots
        const slots = template?.slots || [];
        for (let i = 0; i < capturedShots.value.length; i++) {
            const slot = slots[i] || {
                x_position: 60,
                y_position: 80 + (i * 520),
                width: canvasW - 120,
                height: 480,
            };

            const img = await loadImage(capturedShots.value[i]);

            // Draw clean shadow card behind photo
            ctx.save();
            ctx.fillStyle = '#F8FAFC';
            ctx.shadowColor = 'rgba(15, 23, 42, 0.12)';
            ctx.shadowBlur = 16;
            ctx.shadowOffsetY = 6;
            ctx.fillRect(slot.x_position - 6, slot.y_position - 6, slot.width + 12, slot.height + 12);
            ctx.restore();

            // Aspect Ratio Cover Crop inside slot
            drawCoverImage(ctx, img, slot.x_position, slot.y_position, slot.width, slot.height);
        }

        // 3. Draw Template PNG Overlay if present (Ensuring it never distorts/ketarik)
        if (template?.overlay_image_path) {
            try {
                const overlayImg = await loadImage(template.overlay_image_path);
                const overlayRatio = overlayImg.width / overlayImg.height;
                const canvasRatio = canvasW / canvasH;

                // If aspect ratio matches canvas within 3%, draw exact. Otherwise contain to prevent distortion!
                if (Math.abs(overlayRatio - canvasRatio) < 0.03) {
                    ctx.drawImage(overlayImg, 0, 0, canvasW, canvasH);
                } else {
                    drawContainImage(ctx, overlayImg, 0, 0, canvasW, canvasH);
                }
            } catch (e) {
                console.warn('Overlay failed to load, proceeding with clean canvas');
            }
        }

        // 4. Draw Event Branding Footer only if no custom overlay or if watermark is requested
        if (!template?.overlay_image_path || props.event.settings?.watermark_enabled) {
            ctx.fillStyle = '#0F172A'; // Slate 900
            ctx.font = 'bold 32px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(props.event.name, canvasW / 2, canvasH - 75);

            ctx.font = 'bold 20px sans-serif';
            ctx.fillStyle = '#0D9488'; // Teal 600
            const dateStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            ctx.fillText(`${dateStr} • by YOTA INOVASI NUSANTARA`, canvasW / 2, canvasH - 35);
        }

        // 5. Generate final high-res Data URL
        const finalDataUrl = canvas.toDataURL('image/jpeg', 0.92);
        finalPhotoUrl.value = finalDataUrl;

        // 6. Post to backend
        const response = await axios.post(`/booth/${props.event.slug}/sessions/${currentSessionId.value}/finish`, {
            final_image: finalDataUrl,
            frame_template_id: template?.id,
            raw_captures: capturedShots.value,
        });

        if (response.data.success) {
            privateGalleryUrl.value = response.data.gallery_url;

            // Generate QR Code for instant phone scan
            qrDataUrl.value = await QRCode.toDataURL(response.data.gallery_url, {
                width: 260,
                margin: 2,
                color: {
                    dark: '#0f172a',
                    light: '#ffffff',
                },
            });

            currentStep.value = 'finish';
            triggerCelebration();
            startAutoResetTimer();
        }
    } catch (err) {
        console.error('Rendering error:', err);
        alert('Terjadi kesalahan saat memproses foto. Silakan coba lagi.');
    } finally {
        isRendering.value = false;
    }
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const cleanSrc = src.startsWith('http') || src.startsWith('data:') || src.startsWith('/') ? src : '/' + src;
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => {
            if (!cleanSrc.startsWith('data:') && !cleanSrc.endsWith('kawaii-strip-3.png')) {
                const fallbackImg = new Image();
                fallbackImg.crossOrigin = 'anonymous';
                fallbackImg.onload = () => resolve(fallbackImg);
                fallbackImg.onerror = reject;
                fallbackImg.src = '/frames/kawaii-strip-3.png';
            } else {
                reject(new Error('Failed to load image: ' + cleanSrc));
            }
        };
        img.src = cleanSrc;
    });
}

function drawCoverImage(ctx, img, x, y, w, h) {
    const imgRatio = img.width / img.height;
    const targetRatio = w / h;
    let sW, sH, sX, sY;

    if (imgRatio > targetRatio) {
        sH = img.height;
        sW = img.height * targetRatio;
        sX = (img.width - sW) / 2;
        sY = 0;
    } else {
        sW = img.width;
        sH = img.width / targetRatio;
        sX = 0;
        sY = (img.height - sH) / 2;
    }

    ctx.drawImage(img, sX, sY, sW, sH, x, y, w, h);
}

function drawContainImage(ctx, img, x, y, targetW, targetH) {
    const imgRatio = img.width / img.height;
    const targetRatio = targetW / targetH;
    let dW = targetW;
    let dH = targetH;
    let dX = x;
    let dY = y;

    if (imgRatio > targetRatio) {
        dH = targetW / imgRatio;
        dY = y + (targetH - dH) / 2;
    } else {
        dW = targetH * imgRatio;
        dX = x + (targetW - dW) / 2;
    }

    ctx.drawImage(img, 0, 0, img.width, img.height, dX, dY, dW, dH);
}

function triggerCelebration() {
    confetti({
        particleCount: 100,
        spread: 70,
        colors: ['#0D9488', '#14B8A6', '#5EEAD4', '#10B981'],
        origin: { y: 0.6 },
    });
}

function startAutoResetTimer() {
    autoResetTimer.value = 18;
    clearInterval(resetInterval);
    resetInterval = setInterval(() => {
        autoResetTimer.value--;
        if (autoResetTimer.value <= 0) {
            resetToWelcome();
        }
    }, 1000);
}

function resetToWelcome() {
    clearInterval(resetInterval);
    stopCamera();
    visitorForm.name = '';
    visitorForm.phone_number = '';
    visitorForm.email = '';
    activeVisitor.value = null;
    currentSessionId.value = null;
    capturedShots.value = [];
    finalPhotoUrl.value = '';
    qrDataUrl.value = '';
    currentStep.value = 'welcome';
}

onUnmounted(() => {
    stopCamera();
    clearInterval(resetInterval);
});
</script>

<template>
    <Head :title="`Photobooth - ${event.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-900 font-sans select-none overflow-hidden flex flex-col justify-between relative">
        <!-- Top Kiosk Bar -->
        <header class="relative z-30 px-6 py-4 flex items-center justify-between bg-white border-b border-slate-200 shadow-xs">
            <div class="flex items-center space-x-3">
                <img src="/logo.png" alt="Luma" class="h-9 w-auto object-contain" />
                <div>
                    <h1 class="text-sm font-extrabold tracking-tight text-slate-900 leading-tight">{{ event.name }}</h1>
                    <p class="text-xs text-teal-600 font-bold">Self-Service Photobooth Kiosk</p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <PwaInstallButton />

                <button
                    @click="toggleFullscreen"
                    class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors"
                    title="Fullscreen Mode"
                >
                    <Maximize2 v-if="!isFullscreen" class="w-4 h-4" />
                    <Minimize2 v-else class="w-4 h-4" />
                </button>

                <!-- Hidden Emergency Organizer Exit Button -->
                <button
                    @click="currentStep = 'pair'"
                    class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors"
                    title="Pengaturan Perangkat"
                >
                    <Lock class="w-4 h-4" />
                </button>
            </div>
        </header>

        <!-- SCREEN FLASH EFFECT ON CAPTURE -->
        <div
            v-if="isFlashing"
            class="fixed inset-0 bg-white z-50 pointer-events-none transition-opacity duration-300"
        ></div>

        <!-- MAIN KIOSK VIEWPORT -->
        <main class="flex-1 flex items-center justify-center p-4 relative z-10">

            <!-- STEP: PAIRING DEVICE -->
            <div v-if="currentStep === 'pair'" class="w-full max-w-md p-8 rounded-3xl bg-white border border-slate-200 shadow-xl text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-600 mb-6">
                    <Smartphone class="w-8 h-8" />
                </div>
                <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Aktivasi Kiosk Photobooth</h2>
                <p class="text-slate-500 text-sm mb-6">
                    Masukkan kode aktivasi 6-karakter yang tertera di dashboard event organizer untuk mengunci booth ini.
                </p>

                <div class="space-y-4">
                    <input
                        v-model="pairingCode"
                        type="text"
                        maxlength="10"
                        placeholder="Contoh: LUMA26"
                        class="w-full text-center tracking-widest text-2xl font-mono uppercase font-bold py-3.5 px-4 rounded-2xl bg-slate-50 border-2 border-slate-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-slate-900"
                        @keyup.enter="handlePairDevice"
                    />

                    <div v-if="pairingError" class="text-xs text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-200">
                        {{ pairingError }}
                    </div>

                    <button
                        @click="handlePairDevice"
                        :disabled="isPairingLoading"
                        class="w-full py-4 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-base transition-all disabled:opacity-50 shadow-lg shadow-teal-600/30"
                    >
                        {{ isPairingLoading ? 'Memvalidasi...' : 'Hubungkan Perangkat' }}
                    </button>
                </div>
            </div>

            <!-- STEP 0: WELCOME SCREEN -->
            <div v-else-if="currentStep === 'welcome'" class="text-center max-w-2xl px-6 py-8">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-sm font-bold mb-6">
                    <Sparkles class="w-4 h-4 text-teal-600" />
                    <span>Selamat Datang di Photobooth</span>
                </div>

                <h1 class="text-5xl sm:text-7xl font-extrabold tracking-tight text-slate-900 mb-6">
                    Abadikan <br />
                    <span class="bg-gradient-to-r from-teal-600 to-emerald-600 bg-clip-text text-transparent">
                        Momen Terbaikmu
                    </span>
                </h1>

                <p class="text-lg text-slate-600 mb-10 max-w-lg mx-auto leading-relaxed">
                    Sentuh tombol di bawah untuk mulai berfoto secara mandiri. Hasil foto akan langsung dikirimkan ke WhatsApp Anda!
                </p>

                <button
                    @click="startPhotobooth"
                    class="group relative inline-flex items-center gap-4 px-12 py-6 rounded-3xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-2xl shadow-2xl shadow-teal-600/35 transition-all transform hover:scale-105 active:scale-95"
                >
                    <Camera class="w-8 h-8 group-hover:rotate-12 transition-transform" />
                    <span>Mulai Foto Sekarang</span>
                    <ArrowRight class="w-7 h-7 group-hover:translate-x-1 transition-transform" />
                </button>

                <div class="mt-12 flex items-center justify-center gap-6 text-xs text-slate-500 font-medium">
                    <span class="flex items-center gap-1.5">
                        <Check class="w-4 h-4 text-teal-600" /> Tanpa Antri Ribet
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Check class="w-4 h-4 text-teal-600" /> Galeri Privat Aman
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Check class="w-4 h-4 text-teal-600" /> Resolusi HD
                    </span>
                </div>
            </div>

            <!-- STEP 1: VISITOR REGISTRATION -->
            <div v-else-if="currentStep === 'register'" class="w-full max-w-md p-8 rounded-3xl bg-white border border-slate-200 shadow-xl">
                <!-- Selected Frame Banner -->
                <div v-if="selectedTemplate" class="mb-5 p-3 rounded-2xl bg-teal-50 border border-teal-200 flex items-center gap-3 text-left">
                    <div class="w-12 h-14 rounded-lg bg-white border border-teal-200 overflow-hidden flex items-center justify-center p-0.5">
                        <img v-if="selectedTemplate.overlay_image_path" :src="formatOverlayUrl(selectedTemplate.overlay_image_path)" class="w-full h-full object-contain" />
                        <Camera v-else class="w-5 h-5 text-teal-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] uppercase font-bold text-teal-600 tracking-wider">Frame Terpilih</div>
                        <div class="text-xs font-extrabold text-slate-900 truncate">{{ selectedTemplate.name }}</div>
                        <div class="text-[11px] text-teal-700 font-semibold">{{ totalSlots }} Pose Foto</div>
                    </div>
                    <button @click="currentStep = 'select_frame'" type="button" class="text-[11px] font-bold text-teal-600 hover:text-teal-800 underline">
                        Ganti
                    </button>
                </div>

                <div class="text-center mb-6">
                    <h2 class="text-2xl font-extrabold text-slate-900">Identitas Pengunjung</h2>
                    <p class="text-slate-500 text-xs mt-1">
                        Masukkan nomor WhatsApp untuk menerima tautan foto setelah sesi selesai
                    </p>
                </div>

                <div v-if="formErrors.general" class="mb-4 text-xs text-rose-700 bg-rose-50 p-3 rounded-xl border border-rose-200">
                    {{ formErrors.general }}
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap *</label>
                        <input
                            v-model="visitorForm.name"
                            type="text"
                            placeholder="Contoh: Sarah Anindya"
                            class="w-full py-3.5 px-4 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-base"
                        />
                        <span v-if="formErrors.name" class="text-xs text-rose-500 mt-1 block">{{ formErrors.name }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Nomor WhatsApp * (Untuk Pengiriman Foto)
                        </label>
                        <input
                            v-model="visitorForm.phone_number"
                            type="tel"
                            placeholder="Contoh: 081234567890"
                            class="w-full py-3.5 px-4 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-base font-mono"
                        />
                        <span v-if="formErrors.phone_number" class="text-xs text-rose-500 mt-1 block">{{ formErrors.phone_number }}</span>
                    </div>

                    <div v-if="event.settings?.require_email">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Email (Opsional)</label>
                        <input
                            v-model="visitorForm.email"
                            type="email"
                            placeholder="nama@email.com"
                            class="w-full py-3.5 px-4 rounded-xl bg-slate-50 border border-slate-300 text-slate-900 focus:border-teal-500 outline-none text-base"
                        />
                    </div>

                    <div class="pt-2">
                        <button
                            @click="submitVisitorRegistration"
                            :disabled="isSubmittingVisitor"
                            class="w-full py-4 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-base transition-all shadow-lg shadow-teal-600/30 flex items-center justify-center gap-2"
                        >
                            <Camera class="w-5 h-5" />
                            <span>{{ isSubmittingVisitor ? 'Memverifikasi...' : 'Lanjut Ambil Foto' }}</span>
                            <ArrowRight class="w-5 h-5" />
                        </button>

                        <button
                            @click="currentStep = 'select_frame'"
                            class="w-full mt-3 py-2 text-xs text-slate-500 hover:text-slate-700 font-semibold transition-colors"
                        >
                            &larr; Ganti Pilihan Frame
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: SELECT FRAME TEMPLATE -->
            <div v-else-if="currentStep === 'select_frame'" class="w-full max-w-5xl p-6 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 text-teal-700 text-xs font-bold border border-teal-200 mb-2">
                    <Sparkles class="w-3.5 h-3.5 text-teal-600" />
                    <span>Aneka Frame Lucu & Berbagai Layout</span>
                </div>
                <h2 class="text-3xl font-extrabold text-slate-900 mb-2">Pilih Desain Frame Favorit</h2>
                <p class="text-slate-500 text-sm mb-8 max-w-md mx-auto">Sentuh frame yang Anda sukai. Jumlah foto akan otomatis menyesuaikan dengan tata letak frame!</p>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5 mx-auto">
                    <div
                        v-for="tmpl in event.frame_templates"
                        :key="tmpl.id"
                        @click="selectFrameAndProceed(tmpl)"
                        class="p-4 rounded-3xl bg-white border-2 border-slate-200 hover:border-teal-500 cursor-pointer transition-all transform hover:-translate-y-2 text-left group shadow-xs hover:shadow-xl hover:shadow-teal-600/10 flex flex-col justify-between relative"
                    >
                        <!-- Top Badge -->
                        <div class="absolute top-6 right-6 z-10">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-teal-600 text-white shadow-sm">
                                {{ tmpl.slots?.length || 3 }} Foto
                            </span>
                        </div>

                        <!-- Frame Preview Card with Simulated Sample Photo behind transparent cutouts -->
                        <div class="aspect-[3/4] w-full rounded-2xl bg-slate-100/70 border border-slate-200/80 mb-3 overflow-hidden relative flex items-center justify-center p-2.5">
                            <!-- Background subtle pattern -->
                            <div class="absolute inset-0 bg-gradient-to-b from-teal-50/30 to-slate-100/60 pointer-events-none"></div>

                            <!-- Inner Canvas Box matching EXACT aspect ratio of the template -->
                            <div
                                class="relative rounded-lg overflow-hidden shadow-sm bg-white flex items-center justify-center transition-transform group-hover:scale-105 duration-300"
                                :style="getPreviewCanvasStyle(tmpl)"
                            >
                                <!-- Sample Photos in Slots (strictly inside the canvas) -->
                                <div
                                    v-for="slot in tmpl.slots"
                                    :key="slot.id"
                                    class="absolute rounded-xs bg-slate-200 overflow-hidden"
                                    :style="{
                                        left: `${(slot.x_position / (tmpl.canvas_width || 1200)) * 100}%`,
                                        top: `${(slot.y_position / (tmpl.canvas_height || 1800)) * 100}%`,
                                        width: `${(slot.width / (tmpl.canvas_width || 1200)) * 100}%`,
                                        height: `${(slot.height / (tmpl.canvas_height || 1800)) * 100}%`,
                                    }"
                                >
                                    <img
                                        :src="`https://picsum.photos/seed/thumb${slot.id + tmpl.id * 7}/300/300`"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    />
                                </div>

                                <!-- Transparent PNG Frame Overlay (Exact match, never stretched) -->
                                <img
                                    v-if="tmpl.overlay_image_path"
                                    :src="formatOverlayUrl(tmpl.overlay_image_path)"
                                    @error="handleOverlayError"
                                    class="absolute inset-0 w-full h-full object-contain pointer-events-none z-10"
                                />
                                <div v-else class="absolute inset-0 flex flex-col items-center justify-center p-2 text-center z-10 bg-white/40 backdrop-blur-2xs">
                                    <div class="text-teal-700 font-extrabold text-xs mb-0.5">{{ tmpl.slots?.length || 3 }} Foto</div>
                                    <div class="text-[9px] text-slate-500 uppercase tracking-widest font-mono font-bold">{{ tmpl.orientation }}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="font-extrabold text-sm text-slate-900 group-hover:text-teal-600 transition-colors line-clamp-1">
                                {{ tmpl.name }}
                            </div>
                            <div class="text-[11px] text-slate-400 font-medium mt-0.5 flex items-center justify-between">
                                <span class="capitalize">{{ tmpl.orientation }}</span>
                                <span class="text-teal-600 font-bold">Pilih &rarr;</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <button
                        @click="currentStep = 'welcome'"
                        class="px-6 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 transition"
                    >
                        &larr; Batal & Kembali ke Awal
                    </button>
                </div>
            </div>

            <!-- STEP 3: CAMERA CAPTURE -->
            <div v-else-if="currentStep === 'camera'" class="w-full max-w-5xl flex flex-col items-center">
                <!-- Status Header -->
                <div class="w-full flex items-center justify-between px-4 mb-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="px-3.5 py-1 rounded-full bg-teal-600 text-white text-xs font-extrabold shadow-xs">
                            Pose {{ currentSlotIndex + 1 }} dari {{ totalSlots }}
                        </span>
                        <span class="text-xs font-extrabold text-teal-800 bg-teal-50 px-3 py-1 rounded-full border border-teal-200 truncate max-w-xs">
                            {{ selectedTemplate?.name }}
                        </span>
                        <span class="hidden sm:inline text-slate-500 text-xs">Pengunjung: <strong class="text-slate-800">{{ activeVisitor?.name }}</strong></span>
                    </div>

                    <button
                        v-if="availableDevices.length > 1"
                        @click="switchCamera"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700 border border-slate-300 shadow-xs"
                    >
                        <RefreshCw class="w-3.5 h-3.5 text-teal-600" />
                        <span>Ganti Kamera</span>
                    </button>
                </div>

                <!-- Live Camera Viewport -->
                <div class="relative w-full max-w-4xl aspect-[16/9] rounded-3xl bg-slate-900 border-4 border-white shadow-2xl overflow-hidden flex items-center justify-center">
                    <video
                        ref="videoRef"
                        autoplay
                        playsinline
                        muted
                        class="w-full h-full object-cover transform -scale-x-100"
                    ></video>

                    <!-- Camera Flash Overlay -->
                    <div
                        v-if="isFlashing"
                        class="absolute inset-0 bg-white z-20 pointer-events-none transition-opacity duration-300"
                    ></div>

                    <!-- Mini Frame Preview in Corner -->
                    <div
                        v-if="selectedTemplate?.overlay_image_path"
                        class="absolute bottom-4 right-4 z-10 w-16 h-22 rounded-xl bg-white/90 backdrop-blur-xs p-1 shadow-lg border border-teal-200 pointer-events-none hidden sm:flex items-center justify-center"
                    >
                        <img :src="formatOverlayUrl(selectedTemplate.overlay_image_path)" class="w-full h-full object-contain" />
                    </div>

                    <!-- Camera Error Notice -->
                    <div v-if="cameraError" class="absolute inset-0 bg-white/95 flex flex-col items-center justify-center p-6 text-center">
                        <AlertCircle class="w-12 h-12 text-rose-500 mb-3" />
                        <div class="text-slate-900 font-bold text-lg mb-1">{{ cameraError }}</div>
                        <button @click="startCamera" class="mt-4 px-6 py-2.5 rounded-xl bg-teal-600 text-white text-sm font-bold">
                            Coba Lagi
                        </button>
                    </div>

                    <!-- Visual Countdown Overlay in Teal -->
                    <div
                        v-if="isCountingDown"
                        class="absolute inset-0 bg-white/30 backdrop-blur-xs flex items-center justify-center pointer-events-none"
                    >
                        <div class="w-36 h-36 rounded-full bg-teal-600 text-white font-extrabold text-8xl flex items-center justify-center shadow-2xl animate-bounce">
                            {{ countdown }}
                        </div>
                    </div>
                </div>

                <!-- Capture Control Button -->
                <div class="mt-6 flex items-center gap-4">
                    <button
                        @click="triggerCountdown"
                        :disabled="!isCameraReady || isCountingDown"
                        class="px-10 py-5 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-xl shadow-xl shadow-teal-600/35 transition-all transform hover:scale-105 active:scale-95 disabled:opacity-50 flex items-center gap-3"
                    >
                        <Camera class="w-7 h-7" />
                        <span>{{ isCountingDown ? `Tersenyum dalam ${countdown}...` : 'Ambil Foto' }}</span>
                    </button>
                </div>
            </div>

            <!-- STEP 4: REVIEW & RETAKE -->
            <div v-else-if="currentStep === 'review'" class="w-full max-w-4xl p-6 text-center">
                <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Tinjau Foto Anda</h2>
                <p class="text-slate-500 text-xs mb-6">Anda dapat mengulang (retake) foto bila kurang puas sebelum dicetak ke frame.</p>

                <div
                    class="grid gap-4 mb-8"
                    :class="[
                        capturedShots.length === 1 ? 'max-w-xs mx-auto grid-cols-1' : '',
                        capturedShots.length === 2 ? 'max-w-lg mx-auto grid-cols-2' : '',
                        capturedShots.length === 3 ? 'max-w-3xl mx-auto grid-cols-1 sm:grid-cols-3' : '',
                        capturedShots.length >= 4 ? 'max-w-4xl mx-auto grid-cols-2 sm:grid-cols-4' : '',
                    ]"
                >
                    <div
                        v-for="(shot, idx) in capturedShots"
                        :key="idx"
                        class="p-3.5 rounded-2xl bg-white border border-slate-200 shadow-sm"
                    >
                        <div class="aspect-[4/3] rounded-xl overflow-hidden bg-slate-100 mb-3 border border-slate-200">
                            <img :src="shot" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700">Foto #{{ idx + 1 }}</span>
                            <button
                                @click="retakeShot(idx)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-700 font-bold border border-teal-200"
                            >
                                <RotateCcw class="w-3.5 h-3.5 text-teal-600" />
                                <span>Retake</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    @click="compositeAndFinish"
                    :disabled="isRendering"
                    class="px-12 py-5 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white font-extrabold text-xl shadow-xl shadow-teal-600/30 transition-all transform hover:scale-105 disabled:opacity-50 inline-flex items-center gap-3"
                >
                    <Check class="w-7 h-7" />
                    <span>{{ isRendering ? 'Mengemas Foto & Frame...' : 'Cetak & Kirim ke WhatsApp' }}</span>
                </button>
            </div>

            <!-- STEP 5: FINISH & QR DELIVERY -->
            <div v-else-if="currentStep === 'finish'" class="w-full max-w-4xl p-6 flex flex-col sm:flex-row items-center gap-8 justify-center">
                <!-- Final Rendered Preview -->
                <div class="w-full sm:w-1/2 max-w-sm rounded-3xl overflow-hidden shadow-2xl border-4 border-white bg-white">
                    <img :src="finalPhotoUrl" alt="Final Photobooth Result" class="w-full h-auto object-contain" />
                </div>

                <!-- QR & Success Message -->
                <div class="w-full sm:w-1/2 text-left space-y-5">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-teal-50 border border-teal-200 text-teal-700 text-xs font-bold">
                        <Check class="w-4 h-4 text-teal-600" />
                        <span>Foto Berhasil Diproses!</span>
                    </div>

                    <h2 class="text-3xl font-extrabold text-slate-900 leading-tight">
                        Terima Kasih, <br />
                        <span class="text-teal-600">{{ activeVisitor?.name }}!</span>
                    </h2>

                    <p class="text-sm text-slate-600 leading-relaxed">
                        Tautan galeri privat telah dikirimkan ke WhatsApp Anda (<strong>{{ activeVisitor?.phone_number }}</strong>). Anda juga bisa langsung scan kode QR berikut:
                    </p>

                    <!-- QR Code Card -->
                    <div class="p-4 rounded-2xl bg-white border border-slate-200 text-slate-900 inline-block shadow-lg">
                        <img v-if="qrDataUrl" :src="qrDataUrl" alt="Scan QR Code" class="w-48 h-48 mx-auto" />
                        <div class="text-[11px] font-extrabold text-center text-teal-700 mt-2">Scan untuk Buka Galeri Privat</div>
                    </div>

                    <div class="pt-2 flex items-center gap-4">
                        <button
                            @click="resetToWelcome"
                            class="px-6 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition-all"
                        >
                            Selesai & Reset
                        </button>
                        <span class="text-xs text-slate-500 font-medium">Auto-reset dalam {{ autoResetTimer }} detik</span>
                    </div>
                </div>
            </div>
        </main>

        <!-- Kiosk Footer Info -->
        <footer class="relative z-30 px-6 py-3 bg-white border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
            <div>
                Perangkat: <span class="text-slate-700 font-mono font-semibold">{{ device?.device_name || 'Station Touchscreen' }}</span>
            </div>
            <div>
                Powered by <span class="text-teal-600 font-bold">Luma Photobooth</span> &bull; <strong class="text-slate-700">by YOTA INOVASI NUSANTARA</strong>
            </div>
        </footer>
    </div>
</template>
