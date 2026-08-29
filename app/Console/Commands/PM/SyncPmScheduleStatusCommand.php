<?php

namespace App\Console\Commands\PM;

use App\Models\PmScheduleDate;
use App\Services\Notification\AdminNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SyncPmScheduleStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pm:sync-schedule-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi status PM schedule menjadi overdue/missed dan buat notifikasi admin tanpa duplikasi';

    public function __construct(
        protected AdminNotificationService $notificationService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now()->startOfDay();
        $overdueCount = 0;
        $missedCount = 0;

        // Memproses scheduled → overdue. Query mengambil semua scheduled sebelum hari ini
        // agar stale occurrence tidak stuck selamanya. Occurrence dengan execution
        // (termasuk soft-deleted) tidak ikut dimutasi agar pekerjaan yang sudah dimulai
        // tidak terganggu cron
        /** @var Collection<int, PmScheduleDate> $overdueSchedules */
        $overdueSchedules = PmScheduleDate::query()
            ->with('machine')
            ->where('scheduled_date', '<', $today->toDateString())
            ->where('status', 'scheduled')
            ->whereDoesntHave('executions', function ($query): void {
                $query->withTrashed();
            })
            ->get();

        // Batas tanggal untuk membedakan normal overdue (kemarin) dan catch-up (sebelum kemarin).
        $yesterdayString = $today->copy()->subDay()->toDateString();

        foreach ($overdueSchedules as $scheduleDate) {
            $scheduleDate->forceFill([
                'status' => 'overdue',
                'status_changed_at' => now(),
            ])->save();

            // Hanya buat pm_overdue untuk normal transition (kemarin). Catch-up intermediate
            // tidak menghasilkan pm_overdue karena akan langsung diproses menjadi missed.
            if ($scheduleDate->scheduled_date->toDateString() >= $yesterdayString) {
                $this->notificationService->createUnique(
                    notificationType: 'pm_overdue',
                    title: sprintf(
                        'Pengingat: Jadwal PM Mesin %s Melewati Batas Waktu',
                        (string) ($scheduleDate->machine?->machine_name ?? '-'),
                    ),
                    message: sprintf(
                        'Hai, mengingatkan bahwa jadwal Preventive Maintenance untuk mesin %s saat ini telah melewati tanggal pengerjaan yang ditentukan. Mohon agar dapat segera ditindaklanjuti.',
                        (string) ($scheduleDate->machine?->machine_name ?? '-'),
                    ),
                    relatedTable: 'pm_schedule_dates',
                    relatedId: $scheduleDate->id,
                    targetUrl: $scheduleDate->machine ? '/machines/'.$scheduleDate->machine->machine_code : '/calendar',
                    data: [
                        'machine_id' => $scheduleDate->machine_id,
                        'machine_code' => (string) ($scheduleDate->machine?->machine_code ?? ''),
                        'scheduled_date' => (string) optional($scheduleDate->scheduled_date)?->toDateString(),
                    ],
                );
            }

            $overdueCount++;
        }

        // Memproses overdue → missed, termasuk catch-up occurrence yang baru saja
        // menjadi overdue pada loop pertama. Query dilakukan ulang agar stale scheduled
        // yang sudah berubah menjadi overdue tetap terproses dalam satu command run.
        /** @var Collection<int, PmScheduleDate> $missedSchedules */
        $missedSchedules = PmScheduleDate::query()
            ->with('machine')
            ->where('scheduled_date', '<', $today->copy()->subDay()->toDateString())
            ->where('status', 'overdue')
            ->whereDoesntHave('executions', function ($query): void {
                $query->withTrashed();
            })
            ->get();

        foreach ($missedSchedules as $scheduleDate) {
            $scheduleDate->forceFill([
                'status' => 'missed',
                'status_changed_at' => now(),
            ])->save();

            $this->notificationService->createUnique(
                notificationType: 'pm_missed',
                title: sprintf(
                    'Pembaruan Status: Jadwal PM Mesin %s Tidak Terlaksana (Missed)',
                    (string) ($scheduleDate->machine?->machine_name ?? '-'),
                ),
                message: sprintf(
                    'Mohon perhatiannya, jadwal Preventive Maintenance untuk mesin %s kini berstatus Missed karena telah melewati batas waktu pengerjaan. Pastikan operator mengerjakan PM sesuai jadwal yang ditentukan.',
                    (string) ($scheduleDate->machine?->machine_name ?? '-'),
                ),
                relatedTable: 'pm_schedule_dates',
                relatedId: $scheduleDate->id,
                targetUrl: $scheduleDate->machine ? '/machines/'.$scheduleDate->machine->machine_code : '/calendar',
                data: [
                    'machine_id' => $scheduleDate->machine_id,
                    'machine_code' => (string) ($scheduleDate->machine?->machine_code ?? ''),
                    'scheduled_date' => (string) optional($scheduleDate->scheduled_date)?->toDateString(),
                ],
            );

            $missedCount++;
        }

        $this->info(sprintf('PM schedule disinkronkan. Overdue: %d, Missed: %d', $overdueCount, $missedCount));

        return self::SUCCESS;
    }
}
