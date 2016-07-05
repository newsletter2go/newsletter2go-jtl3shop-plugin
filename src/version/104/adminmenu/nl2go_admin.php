<?php
$user = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1);
?>
<div id="settings">
	F&uuml;gen Sie diese Daten in Ihrem <a href="https://www.newsletter2go.de/" href="_blank">Newsletter2Go</a>-Account unter <em>Einstellungen > Schnittstellen</em> ein. Einen <em>Newsletter2Go</em>-Account k&ouml;nnen Sie sich <a href="https://www.newsletter2go.de/de/registrierung" target="_blank">hier</a> erstellen.<br><br>
	<div class="category first">Daten f&uuml;r die Verbindung mit <em>Newsletter2Go</em></div>
	<div class="item">
		<div class="name">
			<label>Username</label>
		</div>
		<div class="for">
			<?php echo $user->username; ?>
		</div>
	</div>
	<div class="item">
		<div class="name">
			<label>Passwort</label>
		</div>
		<div class="for">
			<?php echo $user->apikey; ?>
		</div>
	</div>
	<div class="item">
		<div class="name">
			<label>URL</label>
		</div>
		<div class="for">
			<?php echo gibShopURL(); ?>
		</div>
	</div>
	<br><br>
	Haben Sie noch Fragen zur <em>Newsletter2Go</em>-Integration?
	Dann schreiben Sie uns eine Email an <a href="mailto:support@newsletter2go.de">support@newsletter2go.de</a> oder rufen uns kostenfrei unter der Nummer 0800 778 776 77 an.
</div>