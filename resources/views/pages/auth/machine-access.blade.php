<x-layouts.app title="Akses Mesin" heading="" :actor-name="$actorName" :role="$role" :show-operator-bottom-actions="false">
    <section class="mx-auto w-full max-w-[26rem] space-y-5 pb-20" data-machine-access-root>
        <p class="pt-2 text-center text-lg font-semibold tracking-[0.32em] text-[var(--color-prime-ink)]">PRIME</p>

        <div>
            <h1 class="text-[2.55rem] font-semibold tracking-[-0.03em] text-[var(--color-prime-ink)]">Akses Mesin</h1>
            <p class="mt-2 text-[1.08rem] leading-7 text-[var(--color-prime-muted)]">
                Scan QR Code atau masukkan kode mesin untuk memulai sesi operator.
            </p>
        </div>

        @if ($errors->any())
            <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <div>
            <div class="grid grid-cols-2 border-b border-[var(--color-prime-border)]">
                <button
                    type="button"
                    data-machine-access-tab-trigger="scan"
                    class="flex items-center justify-center gap-2 border-b-2 border-[var(--color-prime-primary)] px-3 py-3 text-lg font-semibold text-[var(--color-prime-primary)]"
                >
                    <span class="inline-flex h-5 w-5 items-center justify-center text-[var(--color-prime-muted)]">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M4 7V4h3" />
                            <path d="M13 4h3v3" />
                            <path d="M16 13v3h-3" />
                            <path d="M7 16H4v-3" />
                            <rect x="8" y="8" width="4" height="4" rx="1" />
                        </svg>
                    </span>
                    Scan QR
                </button>
                <button
                    type="button"
                    data-machine-access-tab-trigger="code"
                    class="flex items-center justify-center gap-2 border-b-2 border-transparent px-3 py-3 text-lg font-semibold text-[var(--color-prime-muted)]"
                >
                    <span class="inline-flex h-5 w-5 items-center justify-center text-[var(--color-prime-muted)]">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <rect x="3" y="5" width="14" height="10" rx="1.6" />
                            <path d="M6 8h8" />
                            <path d="M6 11h4" />
                        </svg>
                    </span>
                    Kode Mesin
                </button>
            </div>
        </div>

        <div data-machine-access-panel="scan">
            <div class="rounded-2xl border border-[var(--color-prime-border)] bg-[var(--color-prime-panel)] p-5 shadow-sm">
                <div class="rounded-xl border border-dashed border-[var(--color-prime-border)] bg-[var(--color-prime-bg)] p-4">
                    <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-lg border-2 border-[#3f6ad8]/75 bg-black/5">
                        <video
                            data-qr-video
                            class="hidden h-full w-full object-cover"
                            autoplay
                            muted
                            playsinline
                        ></video>
                        <svg data-qr-placeholder class="h-16 w-16 text-slate-300" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M8 12V8h4" />
                            <path d="M20 8h4v4" />
                            <path d="M24 20v4h-4" />
                            <path d="M12 24H8v-4" />
                            <rect x="12.5" y="12.5" width="7" height="7" rx="1" />
                            <path d="M15 15h2v2h-2z" />
                        </svg>
                        <div class="pointer-events-none absolute inset-x-0 top-0 bg-gradient-to-b from-black/20 to-transparent p-3 text-center text-xs font-medium text-white/90">
                            Arahkan QR code ke area kamera
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    data-start-scan
                    class="mt-5 w-full rounded-lg bg-[var(--color-prime-primary)] px-4 py-3 text-lg font-semibold text-white transition hover:bg-[var(--color-prime-primary-strong)]"
                >
                    Mulai Scan
                </button>
                <p data-scan-status class="mt-4 text-center text-sm text-[var(--color-prime-muted)]">Arahkan kamera ke QR Code yang tertempel pada panel mesin.</p>
            </div>
        </div>

        <div class="hidden" data-machine-access-panel="code">
            <form action="{{ route('machine-access.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="machine_code" class="mb-2 block text-lg font-semibold text-[var(--color-prime-ink)]">Kode Mesin</label>
                    <div class="flex items-center gap-3 rounded-sm border border-[var(--color-prime-border)] bg-white px-4 py-3">
                        <span class="text-2xl font-semibold text-[var(--color-prime-muted)]">#</span>
                        <input
                            id="machine_code"
                            name="machine_code"
                            type="text"
                            value="{{ old('machine_code') }}"
                            class="w-full border-0 bg-transparent px-0 text-lg uppercase outline-none focus:ring-0"
                            placeholder="UC-001"
                        >
                    </div>
                </div>

                <button type="submit" class="mt-6 w-full rounded-lg bg-[var(--color-prime-primary)] px-4 py-3 text-lg font-semibold text-white transition hover:bg-[var(--color-prime-primary-strong)]">
                    Buka Mesin
                </button>
            </form>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('[data-machine-access-root]');
            if (! root) {
                return;
            }

            const triggers = root.querySelectorAll('[data-machine-access-tab-trigger]');
            const panels = root.querySelectorAll('[data-machine-access-panel]');
            const machineCodeInput = root.querySelector('#machine_code');
            const hasMachineCodeError = {{ $errors->has('machine_code') ? 'true' : 'false' }};
            const startScanButton = root.querySelector('[data-start-scan]');
            const scanStatus = root.querySelector('[data-scan-status]');
            const qrVideo = root.querySelector('[data-qr-video]');
            const qrPlaceholder = root.querySelector('[data-qr-placeholder]');
            const scanCanvas = document.createElement('canvas');
            const scanContext = scanCanvas.getContext('2d', {
                willReadFrequently: true,
            });
            let scanStream = null;
            let scanFrameId = null;
            let qrDecoder = null;
            let isScanning = false;

            const setActiveTab = (tabName) => {
                triggers.forEach((trigger) => {
                    const isActive = trigger.getAttribute('data-machine-access-tab-trigger') === tabName;
                    trigger.classList.toggle('border-[var(--color-prime-primary)]', isActive);
                    trigger.classList.toggle('text-[var(--color-prime-primary)]', isActive);
                    trigger.classList.toggle('border-transparent', ! isActive);
                    trigger.classList.toggle('text-[var(--color-prime-muted)]', ! isActive);
                });

                panels.forEach((panel) => {
                    const isTargetPanel = panel.getAttribute('data-machine-access-panel') === tabName;
                    panel.classList.toggle('hidden', ! isTargetPanel);

                    if (! isTargetPanel && panel.getAttribute('data-machine-access-panel') === 'scan') {
                        stopScanner();
                    }
                });
            };

            const setScanStatus = (message) => {
                if (scanStatus instanceof HTMLElement) {
                    scanStatus.textContent = message;
                }
            };

            const stopScanner = () => {
                if (scanFrameId) {
                    window.cancelAnimationFrame(scanFrameId);
                    scanFrameId = null;
                }

                if (scanStream instanceof MediaStream) {
                    scanStream.getTracks().forEach((track) => track.stop());
                }

                scanStream = null;
                isScanning = false;

                if (qrVideo instanceof HTMLVideoElement) {
                    qrVideo.pause();
                    qrVideo.srcObject = null;
                    qrVideo.classList.add('hidden');
                }

                if (qrPlaceholder instanceof SVGElement) {
                    qrPlaceholder.classList.remove('hidden');
                }

                if (startScanButton instanceof HTMLButtonElement) {
                    startScanButton.disabled = false;
                    startScanButton.textContent = 'Mulai Scan';
                }
            };

            const toRedirectUrl = (rawValue) => {
                const value = rawValue.trim();

                if (value === '') {
                    return '';
                }

                if (value.startsWith('http://') || value.startsWith('https://')) {
                    return value;
                }

                if (value.startsWith('/qr/')) {
                    return value;
                }

                return `/qr/${encodeURIComponent(value)}`;
            };

            const scanLoop = () => {
                if (! isScanning || ! (qrVideo instanceof HTMLVideoElement) || typeof qrDecoder !== 'function' || ! scanContext) {
                    return;
                }

                const width = qrVideo.videoWidth;
                const height = qrVideo.videoHeight;

                if (width > 0 && height > 0) {
                    scanCanvas.width = width;
                    scanCanvas.height = height;
                    scanContext.drawImage(qrVideo, 0, 0, width, height);

                    const imageData = scanContext.getImageData(0, 0, width, height);
                    const result = qrDecoder(imageData.data, width, height, {
                        inversionAttempts: 'attemptBoth',
                    });

                    const qrValue = (result?.data ?? '').trim();

                    if (qrValue !== '') {
                        const redirectUrl = toRedirectUrl(qrValue);
                        setScanStatus('QR terdeteksi, membuka halaman...');
                        stopScanner();

                        if (redirectUrl !== '') {
                            window.location.href = redirectUrl;
                        } else {
                            setScanStatus('Isi QR tidak valid. Coba scan ulang.');
                        }

                        return;
                    }
                }

                scanFrameId = window.requestAnimationFrame(scanLoop);
            };

            const ensureQrDecoder = async () => {
                if (typeof window.jsQR === 'function') {
                    qrDecoder = window.jsQR;

                    return;
                }

                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
                    script.async = true;
                    script.onload = resolve;
                    script.onerror = () => reject(new Error('Gagal memuat pustaka scanner QR.'));
                    document.head.appendChild(script);
                });

                if (typeof window.jsQR !== 'function') {
                    throw new Error('Pustaka scanner QR tidak tersedia.');
                }

                qrDecoder = window.jsQR;
            };

            const startScanner = async () => {
                if (!(startScanButton instanceof HTMLButtonElement)) {
                    return;
                }

                try {
                    startScanButton.disabled = true;
                    startScanButton.textContent = 'Memulai Kamera...';
                    setScanStatus('Meminta izin kamera...');
                    await ensureQrDecoder();

                    scanStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: {
                                ideal: 'environment',
                            },
                        },
                        audio: false,
                    });

                    if (qrVideo instanceof HTMLVideoElement) {
                        qrVideo.srcObject = scanStream;
                        await qrVideo.play();
                        qrVideo.classList.remove('hidden');
                    }

                    if (qrPlaceholder instanceof SVGElement) {
                        qrPlaceholder.classList.add('hidden');
                    }

                    isScanning = true;
                    startScanButton.textContent = 'Sedang Scan...';
                    setScanStatus('Kamera aktif. Arahkan kamera ke QR code mesin.');
                    scanLoop();
                } catch (error) {
                    stopScanner();
                    setScanStatus('Kamera tidak bisa diakses. Izinkan permission kamera/browser lalu coba lagi.');
                }
            };

            setActiveTab(hasMachineCodeError ? 'code' : 'scan');

            triggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const tab = trigger.getAttribute('data-machine-access-tab-trigger');
                    if (! tab) {
                        return;
                    }

                    setActiveTab(tab);
                });
            });

            startScanButton?.addEventListener('click', startScanner);

            if (hasMachineCodeError && machineCodeInput instanceof HTMLInputElement) {
                machineCodeInput.focus();
            }

            window.addEventListener('beforeunload', stopScanner);
        });
    </script>
</x-layouts.app>
