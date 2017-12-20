/* JS for KAVA Event Module */

jQuery(function ($) {

    $('body').on('click', '#kavaevent-register-self', function (ev) {
        $('#kavaevent-team-form').slideUp();
        location.href = '/civicrm/event/register?id=' + $(this).attr('data-event-id') + '&reset=1';
    });

    $('body').on('click', '#kavaevent-register-team', function (ev) {
       $('#kavaevent-team-form').slideDown();
    });

});