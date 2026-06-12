<?php

namespace Modules\SendLater\Services;

use App\Conversation;
use App\Events\UserCreatedConversation;
use App\Events\UserReplied;
use App\Thread;
use Carbon\Carbon;

/**
 * Seul endroit du module qui mute threads/conversations.
 * L'envoi réel passe par les événements natifs (UserReplied pour une réponse,
 * UserCreatedConversation pour une nouvelle conversation rédigée en draft),
 * tous deux branchés sur le listener SendReplyToCustomer → job.
 */
class SendLaterService
{
    const META_KEY = 'sendlater';

    /** Dernier draft "message" de la conversation (celui que l'éditeur vient de sauver). */
    public static function latestDraft(Conversation $conversation): ?Thread
    {
        return Thread::where('conversation_id', $conversation->id)
            ->where('state', Thread::STATE_DRAFT)
            ->where('type', Thread::TYPE_MESSAGE)
            ->orderBy('id', 'desc')
            ->first();
    }

    /** Draft planifié de la conversation, s'il existe. */
    public static function scheduledDraft(Conversation $conversation): ?Thread
    {
        return Thread::where('conversation_id', $conversation->id)
            ->where('state', Thread::STATE_DRAFT)
            ->where('type', Thread::TYPE_MESSAGE)
            ->where('meta', 'like', '%"'.self::META_KEY.'"%')
            ->orderBy('id', 'desc')
            ->first();
    }

    public static function schedule(Thread $draft, Carbon $when_utc, ?int $user_id = null): void
    {
        $draft->setMeta(self::META_KEY, [
            'scheduled_at' => $when_utc->toIso8601String(),
            'scheduled_by' => $user_id,
        ]);
        $draft->save();
    }

    public static function cancel(Thread $draft): void
    {
        $draft->setMeta(self::META_KEY, null);
        $draft->save();
    }

    public static function scheduledAt(Thread $thread): ?Carbon
    {
        $meta = $thread->getMeta(self::META_KEY);
        if (empty($meta['scheduled_at'])) {
            return null;
        }
        try {
            return Carbon::parse($meta['scheduled_at']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return \Illuminate\Support\Collection<Thread> drafts dont l'heure est passée */
    public static function dueDrafts()
    {
        return Thread::where('state', Thread::STATE_DRAFT)
            ->where('type', Thread::TYPE_MESSAGE)
            ->where('meta', 'like', '%"'.self::META_KEY.'"%')
            ->get()
            ->filter(function (Thread $t) {
                $at = self::scheduledAt($t);
                return $at !== null && $at->lte(now());
            });
    }

    /** Publie le draft planifié et déclenche la chaîne d'envoi native. */
    public static function publishAndSend(Thread $thread): void
    {
        $conversation = Conversation::find($thread->conversation_id);

        // Garde-fous : conversation indisponible → déplanifier sans envoyer.
        // STATE_DRAFT est accepté : c'est une NOUVELLE conversation rédigée puis planifiée
        // depuis la page de composition — elle sera publiée ici.
        if (!$conversation
            || $conversation->state == Conversation::STATE_DELETED
            || $conversation->status == Conversation::STATUS_SPAM
        ) {
            self::cancel($thread);
            \Log::warning('[sendlater] unscheduled without sending (conversation unavailable), thread='.$thread->id);
            return;
        }

        $is_new_conversation = ($conversation->state == Conversation::STATE_DRAFT);

        // CLAIM ATOMIQUE anti double-envoi : cron, « Envoyer maintenant » et le listener
        // d'auto-envoi peuvent viser le même thread au même instant. Un seul UPDATE
        // conditionnel gagne ; les autres voient 0 ligne affectée et abandonnent.
        $claimed = Thread::where('id', $thread->id)
            ->where('state', Thread::STATE_DRAFT)
            ->update(['state' => Thread::STATE_PUBLISHED]);
        if (!$claimed) {
            return; // déjà publié ou annulé par un autre chemin
        }

        // Recharger l'état frais après le claim.
        $thread = Thread::find($thread->id);
        if (!$thread) {
            return;
        }

        // IMPORTANT : retirer le meta AVANT de déclencher l'événement.
        // Le listener d'auto-envoi écoute UserReplied : sans ça → récursion.
        $thread->setMeta(self::META_KEY, null);

        $now = now();
        $thread->created_at = $now; // position correcte dans le fil
        $thread->save();

        // Miroir du chemin send_reply du contrôleur (réf. ConversationsController:1030-1219).
        $conversation->last_reply_at = $now;
        $conversation->last_reply_from = Conversation::PERSON_USER;
        $conversation->user_updated_at = $now;
        if ($is_new_conversation) {
            // Nouvelle conversation rédigée en draft : la publier (miroir du chemin
            // is_create+from_draft du contrôleur, réf. lignes 978/1043-1046).
            $conversation->state = Conversation::STATE_PUBLISHED;
            $conversation->threads_count++;
            $conversation->setPreview($thread->body);
        }
        $conversation->updateFolder();
        $conversation->save();
        $conversation->mailbox->updateFoldersCounters();

        if ($is_new_conversation) {
            event(new UserCreatedConversation($conversation, $thread));
        } else {
            event(new UserReplied($conversation, $thread));
        }

        \Log::info('[sendlater] sent thread='.$thread->id.' conversation='.$conversation->id
            .($is_new_conversation ? ' (new conversation published)' : ''));
    }
}
