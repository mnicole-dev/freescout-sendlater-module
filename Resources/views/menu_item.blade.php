<li>
    <a href="#" class="sendlater-open"
       data-conversation-id="{{ $conversation->id ?? '' }}"
       data-url-template="{{ route('sendlater.schedule', ['conversation' => '__ID__']) }}"
       data-csrf="{{ csrf_token() }}">
        <small class="glyphicon glyphicon-time"></small> {{ __('Send Later') }}…
    </a>
</li>
<div class="sendlater-modal hidden">
    <div class="sendlater-modal-box">
        <h4>{{ __('Send Later') }}</h4>
        <div class="sendlater-presets">
            <button type="button" class="btn btn-default btn-xs sendlater-preset" data-preset="1h">{{ __('In 1 hour') }}</button>
            <button type="button" class="btn btn-default btn-xs sendlater-preset" data-preset="tomorrow9">{{ __('Tomorrow 9am') }}</button>
            <button type="button" class="btn btn-default btn-xs sendlater-preset" data-preset="monday9">{{ __('Monday 9am') }}</button>
        </div>
        <label>{{ __('Pick date and time') }}</label>
        <input type="datetime-local" class="form-control sendlater-datetime">
        <div class="sendlater-actions">
            <button type="button" class="btn btn-primary sendlater-confirm">{{ __('Schedule') }}</button>
            <button type="button" class="btn btn-link sendlater-close">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>
