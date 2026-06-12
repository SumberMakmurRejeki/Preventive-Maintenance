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

        /** @var Collection<int, PmScheduleDate> $overdueSchedules */
        $overdueSchedules = PmScheduleDate::query()
            ->with('machine')
            ->where('scheduled_date', '<', $today->toDateString())
            ->where('scheduled_date', '>=', $today->copy()->subDay()->toDateString())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get();

        foreach ($overdueSchedules as $scheduleDate) {
            $scheduleDate->forceFill([
                'status' => 'overdue',
                'status_changed_at' => now(),
            ])->save();

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

            $overdueCount++;
        }

        /** @var Collection<int, PmScheduleDate> $missedSchedules */
        $missedSchedules = PmScheduleDate::query()
            ->with('machine')
            ->where('scheduled_date', '<', $today->copy()->subDay()->toDateString())
            ->whereIn('status', ['scheduled', 'in_progress', 'overdue'])
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
