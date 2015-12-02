<?php

require_once('nl2go_endpoint.abstract.class.php');
require_once(PFAD_ROOT.PFAD_CLASSES.'class.JTL-Shop.Artikel.php');

class Nl2goProduktEndpoint extends Nl2goEndpoint {

	var $itemid;

	public function __construct($itemid) {
		$this->itemid = $itemid;
	}

	public function getJSON() {
		$itemid = $this->itemid;
		$apiuser = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1); 


		// Query Artikel [tartikel]
		// Kann man irgendwie auch den Artikel als Objekt direkt so laden?
		$query = "SELECT `kArtikel` FROM `tartikel` WHERE `cArtNr` = '$itemid'";
		$produkt = $GLOBALS["DB"]->executeQuery($query, 9);

		if(empty($produkt)) {
			return $this->getError(200);
		}

		$kArtikel = $produkt[0]['kArtikel'];
		$artikel = new Artikel();
		$artikel->fuelleArtikel($kArtikel, false);

		$json = array(
				"itemID" => $artikel->cArtNr,
				"name" =>  utf8_encode($artikel->cName),
				"url" => gibShopURL(),
				"link" => '/' . $artikel->cSeo,
				"short_description" => utf8_encode($artikel->cKurzBeschreibung),
				"description" => utf8_encode($artikel->cBeschreibung),
				"images" => array()
		);

		// Bilder zum Artikel
		$artikel->holBilder();
		foreach($artikel->Bilder as $bild) {
			$json['images'][] = gibShopURL() . '/' . $bild->cPfadNormal;
		}

		$json['price'] = $artikel->gibPreis(1, false); 
		$json['taxes'] = ((float) gibUst($artikel->kSteuerklasse)) / 100;

		return json_encode($json);
	}

}