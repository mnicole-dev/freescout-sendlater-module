<?php

namespace Modules\SendLater\Console;

use Illuminate\Console\Command;
use Modules\SendLater\Services\SendLaterService;

class ProcessScheduled extends Command
{
    protected $signature = 'sendlater:process';

    protected $description = 'Send scheduled replies whose time has come';

    public function handle()
    {
        foreach (SendLaterService::dueDrafts() as $thread) {
            try {
                SendLaterService::publishAndSend($thread);
                $this->line('Sent thread '.$thread->id);
            } catch (\Throwable $e) {
                // Meta conservé → retenté au prochain cycle. Ne casse pas les autres envois.
                \Log::error('[sendlater] failed thread='.$thread->id.': '.$e->getMessage());
                $this->error('Failed thread '.$thread->id.': '.$e->getMessage());
            }
        }
    }
}
