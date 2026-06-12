<x-layouts.app
    title="Dashboard PRIME"
    heading=""
    subheading=""
    :actor-name="$actorName"
    :role="$role"
>
    @php
        $pmDoneCount = (int) ($pmStatus['completed'] ?? 0);
        $pmOverdueCount = (int) ($pmStatus['overdue'] ?? 0);
        $pmTotal = (int) ($pmStatus['total'] ?? 0);
        $pmDonePercentage = $pmTotal > 0 ? round(($pmDoneCount / $pmTotal) * 100) : 0;
        $pmOverduePercentage = max(0, 100 - $pmDonePercentage);

        $pmRateLabels = $pmRateSeries->pluck('month_label')->values();
        $pmRateTargetSeries = $pmRateSeries->pluck('target_percentage')->map(fn ($value) => (float) $value)->values();
        $pmRateActualSeries = $pmRateSeries->pluck('actual_percentage')->map(fn ($value) => (float) $value)->values();

        $breakdownPartLabels = $breakdownDurationSeries->pluck('part_name')->values();
        $breakdownDurationValues = $breakdownDurationSeries->pluck('total_duration_hours')->map(fn ($value) => (float) $value)->values();
        $breakdownCountValues = $breakdownDurationSeries->pluck('total_breakdown_count')->map(fn ($value) => (int) $value)->values();
    @endphp

    <section class="space-y-5">
        @if ($loadErrorMessage)
            <x-ui.alert variant="error">{{ $loadErrorMessage }}</x-ui.alert>
        @endif

        @if ($errors->has('end_date'))
            <x-ui.alert variant="error">{{ $errors->first('end_date') }}</x-ui.alert>
        @endif

        <div class="space-y-2">
            <h2 class="text-[29px] font-bold tracking-[-0.045em] text-[var(--color-prime-ink)]">DASHBOARD PRIME</h2>
            <p class="max-w-3xl text-[15px] text-[var(--color-prime-muted)]">Pantau performa preventive maintenance dan breakdown secara cepat dan akurat.</p>
        </div>

        <div class="rounded-[16px] border border-[var(--color-prime-border)] bg-white px-5 py-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.22)]">
            <form
                method="GET"
                class="flex flex-wrap items-end gap-4 xl:flex-nowrap"
                data-dashboard-filter
                data-filter-default-start="{{ $dateRange['start'] }}"
                data-filter-default-end="{{ $dateRange['end'] }}"
            >
                <div class="w-full min-w-[210px] xl:max-w-[230px]">
                    <label class="mb-2 block text-[13px] font-semibold text-[var(--color-prime-ink)]">Tanggal Mulai</label>
                    <x-ui.input type="date" name="start_date" :value="$dateRange['start']" class="h-11 rounded-[8px] border-black/10 px-3.5 py-2.5 text-[14px]" />
                </div>
                <div class="w-full min-w-[210px] xl:max-w-[230px]">
                    <label class="mb-2 block text-[13px] font-semibold text-[var(--color-prime-ink)]">Tanggal Selesai</label>
                    <x-ui.input type="date" name="end_date" :value="$dateRange['end']" class="h-11 rounded-[8px] border-black/10 px-3.5 py-2.5 text-[14px]" />
                </div>
                <div class="flex w-full flex-wrap items-center gap-3 xl:ml-auto xl:w-auto xl:justify-end">
                    <x-ui.button type="submit" size="lg" data-dashboard-apply class="h-11 rounded-[8px] px-5 text-[14px] shadow-none">
                        <x-ui.icon name="chart-line-down" class="h-4 w-4" />
                        <span>Terapkan Filter</span>
                    </x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="lg" data-dashboard-reset class="h-11 rounded-[8px] border-black/10 px-5 text-[14px] text-[var(--color-prime-ink)] shadow-none">
                        <span>Reset</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-4">
            <article class="rounded-[16px] border border-[rgba(0,0,0,0.08)] bg-[#F2F7FF] p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.18)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[15px] font-semibold text-[var(--color-prime-ink)]">Total Mesin</p>
                        <p class="mt-3 text-[40px] font-bold leading-none tracking-[-0.05em] text-[var(--color-prime-ink)]">{{ (int) ($machineSummary['total'] ?? 0) }}</p>
                        <div class="mt-4 flex items-center gap-2 text-[14px]">
                            <span class="font-semibold text-[var(--color-prime-primary)]">{{ (int) ($machineSummary['active'] ?? 0) }} Aktif</span>
                            <span class="text-black/18">•</span>
                            <span class="font-medium text-[var(--color-prime-muted)]">{{ (int) ($machineSummary['inactive'] ?? 0) }} Nonaktif</span>
                        </div>
                    </div>
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-[#DBEAFF] text-[var(--color-prime-primary)]">
                        <x-ui.icon name="server" class="h-7 w-7 [stroke-width:1.6]" />
                    </span>
                </div>
            </article>

            <article class="rounded-[16px] border border-[rgba(0,0,0,0.08)] bg-[#F2FBF4] p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.18)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[15px] font-semibold text-[var(--color-prime-ink)]">MTBF</p>
                        <p class="mt-3 text-[40px] font-bold leading-none tracking-[-0.05em] text-[var(--color-prime-ink)]">
                            {{ number_format((float) $mtbfHours, 1) }}
                            <span class="text-[19px] font-semibold tracking-[-0.02em] text-[var(--color-prime-ink)]">Jam</span>
                        </p>
                        <p class="mt-4 text-[13px] leading-6 text-[var(--color-prime-muted)]">Rata-rata waktu antar kerusakan</p>
                    </div>
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-[#DDF8E4] text-[var(--color-prime-success)]">
                        <x-ui.icon name="clock" class="h-7 w-7 [stroke-width:1.6]" />
                    </span>
                </div>
            </article>

            <article class="rounded-[16px] border border-[rgba(0,0,0,0.08)] bg-[#FFF7EA] p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.18)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[15px] font-semibold text-[var(--color-prime-ink)]">MTBR</p>
                        <p class="mt-3 text-[40px] font-bold leading-none tracking-[-0.05em] text-[var(--color-prime-ink)]">
                            {{ number_format((float) $mtbrHours, 1) }}
                            <span class="text-[19px] font-semibold tracking-[-0.02em] text-[var(--color-prime-ink)]">Jam</span>
                        </p>
                        <p class="mt-4 text-[13px] leading-6 text-[var(--color-prime-muted)]">Rata-rata waktu perbaikan</p>
                    </div>
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-[16px] bg-[#FFEBC7] text-[#8a5a03]">
                        <x-ui.icon name="wrench" class="h-7 w-7 [stroke-width:1.6]" />
                    </span>
                </div>
            </article>

            <article class="rounded-[16px] border border-[rgba(0,0,0,0.08)] bg-[#F6F4FF] p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.18)]">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-4">
                        <p class="text-[15px] font-semibold text-[var(--color-prime-ink)]">PM Execution</p>
                        @if ($pmTotal === 0)
                            <div class="flex min-h-[150px] flex-col justify-center">
                                <p class="text-[14px] font-semibold text-[var(--color-prime-ink)]">Belum ada data PM</p>
                                <p class="mt-1 text-[13px] text-[var(--color-prime-muted)]">Data PM tidak ditemukan pada periode yang dipilih.</p>
                            </div>
                        @else
                            <div class="flex items-center gap-5">
                                <div class="h-[120px] w-[120px] shrink-0">
                                    <canvas id="pmExecutionChart"></canvas>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3 text-[14px]">
                                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[var(--color-prime-primary)]"></span>
                                        <span class="text-[var(--color-prime-ink)]">{{ $pmDonePercentage }}% Done</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-[14px]">
                                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#f15b3c]"></span>
                                        <span class="text-[var(--color-prime-ink)]">{{ $pmOverduePercentage }}% Overdue</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        </div>

        <article class="rounded-[16px] border border-[var(--color-prime-border)] bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.22)]">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-[16px] font-semibold text-[var(--color-prime-ink)]">PM Achievement</h3>
                <div class="flex flex-wrap items-center gap-5 text-[13px] text-[var(--color-prime-ink)]">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-3 w-3 rounded-[3px] bg-[var(--color-prime-primary)]"></span>
                        <span>PM Aktual (%)</span>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex w-6 border-t-2 border-dashed border-[var(--color-prime-primary)]"></span>
                        <span>Target (%)</span>
                    </span>
                </div>
            </div>

            @if ($pmRateSeries->isEmpty())
                <div class="flex h-[320px] flex-col items-center justify-center text-center">
                    <p class="text-[14px] font-semibold text-[var(--color-prime-ink)]">Belum ada data PM</p>
                    <p class="mt-1 text-[13px] text-[var(--color-prime-muted)]">Data PM tidak ditemukan pada periode yang dipilih.</p>
                </div>
            @else
                <div class="h-[320px]">
                    <canvas id="pmRateChart"></canvas>
                </div>
            @endif
        </article>

        <div class="grid grid-cols-1 gap-4 2xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
            <article class="rounded-[16px] border border-[var(--color-prime-border)] bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.22)]">
                <div class="mb-4">
                    <h3 class="text-[16px] font-semibold text-[var(--color-prime-ink)]">Breakdown Duration</h3>
                    <p class="mt-1 text-[13px] text-[var(--color-prime-muted)]">(Jam)</p>
                </div>

                @if ($breakdownDurationSeries->isEmpty())
                    <div class="flex h-[320px] flex-col items-center justify-center text-center">
                        <p class="text-[14px] font-semibold text-[var(--color-prime-ink)]">Belum ada data breakdown</p>
                        <p class="mt-1 text-[13px] text-[var(--color-prime-muted)]">Data breakdown tidak ditemukan pada periode yang dipilih.</p>
                    </div>
                @else
                    <div class="h-[320px]">
                        <canvas id="breakdownDurationChart"></canvas>
                    </div>
                @endif
            </article>

            <article class="rounded-[16px] border border-[var(--color-prime-border)] bg-white p-5 shadow-[0_10px_30px_-24px_rgba(15,23,42,0.22)]">
                <div class="mb-4">
                    <h3 class="text-[16px] font-semibold text-[var(--color-prime-ink)]">Breakdown Status</h3>
                </div>

                @if ($closedBreakdownLogs->isEmpty())
                    <div class="flex h-[320px] flex-col items-center justify-center text-center">
                        <p class="text-[14px] font-semibold text-[var(--color-prime-ink)]">Belum ada data breakdown</p>
                        <p class="mt-1 text-[13px] text-[var(--color-prime-muted)]">Data breakdown tidak ditemukan pada periode yang dipilih.</p>
                    </div>
                @else
                    <div class="max-h-[320px] overflow-y-auto rounded-[14px] border border-[var(--color-prime-border)] bg-white">
                        <table class="min-w-full text-left">
                            <thead class="sticky top-0 z-[1] bg-[var(--color-prime-soft)]/55">
                                <tr class="border-b border-[var(--color-prime-border)]">
                                    <th class="px-5 py-3 text-[13px] font-medium text-[var(--color-prime-ink)]">Nama Mesin</th>
                                    <th class="px-5 py-3 text-[13px] font-medium text-[var(--color-prime-ink)]">Part Mesin</th>
                                    <th class="px-5 py-3 text-[13px] font-medium text-[var(--color-prime-ink)]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($closedBreakdownLogs as $log)
                                    <tr class="border-b border-[var(--color-prime-border)] last:border-b-0">
                                        <td class="px-5 py-4 text-[14px] font-medium text-[var(--color-prime-ink)]">{{ $log['machine_name'] }}</td>
                                        <td class="px-5 py-4 text-[14px] text-[var(--color-prime-ink)]">{{ $log['part_name'] }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center rounded-full bg-[#e6f7e8] px-3 py-1 text-[12px] font-semibold text-[#1aae39]">{{ $log['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const pmDonePercentage = @json($pmDonePercentage);
            const pmOverduePercentage = @json($pmOverduePercentage);
            const pmTotal = @json($pmTotal);

            const pmRateLabels = @json($pmRateLabels);
            const pmRateTargetSeries = @json($pmRateTargetSeries);
            const pmRateActualSeries = @json($pmRateActualSeries);

            const breakdownPartLabels = @json($breakdownPartLabels);
            const breakdownDurationValues = @json($breakdownDurationValues);
            const breakdownCountValues = @json($breakdownCountValues);

            if (typeof Chart === 'undefined') {
                return;
            }

            const commonGrid = {
                color: 'rgba(0,0,0,0.08)',
                drawBorder: false,
            };

            const donutCenterText = {
                id: 'donutCenterText',
                afterDraw(chart) {
                    if (! chart?.chartArea || chart.getDatasetMeta(0).data.length === 0) {
                        return;
                    }

                    const { ctx } = chart;
                    const centerPoint = chart.getDatasetMeta(0).data[0];

                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = 'rgba(0,0,0,0.95)';
                    ctx.font = '700 22px Inter, sans-serif';
                    ctx.fillText(`${pmDonePercentage}%`, centerPoint.x, centerPoint.y - 6);
                    ctx.fillStyle = '#615d59';
                    ctx.font = '500 12px Inter, sans-serif';
                    ctx.fillText('Done', centerPoint.x, centerPoint.y + 15);
                    ctx.restore();
                },
            };

            const valueLabelPlugin = {
                id: 'valueLabelPlugin',
                afterDatasetsDraw(chart, _args, options) {
                    const { ctx } = chart;
                    const targetDatasetIndex = options?.targetDatasetIndex ?? null;
                    const actualDatasetIndex = options?.actualDatasetIndex ?? 0;
                    const labelColor = options?.labelColor ?? 'rgba(0,0,0,0.95)';
                    const mutedColor = options?.mutedColor ?? '#615d59';

                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';

                    if (targetDatasetIndex !== null) {
                        const targetMeta = chart.getDatasetMeta(targetDatasetIndex);

                        targetMeta.data.forEach((element, index) => {
                            const value = chart.data.datasets[targetDatasetIndex].data[index];
                            ctx.fillStyle = labelColor;
                            ctx.font = '600 12px Inter, sans-serif';
                            ctx.fillText(`${value}%`, element.x, element.y - 12);
                        });
                    }

                    const actualMeta = chart.getDatasetMeta(actualDatasetIndex);
                    actualMeta.data.forEach((element, index) => {
                        const value = chart.data.datasets[actualDatasetIndex].data[index];
                        ctx.fillStyle = actualDatasetIndex === 0 ? labelColor : mutedColor;
                        ctx.font = '600 12px Inter, sans-serif';
                        ctx.fillText(`${value}${options?.suffix ?? ''}`, element.x, element.y - 8);
                    });

                    ctx.restore();
                },
            };

            const pmExecutionNode = document.getElementById('pmExecutionChart');
            if (pmExecutionNode && pmTotal > 0) {
                new Chart(pmExecutionNode, {
                    type: 'doughnut',
                    data: {
                        labels: ['Done', 'Overdue'],
                        datasets: [{
                            data: [pmDonePercentage, pmOverduePercentage],
                            backgroundColor: ['#0b66d8', '#f15b3c'],
                            borderWidth: 0,
                            cutout: '74%',
                            spacing: 1,
                            hoverOffset: 0,
                        }],
                    },
                    plugins: [donutCenterText],
                    options: {
                        maintainAspectRatio: false,
                        animation: {
                            duration: 600,
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    label(context) {
                                        return `${context.label}: ${context.parsed}%`;
                                    },
                                },
                            },
                        },
                    },
                });
            }

            const pmRateNode = document.getElementById('pmRateChart');
            if (pmRateNode && pmRateLabels.length > 0) {
                new Chart(pmRateNode, {
                    data: {
                        labels: pmRateLabels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'PM Aktual (%)',
                                data: pmRateActualSeries,
                                backgroundColor: '#0b66d8',
                                borderRadius: 0,
                                maxBarThickness: 42,
                                categoryPercentage: 0.62,
                                barPercentage: 0.72,
                            },
                            {
                                type: 'line',
                                label: 'Target (%)',
                                data: pmRateTargetSeries,
                                borderColor: '#0b66d8',
                                backgroundColor: '#0b66d8',
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#0b66d8',
                                pointBorderWidth: 1.5,
                                pointRadius: 4,
                                pointHoverRadius: 4,
                                borderWidth: 2,
                                borderDash: [6, 5],
                                tension: 0,
                            },
                        ],
                    },
                    plugins: [valueLabelPlugin],
                    options: {
                        maintainAspectRatio: false,
                        animation: {
                            duration: 650,
                        },
                        layout: {
                            padding: {
                                top: 18,
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    color: '#615d59',
                                    font: { size: 12, weight: 500 },
                                },
                            },
                            y: {
                                min: 0,
                                max: 104,
                                ticks: {
                                    stepSize: 20,
                                    color: '#615d59',
                                    font: { size: 12 },
                                    callback(value) {
                                        if (value > 100) {
                                            return '';
                                        }

                                        return `${value}%`;
                                    },
                                },
                                grid: commonGrid,
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    label(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}%`;
                                    },
                                },
                            },
                            valueLabelPlugin: {
                                targetDatasetIndex: 1,
                                actualDatasetIndex: 0,
                                labelColor: 'rgba(0,0,0,0.95)',
                                mutedColor: 'rgba(0,0,0,0.95)',
                                suffix: '%',
                            },
                        },
                    },
                });
            }

            const breakdownDurationNode = document.getElementById('breakdownDurationChart');
            if (breakdownDurationNode && breakdownPartLabels.length > 0) {
                new Chart(breakdownDurationNode, {
                    type: 'bar',
                    data: {
                        labels: breakdownPartLabels,
                        datasets: [{
                            label: 'Durasi (Jam)',
                            data: breakdownDurationValues,
                            backgroundColor: '#0b66d8',
                            borderRadius: 0,
                            maxBarThickness: 42,
                            categoryPercentage: 0.62,
                            barPercentage: 0.72,
                        }],
                    },
                    plugins: [valueLabelPlugin],
                    options: {
                        maintainAspectRatio: false,
                        animation: {
                            duration: 650,
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    color: '#615d59',
                                    font: { size: 12, weight: 500 },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#615d59',
                                    font: { size: 12 },
                                },
                                title: {
                                    display: true,
                                    text: 'Jam',
                                    color: '#615d59',
                                    font: { size: 12, weight: 500 },
                                },
                                grid: commonGrid,
                            },
                        },
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    title(items) {
                                        return items[0]?.label ?? '-';
                                    },
                                    label(context) {
                                        const index = context.dataIndex;
                                        const totalHours = breakdownDurationValues[index] ?? 0;
                                        const totalCount = breakdownCountValues[index] ?? 0;

                                        return [`Total Jam: ${totalHours}`, `Jumlah Breakdown: ${totalCount}`];
                                    },
                                },
                            },
                            valueLabelPlugin: {
                                actualDatasetIndex: 0,
                                labelColor: 'rgba(0,0,0,0.95)',
                                mutedColor: 'rgba(0,0,0,0.95)',
                                suffix: '',
                            },
                        },
                    },
                });
            }
        })();
    </script>
</x-layouts.app>
