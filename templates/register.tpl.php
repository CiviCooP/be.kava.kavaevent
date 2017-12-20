<p>Wilt u zichzelf inschrijven, of leden van uw apotheekteam?</p>

<div id="kavaevent-register-buttons">
  <input type="button" id="kavaevent-register-self" value="Mezelf inschrijven" data-event-id="<?php print $eventId; ?>"/>
  <input type="button" id="kavaevent-register-team" value="Team inschrijven"/>
</div>

<div id="kavaevent-team-form">
  <?php print drupal_render($form); ?>
</div>
