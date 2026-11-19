<?php

namespace App\Console\Commands;

use App\Models\CourseRegistrationEvent;
use App\Models\PortalMail;
use App\Services\CourseRegistration;
use App\Services\PortalMailDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessPortalIntegration extends Command
{
    protected $signature = 'portal:process-integrations';

    protected $description = 'Обработать сохранённые регистрации и исходящие письма';

    public function handle(CourseRegistration $registrations, PortalMailDelivery $delivery): int
    {
        if (! config('course_integration.enabled')) {
            return self::SUCCESS;
        }
        $lock = Cache::lock('portal-integrations', 120);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            foreach (CourseRegistrationEvent::where('status', 'received')->orderBy('id')->limit(20)->pluck('id') as $id) {
                $registrations->process($id);
            }
            foreach (PortalMail::where('status', 'pending')->orderBy('id')->limit(5)->pluck('id') as $id) {
                $delivery->send($id);
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
