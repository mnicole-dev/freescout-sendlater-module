<div class="sendlater-badge alert alert-warning">
    <small class="glyphicon glyphicon-time"></small>
    {{ __('Scheduled for') }} <strong>{{ \App\User::dateFormat($scheduled_at) }}</strong>
    &nbsp;·&nbsp;
    <a href="#" class="sendlater-send-now" data-url="{{ route('sendlater.send_now', $conversation->id) }}" data-csrf="{{ csrf_token() }}">{{ __('Send now') }}</a>
    &nbsp;·&nbsp;
    <a href="#" class="sendlater-cancel" data-url="{{ route('sendlater.cancel', $conversation->id) }}" data-csrf="{{ csrf_token() }}">{{ __('Cancel schedule') }}</a>
</div>
