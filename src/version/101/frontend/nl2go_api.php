<?php
require_once('includes/nl2go_kundenendpoint.class.php');
require_once('includes/nl2go_produktendpoint.class.php');

// API nur aktiv, wenn das POST-Feld api auf nl2go gesetzt wurde und ein valider Endpoint gewählt wurde
if($_POST['api'] == 'nl2go' && ($_POST['r'] == 'kunden' || $_POST['r'] == 'produkt')) {

	if($_POST['r'] == 'kunden') {	
		$allCustomers = $oPlugin->oPluginEinstellungAssoc_arr['newsletter2go_syncgroup'] == 'All';
		$endpoint = new Nl2goKundenEndpoint($allCustomers, (int) $_POST['page'], (int) $_POST['row_count']);
	} else if($_POST['r'] == 'produkt') {
		$endpoint = new Nl2goProduktEndpoint($_POST['item']);
	} 

	// Authentifizierung testen
	$apiuser = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1); 
	if(!$apiuser) {
		$json = $endpoint->getError(100);
	} if($_POST['user'] != $apiuser->username || $_POST['key'] != $apiuser->apikey) {
		$json = $endpoint->getError(101);
	} else {
		$json = $endpoint->getJSON();
	}

	echo $json;
	exit();
}