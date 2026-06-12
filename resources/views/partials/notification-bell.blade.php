@props([
    'count' => 0,
])

<div class="relative" id="admin-notification-root" data-endpoint="{{ route('admin-notifications.index') }}" data-read-all-endpoint="{{ route('admin-notifications.read-all') }}" data-read-endpoint-template="{{ route('admin-notifications.read', ['notificationId' => '__ID__']) }}">
    <button
        type="button"
        id="admin-notification-toggle"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-transparent bg-white text-[var(--color-prime-muted)] transition hover:border-[rgba(0,0,0,0.08)] hover:bg-[var(--color-prime-soft)] hover:text-[var(--color-prime-ink)]"
        aria-haspopup="true"
        aria-expanded="false"
        data-testid="admin-notification-toggle"
    >
        <x-ui.icon name="bell" class="h-5 w-5" />
        <span id="admin-notification-badge" class="absolute -right-0.5 -top-0.5 hidden min-w-5 rounded-full bg-[var(--color-prime-primary)] px-1.5 py-0.5 text-center text-[11px] font-semibold leading-none text-white"></span>
    </button>

    <div id="admin-notification-dropdown" class="absolute right-0 z-50 mt-2 hidden w-[min(25rem,calc(100vw-1rem))] min-w-[22.5rem] max-w-[calc(100vw-1rem)] overflow-hidden rounded-2xl border border-[var(--color-prime-border)] bg-white shadow-[0_22px_44px_-30px_rgba(15,23,42,0.35)]">
        <div class="flex items-start justify-between gap-3 border-b border-[var(--color-prime-border)] px-4 py-3">
            <div>
                <p class="text-sm font-semibold leading-5 text-[var(--color-prime-ink)]">Notifikasi</p>
                <p id="admin-push-status" class="mt-1 hidden text-[11px] font-medium text-[var(--color-prime-muted)]"></p>
            </div>
            <button type="button" id="admin-notification-read-all" class="shrink-0 text-xs font-medium leading-5 text-[var(--color-prime-primary)] transition hover:opacity-80">
                <span class="hidden sm:inline">Tandai semua dibaca</span>
                <span class="sm:hidden">Tandai dibaca</span>
            </button>
        </div>

        <div id="admin-notification-loading" class="space-y-3 px-4 py-4">
            <div class="animate-pulse rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)]/50 p-3">
                <div class="mb-2 h-4 w-2/3 rounded bg-slate-200"></div>
                <div class="mb-1 h-3 w-full rounded bg-slate-100"></div>
                <div class="h-3 w-5/6 rounded bg-slate-100"></div>
            </div>
            <div class="animate-pulse rounded-xl border border-[var(--color-prime-border)] bg-[var(--color-prime-soft)]/50 p-3">
                <div class="mb-2 h-4 w-3/4 rounded bg-slate-200"></div>
                <div class="mb-1 h-3 w-full rounded bg-slate-100"></div>
                <div class="h-3 w-2/3 rounded bg-slate-100"></div>
            </div>
        </div>
        <div id="admin-notification-error" class="hidden px-4 py-5 text-sm text-red-600">Notifikasi gagal dimuat. Coba muat ulang.</div>
        <div id="admin-notification-empty" class="hidden px-4 py-6 text-center">
            <p class="text-sm font-medium text-[var(--color-prime-ink)]">Tidak ada notifikasi</p>
            <p class="mt-1 text-xs text-[var(--color-prime-muted)]">Semua aktivitas maintenance sudah terkendali.</p>
        </div>
        <div id="admin-notification-list" class="max-h-[26.25rem] space-y-1 overflow-y-auto p-2"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('admin-notification-root');
    if (!root) {
        return;
    }

    const toggleButton = document.getElementById('admin-notification-toggle');
    const dropdown = document.getElementById('admin-notification-dropdown');
    const badge = document.getElementById('admin-notification-badge');
    const listEl = document.getElementById('admin-notification-list');
    const loadingEl = document.getElementById('admin-notification-loading');
    const errorEl = document.getElementById('admin-notification-error');
    const emptyEl = document.getElementById('admin-notification-empty');
    const markAllButton = document.getElementById('admin-notification-read-all');
    const pushStatusEl = document.getElementById('admin-push-status');
    const endpoint = root.dataset.endpoint;
    const readAllEndpoint = root.dataset.readAllEndpoint;
    const readEndpointTemplate = root.dataset.readEndpointTemplate;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const state = {
        opened: false,
    };

    const syncPushStatus = () => {
        if (!pushStatusEl) {
            return;
        }

        const pushState = document.body?.dataset.webpushState || 'unknown';
        const labelMapping = {
            enabled: '',
            blocked: 'Izin notifikasi browser diblokir.',
            unsupported: 'Browser ini belum mendukung Web Push.',
            unavailable: 'Kunci Web Push belum dikonfigurasi.',
            pending: 'Menunggu izin notifikasi browser.',
            syncing: '',
            error: 'Sinkronisasi notifikasi browser gagal.',
            unknown: '',
        };

        const statusLabel = labelMapping[pushState] || labelMapping.unknown;

        pushStatusEl.textContent = statusLabel;
        pushStatusEl.classList.toggle('hidden', statusLabel === '');
    };

    const shortMessage = (type) => {
        const mapping = {
            pm_waiting_review: 'PM menunggu review',
            pm_overdue: 'Jadwal PM terlambat',
            pm_missed: 'Jadwal PM terlewat',
            breakdown_open: 'Breakdown baru tercatat',
            breakdown_closed: 'Breakdown telah ditutup',
        };

        return mapping[String(type || '')] || 'Ada pembaruan maintenance';
    };

    const extractMachineCode = (item) => {
        const candidates = [
            item?.data?.machine_code,
            item?.machine_code,
            item?.message,
            item?.target_url,
        ].filter(Boolean);

        for (const value of candidates) {
            const text = String(value);
            const match = text.match(/\b[A-Z]{2,6}-\d{2,6}\b/);
            if (match) {
                return match[0];
            }
        }

        return 'Mesin';
    };

    const getCategory = (type) => {
        if (String(type || '').startsWith('breakdown_')) {
            return 'Breakdown';
        }

        if (String(type || '').startsWith('pm_')) {
            return 'PM';
        }

        return 'Sistem';
    };

    const hideStates = () => {
        loadingEl?.classList.add('hidden');
        errorEl?.classList.add('hidden');
        emptyEl?.classList.add('hidden');
    };

    const setBadge = (count) => {
        if (!badge) {
            return;
        }

        if (count > 0) {
            badge.textContent = String(count > 99 ? '99+' : count);
            badge.classList.remove('hidden');
        } else {
            badge.textContent = '';
            badge.classList.add('hidden');
        }
    };

    const renderItems = (items) => {
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            emptyEl?.classList.remove('hidden');
            return;
        }

        items.forEach((item) => {
            const isRead = !!item.is_read;
            const category = getCategory(item.notification_type);
            const machineCode = extractMachineCode(item);
            const message = shortMessage(item.notification_type);
            const iconBg = category === 'Breakdown' ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-blue-600';
            const iconPath = category === 'Breakdown'
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M11.42 15.17 17.25 9.34a1.5 1.5 0 0 0 0-2.12l-1.47-1.47a1.5 1.5 0 0 0-2.12 0l-5.83 5.83M4.75 19.25l3.73-.63a2 2 0 0 0 1.08-.56l1.86-1.86-4.5-4.5-1.86 1.86a2 2 0 0 0-.56 1.08l-.63 3.73Z" />'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M8.25 7.75h7.5m-7.5 4.5h7.5m-7.5 4.5h4.5M5.75 4.75h12.5a1 1 0 0 1 1 1v12.5a1 1 0 0 1-1 1H5.75a1 1 0 0 1-1-1V5.75a1 1 0 0 1 1-1Z" />';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `w-full rounded-xl border px-3 py-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-prime-primary)] hover:bg-[var(--color-prime-soft)] ${isRead ? 'border-[var(--color-prime-border)] bg-white' : 'border-[var(--color-prime-primary)]/20 bg-[var(--color-prime-primary-soft)]/20'}`;
            button.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${iconBg}">
                        <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">${iconPath}</svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm leading-5 ${isRead ? 'font-semibold text-[var(--color-prime-ink)]' : 'font-bold text-[var(--color-prime-ink)]'} [word-break:keep-all] [overflow-wrap:normal]">${machineCode}</p>
                        <p class="truncate text-sm leading-5 text-[var(--color-prime-muted)] [word-break:keep-all] [overflow-wrap:normal]">${message}</p>
                        <p class="mt-1 truncate text-xs text-[var(--color-prime-muted)]">${item.created_at_human ?? '-'}</p>
                    </div>
                    <div class="pt-1">
                        ${isRead ? '' : '<span class="inline-block h-2 w-2 rounded-full bg-[var(--color-prime-primary)]"></span>'}
                    </div>
                </div>
            `;

            button.addEventListener('click', async () => {
                if (!readEndpointTemplate) {
                    return;
                }

                try {
                    const url = readEndpointTemplate.replace('__ID__', String(item.id));
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const payload = await response.json();
                    if (payload && payload.redirect_url) {
                        window.location.href = payload.redirect_url;
                        return;
                    }

                    window.location.href = '/dashboard';
                } catch (e) {
                    window.location.href = '/dashboard';
                }
            });

            listEl.appendChild(button);
        });
    };

    const loadNotifications = async () => {
        if (!endpoint) {
            return;
        }

        hideStates();
        loadingEl?.classList.remove('hidden');

        try {
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('load_failed');
            }

            const payload = await response.json();
            hideStates();
            setBadge(payload.unread_count || 0);
            renderItems(payload.notifications || []);
        } catch (e) {
            hideStates();
            errorEl?.classList.remove('hidden');
        }
    };

    toggleButton?.addEventListener('click', async () => {
        state.opened = !state.opened;
        dropdown?.classList.toggle('hidden', !state.opened);
        toggleButton.setAttribute('aria-expanded', state.opened ? 'true' : 'false');

        if (state.opened) {
            await loadNotifications();
        }
    });

    markAllButton?.addEventListener('click', async () => {
        if (!readAllEndpoint) {
            return;
        }

        try {
            await fetch(readAllEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });
        } finally {
            await loadNotifications();
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Node) || !root.contains(target)) {
            state.opened = false;
            dropdown?.classList.add('hidden');
            toggleButton?.setAttribute('aria-expanded', 'false');
        }
    });

    syncPushStatus();
    document.addEventListener('prime:webpush-status', syncPushStatus);
    loadNotifications();
});
</script>
