<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-appointment-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appointments = \App\Models\Appointment::with([
            'user',
            'doctor.user'
        ])
            ->where('status', 'booked')
            ->where('reminder_sent', false)
            ->get();

        $firebase = app(\App\Services\FirebaseNotificationService::class);

        foreach ($appointments as $appointment) {

            $appointmentDateTime = \Carbon\Carbon::parse(
                $appointment->appointment_date . ' ' . $appointment->appointment_time,
                'Asia/Damascus'
            );

            $minutesLeft = now('Asia/Damascus')
                ->diffInMinutes($appointmentDateTime, false);

            /*
         * إذا بقي أقل من ساعة وأكبر من صفر
         * أرسل التذكير مرة واحدة فقط
         */
            if ($minutesLeft <= 60 && $minutesLeft > 0) {
                // إشعار للمريض
                if (
                    $appointment->user &&
                    !empty($appointment->user->fcm_token)
                ) {

                    $firebase->sendNotification(
                        $appointment->user->fcm_token,
                        'Appointment Reminder',
                        'You have an appointment within the next hour.'
                    );
                }
                // إشعار للطبيب
                if (
                    $appointment->doctor &&
                    $appointment->doctor->user &&
                    !empty($appointment->doctor->user->fcm_token)
                ) {

                    $firebase->sendNotification(
                        $appointment->doctor->user->fcm_token,
                        'Upcoming Appointment',
                        'You have an appointment scheduled within the next hour.'
                    );
                }
                // منع تكرار الإشعار
                $appointment->update([
                    'reminder_sent' => true
                ]);
            }
        }
        $this->info('Reminder command executed successfully.');
        return Command::SUCCESS;
    }
}
