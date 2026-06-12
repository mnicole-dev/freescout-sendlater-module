<?php

namespace Modules\SendLater\Http\Controllers;

use App\Conversation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\SendLater\Services\SendLaterService;

class SendLaterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function schedule(Request $request, $conversation)
    {
        $conversation = Conversation::findOrFail($conversation);
        $this->authorize('viewCached', $conversation);

        $this->validate($request, ['scheduled_at' => 'required|date']);

        // Le JS envoie un ISO-8601 UTC calculé depuis le fuseau du navigateur de l'agent.
        $when = Carbon::parse($request->scheduled_at)->utc();
        if ($when->lte(now())) {
            return response()->json(['status' => 'error', 'msg' => __('The date must be in the future.')]);
        }

        $draft = SendLaterService::latestDraft($conversation);
        if (!$draft) {
            return response()->json(['status' => 'error', 'msg' => __('No draft found. Save the draft first.')]);
        }

        SendLaterService::schedule($draft, $when, auth()->id());
        \Log::info('[sendlater] scheduled thread='.$draft->id.' conversation='.$conversation->id.' at='.$when->toIso8601String().' by user='.auth()->id());

        $response = ['status' => 'success', 'msg' => __('Reply scheduled.')];
        // Nouvelle conversation composée en draft : rediriger vers la page de la conversation
        // (où le badge planifié est visible), la page de composition n'ayant plus d'objet.
        if ($conversation->state == Conversation::STATE_DRAFT) {
            $response['redirect_url'] = route('conversations.view', ['id' => $conversation->id]);
        }

        return response()->json($response);
    }

    public function cancel(Request $request, $conversation)
    {
        $conversation = Conversation::findOrFail($conversation);
        $this->authorize('viewCached', $conversation);

        $draft = SendLaterService::scheduledDraft($conversation);
        if ($draft) {
            SendLaterService::cancel($draft);
            \Log::info('[sendlater] cancelled thread='.$draft->id.' by user='.auth()->id());
        }

        return response()->json(['status' => 'success', 'msg' => __('Schedule cancelled.')]);
    }

    public function sendNow(Request $request, $conversation)
    {
        $conversation = Conversation::findOrFail($conversation);
        $this->authorize('viewCached', $conversation);

        $draft = SendLaterService::scheduledDraft($conversation);
        if ($draft) {
            \Log::info('[sendlater] send-now thread='.$draft->id.' by user='.auth()->id());
            SendLaterService::publishAndSend($draft);
        }

        return response()->json(['status' => 'success']);
    }
}
