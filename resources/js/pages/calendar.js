import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import idLocale from '@fullcalendar/core/locales/id';

const toggleState = (node, visible) => {
    if (!node) {
        return;
    }

    node.classList.toggle('hidden', !visible);
    node.classList.toggle('flex', visible);
};

const getErrorMessage = async (response) => {
    try {
        const payload = await response.json();
        if (payload?.message) {
            return payload.message;
        }
    } catch {
        // noop
    }

    return 'Gagal memuat data kalender.';
};

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-calendar-page]');

    if (!root) {
        return;
    }

    const role = root.getAttribute('data-calendar-role') ?? 'guest';
    const eventsUrl = root.getAttribute('data-events-url') ?? '/calendar/events';
    const form = root.querySelector('[data-calendar-filter-form]');
    const resetButton = root.querySelector('[data-calendar-reset]');
    const loadingNode = root.querySelector('[data-calendar-loading]');
    const emptyNode = root.querySelector('[data-calendar-empty]');
    const errorNode = root.querySelector('[data-calendar-error]');
    const errorMessageNode = root.querySelector('[data-calendar-error-message]');

    if (!form) {
        return;
    }

    const collectFilters = () => {
        const formData = new FormData(form);
        const params = new URLSearchParams();

        ['event_type', 'location_id', 'machine_id', 'status'].forEach((key) => {
            const value = formData.get(key);
            if (typeof value === 'string' && value !== '') {
                params.set(key, value);
            }
        });

        return params;
    };

    const calendarNode = document.getElementById('maintenance-calendar');
    if (!calendarNode) {
        return;
    }

    let latestRequestId = 0;

    const calendar = new Calendar(calendarNode, {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
        },
        buttonText: {
            today: 'Hari Ini',
            month: 'Bulan',
            week: 'Minggu',
            day: 'Hari',
        },
        locale: idLocale,
        events: async (fetchInfo, successCallback, failureCallback) => {
            const requestId = ++latestRequestId;
            toggleState(errorNode, false);
            toggleState(emptyNode, false);
            toggleState(loadingNode, true);

            const params = collectFilters();
            params.set('start', fetchInfo.startStr);
            params.set('end', fetchInfo.endStr);

            try {
                const response = await fetch(`${eventsUrl}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    const errorMessage = await getErrorMessage(response);
                    throw new Error(errorMessage);
                }

                if (requestId !== latestRequestId) {
                    return;
                }

                const events = await response.json();
                successCallback(events);

                calendarNode.classList.toggle('calendar-empty', events.length === 0);
                toggleState(emptyNode, events.length === 0);
                toggleState(loadingNode, false);
                requestAnimationFrame(() => {
                    calendar.updateSize();
                });
            } catch (error) {
                if (requestId !== latestRequestId) {
                    return;
                }

                const message = error instanceof Error ? error.message : 'Gagal memuat data kalender.';
                errorMessageNode.textContent = message;
                calendarNode.classList.remove('calendar-empty');
                toggleState(errorNode, true);
                toggleState(loadingNode, false);
                requestAnimationFrame(() => {
                    calendar.updateSize();
                });
                failureCallback(error instanceof Error ? error : new Error(message));
            }
        },
        eventClick: (info) => {
            if (role !== 'admin' || !info.event.url) {
                info.jsEvent.preventDefault();
                return;
            }

            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
        },
        eventDidMount: (info) => {
            const props = info.event.extendedProps;
            const lines = [
                `Status: ${props.status ?? '-'}`,
                `Mesin: ${props.machine_name ?? '-'}`,
                `Lokasi: ${props.location_name ?? '-'}`,
            ];

            info.el.setAttribute('title', lines.join(' | '));
        },
    });

    calendar.render();

    form.querySelectorAll('select').forEach((selectNode) => {
        selectNode.addEventListener('change', () => {
            calendar.refetchEvents();
        });
    });

    resetButton?.addEventListener('click', () => {
        form.reset();
        calendarNode.classList.remove('calendar-empty');
        calendar.refetchEvents();
    });
});
