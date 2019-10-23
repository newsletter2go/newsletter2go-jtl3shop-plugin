<?php
$user = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1);
$frontend_url = "https://ui.newsletter2go.com/integrations/connect/JTL/?version=3000&username=".$user->username."&password=".$user->apikey."&url=".gibShopURL()
?>
<div id="settings">
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
	<br/>
	<br/>
	<div style="text-align: center;">
		<a href="<?php echo $frontend_url ?>" target="_new" class="button orange"> Mit Newsletter2Go verbinden </a>
	</div>

</div>