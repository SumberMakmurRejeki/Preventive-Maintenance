import './bootstrap';
import './pages/calendar';
import { renderScheduleImpact } from './checksheet-impact';
import { escapeHtml } from './safe-html';

const webPushDeniedStorageKey = 'prime.webpush.permission-denied';
const webPushServiceWorkerUrl = '/push-service-worker.js?v=20260607-2';

const closeSidebar = () => {
    const sidebar = document.querySelector('#prime-sidebar');
    const overlay = document.querySelector('#prime-sidebar-overlay');

    if (! sidebar || ! overlay) {
        return;
    }

    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
};

const openSidebar = () => {
    const sidebar = document.querySelector('#prime-sidebar');
    const overlay = document.querySelector('#prime-sidebar-overlay');

    if (! sidebar || ! overlay) {
        return;
    }

    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
};

const setWebPushState = (state) => {
    document.body?.setAttribute('data-webpush-state', state);
    document.dispatchEvent(new CustomEvent('prime:webpush-status'));
};

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let index = 0; index < rawData.length; index += 1) {
        outputArray[index] = rawData.charCodeAt(index);
    }

    return outputArray;
};

const subscriptionPayload = (subscription) => {
    const json = subscription.toJSON();

    return {
        endpoint: json.endpoint,
        keys: json.keys || {},
        content_encoding: 'aes128gcm',
    };
};

const registerAdminWebPush = async () => {
    const body = document.body;

    if (!body || body.dataset.userRole !== 'admin') {
        return;
    }

    const publicKey = body.dataset.webpushPublicKey || '';
    const storeEndpoint = body.dataset.webpushStoreEndpoint || '';
    const destroyEndpoint = body.dataset.webpushDestroyEndpoint || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (publicKey === '') {
        setWebPushState('unavailable');
        return;
    }

    if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        setWebPushState('unsupported');
        return;
    }

    const initialPermission = window.Notification.permission;

    if (initialPermission === 'denied') {
        localStorage.setItem(webPushDeniedStorageKey, '1');
        setWebPushState('blocked');
        return;
    }

    if (initialPermission === 'default' && localStorage.getItem(webPushDeniedStorageKey) === '1') {
        setWebPushState('blocked');
        return;
    }

    try {
        setWebPushState(initialPermission === 'granted' ? 'syncing' : 'pending');

        const registration = await navigator.serviceWorker.register(webPushServiceWorkerUrl, {
            updateViaCache: 'none',
        });
        await registration.update();

        let resolvedPermission = initialPermission;
        if (resolvedPermission === 'default') {
            resolvedPermission = await window.Notification.requestPermission();
        }

        if (resolvedPermission !== 'granted') {
            localStorage.setItem(webPushDeniedStorageKey, '1');
            setWebPushState('blocked');

            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription && destroyEndpoint !== '') {
                await fetch(destroyEndpoint, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        endpoint: existingSubscription.endpoint,
                    }),
                });
            }

            return;
        }

        localStorage.removeItem(webPushDeniedStorageKey);

        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });
        }

        if (storeEndpoint === '') {
            setWebPushState('error');
            return;
        }

        await fetch(storeEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(subscriptionPayload(subscription)),
        });

        setWebPushState('enabled');
    } catch (error) {
        setWebPushState('error');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    registerAdminWebPush();

    const primeShell = document.querySelector('#prime-shell');
    const sidebarCollapseStorageKey = 'prime.sidebar.collapsed';
    const applySidebarCollapseState = (collapsed) => {
        if (! primeShell) {
            return;
        }

        primeShell.classList.toggle('sidebar-collapsed', collapsed);
    };

    if (window.innerWidth >= 1024) {
        applySidebarCollapseState(localStorage.getItem(sidebarCollapseStorageKey) === '1');
    }

    document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((toggleButton) => {
        toggleButton.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                return;
            }

            const collapsed = ! primeShell?.classList.contains('sidebar-collapsed');
            applySidebarCollapseState(collapsed);
            localStorage.setItem(sidebarCollapseStorageKey, collapsed ? '1' : '0');
        });
    });

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggleButton) => {
        toggleButton.addEventListener('click', () => {
            if (window.innerWidth >= 1024) {
                return;
            }

            const sidebar = document.querySelector('#prime-sidebar');

            if (! sidebar) {
                return;
            }

            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
                return;
            }

            closeSidebar();
        });
    });

    document.querySelector('#prime-sidebar-overlay')?.addEventListener('click', closeSidebar);

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            const trigger = dropdown.querySelector('[data-dropdown-trigger]');
            const menu = dropdown.querySelector('[data-dropdown-menu]');

            if (! trigger || ! menu) {
                return;
            }

            if (dropdown.contains(event.target)) {
                if (trigger.contains(event.target)) {
                    menu.classList.toggle('hidden');
                }

                return;
            }

            menu.classList.add('hidden');
        });
    });

    document.querySelectorAll('[data-filter-reset]').forEach((resetButton) => {
        resetButton.addEventListener('click', () => {
            const form = resetButton.closest('form');

            if (! form) {
                return;
            }

            form.reset();
            form.querySelectorAll('input[type="date"]').forEach((input) => {
                input.value = '';
            });
            form.submit();
        });
    });

    document.querySelectorAll('[data-dashboard-filter]').forEach((formNode) => {
        if (! (formNode instanceof HTMLFormElement)) {
            return;
        }

        const applyButton = formNode.querySelector('[data-dashboard-apply]');
        const resetButton = formNode.querySelector('[data-dashboard-reset]');
        const startInput = formNode.querySelector('input[name="start_date"]');
        const endInput = formNode.querySelector('input[name="end_date"]');
        const defaultStartDate = formNode.getAttribute('data-filter-default-start') ?? '';
        const defaultEndDate = formNode.getAttribute('data-filter-default-end') ?? '';

        const setLoadingState = (isLoading) => {
            if (applyButton instanceof HTMLButtonElement) {
                applyButton.disabled = isLoading;
            }

            if (resetButton instanceof HTMLButtonElement) {
                resetButton.disabled = isLoading;
            }
        };

        formNode.addEventListener('submit', () => {
            setLoadingState(true);
        });

        if (resetButton instanceof HTMLButtonElement) {
            resetButton.addEventListener('click', () => {
                if (startInput instanceof HTMLInputElement) {
                    startInput.value = defaultStartDate;
                }

                if (endInput instanceof HTMLInputElement) {
                    endInput.value = defaultEndDate;
                }

                setLoadingState(true);
                formNode.submit();
            });
        }
    });

    const syncGlobalModalState = () => {
        const hasOpenModal = document.querySelector('.prime-modal.flex') !== null;
        document.body.classList.toggle('overflow-hidden', hasOpenModal);
    };

    const closeModalById = (modalId) => {
        if (! modalId) {
            return;
        }

        const modal = document.getElementById(modalId);

        if (! modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        syncGlobalModalState();
    };

    const openModalById = (modalId) => {
        if (! modalId) {
            return;
        }

        const modal = document.getElementById(modalId);

        if (! modal) {
            return;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        syncGlobalModalState();
    };

    document.querySelectorAll('[data-modal-open]').forEach((openButton) => {
        openButton.addEventListener('click', () => {
            const modalId = openButton.getAttribute('data-modal-open');
            openModalById(modalId);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => {
            const modalId = closeButton.getAttribute('data-modal-close');
            closeModalById(modalId);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openModals = Array.from(document.querySelectorAll('.prime-modal.flex'));
        const topModal = openModals[openModals.length - 1];

        if (! topModal) {
            return;
        }

        closeModalById(topModal.getAttribute('id'));
    });

    document.querySelectorAll('[data-confirm-action]').forEach((actionButton) => {
        actionButton.addEventListener('click', (event) => {
            const message = actionButton.getAttribute('data-confirm-message') ?? 'Lanjutkan aksi ini?';

            if (! window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const loginRoot = document.querySelector('[data-login-root]');

    if (loginRoot) {
        const loginSwitchButtons = loginRoot.querySelectorAll('[data-login-switch]');
        const loginPanels = loginRoot.querySelectorAll('[data-login-panel]');
        const setActiveTab = (tabName) => {
            loginRoot.setAttribute('data-login-tab', tabName);

            loginSwitchButtons.forEach((button) => {
                const isActive = button.getAttribute('data-login-switch') === tabName;

                button.classList.toggle('bg-white', isActive);
                button.classList.toggle('text-[var(--color-prime-ink)]', isActive);
                button.classList.toggle('shadow-[0_1px_2px_rgba(0,0,0,0.08)]', isActive);
                button.classList.toggle('font-semibold', isActive);
                button.classList.toggle('text-[var(--color-prime-muted)]', ! isActive);
                button.classList.toggle('font-medium', ! isActive);
            });

            loginPanels.forEach((panel) => {
                const isActive = panel.getAttribute('data-login-panel') === tabName;

                panel.classList.toggle('hidden', ! isActive);
            });
        };

        setActiveTab(loginRoot.getAttribute('data-login-tab') ?? 'staff');

        loginSwitchButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setActiveTab(button.getAttribute('data-login-switch') ?? 'staff');
            });
        });

        loginRoot.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
            toggleButton.addEventListener('click', () => {
                const inputId = toggleButton.getAttribute('data-password-toggle');
                const input = document.getElementById(inputId);

                if (! input) {
                    return;
                }

                const makeVisible = input.type === 'password';
                input.type = makeVisible ? 'text' : 'password';

                toggleButton.querySelector('[data-password-icon="default"]')?.classList.toggle('hidden', makeVisible);
                toggleButton.querySelector('[data-password-icon="active"]')?.classList.toggle('hidden', ! makeVisible);
            });
        });

        loginRoot.querySelectorAll('[data-submit-loading]').forEach((submitButton) => {
            submitButton.closest('form')?.addEventListener('submit', () => {
                submitButton.setAttribute('disabled', 'disabled');
                submitButton.classList.add('opacity-85');
                submitButton.querySelector('span:first-child')?.classList.add('hidden');
                submitButton.querySelector('[data-submit-spinner]')?.classList.remove('hidden');
            });
        });

    }

    const locationPageRoot = document.querySelector('[data-location-page]');

    if (locationPageRoot) {
        const createModal = document.getElementById('location-create-modal');
        const editModal = document.getElementById('location-edit-modal');
        const statusModal = document.getElementById('location-status-modal');
        const deleteModal = document.getElementById('location-delete-modal');
        const locationFilterForm = locationPageRoot.querySelector('[data-location-filter-form]');
        const locationSearchInput = locationPageRoot.querySelector('[data-location-filter-search]');
        const locationSearchLoading = locationPageRoot.querySelector('[data-location-filter-search-loading]');
        const locationStatusSelect = locationPageRoot.querySelector('[data-location-filter-status]');
        const locationResetButton = locationPageRoot.querySelector('[data-location-filter-reset]');
        const locationRows = locationPageRoot.querySelectorAll('[data-location-row]');
        const locationDesktopTable = locationPageRoot.querySelector('[data-location-table-desktop]');
        const locationMobileTable = locationPageRoot.querySelector('[data-location-table-mobile]');
        const locationEmptyFilter = locationPageRoot.querySelector('[data-location-empty-filter]');
        const locationPagination = locationPageRoot.querySelector('[data-location-pagination]');

        const openModal = (modal) => {
            if (! modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const setFieldValue = (selector, value) => {
            const field = document.querySelector(selector);

            if (! field) {
                return;
            }

            field.value = value ?? '';
        };

        if (locationFilterForm instanceof HTMLFormElement) {
            locationFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            const normalize = (value) => (value ?? '').toString().toLowerCase().trim();
            let locationDebounceTimer;

            const updateResetState = () => {
                if (! (locationResetButton instanceof HTMLButtonElement)) {
                    return;
                }

                const hasFilter = normalize(locationSearchInput?.value) !== '' || normalize(locationStatusSelect?.value) !== '';
                locationResetButton.disabled = ! hasFilter;
                locationResetButton.classList.toggle('opacity-50', ! hasFilter);
                locationResetButton.classList.toggle('pointer-events-none', ! hasFilter);
            };

            const applyLocationFilter = () => {
                const keyword = normalize(locationSearchInput?.value);
                const status = normalize(locationStatusSelect?.value);
                let visibleRowsCount = 0;

                locationRows.forEach((rowNode) => {
                    if (! (rowNode instanceof HTMLElement)) {
                        return;
                    }

                    const code = normalize(rowNode.dataset.code);
                    const name = normalize(rowNode.dataset.name);
                    const rowStatus = normalize(rowNode.dataset.status);
                    const matchesKeyword = keyword === '' || code.includes(keyword) || name.includes(keyword);
                    const matchesStatus = status === '' || rowStatus === status;
                    const isVisible = matchesKeyword && matchesStatus;

                    rowNode.hidden = ! isVisible;
                    if (isVisible) {
                        visibleRowsCount += 1;
                    }
                });

                const hasNoResult = visibleRowsCount === 0;
                if (locationEmptyFilter instanceof HTMLElement) {
                    locationEmptyFilter.classList.toggle('hidden', ! hasNoResult);
                }
                if (locationDesktopTable instanceof HTMLElement) {
                    locationDesktopTable.classList.toggle('hidden', hasNoResult || window.innerWidth < 1024);
                    locationDesktopTable.classList.toggle('lg:block', ! hasNoResult);
                }
                if (locationMobileTable instanceof HTMLElement) {
                    locationMobileTable.classList.toggle('hidden', hasNoResult || window.innerWidth >= 1024);
                    locationMobileTable.classList.toggle('lg:hidden', ! hasNoResult);
                }
                if (locationPagination instanceof HTMLElement) {
                    locationPagination.classList.toggle('hidden', hasNoResult);
                }

                if (locationSearchLoading) {
                    locationSearchLoading.classList.add('hidden');
                    locationSearchLoading.classList.remove('flex');
                }

                updateResetState();
            };

            if (locationSearchInput instanceof HTMLInputElement) {
                locationSearchInput.addEventListener('input', () => {
                    if (locationSearchLoading) {
                        locationSearchLoading.classList.remove('hidden');
                        locationSearchLoading.classList.add('flex');
                    }

                    window.clearTimeout(locationDebounceTimer);
                    locationDebounceTimer = window.setTimeout(() => {
                        applyLocationFilter();
                    }, 300);
                });
            }

            if (locationStatusSelect instanceof HTMLSelectElement) {
                locationStatusSelect.addEventListener('change', applyLocationFilter);
            }

            if (locationResetButton instanceof HTMLButtonElement) {
                locationResetButton.addEventListener('click', () => {
                    if (locationSearchInput instanceof HTMLInputElement) {
                        locationSearchInput.value = '';
                    }
                    if (locationStatusSelect instanceof HTMLSelectElement) {
                        locationStatusSelect.value = '';
                    }
                    applyLocationFilter();
                    locationSearchInput?.focus();
                });
            }

            applyLocationFilter();
        }

        document.querySelectorAll('[data-location-create-open]').forEach((button) => {
            button.addEventListener('click', () => {
                openModal(createModal);
            });
        });

        document.querySelectorAll('[data-location-edit-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const updateUrl = button.getAttribute('data-update-url') ?? '';
                const code = button.getAttribute('data-location-code') ?? '';
                const name = button.getAttribute('data-location-name') ?? '';
                const description = button.getAttribute('data-location-description') ?? '';
                const isActive = button.getAttribute('data-location-active') ?? '1';

                const editForm = document.querySelector('[data-location-edit-form]');

                if (editForm) {
                    editForm.setAttribute('action', updateUrl);
                }

                setFieldValue('[data-location-edit-field="code"]', code);
                setFieldValue('[data-location-edit-field="name"]', name);
                setFieldValue('[data-location-edit-field="description"]', description);
                setFieldValue('[data-location-edit-field="active"]', isActive);
                setFieldValue('[data-location-edit-field="id"]', button.getAttribute('data-location-id') ?? '');

                openModal(editModal);
            });
        });

        document.querySelectorAll('[data-location-status-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const statusForm = document.querySelector('[data-location-status-form]');
                const actionLabel = button.getAttribute('data-status-label') ?? 'Ubah status';
                const description = button.getAttribute('data-status-description') ?? '';
                const buttonLabel = button.getAttribute('data-status-button') ?? 'Simpan';
                const buttonVariant = button.getAttribute('data-status-variant') ?? 'warning';

                if (statusForm) {
                    statusForm.setAttribute('action', button.getAttribute('data-status-url') ?? '');
                }

                const titleNode = document.querySelector('[data-location-status-title]');
                const descriptionNode = document.querySelector('[data-location-status-description]');
                const submitNode = document.querySelector('[data-location-status-submit]');
                const iconWrapNode = document.querySelector('[data-location-status-icon-wrap]');
                const iconNode = document.querySelector('[data-location-status-icon]');

                titleNode && (titleNode.textContent = actionLabel);
                descriptionNode && (descriptionNode.textContent = description);

                if (submitNode) {
                    submitNode.textContent = buttonLabel;
                    submitNode.classList.remove('bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]', 'bg-[var(--color-prime-success)]', 'hover:bg-[#149847]');

                    if (buttonVariant === 'success') {
                        submitNode.classList.add('bg-[var(--color-prime-success)]', 'hover:bg-[#149847]');
                    } else {
                        submitNode.classList.add('bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]');
                    }
                }

                if (iconWrapNode) {
                    iconWrapNode.classList.remove('bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]', 'bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]');

                    if (buttonVariant === 'success') {
                        iconWrapNode.classList.add('bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]');
                    } else {
                        iconWrapNode.classList.add('bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]');
                    }
                }

                if (iconNode) {
                    iconNode.innerHTML = buttonVariant === 'success'
                        ? '<path d="m8 6 10 6-10 6V6Z"></path>'
                        : '<path d="M12 2v8"></path><path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path>';
                }

                openModal(statusModal);
            });
        });

        document.querySelectorAll('[data-location-delete-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const activeMachinesCount = Number(button.getAttribute('data-active-machines-count') ?? '0');
                const totalMachinesCount = Number(button.getAttribute('data-total-machines-count') ?? '0');
                const deleteForm = document.querySelector('[data-location-delete-form]');
                const titleNode = document.querySelector('[data-location-delete-title]');
                const descriptionNode = document.querySelector('[data-location-delete-description]');
                const submitNode = document.querySelector('[data-location-delete-submit]');

                if (deleteForm) {
                    deleteForm.setAttribute('action', button.getAttribute('data-delete-url') ?? '');
                }

                if (activeMachinesCount > 0) {
                    titleNode && (titleNode.textContent = 'Hapus Ditolak');
                    descriptionNode && (descriptionNode.textContent = `Lokasi tidak dapat dihapus karena masih digunakan oleh ${activeMachinesCount} mesin aktif. Silakan pindahkan mesin terkait terlebih dahulu.`);
                    submitNode?.setAttribute('disabled', 'disabled');
                } else if (totalMachinesCount > 0) {
                    titleNode && (titleNode.textContent = 'Hapus Ditolak');
                    descriptionNode && (descriptionNode.textContent = 'Lokasi tidak dapat dihapus karena masih terhubung dengan data mesin. Silakan pindahkan atau hapus relasi mesin terlebih dahulu.');
                    submitNode?.setAttribute('disabled', 'disabled');
                } else {
                    titleNode && (titleNode.textContent = 'Hapus Lokasi');
                    descriptionNode && (descriptionNode.textContent = 'Data lokasi akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.');
                    submitNode?.removeAttribute('disabled');
                }

                openModal(deleteModal);
            });
        });

        const initialModal = locationPageRoot.getAttribute('data-open-modal');

        if (initialModal === 'create') {
            openModal(createModal);
        }

        if (initialModal === 'edit') {
            openModal(editModal);
        }
    }

    const machinePageRoot = document.querySelector('[data-machine-master-page]');

    const userSettingsPageRoot = document.querySelector('[data-user-settings-page]');

    if (userSettingsPageRoot) {
        const createModal = document.getElementById('user-create-modal');
        const editModal = document.getElementById('user-edit-modal');
        const resetModal = document.getElementById('user-reset-modal');
        const statusModal = document.getElementById('user-status-modal');
        const deleteModal = document.getElementById('user-delete-modal');

        const openModal = (modal) => {
            if (! modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        const setFieldValue = (selector, value) => {
            const field = document.querySelector(selector);

            if (! field) {
                return;
            }

            field.value = value ?? '';
        };

        const loadingState = document.querySelector('[data-user-loading-state]');
        if (loadingState) {
            loadingState.classList.remove('hidden');
            setTimeout(() => {
                loadingState.classList.add('hidden');
            }, 150);
        }

        document.querySelectorAll('[data-user-create-open]').forEach((button) => {
            button.addEventListener('click', () => {
                openModal(createModal);
            });
        });

        document.querySelectorAll('[data-user-edit-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const editForm = document.querySelector('[data-user-edit-form]');

                if (editForm) {
                    editForm.setAttribute('action', button.getAttribute('data-update-url') ?? '#');
                }

                setFieldValue('[data-user-edit-field="id"]', button.getAttribute('data-user-id') ?? '');
                setFieldValue('[data-user-edit-field="name"]', button.getAttribute('data-user-name') ?? '');
                setFieldValue('[data-user-edit-field="username"]', button.getAttribute('data-user-username') ?? '');
                setFieldValue('[data-user-edit-field="role"]', button.getAttribute('data-user-role') ?? 'operator');
                setFieldValue('[data-user-edit-field="active"]', button.getAttribute('data-user-active') ?? '1');

                openModal(editModal);
            });
        });

        document.querySelectorAll('[data-user-reset-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const resetForm = document.querySelector('[data-user-reset-form]');
                const labelNode = document.querySelector('[data-user-reset-label]');

                if (resetForm) {
                    resetForm.setAttribute('action', button.getAttribute('data-reset-url') ?? '#');
                }

                setFieldValue('[data-user-reset-field="id"]', button.getAttribute('data-user-id') ?? '');

                if (labelNode) {
                    const userName = button.getAttribute('data-user-name') ?? '-';
                    const username = button.getAttribute('data-user-username') ?? '-';
                    labelNode.textContent = `Ubah sandi untuk: ${userName} (@${username})`;
                }

                openModal(resetModal);
            });
        });

        document.querySelectorAll('[data-user-status-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const statusForm = document.querySelector('[data-user-status-form]');
                const titleNode = document.querySelector('[data-user-status-title]');
                const descriptionNode = document.querySelector('[data-user-status-description]');
                const submitNode = document.querySelector('[data-user-status-submit]');
                const iconWrapNode = document.querySelector('[data-user-status-icon-wrap]');
                const iconNode = document.querySelector('[data-user-status-icon]');
                const variant = button.getAttribute('data-status-variant') ?? 'warning';

                statusForm?.setAttribute('action', button.getAttribute('data-status-url') ?? '#');
                titleNode && (titleNode.textContent = button.getAttribute('data-status-label') ?? 'Ubah status user');
                descriptionNode && (descriptionNode.textContent = button.getAttribute('data-status-description') ?? '');

                if (submitNode) {
                    submitNode.textContent = button.getAttribute('data-status-button') ?? 'Simpan';
                    submitNode.classList.remove('bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]', 'bg-[var(--color-prime-success)]', 'hover:bg-[#149847]');
                    submitNode.classList.add(
                        ...(variant === 'success'
                            ? ['bg-[var(--color-prime-success)]', 'hover:bg-[#149847]']
                            : ['bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]']),
                    );
                }

                if (iconWrapNode) {
                    iconWrapNode.classList.remove('bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]', 'bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]');
                    iconWrapNode.classList.add(
                        ...(variant === 'success'
                            ? ['bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]']
                            : ['bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]']),
                    );
                }

                if (iconNode) {
                    iconNode.innerHTML = variant === 'success'
                        ? '<path d=\"m8 6 10 6-10 6V6Z\"></path>'
                        : '<path d=\"M12 2v8\"></path><path d=\"M7.8 4.8a8 8 0 1 0 8.4 0\"></path>';
                }

                openModal(statusModal);
            });
        });

        document.querySelectorAll('[data-user-delete-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const deleteForm = document.querySelector('[data-user-delete-form]');
                const descriptionNode = document.querySelector('[data-user-delete-description]');

                deleteForm?.setAttribute('action', button.getAttribute('data-delete-url') ?? '#');
                if (descriptionNode) {
                    descriptionNode.textContent = `User ${button.getAttribute('data-user-name') ?? '-'} akan dihapus dari sistem. History PM dan breakdown yang pernah dikerjakan user tetap tersimpan.`;
                }

                openModal(deleteModal);
            });
        });

        const initialModal = userSettingsPageRoot.getAttribute('data-open-modal');

        if (initialModal === 'create') {
            openModal(createModal);
        }

        if (initialModal === 'edit') {
            openModal(editModal);
        }

        if (initialModal === 'reset_password') {
            openModal(resetModal);
        }

    }

    if (machinePageRoot) {
        const machineFormModal = document.getElementById('machine-form-modal');
        const machineQrModal = document.getElementById('machine-qr-modal');
        const machineDetailModal = document.getElementById('machine-detail-modal');
        const machineStatusModal = document.getElementById('machine-status-modal');
        const machineDeleteModal = document.getElementById('machine-delete-modal');

        const openMachineModal = (modal) => {
            if (! modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            syncGlobalModalState();
        };

        const setMachineFieldValue = (selector, value) => {
            const field = document.querySelector(selector);

            if (! field) {
                return;
            }

            field.value = value ?? '';
        };

        const machineFormTitle = machineFormModal?.querySelector('h3');
        const machineFormElement = document.querySelector('[data-machine-form]');
        const machineFormMethodWrapper = document.querySelector('[data-machine-form-method-wrapper]');
        const machineFormActionInput = document.querySelector('[data-machine-form-action-input]');
        const machineFormSubmitLabel = document.querySelector('[data-machine-form-submit-label]');
        const machineCodeField = document.querySelector('[data-machine-form-field="code"]');
        const machineFilterForm = machinePageRoot.querySelector('[data-machine-filter-form]');
        const machineFilterReset = machinePageRoot.querySelector('[data-machine-filter-reset]');
        const machineFilterSearch = machinePageRoot.querySelector('[data-machine-filter-search]');
        const machineFilterSearchLoading = machinePageRoot.querySelector('[data-machine-filter-search-loading]');
        const machineLocationSelect = machinePageRoot.querySelector('#filterLocation');
        const machineStatusSelect = machinePageRoot.querySelector('#filterStatus');
        const machineSuccessAlert = machinePageRoot.querySelector('[data-machine-success-alert]');
        const machineRows = machinePageRoot.querySelectorAll('[data-machine-row]');
        const machineDesktopTable = machinePageRoot.querySelector('[data-machine-table-desktop]');
        const machineMobileTable = machinePageRoot.querySelector('[data-machine-table-mobile]');
        const machineEmptyFilterState = machinePageRoot.querySelector('[data-machine-empty-filter]');
        const machinePaginationWrap = machinePageRoot.querySelector('[data-machine-pagination]');

        if (machineSuccessAlert) {
            window.setTimeout(() => {
                machineSuccessAlert.classList.add('hidden');
            }, 4000);
        }

        if (machineFilterForm instanceof HTMLFormElement) {
            machineFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            const normalize = (value) => (value ?? '').toString().toLowerCase().trim();
            let filterDebounceTimeout;

            const updateResetState = () => {
                if (! machineFilterReset || ! (machineFilterReset instanceof HTMLButtonElement)) {
                    return;
                }

                const hasFilter = normalize(machineFilterSearch?.value) !== ''
                    || normalize(machineLocationSelect?.value) !== ''
                    || normalize(machineStatusSelect?.value) !== '';

                machineFilterReset.disabled = ! hasFilter;
                machineFilterReset.classList.toggle('opacity-50', ! hasFilter);
                machineFilterReset.classList.toggle('pointer-events-none', ! hasFilter);
            };

            const applyFilter = () => {
                const keyword = normalize(machineFilterSearch?.value);
                const location = normalize(machineLocationSelect?.value);
                const status = normalize(machineStatusSelect?.value);
                let visibleRowsCount = 0;

                machineRows.forEach((rowNode) => {
                    if (! (rowNode instanceof HTMLElement)) {
                        return;
                    }

                    const code = normalize(rowNode.dataset.code);
                    const name = normalize(rowNode.dataset.name);
                    const rowLocation = normalize(rowNode.dataset.location);
                    const rowStatus = normalize(rowNode.dataset.status);

                    const matchKeyword = keyword === '' || code.includes(keyword) || name.includes(keyword);
                    const matchLocation = location === '' || rowLocation === location;
                    const matchStatus = status === '' || rowStatus === status;
                    const isVisible = matchKeyword && matchLocation && matchStatus;

                    rowNode.hidden = ! isVisible;
                    if (isVisible) {
                        visibleRowsCount += 1;
                    }
                });

                const isEmptyResult = visibleRowsCount === 0;
                if (machineEmptyFilterState instanceof HTMLElement) {
                    machineEmptyFilterState.classList.toggle('hidden', ! isEmptyResult);
                }

                if (machineDesktopTable instanceof HTMLElement) {
                    machineDesktopTable.classList.toggle('hidden', isEmptyResult || window.innerWidth < 1024);
                    machineDesktopTable.classList.toggle('lg:block', ! isEmptyResult);
                }

                if (machineMobileTable instanceof HTMLElement) {
                    machineMobileTable.classList.toggle('hidden', isEmptyResult || window.innerWidth >= 1024);
                    machineMobileTable.classList.toggle('lg:hidden', ! isEmptyResult);
                }

                if (machinePaginationWrap instanceof HTMLElement) {
                    machinePaginationWrap.classList.toggle('hidden', isEmptyResult);
                }

                if (machineFilterSearchLoading) {
                    machineFilterSearchLoading.classList.add('hidden');
                    machineFilterSearchLoading.classList.remove('flex');
                }

                updateResetState();
            };

            if (machineFilterSearch instanceof HTMLInputElement) {
                machineFilterSearch.addEventListener('input', () => {
                    if (machineFilterSearchLoading) {
                        machineFilterSearchLoading.classList.remove('hidden');
                        machineFilterSearchLoading.classList.add('flex');
                    }

                    window.clearTimeout(filterDebounceTimeout);
                    filterDebounceTimeout = window.setTimeout(() => {
                        applyFilter();
                    }, 300);
                });
            }

            if (machineLocationSelect instanceof HTMLSelectElement) {
                machineLocationSelect.addEventListener('change', applyFilter);
            }

            if (machineStatusSelect instanceof HTMLSelectElement) {
                machineStatusSelect.addEventListener('change', applyFilter);
            }

            if (machineFilterReset instanceof HTMLButtonElement) {
                machineFilterReset.addEventListener('click', () => {
                    if (machineFilterSearch instanceof HTMLInputElement) {
                        machineFilterSearch.value = '';
                    }

                    if (machineLocationSelect instanceof HTMLSelectElement) {
                        machineLocationSelect.value = '';
                    }

                    if (machineStatusSelect instanceof HTMLSelectElement) {
                        machineStatusSelect.value = '';
                    }

                    applyFilter();
                    machineFilterSearch?.focus();
                });
            }

            applyFilter();
        }

        const openCreateMachineModal = () => {
            machineFormTitle && (machineFormTitle.textContent = 'Tambah Mesin');
            machineFormElement?.setAttribute('action', machinePageRoot.getAttribute('data-machine-store-url') ?? '#');
            machineFormMethodWrapper && (machineFormMethodWrapper.innerHTML = '');
            machineFormActionInput && (machineFormActionInput.value = 'create');
            machineFormSubmitLabel && (machineFormSubmitLabel.textContent = 'Simpan');
            setMachineFieldValue('[data-machine-form-id-input]', '');
            setMachineFieldValue('[data-machine-form-field="code"]', '');
            setMachineFieldValue('[data-machine-form-field="name"]', '');
            setMachineFieldValue('[data-machine-form-field="location"]', '');
            setMachineFieldValue('[data-machine-form-field="active"]', '1');
            setMachineFieldValue('[data-machine-form-field="description"]', '');

            if (machineCodeField) {
                machineCodeField.readOnly = false;
                machineCodeField.classList.remove('bg-[var(--color-prime-soft)]');
            }

            openMachineModal(machineFormModal);
        };

        document.querySelectorAll('[data-machine-create-open]').forEach((button) => {
            button.addEventListener('click', openCreateMachineModal);
        });

        document.querySelectorAll('[data-machine-edit-open]').forEach((button) => {
            button.addEventListener('click', () => {
                machineFormTitle && (machineFormTitle.textContent = 'Edit Mesin');
                machineFormElement?.setAttribute('action', button.getAttribute('data-machine-update-url') ?? '#');
                machineFormMethodWrapper && (machineFormMethodWrapper.innerHTML = '<input type="hidden" name="_method" value="PUT">');
                machineFormActionInput && (machineFormActionInput.value = 'edit');
                machineFormSubmitLabel && (machineFormSubmitLabel.textContent = 'Simpan Perubahan');
                setMachineFieldValue('[data-machine-form-id-input]', button.getAttribute('data-machine-id') ?? '');
                setMachineFieldValue('[data-machine-form-field="code"]', button.getAttribute('data-machine-code') ?? '');
                setMachineFieldValue('[data-machine-form-field="name"]', button.getAttribute('data-machine-name') ?? '');
                setMachineFieldValue('[data-machine-form-field="location"]', button.getAttribute('data-machine-location-id') ?? '');
                setMachineFieldValue('[data-machine-form-field="active"]', button.getAttribute('data-machine-active') ?? '1');
                setMachineFieldValue('[data-machine-form-field="description"]', button.getAttribute('data-machine-description') ?? '');

                if (machineCodeField) {
                    machineCodeField.readOnly = true;
                    machineCodeField.classList.add('bg-[var(--color-prime-soft)]');
                }

                openMachineModal(machineFormModal);
            });
        });

        document.querySelectorAll('[data-machine-qr-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const codeNode = document.querySelector('[data-machine-qr-code]');
                const nameNode = document.querySelector('[data-machine-qr-name]');
                const locationNode = document.querySelector('[data-machine-qr-location]');
                const imageNode = document.querySelector('[data-machine-qr-image-preview]');
                const downloadLinkNode = document.querySelector('[data-machine-qr-download-link]');
                const generateFormNode = document.querySelector('[data-machine-qr-generate-form]');
                const machineCode = button.getAttribute('data-machine-code') ?? 'machine';
                const machineName = button.getAttribute('data-machine-name') ?? '-';
                const machineLocation = button.getAttribute('data-machine-location') ?? '-';

                codeNode && (codeNode.textContent = machineCode);
                nameNode && (nameNode.textContent = machineName);
                locationNode && (locationNode.textContent = machineLocation);

                if (imageNode) {
                    imageNode.setAttribute('src', button.getAttribute('data-machine-qr-image') ?? '');
                }

                if (downloadLinkNode) {
                    downloadLinkNode.setAttribute('data-machine-code', machineCode);
                    downloadLinkNode.setAttribute('data-machine-name', machineName);
                    downloadLinkNode.setAttribute('data-machine-location', machineLocation);
                }

                generateFormNode?.setAttribute('action', button.getAttribute('data-machine-qr-generate-url') ?? '#');

                openMachineModal(machineQrModal);
            });
        });

        document.querySelector('[data-machine-qr-download-link]')?.addEventListener('click', (event) => {
            event.preventDefault();

            const button = event.currentTarget;

            if (! (button instanceof HTMLElement)) {
                return;
            }

            const previewImage = document.querySelector('[data-machine-qr-image-preview]');

            if (! (previewImage instanceof HTMLImageElement) || ! previewImage.src) {
                return;
            }

            const machineCode = button.getAttribute('data-machine-code') ?? 'machine';
            const machineName = button.getAttribute('data-machine-name') ?? '-';
            const machineLocation = button.getAttribute('data-machine-location') ?? '-';

            const image = new Image();
            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = 900;
                canvas.height = 1080;
                const context = canvas.getContext('2d');

                if (! context) {
                    return;
                }

                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);

                context.fillStyle = '#191c22';
                context.font = '700 42px Inter, sans-serif';
                context.fillText('QR CODE MESIN', 52, 72);

                context.strokeStyle = '#e0e3ec';
                context.lineWidth = 2;
                context.beginPath();
                context.moveTo(52, 98);
                context.lineTo(848, 98);
                context.stroke();

                context.fillStyle = '#5c6070';
                context.font = '600 27px Inter, sans-serif';
                context.fillText('Kode Mesin', 52, 148);
                context.fillStyle = '#191c22';
                context.font = '700 34px Inter, sans-serif';
                context.fillText(machineCode, 52, 190);

                context.fillStyle = '#5c6070';
                context.font = '600 27px Inter, sans-serif';
                context.fillText('Nama Mesin', 52, 244);
                context.fillStyle = '#191c22';
                context.font = '700 34px Inter, sans-serif';
                context.fillText(machineName, 52, 286);

                context.fillStyle = '#5c6070';
                context.font = '600 27px Inter, sans-serif';
                context.fillText('Lokasi', 52, 340);
                context.fillStyle = '#191c22';
                context.font = '700 34px Inter, sans-serif';
                context.fillText(machineLocation, 52, 382);

                const qrSize = 520;
                const qrX = (canvas.width - qrSize) / 2;
                const qrY = 430;
                context.drawImage(image, qrX, qrY, qrSize, qrSize);

                context.strokeStyle = '#e0e3ec';
                context.lineWidth = 2;
                context.strokeRect(qrX - 14, qrY - 14, qrSize + 28, qrSize + 28);

                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = `${machineCode.toLowerCase()}-qr.png`;
                link.click();
            };

            image.src = previewImage.src;
        });

        document.querySelectorAll('[data-machine-detail-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const codeNode = document.querySelector('[data-machine-detail-code]');
                const nameNode = document.querySelector('[data-machine-detail-name]');
                const locationNode = document.querySelector('[data-machine-detail-location]');
                const statusNode = document.querySelector('[data-machine-detail-status]');
                const descriptionNode = document.querySelector('[data-machine-detail-description]');
                const createdAtNode = document.querySelector('[data-machine-detail-created-at]');
                const updatedAtNode = document.querySelector('[data-machine-detail-updated-at]');

                codeNode && (codeNode.textContent = button.getAttribute('data-machine-code') ?? '-');
                nameNode && (nameNode.textContent = button.getAttribute('data-machine-name') ?? '-');
                locationNode && (locationNode.textContent = button.getAttribute('data-machine-location') ?? '-');
                statusNode && (statusNode.textContent = button.getAttribute('data-machine-status') ?? '-');
                descriptionNode && (descriptionNode.textContent = button.getAttribute('data-machine-description') ?? '-');
                createdAtNode && (createdAtNode.textContent = button.getAttribute('data-machine-created-at') ?? '-');
                updatedAtNode && (updatedAtNode.textContent = button.getAttribute('data-machine-updated-at') ?? '-');

                openMachineModal(machineDetailModal);
            });
        });

        document.querySelectorAll('[data-machine-status-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const statusForm = document.querySelector('[data-machine-status-form]');
                const titleNode = document.querySelector('[data-machine-status-title]');
                const descriptionNode = document.querySelector('[data-machine-status-description-text]');
                const submitNode = document.querySelector('[data-machine-status-submit]');
                const iconWrapNode = document.querySelector('[data-machine-status-icon-wrap]');
                const iconNode = document.querySelector('[data-machine-status-icon]');
                const variant = button.getAttribute('data-machine-status-variant') ?? 'warning';

                statusForm?.setAttribute('action', button.getAttribute('data-machine-status-url') ?? '#');
                titleNode && (titleNode.textContent = button.getAttribute('data-machine-status-label') ?? 'Ubah status mesin');
                descriptionNode && (descriptionNode.textContent = button.getAttribute('data-machine-status-description') ?? '');

                if (submitNode) {
                    submitNode.textContent = button.getAttribute('data-machine-status-button') ?? 'Simpan';
                    submitNode.classList.remove('bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]', 'bg-[var(--color-prime-success)]', 'hover:bg-[#149847]');

                    if (variant === 'success') {
                        submitNode.classList.add('bg-[var(--color-prime-success)]', 'hover:bg-[#149847]');
                    } else {
                        submitNode.classList.add('bg-[var(--color-prime-warning)]', 'hover:bg-[#dd6504]');
                    }
                }

                if (iconWrapNode) {
                    iconWrapNode.classList.remove('bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]', 'bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]');

                    if (variant === 'success') {
                        iconWrapNode.classList.add('bg-[var(--color-prime-success-soft)]', 'text-[var(--color-prime-success)]');
                    } else {
                        iconWrapNode.classList.add('bg-[var(--color-prime-warning-soft)]', 'text-[var(--color-prime-warning)]');
                    }
                }

                if (iconNode) {
                    iconNode.innerHTML = variant === 'success'
                        ? '<path d="m8 6 10 6-10 6V6Z"></path>'
                        : '<path d="M12 2v8"></path><path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path>';
                }

                openMachineModal(machineStatusModal);
            });
        });

        document.querySelectorAll('[data-machine-delete-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const deleteForm = document.querySelector('[data-machine-delete-form]');
                deleteForm?.setAttribute('action', button.getAttribute('data-machine-delete-url') ?? '#');
                openMachineModal(machineDeleteModal);
            });
        });

        const initialMachineModal = machinePageRoot.getAttribute('data-open-modal');

        if (initialMachineModal === 'create') {
            openMachineModal(machineFormModal);
        }

        if (initialMachineModal === 'edit') {
            openMachineModal(machineFormModal);
        }

        machinePageRoot.querySelectorAll('.prime-modal').forEach((modalNode) => {
            if (! (modalNode instanceof HTMLElement)) {
                return;
            }

            modalNode.addEventListener('click', (event) => {
                if (event.target !== modalNode) {
                    return;
                }

                closeModalById(modalNode.id);
            });
        });
    }

    const checksheetWizardRoot = document.querySelector('[data-checksheet-wizard]');

    const checksheetPageRoot = document.querySelector('[data-checksheet-page]');

    if (checksheetPageRoot) {
        const checksheetStatusModal = document.getElementById('checksheet-status-modal');
        const checksheetDeleteModal = document.getElementById('checksheet-delete-modal');
        const checksheetFilterForm = checksheetPageRoot.querySelector('[data-checksheet-filter-form]');
        const checksheetSearchInput = checksheetPageRoot.querySelector('[data-checksheet-filter-search]');
        const checksheetStatusSelect = checksheetPageRoot.querySelector('[data-checksheet-filter-status]');
        const checksheetLocationSelect = checksheetPageRoot.querySelector('[data-checksheet-filter-location]');
        const checksheetMachineSelect = checksheetPageRoot.querySelector('[data-checksheet-filter-machine]');
        const checksheetResetButton = checksheetPageRoot.querySelector('[data-checksheet-filter-reset]');
        const checksheetRows = checksheetPageRoot.querySelectorAll('[data-checksheet-row]');
        const checksheetDesktopTable = checksheetPageRoot.querySelector('[data-checksheet-table-desktop]');
        const checksheetMobileTable = checksheetPageRoot.querySelector('[data-checksheet-table-mobile]');
        const checksheetEmptyFilter = checksheetPageRoot.querySelector('[data-checksheet-empty-filter]');
        const checksheetPagination = checksheetPageRoot.querySelector('[data-checksheet-pagination]');
        const checksheetSearchLoading = checksheetPageRoot.querySelector('[data-checksheet-filter-search-loading]');
        const openChecksheetModal = (modal) => {
            if (! modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            syncGlobalModalState();
        };

        if (checksheetFilterForm instanceof HTMLFormElement) {
            checksheetFilterForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            const normalize = (value) => (value ?? '').toString().toLowerCase().trim();
            let checksheetDebounceTimer;

            const updateResetState = () => {
                if (! (checksheetResetButton instanceof HTMLButtonElement)) {
                    return;
                }

                const hasFilter = normalize(checksheetSearchInput?.value) !== ''
                    || normalize(checksheetStatusSelect?.value) !== ''
                    || normalize(checksheetLocationSelect?.value) !== ''
                    || normalize(checksheetMachineSelect?.value) !== '';
                checksheetResetButton.disabled = ! hasFilter;
                checksheetResetButton.classList.toggle('opacity-50', ! hasFilter);
                checksheetResetButton.classList.toggle('pointer-events-none', ! hasFilter);
            };

            const applyChecksheetFilter = () => {
                const keyword = normalize(checksheetSearchInput?.value);
                const status = normalize(checksheetStatusSelect?.value);
                const location = normalize(checksheetLocationSelect?.value);
                const machine = normalize(checksheetMachineSelect?.value);
                let visibleRowsCount = 0;

                checksheetRows.forEach((rowNode) => {
                    if (! (rowNode instanceof HTMLElement)) {
                        return;
                    }

                    const code = normalize(rowNode.dataset.code);
                    const name = normalize(rowNode.dataset.name);
                    const rowStatus = normalize(rowNode.dataset.status);
                    const rowLocation = normalize(rowNode.dataset.location);
                    const rowMachine = normalize(rowNode.dataset.machine);
                    const matchesKeyword = keyword === '' || code.includes(keyword) || name.includes(keyword);
                    const matchesStatus = status === '' || rowStatus === status;
                    const matchesLocation = location === '' || rowLocation === location;
                    const matchesMachine = machine === '' || rowMachine === machine;
                    const isVisible = matchesKeyword && matchesStatus && matchesLocation && matchesMachine;

                    rowNode.hidden = ! isVisible;
                    if (isVisible) {
                        visibleRowsCount += 1;
                    }
                });

                const hasNoResult = visibleRowsCount === 0;
                if (checksheetEmptyFilter instanceof HTMLElement) {
                    checksheetEmptyFilter.classList.toggle('hidden', ! hasNoResult);
                }
                if (checksheetDesktopTable instanceof HTMLElement) {
                    checksheetDesktopTable.classList.toggle('hidden', hasNoResult || window.innerWidth < 1024);
                    checksheetDesktopTable.classList.toggle('lg:block', ! hasNoResult);
                }
                if (checksheetMobileTable instanceof HTMLElement) {
                    checksheetMobileTable.classList.toggle('hidden', hasNoResult || window.innerWidth >= 1024);
                    checksheetMobileTable.classList.toggle('lg:hidden', ! hasNoResult);
                }
                if (checksheetPagination instanceof HTMLElement) {
                    checksheetPagination.classList.toggle('hidden', hasNoResult);
                }

                if (checksheetSearchLoading) {
                    checksheetSearchLoading.classList.add('hidden');
                    checksheetSearchLoading.classList.remove('flex');
                }

                updateResetState();
            };

            if (checksheetSearchInput instanceof HTMLInputElement) {
                checksheetSearchInput.addEventListener('input', () => {
                    if (checksheetSearchLoading) {
                        checksheetSearchLoading.classList.remove('hidden');
                        checksheetSearchLoading.classList.add('flex');
                    }

                    window.clearTimeout(checksheetDebounceTimer);
                    checksheetDebounceTimer = window.setTimeout(() => {
                        applyChecksheetFilter();
                    }, 300);
                });
            }

            if (checksheetStatusSelect instanceof HTMLSelectElement) {
                checksheetStatusSelect.addEventListener('change', applyChecksheetFilter);
            }

            if (checksheetLocationSelect instanceof HTMLSelectElement) {
                checksheetLocationSelect.addEventListener('change', applyChecksheetFilter);
            }

            if (checksheetMachineSelect instanceof HTMLSelectElement) {
                checksheetMachineSelect.addEventListener('change', applyChecksheetFilter);
            }

            if (checksheetResetButton instanceof HTMLButtonElement) {
                checksheetResetButton.addEventListener('click', () => {
                    if (checksheetSearchInput instanceof HTMLInputElement) {
                        checksheetSearchInput.value = '';
                    }
                    if (checksheetStatusSelect instanceof HTMLSelectElement) {
                        checksheetStatusSelect.value = '';
                    }
                    if (checksheetLocationSelect instanceof HTMLSelectElement) {
                        checksheetLocationSelect.value = '';
                    }
                    if (checksheetMachineSelect instanceof HTMLSelectElement) {
                        checksheetMachineSelect.value = '';
                    }

                    applyChecksheetFilter();
                    checksheetSearchInput?.focus();
                });
            }

            applyChecksheetFilter();
        }

        document.querySelectorAll('[data-checksheet-status-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const statusForm = document.querySelector('[data-checksheet-status-form]');
                const titleNode = document.querySelector('[data-checksheet-status-title]');
                const descriptionNode = document.querySelector('[data-checksheet-status-modal-description]');
                const submitNode = document.querySelector('[data-checksheet-status-submit]');
                const iconWrapNode = document.querySelector('[data-checksheet-status-icon-wrap]');
                const iconNode = document.querySelector('[data-checksheet-status-icon]');
                const variant = button.getAttribute('data-checksheet-status-variant') ?? 'warning';

                statusForm?.setAttribute('action', button.getAttribute('data-checksheet-status-url') ?? '#');
                titleNode && (titleNode.textContent = button.getAttribute('data-checksheet-status-label') ?? 'Ubah status checksheet');
                descriptionNode && (descriptionNode.textContent = button.getAttribute('data-checksheet-status-description') ?? '');

                if (submitNode) {
                    submitNode.textContent = button.getAttribute('data-checksheet-status-button') ?? 'Simpan';
                    submitNode.classList.remove('bg-[#b86b00]', 'hover:bg-[#965500]', 'bg-[#15803d]', 'hover:bg-[#166534]');

                    if (variant === 'success') {
                        submitNode.classList.add('bg-[#15803d]', 'hover:bg-[#166534]');
                    } else {
                        submitNode.classList.add('bg-[#b86b00]', 'hover:bg-[#965500]');
                    }
                }

                if (iconWrapNode) {
                    iconWrapNode.classList.remove('bg-[#fff4df]', 'text-[#a15c00]', 'bg-[#e9fbf1]', 'text-[#0f8a3b]');

                    if (variant === 'success') {
                        iconWrapNode.classList.add('bg-[#e9fbf1]', 'text-[#0f8a3b]');
                    } else {
                        iconWrapNode.classList.add('bg-[#fff4df]', 'text-[#a15c00]');
                    }
                }

                if (iconNode) {
                    iconNode.innerHTML = variant === 'success'
                        ? '<path d="m8 6 10 6-10 6V6Z"></path>'
                        : '<path d="M12 2v8"></path><path d="M7.8 4.8a8 8 0 1 0 8.4 0"></path>';
                }

                openChecksheetModal(checksheetStatusModal);
            });
        });

        document.querySelectorAll('[data-checksheet-delete-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const deleteForm = document.querySelector('[data-checksheet-delete-form]');
                deleteForm?.setAttribute('action', button.getAttribute('data-checksheet-delete-url') ?? '#');
                openChecksheetModal(checksheetDeleteModal);
            });
        });

        checksheetPageRoot.querySelectorAll('.prime-modal').forEach((modalNode) => {
            if (! (modalNode instanceof HTMLElement)) {
                return;
            }

            modalNode.addEventListener('click', (event) => {
                if (event.target !== modalNode) {
                    return;
                }

                closeModalById(modalNode.id);
            });
        });
    }

    const pmReviewPageRoot = document.querySelector('[data-pm-review-page]');

    if (pmReviewPageRoot) {
        const filterForm = pmReviewPageRoot.querySelector('[data-pm-review-filter-form]');
        const searchInput = pmReviewPageRoot.querySelector('[data-pm-review-filter-search]');
        const statusSelect = pmReviewPageRoot.querySelector('[data-pm-review-filter-status]');
        const locationSelect = pmReviewPageRoot.querySelector('[data-pm-review-filter-location]');
        const startDateInput = pmReviewPageRoot.querySelector('[data-pm-review-filter-start-date]');
        const endDateInput = pmReviewPageRoot.querySelector('[data-pm-review-filter-end-date]');
        const resetButton = pmReviewPageRoot.querySelector('[data-pm-review-filter-reset]');
        const emptyResetButton = pmReviewPageRoot.querySelector('[data-pm-review-filter-empty-reset]');
        const rowNodes = pmReviewPageRoot.querySelectorAll('[data-pm-review-row]');
        const desktopTable = pmReviewPageRoot.querySelector('[data-pm-review-table-desktop]');
        const mobileTable = pmReviewPageRoot.querySelector('[data-pm-review-table-mobile]');
        const emptyFilterWrap = pmReviewPageRoot.querySelector('[data-pm-review-empty-filter]');
        const paginationWrap = pmReviewPageRoot.querySelector('[data-pm-review-pagination]');
        const searchLoading = pmReviewPageRoot.querySelector('[data-pm-review-filter-search-loading]');

        if (filterForm instanceof HTMLFormElement) {
            filterForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            const normalize = (value) => (value ?? '').toString().toLowerCase().trim();
            let debounceTimer;

            const updateResetState = () => {
                if (! (resetButton instanceof HTMLButtonElement)) {
                    return;
                }

                const hasFilter = normalize(searchInput?.value) !== ''
                    || normalize(statusSelect?.value) !== ''
                    || normalize(locationSelect?.value) !== ''
                    || normalize(startDateInput?.value) !== ''
                    || normalize(endDateInput?.value) !== '';

                resetButton.disabled = ! hasFilter;
                resetButton.classList.toggle('opacity-50', ! hasFilter);
                resetButton.classList.toggle('pointer-events-none', ! hasFilter);
            };

            const isInDateRange = (rowDateValue, startDateValue, endDateValue) => {
                if (rowDateValue === '') {
                    return false;
                }

                if (startDateValue !== '' && rowDateValue < startDateValue) {
                    return false;
                }

                if (endDateValue !== '' && rowDateValue > endDateValue) {
                    return false;
                }

                return true;
            };

            const applyFilter = () => {
                const keyword = normalize(searchInput?.value);
                const status = normalize(statusSelect?.value);
                const location = normalize(locationSelect?.value);
                const startDate = normalize(startDateInput?.value);
                const endDate = normalize(endDateInput?.value);
                const hasFilter = keyword !== '' || status !== '' || location !== '' || startDate !== '' || endDate !== '';
                let visibleRowsCount = 0;

                rowNodes.forEach((rowNode) => {
                    if (! (rowNode instanceof HTMLElement)) {
                        return;
                    }

                    const code = normalize(rowNode.dataset.code);
                    const name = normalize(rowNode.dataset.name);
                    const operator = normalize(rowNode.dataset.operator);
                    const rowLocation = normalize(rowNode.dataset.location);
                    const rowStatus = normalize(rowNode.dataset.status);
                    const rowDate = normalize(rowNode.dataset.scheduledDate);
                    const matchesKeyword = keyword === '' || code.includes(keyword) || name.includes(keyword) || operator.includes(keyword);
                    const matchesStatus = status === '' || rowStatus === status;
                    const matchesLocation = location === '' || rowLocation === location;
                    const matchesDate = (startDate === '' && endDate === '') || isInDateRange(rowDate, startDate, endDate);
                    const isVisible = matchesKeyword && matchesStatus && matchesLocation && matchesDate;

                    rowNode.hidden = ! isVisible;
                    if (isVisible) {
                        visibleRowsCount += 1;
                    }
                });

                const hasNoResult = visibleRowsCount === 0;
                const hasSourceRows = rowNodes.length > 0;
                const shouldShowFilterEmpty = hasNoResult && hasSourceRows;

                if (emptyFilterWrap instanceof HTMLElement) {
                    emptyFilterWrap.classList.toggle('hidden', ! shouldShowFilterEmpty);
                }

                if (desktopTable instanceof HTMLElement) {
                    desktopTable.classList.toggle('hidden', shouldShowFilterEmpty || window.innerWidth < 1024);
                    desktopTable.classList.toggle('lg:block', ! shouldShowFilterEmpty);
                }

                if (mobileTable instanceof HTMLElement) {
                    mobileTable.classList.toggle('hidden', shouldShowFilterEmpty || window.innerWidth >= 1024);
                    mobileTable.classList.toggle('lg:hidden', ! shouldShowFilterEmpty);
                }

                if (paginationWrap instanceof HTMLElement) {
                    paginationWrap.classList.toggle('hidden', shouldShowFilterEmpty || hasFilter);
                }

                if (searchLoading) {
                    searchLoading.classList.add('hidden');
                    searchLoading.classList.remove('flex');
                }

                updateResetState();
            };

            const resetFilter = () => {
                if (searchInput instanceof HTMLInputElement) {
                    searchInput.value = '';
                }
                if (statusSelect instanceof HTMLSelectElement) {
                    statusSelect.value = '';
                }
                if (locationSelect instanceof HTMLSelectElement) {
                    locationSelect.value = '';
                }
                if (startDateInput instanceof HTMLInputElement) {
                    startDateInput.value = '';
                }
                if (endDateInput instanceof HTMLInputElement) {
                    endDateInput.value = '';
                }

                applyFilter();
                searchInput?.focus();
            };

            if (searchInput instanceof HTMLInputElement) {
                searchInput.addEventListener('input', () => {
                    if (searchLoading) {
                        searchLoading.classList.remove('hidden');
                        searchLoading.classList.add('flex');
                    }

                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(() => {
                        applyFilter();
                    }, 300);
                });
            }

            if (statusSelect instanceof HTMLSelectElement) {
                statusSelect.addEventListener('change', applyFilter);
            }

            if (locationSelect instanceof HTMLSelectElement) {
                locationSelect.addEventListener('change', applyFilter);
            }

            if (startDateInput instanceof HTMLInputElement) {
                startDateInput.addEventListener('change', applyFilter);
            }

            if (endDateInput instanceof HTMLInputElement) {
                endDateInput.addEventListener('change', applyFilter);
            }

            if (resetButton instanceof HTMLButtonElement) {
                resetButton.addEventListener('click', resetFilter);
            }

            if (emptyResetButton instanceof HTMLButtonElement) {
                emptyResetButton.addEventListener('click', resetFilter);
            }

            applyFilter();
        }
    }

    const breakdownReviewPageRoot = document.querySelector('[data-breakdown-review-page]');

    if (breakdownReviewPageRoot) {
        const filterForm = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-form]');
        const searchInput = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-search]');
        const statusSelect = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-status]');
        const locationSelect = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-location]');
        const startDateInput = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-start-date]');
        const endDateInput = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-end-date]');
        const resetButton = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-reset]');
        const emptyResetButton = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-empty-reset]');
        const rowNodes = breakdownReviewPageRoot.querySelectorAll('[data-breakdown-review-row]');
        const desktopTable = breakdownReviewPageRoot.querySelector('[data-breakdown-review-table-desktop]');
        const mobileTable = breakdownReviewPageRoot.querySelector('[data-breakdown-review-table-mobile]');
        const emptyFilterWrap = breakdownReviewPageRoot.querySelector('[data-breakdown-review-empty-filter]');
        const paginationWrap = breakdownReviewPageRoot.querySelector('[data-breakdown-review-pagination]');
        const searchLoading = breakdownReviewPageRoot.querySelector('[data-breakdown-review-filter-search-loading]');

        if (filterForm instanceof HTMLFormElement) {
            filterForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            const normalize = (value) => (value ?? '').toString().toLowerCase().trim();
            let debounceTimer;

            const updateResetState = () => {
                if (! (resetButton instanceof HTMLButtonElement)) {
                    return;
                }

                const hasFilter = normalize(searchInput?.value) !== ''
                    || normalize(statusSelect?.value) !== ''
                    || normalize(locationSelect?.value) !== ''
                    || normalize(startDateInput?.value) !== ''
                    || normalize(endDateInput?.value) !== '';

                resetButton.disabled = ! hasFilter;
                resetButton.classList.toggle('opacity-50', ! hasFilter);
                resetButton.classList.toggle('pointer-events-none', ! hasFilter);
            };

            const isInDateRange = (rowDateValue, startDateValue, endDateValue) => {
                if (rowDateValue === '') {
                    return false;
                }

                if (startDateValue !== '' && rowDateValue < startDateValue) {
                    return false;
                }

                if (endDateValue !== '' && rowDateValue > endDateValue) {
                    return false;
                }

                return true;
            };

            const applyFilter = () => {
                const keyword = normalize(searchInput?.value);
                const status = normalize(statusSelect?.value);
                const location = normalize(locationSelect?.value);
                const startDate = normalize(startDateInput?.value);
                const endDate = normalize(endDateInput?.value);
                let visibleRowsCount = 0;

                rowNodes.forEach((rowNode) => {
                    if (! (rowNode instanceof HTMLElement)) {
                        return;
                    }

                    const code = normalize(rowNode.dataset.code);
                    const machine = normalize(rowNode.dataset.machine);
                    const part = normalize(rowNode.dataset.part);
                    const problem = normalize(rowNode.dataset.problem);
                    const rowStatus = normalize(rowNode.dataset.status);
                    const rowLocation = normalize(rowNode.dataset.location);
                    const rowDate = normalize(rowNode.dataset.breakdownDay);
                    const matchesKeyword = keyword === ''
                        || code.includes(keyword)
                        || machine.includes(keyword)
                        || part.includes(keyword)
                        || problem.includes(keyword);
                    const matchesStatus = status === '' || rowStatus === status;
                    const matchesLocation = location === '' || rowLocation === location;
                    const matchesDate = (startDate === '' && endDate === '') || isInDateRange(rowDate, startDate, endDate);
                    const isVisible = matchesKeyword && matchesStatus && matchesLocation && matchesDate;

                    rowNode.hidden = ! isVisible;
                    if (isVisible) {
                        visibleRowsCount += 1;
                    }
                });

                const hasNoResult = visibleRowsCount === 0;

                if (emptyFilterWrap instanceof HTMLElement) {
                    emptyFilterWrap.classList.toggle('hidden', ! hasNoResult);
                }

                if (desktopTable instanceof HTMLElement) {
                    desktopTable.classList.toggle('hidden', hasNoResult || window.innerWidth < 1024);
                    desktopTable.classList.toggle('lg:block', ! hasNoResult);
                }

                if (mobileTable instanceof HTMLElement) {
                    mobileTable.classList.toggle('hidden', hasNoResult || window.innerWidth >= 1024);
                    mobileTable.classList.toggle('lg:hidden', ! hasNoResult);
                }

                if (paginationWrap instanceof HTMLElement) {
                    paginationWrap.classList.toggle('hidden', hasNoResult);
                }

                if (searchLoading) {
                    searchLoading.classList.add('hidden');
                    searchLoading.classList.remove('flex');
                }

                updateResetState();
            };

            const resetFilter = () => {
                if (searchInput instanceof HTMLInputElement) {
                    searchInput.value = '';
                }
                if (statusSelect instanceof HTMLSelectElement) {
                    statusSelect.value = '';
                }
                if (locationSelect instanceof HTMLSelectElement) {
                    locationSelect.value = '';
                }
                if (startDateInput instanceof HTMLInputElement) {
                    startDateInput.value = '';
                }
                if (endDateInput instanceof HTMLInputElement) {
                    endDateInput.value = '';
                }

                applyFilter();
                searchInput?.focus();
            };

            if (searchInput instanceof HTMLInputElement) {
                searchInput.addEventListener('input', () => {
                    if (searchLoading) {
                        searchLoading.classList.remove('hidden');
                        searchLoading.classList.add('flex');
                    }

                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(() => {
                        applyFilter();
                    }, 300);
                });
            }

            if (statusSelect instanceof HTMLSelectElement) {
                statusSelect.addEventListener('change', applyFilter);
            }

            if (locationSelect instanceof HTMLSelectElement) {
                locationSelect.addEventListener('change', applyFilter);
            }

            if (startDateInput instanceof HTMLInputElement) {
                startDateInput.addEventListener('change', applyFilter);
            }

            if (endDateInput instanceof HTMLInputElement) {
                endDateInput.addEventListener('change', applyFilter);
            }

            if (resetButton instanceof HTMLButtonElement) {
                resetButton.addEventListener('click', resetFilter);
            }

            if (emptyResetButton instanceof HTMLButtonElement) {
                emptyResetButton.addEventListener('click', resetFilter);
            }

            applyFilter();
        }
    }

    const sortGroupConfig = {
        machine: {
            desktop: {
                containerSelector: '[data-machine-table-desktop] tbody',
                rowSelector: '[data-machine-row]',
            },
            mobile: {
                containerSelector: '[data-machine-table-mobile]',
                rowSelector: '[data-machine-row]',
            },
        },
        location: {
            desktop: {
                containerSelector: '[data-location-table-desktop] tbody',
                rowSelector: '[data-location-row]',
            },
            mobile: {
                containerSelector: '[data-location-table-mobile]',
                rowSelector: '[data-location-row]',
            },
        },
        checksheet: {
            desktop: {
                containerSelector: '[data-checksheet-table-desktop] tbody',
                rowSelector: '[data-checksheet-row]',
            },
            mobile: {
                containerSelector: '[data-checksheet-table-mobile]',
                rowSelector: '[data-checksheet-row]',
            },
        },
        'pm-review': {
            desktop: {
                containerSelector: '[data-pm-review-table-desktop] tbody',
                rowSelector: '[data-pm-review-row]',
            },
            mobile: {
                containerSelector: '[data-pm-review-table-mobile]',
                rowSelector: '[data-pm-review-row]',
            },
        },
        'breakdown-input': {
            desktop: {
                containerSelector: '[data-breakdown-machine-page] tbody',
                rowSelector: '[data-breakdown-machine-row]',
            },
            mobile: {
                containerSelector: '[data-breakdown-machine-page] .lg\\:hidden',
                rowSelector: '[data-breakdown-machine-row]',
            },
        },
        'breakdown-review': {
            desktop: {
                containerSelector: '[data-breakdown-review-page] tbody',
                rowSelector: '[data-breakdown-review-row]',
            },
            mobile: {
                containerSelector: '[data-breakdown-review-table-mobile]',
                rowSelector: '[data-breakdown-review-row]',
            },
        },
        users: {
            desktop: {
                containerSelector: '[data-user-settings-page] tbody',
                rowSelector: '[data-user-row]',
            },
        },
    };

    const sortStateByGroup = {};

    const getComparableValue = (rowNode, field) => {
        if (! (rowNode instanceof HTMLElement)) {
            return '';
        }

        return (rowNode.dataset[field] ?? '').toString().toLowerCase().trim();
    };

    const sortGroupRows = (group, field, direction) => {
        const groupConfig = sortGroupConfig[group];

        if (! groupConfig) {
            return;
        }

        ['desktop', 'mobile'].forEach((viewportType) => {
            const viewportConfig = groupConfig[viewportType];

            if (! viewportConfig) {
                return;
            }

            const container = document.querySelector(viewportConfig.containerSelector);

            if (! container) {
                return;
            }

            const rows = Array.from(container.querySelectorAll(viewportConfig.rowSelector));

            if (rows.length < 2) {
                return;
            }

            rows.sort((firstRow, secondRow) => {
                const firstValue = getComparableValue(firstRow, field);
                const secondValue = getComparableValue(secondRow, field);
                const compareResult = firstValue.localeCompare(secondValue, undefined, { numeric: true, sensitivity: 'base' });

                return direction === 'asc' ? compareResult : compareResult * -1;
            });

            rows.forEach((rowNode) => {
                container.appendChild(rowNode);
            });
        });
    };

    const updateSortIcons = (group, activeField, direction) => {
        document.querySelectorAll(`[data-sort-icon][data-sort-group="${group}"]`).forEach((iconNode) => {
            if (! (iconNode instanceof HTMLElement)) {
                return;
            }

            const iconField = iconNode.getAttribute('data-sort-field');
            const isActive = iconField === activeField;
            iconNode.textContent = isActive ? (direction === 'asc' ? '↑' : '↓') : '↕';
            iconNode.classList.toggle('text-[#615d59]', isActive);
            iconNode.classList.toggle('text-[#a39e98]', ! isActive);
        });
    };

    document.querySelectorAll('[data-sort-trigger]').forEach((sortTrigger) => {
        sortTrigger.addEventListener('click', () => {
            const group = sortTrigger.getAttribute('data-sort-group') ?? '';
            const field = sortTrigger.getAttribute('data-sort-field') ?? '';

            if (group === '' || field === '') {
                return;
            }

            const currentState = sortStateByGroup[group] ?? { field: null, direction: 'asc' };
            const nextDirection = currentState.field === field && currentState.direction === 'asc' ? 'desc' : 'asc';

            sortStateByGroup[group] = {
                field,
                direction: nextDirection,
            };

            sortGroupRows(group, field, nextDirection);
            updateSortIcons(group, field, nextDirection);
        });
    });

    if (checksheetWizardRoot) {
        const machineSeed = JSON.parse(checksheetWizardRoot.getAttribute('data-seed-machines') ?? '[]');
        const payloadSeed = JSON.parse(checksheetWizardRoot.getAttribute('data-seed-payload') ?? '{}');
        const form = checksheetWizardRoot.querySelector('[data-checksheet-form]');
        const payloadInput = checksheetWizardRoot.querySelector('[data-checksheet-payload]');
        const panels = Array.from(checksheetWizardRoot.querySelectorAll('[data-step-panel]'));
        const progress = checksheetWizardRoot.querySelector('[data-stepper-progress]');
        const nextButton = checksheetWizardRoot.querySelector('[data-step-next]');
        const backButton = checksheetWizardRoot.querySelector('[data-step-back]');
        const submitButton = checksheetWizardRoot.querySelector('[data-step-submit]');
        const machineList = checksheetWizardRoot.querySelector('[data-machine-list]');
        const machineSearch = checksheetWizardRoot.querySelector('[data-machine-search]');
        const machineLocationFilter = checksheetWizardRoot.querySelector('[data-machine-location-filter]');
        const partsBuilder = checksheetWizardRoot.querySelector('[data-parts-builder]');
        const standardsBuilder = checksheetWizardRoot.querySelector('[data-standards-builder]');
        const weeklyWrap = checksheetWizardRoot.querySelector('[data-weekly-wrap]');
        const monthlyWrap = checksheetWizardRoot.querySelector('[data-monthly-wrap]');
        const frequencyInput = checksheetWizardRoot.querySelector('[data-schedule-frequency]');
        const weeklyDaysWrap = checksheetWizardRoot.querySelector('[data-weekly-days]');
        const monthlyDayInput = checksheetWizardRoot.querySelector('[data-monthly-day]');
        const operationalDateInput = checksheetWizardRoot.querySelector('[data-schedule-operational]');
        const previewWrap = checksheetWizardRoot.querySelector('[data-schedule-preview]');
        const reviewBox = checksheetWizardRoot.querySelector('[data-review-box]');
        const editId = checksheetWizardRoot.getAttribute('data-edit-id');
        const previewUrl = checksheetWizardRoot.getAttribute('data-preview-url');
        const applyUrl = checksheetWizardRoot.getAttribute('data-apply-url');
        const scheduleStatus = checksheetWizardRoot.querySelector('[data-schedule-status]');
        const scheduleImpact = checksheetWizardRoot.querySelector('[data-schedule-impact]');
        const applyButton = checksheetWizardRoot.querySelector('[data-apply-schedule]');
        const applyStatus = checksheetWizardRoot.querySelector('[data-apply-status]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const dayLabels = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        let currentStep = 1;

        const state = {
            selectedMachineIds: payloadSeed.selected_machine_ids ?? [],
            parts: payloadSeed.parts ?? {},
            // Server mengirim standards: [] (array kosong) saat checksheet belum memiliki
            // part/standard. Assignment key dinamis (partId) pada Array diabaikan
            // oleh JSON.stringify, sehingga payload ke server selalu "[]". Normalisasi
            // ke plain object agar key yang ditambahkan wizard tetap tersimpan.
            standards: Array.isArray(payloadSeed.standards) ? {} : (payloadSeed.standards ?? {}),
            schedule: payloadSeed.schedule ?? {
                frequency_type: '',
                weekly_days: [],
                monthly_day: null,
                operational_from: '',
            },
        };

        const ensureMachineState = () => {
            state.selectedMachineIds.forEach((machineId) => {
                if (! Array.isArray(state.parts[machineId])) {
                    state.parts[machineId] = [{ id: `${machineId}-1`, name: '', description: '' }];
                }
            });
        };

        const renderProgress = () => {
            if (! progress) {
                return;
            }

            const labels = ['Header', 'Pilih Mesin', 'Part Mesin', 'Standard', 'Jadwal PM', 'Review'];
            progress.innerHTML = labels.map((label, index) => {
                const step = index + 1;
                const isCurrent = step === currentStep;
                const isDone = step < currentStep;

                return `<div class="flex items-center gap-2 text-sm ${isCurrent ? 'text-[var(--color-prime-primary)]' : 'text-[var(--color-prime-muted)]'}"><span class="inline-flex h-7 w-7 items-center justify-center rounded-full ${isDone ? 'bg-[var(--color-prime-success)] text-white' : (isCurrent ? 'bg-[var(--color-prime-primary)] text-white' : 'bg-[var(--color-prime-soft)]')}" >${isDone ? '✓' : step}</span>${label}</div>`;
            }).join('<span class="h-px flex-1 bg-[var(--color-prime-border)]"></span>');
        };

        const renderPanels = () => {
            panels.forEach((panel) => {
                panel.classList.toggle('hidden', Number(panel.getAttribute('data-step-panel')) !== currentStep);
            });
            backButton?.classList.toggle('invisible', currentStep === 1);
            nextButton?.classList.toggle('hidden', currentStep === 6);
            submitButton?.classList.toggle('hidden', currentStep !== 6);
            renderProgress();
        };

        const filteredMachines = () => {
            const query = (machineSearch?.value ?? '').toLowerCase().trim();
            const selectedLocation = (machineLocationFilter?.value ?? '').toLowerCase().trim();
            if (query === '') {
                return selectedLocation === ''
                    ? machineSeed
                    : machineSeed.filter((machine) => (machine.location ?? '').toLowerCase() === selectedLocation);
            }

            return machineSeed.filter((machine) => {
                const matchQuery = (`${machine.code} ${machine.name} ${machine.location ?? ''}`).toLowerCase().includes(query);
                const matchLocation = selectedLocation === '' || (machine.location ?? '').toLowerCase() === selectedLocation;

                return matchQuery && matchLocation;
            });
        };

        const renderMachineList = () => {
            if (! machineList) {
                return;
            }

            // Escape seluruh nilai seed agar machine master tidak menjadi HTML executable.
            machineList.innerHTML = filteredMachines().map((machine) => {
                const checked = state.selectedMachineIds.includes(machine.id) ? 'checked' : '';
                return `<label class="flex cursor-pointer items-center justify-between gap-3 border-b border-[var(--color-prime-border)] px-4 py-3 last:border-b-0"><span class="inline-flex items-center gap-3"><input type="checkbox" value="${escapeHtml(machine.id)}" ${checked} data-machine-checkbox><span class="font-semibold">${escapeHtml(machine.code)}</span><span class="text-[var(--color-prime-muted)]">${escapeHtml(machine.name)}</span></span><span class="rounded-full bg-[var(--color-prime-soft)] px-2 py-1 text-xs text-[var(--color-prime-muted)]">${escapeHtml(machine.location ?? '-')}</span></label>`;
            }).join('');
        };

        const renderParts = () => {
            if (! partsBuilder) {
                return;
            }

            partsBuilder.innerHTML = state.selectedMachineIds.map((machineId) => {
                const machine = machineSeed.find((item) => item.id === machineId);
                const parts = state.parts[machineId] ?? [];

                return `<div class="rounded-xl border border-[var(--color-prime-border)] p-4"><div class="mb-3 flex items-center justify-between"><h4 class="font-semibold">${escapeHtml(machine?.code)} - ${escapeHtml(machine?.name)}</h4><button type="button" class="text-sm font-semibold text-[var(--color-prime-primary)]" data-add-part="${escapeHtml(machineId)}">+ Tambah Part</button></div>${parts.map((part, index) => `<div class="mb-2 grid gap-2 md:grid-cols-[3rem_minmax(0,1fr)_minmax(0,1fr)_2rem]"><div class="rounded-lg bg-[var(--color-prime-soft)] px-3 py-2 text-center text-sm">${index + 1}</div><input type="text" value="${escapeHtml(part.name)}" placeholder="Nama Part" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-part-name="${escapeHtml(machineId)}:${escapeHtml(part.id)}"><input type="text" value="${escapeHtml(part.description)}" placeholder="Deskripsi opsional" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-part-description="${escapeHtml(machineId)}:${escapeHtml(part.id)}"><button type="button" class="text-[var(--color-prime-danger)]" data-remove-part="${escapeHtml(machineId)}:${escapeHtml(part.id)}">x</button></div>`).join('')}</div>`;
            }).join('');
        };

        const renderStandards = () => {
            if (! standardsBuilder) {
                return;
            }

            const blocks = [];
            state.selectedMachineIds.forEach((machineId) => {
                const machine = machineSeed.find((item) => item.id === machineId);
                (state.parts[machineId] ?? []).forEach((part) => {
                    const partStandards = state.standards[part.id] ?? [{ name: '', input_type: '', action_options: [], is_required: true, is_active: true }];
                    state.standards[part.id] = partStandards;

                    blocks.push(`<div class="rounded-xl border border-[var(--color-prime-border)] p-4"><div class="mb-3 flex items-center justify-between"><div><p class="text-sm text-[var(--color-prime-muted)]">${escapeHtml(machine?.code)}</p><h4 class="font-semibold">${escapeHtml(part.name || '(Part belum diisi)')}</h4></div><button type="button" class="text-sm font-semibold text-[var(--color-prime-primary)]" data-add-standard="${escapeHtml(part.id)}">+ Tambah Standard</button></div>${partStandards.map((standard, index) => `<div class="mb-3 rounded-lg border border-[var(--color-prime-border)] p-3"><div class="mb-2 grid gap-2 md:grid-cols-2"><input type="text" value="${escapeHtml(standard.name)}" placeholder="Nama Standard" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-name="${escapeHtml(part.id)}:${index}"><select class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-type="${escapeHtml(part.id)}:${index}"><option value="">Pilih Tipe...</option><option value="action" ${standard.input_type === 'action' ? 'selected' : ''}>Action (Pilihan)</option><option value="number" ${standard.input_type === 'number' ? 'selected' : ''}>Number (Target)</option><option value="range" ${standard.input_type === 'range' ? 'selected' : ''}>Range (Min-Max)</option></select></div><div class="mb-2 flex items-center gap-3 text-sm"><label><input type="checkbox" ${standard.is_required ? 'checked' : ''} data-standard-required="${escapeHtml(part.id)}:${index}"> Wajib Diisi</label><label><input type="checkbox" ${standard.is_active ? 'checked' : ''} data-standard-active="${escapeHtml(part.id)}:${index}"> Aktif</label><button type="button" class="ml-auto text-[var(--color-prime-danger)]" data-remove-standard="${escapeHtml(part.id)}:${index}">Hapus</button></div>${standard.input_type === 'action' ? `<div><div class="mb-2 flex gap-2"><input type="text" class="flex-1 rounded-lg border border-[var(--color-prime-border)] px-3 py-2" placeholder="Tambah action option" data-action-input="${escapeHtml(part.id)}:${index}"><button type="button" class="rounded-lg bg-[var(--color-prime-primary)] px-3 py-2 text-sm font-semibold text-white" data-add-action="${escapeHtml(part.id)}:${index}">Tambah</button></div><div class="flex flex-wrap gap-2">${(standard.action_options ?? []).map((option, optionIndex) => `<button type="button" class="rounded-full bg-[var(--color-prime-primary-soft)] px-3 py-1 text-xs text-[var(--color-prime-primary)]" data-remove-action="${escapeHtml(part.id)}:${index}:${optionIndex}">${escapeHtml(option)} x</button>`).join('')}</div></div>` : ''}${standard.input_type === 'number' ? `<div class="grid gap-2 md:grid-cols-2"><input type="number" step="0.01" value="${escapeHtml(standard.target_value)}" placeholder="Target Value" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-target="${escapeHtml(part.id)}:${index}"><input type="text" value="${escapeHtml(standard.unit)}" placeholder="Satuan" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-unit="${escapeHtml(part.id)}:${index}"></div>` : ''}${standard.input_type === 'range' ? `<div class="grid gap-2 md:grid-cols-3"><input type="number" step="0.01" value="${escapeHtml(standard.min_value)}" placeholder="Min Value" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-min="${escapeHtml(part.id)}:${index}"><input type="number" step="0.01" value="${escapeHtml(standard.max_value)}" placeholder="Max Value" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-max="${escapeHtml(part.id)}:${index}"><input type="text" value="${escapeHtml(standard.unit)}" placeholder="Satuan" class="rounded-lg border border-[var(--color-prime-border)] px-3 py-2" data-standard-unit="${escapeHtml(part.id)}:${index}"></div>` : ''}</div>`).join('')}</div>`);
                });
            });
            standardsBuilder.innerHTML = blocks.join('');
        };

        const renderSchedule = () => {
            frequencyInput.value = state.schedule.frequency_type ?? '';
            monthlyDayInput.value = state.schedule.monthly_day ?? '';
            operationalDateInput.value = state.schedule.operational_from ?? '';
            weeklyWrap.classList.toggle('hidden', state.schedule.frequency_type !== 'weekly');
            monthlyWrap.classList.toggle('hidden', state.schedule.frequency_type !== 'monthly');
            weeklyDaysWrap.innerHTML = dayLabels.map((label, index) => `<label><input type="checkbox" value="${index}" ${state.schedule.weekly_days?.includes(index) ? 'checked' : ''} data-weekday> ${label}</label>`).join('');
            renderPreview();
        };

        const renderPreview = () => {
            const frequencyType = state.schedule.frequency_type;
            const operationalFrom = state.schedule.operational_from;
            const planningEnd = planningEndFor(operationalFrom);

            if (! frequencyType || ! operationalFrom || ! planningEnd) {
                previewWrap.innerHTML = '<p class="text-sm text-[var(--color-prime-muted)]">Isi form jadwal secara lengkap untuk melihat preview.</p>';
                return;
            }

            const dates = [];
            let cursor = new Date(operationalFrom);
            const endDate = new Date(planningEnd);
            endDate.setHours(23, 59, 59, 999);
            let guard = 0;

            while (cursor <= endDate && dates.length < 10 && guard < 1000) {
                let include = false;
                if (frequencyType === 'daily') {
                    include = true;
                } else if (frequencyType === 'weekly') {
                    include = (state.schedule.weekly_days ?? []).includes(cursor.getDay());
                } else if (frequencyType === 'monthly') {
                    const monthlyDay = Number(state.schedule.monthly_day ?? 1);
                    const validDay = Math.min(monthlyDay, new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate());
                    include = cursor.getDate() === validDay;
                }

                if (include) {
                    dates.push(new Date(cursor));
                }

                cursor.setDate(cursor.getDate() + 1);
                guard += 1;
            }

            if (dates.length === 0) {
                previewWrap.innerHTML = '<p class="text-sm text-[var(--color-prime-muted)]">Tidak ada jadwal yang cocok.</p>';
                return;
            }

            previewWrap.innerHTML = dates.map((date) => `<span class="rounded-full border border-[var(--color-prime-border)] bg-white px-3 py-1 text-sm text-[var(--color-prime-muted)]">${date.toLocaleDateString('id-ID', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })}</span>`).join('');
        };

        const planningEndFor = (operationalFrom) => {
            if (! operationalFrom) {
                return '';
            }
            const start = new Date(operationalFrom);
            const targetMonth = start.getUTCMonth() + 12;
            const year = start.getUTCFullYear() + Math.floor(targetMonth / 12);
            const month = targetMonth % 12;
            const lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
            const day = Math.min(start.getUTCDate(), lastDay);
            const end = new Date(Date.UTC(year, month, day));
            const iso = end.toISOString().slice(0, 10);

            return iso;
        };

        const renderReview = () => {
            const machineNames = state.selectedMachineIds.map((id) => machineSeed.find((machine) => machine.id === id)?.code).join(', ');
            const partCount = state.selectedMachineIds.reduce((carry, machineId) => carry + (state.parts[machineId] ?? []).length, 0);
            const standardCount = Object.values(state.standards).reduce((carry, rows) => carry + rows.length, 0);
            const planningEnd = planningEndFor(state.schedule.operational_from);

            reviewBox.innerHTML = `<div class="grid gap-4 md:grid-cols-2"><div><p class="text-sm text-[var(--color-prime-muted)]">Mesin</p><p class="font-semibold">${escapeHtml(machineNames || '-')}</p><p class="mt-3 text-sm text-[var(--color-prime-muted)]">Part</p><p class="font-semibold">${partCount} Part</p><p class="mt-3 text-sm text-[var(--color-prime-muted)]">Standard</p><p class="font-semibold">${standardCount} Standard</p></div><div><p class="text-sm text-[var(--color-prime-muted)]">Frekuensi</p><p class="font-semibold">${escapeHtml(state.schedule.frequency_type || '-')}</p><p class="mt-3 text-sm text-[var(--color-prime-muted)]">Mulai Jadwal PRIME</p><p class="font-semibold">${escapeHtml(state.schedule.operational_from || '-')}</p><p class="mt-3 text-sm text-[var(--color-prime-muted)]">Berakhir</p><p class="font-semibold">${escapeHtml(planningEnd || '-')}</p></div></div>`;
        };

        const validateStep = () => {
            if (currentStep === 2 && state.selectedMachineIds.length === 0) {
                window.alert('Pilih minimal 1 mesin.');
                return false;
            }

            if (currentStep === 3) {
                for (const machineId of state.selectedMachineIds) {
                    const rows = state.parts[machineId] ?? [];
                    if (rows.length === 0 || rows.some((part) => ! part.name?.trim())) {
                        window.alert('Setiap mesin wajib memiliki minimal 1 part dengan nama.');
                        return false;
                    }
                }
            }

            if (currentStep === 5) {
                const legacyNullEdit = Boolean(editId) && ! state.schedule.operational_from;
                // Edit legacy tanpa tanggal operasional boleh lanjut agar server menampilkan status unresolved.
                if (! state.schedule.frequency_type || (! state.schedule.operational_from && ! legacyNullEdit)) {
                    window.alert('Lengkapi konfigurasi jadwal PM (frekuensi dan tanggal mulai jadwal PRIME).');
                    return false;
                }
            }

            return true;
        };


        let previewToken = '';
        let scheduleApplied = false;
        // Penanda bahwa notice stale sedang aktif — mencegah runServerPreview() menimpa notice
        // dan menjaga pesan bisnis tetap terlihat sampai Admin menerapkan preview terbaru.
        let staleNoticeActive = false;

        // Render notice stale ke slot applyStatus agar tidak tertimpa oleh status preview.
        // Copy bisnis — jangan pernah tampilkan jargon backend (409/token/fingerprint).
        const renderStaleNotice = () => {
            if (! applyStatus) {
                return;
            }
            applyStatus.textContent = 'Jadwal telah berubah sejak pratinjau dibuat. Pratinjau telah diperbarui berdasarkan kondisi terbaru.';
            applyStatus.className = 'rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900';
            applyStatus.classList.remove('hidden');
        };


        // Panel status server dipakai hanya pada mode edit agar create tetap dapat disimpan sebagai master.
        const renderServerStatus = (message, variant = 'info') => {
            if (! scheduleStatus) {
                return;
            }
            const styles = {
                amber: 'border-amber-300 bg-amber-50 text-amber-900',
                red: 'border-red-300 bg-red-50 text-red-900',
                green: 'border-emerald-300 bg-emerald-50 text-emerald-900',
                info: 'border-[var(--color-prime-border)] bg-[var(--color-prime-soft)] text-[var(--color-prime-ink)]',
            };
            scheduleStatus.className = `rounded-xl border p-4 ${styles[variant] ?? styles.info}`;
            scheduleStatus.textContent = message;
            scheduleStatus.classList.remove('hidden');
        };

        const renderImpact = (impact = {}) => {
            if (! scheduleImpact) {
                return;
            }

            renderScheduleImpact(scheduleImpact, impact);
        };

        // Parse response JSON secara defensif; kembalikan null jika response bukan JSON valid
        // untuk mencegah kebocoran SyntaxError / HTML deprecation ke Admin.
        const safeParseJson = async (response) => {
            const contentType = response.headers.get('content-type') ?? '';
            if (! contentType.includes('application/json')) {
                return null;
            }
            try {
                return await response.json();
            } catch {
                return null;
            }
        };

        const runServerPreview = async () => {
            if (! editId || ! previewUrl || scheduleApplied) {
                return;
            }
            collectPayload();
            previewToken = '';
            applyButton?.classList.add('hidden');
            applyButton?.setAttribute('disabled', 'disabled');
            // Jangan sembunyikan notice stale selama preview authoritative sedang dimuat.
            if (! staleNoticeActive) {
                applyStatus?.classList.add('hidden');
            }
            renderServerStatus('Memuat preview perubahan jadwal...', 'info');
            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ wizard_payload: payloadInput.value }),
                });
                // Parse defensif — jangan tampilkan raw SyntaxError/HTML ke Admin
                const result = await safeParseJson(response);
                if (result === null) {
                    renderServerStatus('Pratinjau jadwal tidak dapat dimuat. Silakan coba kembali.', 'red');
                    return;
                }
                if (! response.ok) {
                    renderServerStatus(result.message ?? 'Preview jadwal gagal dimuat.', 'red');
                    return;
                }
                previewToken = result.token ?? '';
                renderImpact(result.impact ?? {});
                if (result.status === 'unresolved') {
                    renderServerStatus('Jadwal ini belum memiliki tanggal awal operasional (legacy). Simpan sebagai data master saja — jadwal tidak akan disinkronkan.', 'amber');
                } else if (result.status === 'zero') {
                    renderServerStatus('Tidak ada perubahan jadwal yang perlu diterapkan. Tombol terapkan tetap aktif untuk inisialisasi jadwal baru.', 'info');
                    // Bug-fix TASK-002: status=zero + token non-kosong artinya service mengizinkan
                    // apply (kasus checksheet tanpa jadwal aktif). Tampilkan tombol agar wizard
                    // dapat menginisialisasi jadwal pertama via apply endpoint.
                    if (previewToken) {
                        applyButton?.classList.remove('hidden');
                        applyButton?.removeAttribute('disabled');
                    }
                } else if (result.status === 'conflict') {
                    renderServerStatus('Perubahan bentrok dengan data historis/protected. Periksa daftar konflik.', 'red');
                    applyButton?.classList.remove('hidden');
                    applyButton?.setAttribute('disabled', 'disabled');
                } else if (result.status === 'normal') {
                    renderServerStatus('Perubahan jadwal siap diterapkan.', 'green');
                    applyButton?.classList.remove('hidden');
                    applyButton?.removeAttribute('disabled');
                } else {
                    renderServerStatus('Status preview jadwal tidak dikenali.', 'info');
                }
            } catch {
                // Network error atau kegagalan tak terduga — tampilkan pesan stabil
                renderServerStatus('Pratinjau jadwal tidak dapat dimuat. Silakan coba kembali.', 'red');
            }
        };

        applyButton?.addEventListener('click', async () => {
            if (! editId || ! applyUrl || ! previewToken || scheduleApplied) {
                return;
            }
            collectPayload();
            applyButton.setAttribute('disabled', 'disabled');
            applyStatus?.classList.add('hidden');
            try {
                const response = await fetch(applyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ wizard_payload: payloadInput.value, token: previewToken, confirmed: 1 }),
                });
                // Parse defensif — blokir mutation jika response tidak valid
                const result = await safeParseJson(response);
                if (result === null) {
                    renderServerStatus('Penerapan jadwal tidak dapat dimuat. Silakan coba kembali.', 'red');
                    applyButton.removeAttribute('disabled');
                    return;
                }
                if (response.status === 409) {
                    // Tampilkan notice stale ke slot applyStatus — jangan tampilkan jargon backend.
                    staleNoticeActive = true;
                    renderStaleNotice();
                    await runServerPreview();
                    return;
                }
                if (response.status === 422) {
                    renderServerStatus(result.message ?? 'Jadwal belum dapat diterapkan.', 'amber');
                    applyButton.classList.add('hidden');
                    applyButton.setAttribute('disabled', 'disabled');
                    return;
                }
                if (! response.ok || result.status !== 'applied') {
                    renderServerStatus(result.message ?? 'Penerapan jadwal gagal.', 'red');
                    applyButton.removeAttribute('disabled');
                    return;
                }
                scheduleApplied = true;
                // Bersihkan stale notice sebelum menampilkan success copy.
                staleNoticeActive = false;
                applyButton.classList.add('hidden');
                if (applyStatus) {
                    applyStatus.textContent = 'Jadwal berhasil diterapkan. Menyimpan perubahan checksheet...';
                    applyStatus.className = 'rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900';
                }
                form?.requestSubmit();
            } catch {
                // Network error atau kegagalan tak terduga — tampilkan pesan stabil, blokir mutation
                renderServerStatus('Penerapan jadwal gagal. Silakan coba kembali.', 'red');
                applyButton.removeAttribute('disabled');
            }
        });
        const collectPayload = () => {
            payloadInput.value = JSON.stringify({
                selected_machine_ids: state.selectedMachineIds,
                parts: state.parts,
                standards: state.standards,
                schedule: state.schedule,
            });
        };

        machineSearch?.addEventListener('input', renderMachineList);
        machineLocationFilter?.addEventListener('change', renderMachineList);
        machineList?.addEventListener('change', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLInputElement) || target.getAttribute('data-machine-checkbox') === null) {
                return;
            }

            const machineId = Number(target.value);
            if (target.checked) {
                if (! state.selectedMachineIds.includes(machineId)) {
                    state.selectedMachineIds.push(machineId);
                }
            } else {
                state.selectedMachineIds = state.selectedMachineIds.filter((id) => id !== machineId);
                delete state.parts[machineId];
            }
            ensureMachineState();
            renderMachineList();
            renderParts();
            renderStandards();
        });

        partsBuilder?.addEventListener('input', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLInputElement)) {
                return;
            }
            const [machineId, partId] = (target.getAttribute('data-part-name') ?? target.getAttribute('data-part-description') ?? '').split(':');
            if (! machineId || ! partId) {
                return;
            }
            const part = (state.parts[machineId] ?? []).find((item) => item.id === partId);
            if (! part) {
                return;
            }
            if (target.hasAttribute('data-part-name')) {
                part.name = target.value;
            } else {
                part.description = target.value;
            }
        });

        partsBuilder?.addEventListener('click', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLElement)) {
                return;
            }

            const addMachineId = target.getAttribute('data-add-part');
            if (addMachineId) {
                const nextIndex = (state.parts[addMachineId] ?? []).length + 1;
                state.parts[addMachineId] = [...(state.parts[addMachineId] ?? []), { id: `${addMachineId}-${Date.now()}-${nextIndex}`, name: '', description: '' }];
                renderParts();
                renderStandards();
                return;
            }

            const removePart = target.getAttribute('data-remove-part');
            if (removePart) {
                const [machineId, partId] = removePart.split(':');
                if ((state.parts[machineId] ?? []).length <= 1) {
                    window.alert('Minimal 1 part per mesin.');
                    return;
                }
                state.parts[machineId] = state.parts[machineId].filter((part) => part.id !== partId);
                delete state.standards[partId];
                renderParts();
                renderStandards();
            }
        });

        standardsBuilder?.addEventListener('click', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLElement)) {
                return;
            }

            const addStandardId = target.getAttribute('data-add-standard');
            if (addStandardId) {
                state.standards[addStandardId] = [...(state.standards[addStandardId] ?? []), { name: '', input_type: '', action_options: [], is_required: true, is_active: true }];
                renderStandards();
                return;
            }

            const removeStandard = target.getAttribute('data-remove-standard');
            if (removeStandard) {
                const [partId, index] = removeStandard.split(':');
                state.standards[partId].splice(Number(index), 1);
                if (state.standards[partId].length === 0) {
                    state.standards[partId] = [{ name: '', input_type: '', action_options: [], is_required: true, is_active: true }];
                }
                renderStandards();
                return;
            }

            const addAction = target.getAttribute('data-add-action');
            if (addAction) {
                const [partId, index] = addAction.split(':');
                const input = standardsBuilder.querySelector(`[data-action-input="${partId}:${index}"]`);
                if (! (input instanceof HTMLInputElement)) {
                    return;
                }
                const value = input.value.trim().toUpperCase();
                if (value !== '' && ! state.standards[partId][Number(index)].action_options.includes(value)) {
                    state.standards[partId][Number(index)].action_options.push(value);
                    input.value = '';
                    renderStandards();
                }
                return;
            }

            const removeAction = target.getAttribute('data-remove-action');
            if (removeAction) {
                const [partId, index, optionIndex] = removeAction.split(':');
                state.standards[partId][Number(index)].action_options.splice(Number(optionIndex), 1);
                renderStandards();
            }
        });

        standardsBuilder?.addEventListener('input', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLInputElement) && ! (target instanceof HTMLSelectElement)) {
                return;
            }

            const matrix = [
                ['data-standard-name', 'name'],
                ['data-standard-type', 'input_type'],
                ['data-standard-target', 'target_value'],
                ['data-standard-min', 'min_value'],
                ['data-standard-max', 'max_value'],
                ['data-standard-unit', 'unit'],
            ];

            for (const [attribute, field] of matrix) {
                const key = target.getAttribute(attribute);
                if (! key) {
                    continue;
                }
                const [partId, index] = key.split(':');
                state.standards[partId][Number(index)][field] = target.value;
                if (field === 'input_type') {
                    state.standards[partId][Number(index)].action_options = [];
                    renderStandards();
                }
                return;
            }
        });

        standardsBuilder?.addEventListener('change', (event) => {
            const target = event.target;
            if (! (target instanceof HTMLInputElement)) {
                return;
            }

            const requiredKey = target.getAttribute('data-standard-required');
            if (requiredKey) {
                const [partId, index] = requiredKey.split(':');
                state.standards[partId][Number(index)].is_required = target.checked;
                return;
            }

            const activeKey = target.getAttribute('data-standard-active');
            if (activeKey) {
                const [partId, index] = activeKey.split(':');
                state.standards[partId][Number(index)].is_active = target.checked;
            }
        });

        frequencyInput?.addEventListener('change', () => {
            state.schedule.frequency_type = frequencyInput.value;
            state.schedule.weekly_days = [];
            state.schedule.monthly_day = null;
            renderSchedule();
        });

        weeklyDaysWrap?.addEventListener('change', () => {
            state.schedule.weekly_days = Array.from(weeklyDaysWrap.querySelectorAll('[data-weekday]:checked')).map((input) => Number(input.value));
            renderPreview();
        });

        monthlyDayInput?.addEventListener('input', () => {
            state.schedule.monthly_day = monthlyDayInput.value === '' ? null : Number(monthlyDayInput.value);
            renderPreview();
        });

        operationalDateInput?.addEventListener('change', () => {
            state.schedule.operational_from = operationalDateInput.value;
            renderPreview();
        });

        checksheetWizardRoot.querySelector('[data-refresh-preview]')?.addEventListener('click', renderPreview);

        nextButton?.addEventListener('click', () => {
            if (! validateStep()) {
                return;
            }
            currentStep = Math.min(6, currentStep + 1);
            if (currentStep === 6) {
                renderReview();
                collectPayload();
                // Preview server dijalankan saat review edit dibuka, tanpa mengganggu tombol Save legacy.
                runServerPreview();
            }
            renderPanels();
        });

        backButton?.addEventListener('click', () => {
            currentStep = Math.max(1, currentStep - 1);
            renderPanels();
        });

        form?.addEventListener('submit', () => {
            collectPayload();
        });

        ensureMachineState();
        if (machineLocationFilter) {
            const locations = [...new Set(machineSeed.map((machine) => machine.location).filter((location) => typeof location === 'string' && location !== ''))];
            machineLocationFilter.innerHTML = ['<option value="">Semua Lokasi</option>', ...locations.map((location) => `<option value="${escapeHtml(location)}">${escapeHtml(location)}</option>`)].join('');
        }
        renderMachineList();
        renderParts();
        renderStandards();
        renderSchedule();
        renderPanels();
    }
});
