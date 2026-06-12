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
                // Avant publication : meta conservé → retenté au prochain cycle.
                // Si l'échec survient APRÈS la publication (ex. queue indisponible),
                // le thread est publié mais l'email n'est peut-être pas parti : vérifier la queue.
                \Log::error('[sendlater] failed thread='.$thread->id.': '.$e->getMessage()
                    .' (if the thread is already published, check the mail queue)');
                $this->error('Failed thread '.$thread->id.': '.$e->getMessage());
            }
        }
    }
}
