<?php

$user = $GLOBALS['DB']->executeQuery('SELECT * FROM xplugin_newsletter2go_keys', 1);
$order_tracking = isset($user->ordertracking) ? $user->ordertracking : 'false';
$configUrl = '/includes/plugins/newsletter2go/config';

$parameters = array(
	'version' => 4000,
	'username' => $user->username,
	'password' => $user->apikey,
	'url' => gibShopURL(),
	'callback' => gibShopURL() . $configUrl
);

$frontend_url = 'https://ui-sandbox.newsletter2go.com/integrations/connect/JTL/?' . http_build_query($parameters);
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
	<div class="item">
		<div class="name">
			<label>Enable order tracking</label>
		</div>
		<div class="for">
			<select id="nl2go_order_tracking" name="nl2go_order_tracking">
				<option value="true" <?php echo $order_tracking === 'true' ? 'selected' : '' ?>>Ja</option>
				<option value="false" <?php echo $order_tracking === 'false' ? 'selected' : '' ?>>Nein</option>
			</select>
		</div>
	</div>
	<br/>
	<br/>
	<div style="text-align: center;">
		<a href="<?php echo $frontend_url ?>" target="_new" class="button orange"> Mit Newsletter2Go verbinden </a>
	</div>
</div>

<script type="application/javascript">
	$(document).ready(function () {
		$('#nl2go_order_tracking').on('change', function () {
			$.ajax({
				url: '<?php echo $configUrl?>',
				cache: false,
				type: 'POST',
				data: {
					'order_tracking': this.value
				},
				success: function (response) {}
			});
		});
	});
</script>