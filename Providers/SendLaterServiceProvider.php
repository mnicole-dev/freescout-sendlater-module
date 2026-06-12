<?php

namespace Modules\SendLater\Providers;

use App\Events\UserReplied;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\SendLater\Console\ProcessScheduled;
use Modules\SendLater\Services\SendLaterService;

class SendLaterServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sendlater');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'sendlater');
        $this->commands([ProcessScheduled::class]);
        $this->hooks();
    }

    public function register()
    {
    }

    public function hooks()
    {
        // Commande chaque minute via le scheduler du Kernel.
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command('sendlater:process')->everyMinute()->withoutOverlapping();
            return $schedule;
        });

        // Assets.
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = asset('modules/sendlater/css/sendlater.css');
            return $styles;
        });
        \Eventy::addFilter('javascripts', function ($scripts) {
            $scripts[] = asset('modules/sendlater/js/sendlater.js');
            return $scripts;
        });

        // Item « Envoyer plus tard » dans le dropdown du bouton Send (pas pour les chats).
        \Eventy::addAction('conversation.prepend_send_dropdown', function ($conversation, $mailbox) {
            if ($conversation && $conversation->isChat()) {
                return;
            }
            echo \View::make('sendlater::menu_item', ['conversation' => $conversation])->render();
        }, 20, 2);

        // Badge sur le thread draft planifié.
        \Eventy::addAction('thread.after_header', function ($thread, $loop, $threads, $conversation, $mailbox) {
            if ($thread->state != \App\Thread::STATE_DRAFT) {
                return;
            }
            $at = SendLaterService::scheduledAt($thread);
            if (!$at) {
                return;
            }
            echo \View::make('sendlater::badge', [
                'conversation' => $conversation,
                'scheduled_at' => $at,
            ])->render();
        }, 20, 5);

        // Parité module officiel : une nouvelle réponse d'agent envoie immédiatement le planifié.
        // SendLaterService::publishAndSend retire le meta AVANT de re-déclencher UserReplied → pas de récursion.
        Event::listen(UserReplied::class, function (UserReplied $event) {
            $scheduled = SendLaterService::scheduledDraft($event->conversation);
            if ($scheduled && $scheduled->id != $event->thread->id) {
                \Log::info('[sendlater] new agent reply → sending scheduled thread='.$scheduled->id.' immediately');
                SendLaterService::publishAndSend($scheduled);
            }
        });
    }
}
