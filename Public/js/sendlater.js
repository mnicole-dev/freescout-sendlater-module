(function ($) {
    'use strict';

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function toLocalInputValue(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function presetDate(preset) {
        var d = new Date();
        if (preset === '1h') { d.setHours(d.getHours() + 1); }
        if (preset === 'tomorrow9') { d.setDate(d.getDate() + 1); d.setHours(9, 0, 0, 0); }
        if (preset === 'monday9') {
            var days = (8 - d.getDay()) % 7 || 7; // prochain lundi
            d.setDate(d.getDate() + days); d.setHours(9, 0, 0, 0);
        }
        return d;
    }

    function post(url, csrf, data, done) {
        $.ajax({
            url: url, method: 'POST',
            data: $.extend({ _token: csrf }, data || {}),
            dataType: 'json'
        }).done(done).fail(function () { alert('Error'); });
    }

    // Id de conversation : connu au rendu (conversation existante) ou résolu après la
    // sauvegarde du brouillon (page de composition : le cœur pose getGlobalAttr('conversation_id')).
    function resolveConversationId($modal) {
        var id = $modal.data('conversation-id');
        if (id) { return id; }
        if (typeof getGlobalAttr === 'function') { return getGlobalAttr('conversation_id') || ''; }
        return '';
    }

    function scheduleUrl($modal) {
        var id = resolveConversationId($modal);
        if (!id) { return ''; }
        return $modal.data('url-template').replace('__ID__', id);
    }

    $(document).on('click', '.sendlater-open', function (e) {
        e.preventDefault();
        var $modal = $('.sendlater-modal');
        $modal.data('conversation-id', $(this).data('conversation-id'))
              .data('url-template', $(this).data('url-template'))
              .data('csrf', $(this).data('csrf'));
        $modal.find('.sendlater-datetime').val(toLocalInputValue(presetDate('1h')));
        $modal.removeClass('hidden');
    });

    $(document).on('click', '.sendlater-preset', function () {
        $('.sendlater-modal .sendlater-datetime').val(toLocalInputValue(presetDate($(this).data('preset'))));
    });

    $(document).on('click', '.sendlater-close', function () {
        $('.sendlater-modal').addClass('hidden');
    });

    $(document).on('click', '.sendlater-confirm', function () {
        var $modal = $('.sendlater-modal');
        var val = $modal.find('.sendlater-datetime').val();
        if (!val) { return; }
        var iso_utc = new Date(val).toISOString(); // fuseau navigateur → UTC

        // 1) Sauver le brouillon natif, 2) poser la planification (3 tentatives, le save est asynchrone).
        if (typeof saveDraft === 'function') { saveDraft(false, true); }

        var attempts = 0;
        var trySchedule = function () {
            attempts++;
            // L'URL est résolue à CHAQUE tentative : sur la page de composition, l'id de
            // conversation n'existe qu'après que la sauvegarde du brouillon a abouti.
            var url = scheduleUrl($modal);
            if (!url) {
                if (attempts < 4) { setTimeout(trySchedule, 900); }
                else { alert('Draft not saved yet — please try again.'); }
                return;
            }
            post(url, $modal.data('csrf'), { scheduled_at: iso_utc }, function (resp) {
                if (resp.status === 'success') {
                    $modal.addClass('hidden');
                    if (resp.redirect_url) { location.href = resp.redirect_url; }
                    else { location.reload(); }
                } else if (attempts < 4) {
                    setTimeout(trySchedule, 900);
                } else {
                    alert(resp.msg || 'Error');
                }
            });
        };
        setTimeout(trySchedule, 900);
    });

    $(document).on('click', '.sendlater-send-now, .sendlater-cancel', function (e) {
        e.preventDefault();
        post($(this).data('url'), $(this).data('csrf'), {}, function () { location.reload(); });
    });
})(jQuery);
