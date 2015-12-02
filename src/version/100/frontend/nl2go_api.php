<?php

	function sendError($code, $message) {
		/*
			Fehlercodes: 
			100: Konnte keine Logininformationen in der Datenbank finden. Bitte installieren Sie das JTL-Plugin erneut.
			101: Authentifizierung fehlgeschlagen.
			102: Keine Kundengruppen gefunden.

			200: Keine Produkt mit der ID gefunden.
		*/
		echo json_encode(array(
			"error" => true,
			"errorCode" => $code,
			"errorMsg" => $message
		));
		exit(); // Nach einem Fehler beenden
	}

	if($_POST['api'] == 'nl2go') {
		$apiuser = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1); 

		if(!$apiuser) {
			sendError(100, "Konnte keine Logininformationen in der Datenbank finden. Bitte installieren Sie das JTL-Plugin erneut.");
		}

		if($_POST['user'] != $apiuser->username || $_POST['key'] != $apiuser->apikey) {
			sendError(101, "Authentifizierung fehlgeschlagen.");
		}


		if($_POST['r'] == 'kunden') {	

			$json['customers'] = array();

			// Kundengruppen fetchen

			$tkundengruppe = $GLOBALS["DB"]->executeQuery("SELECT `cName`, `kKundengruppe` FROM `tkundengruppe`", 9);

			if(!$tkundengruppe) {
				sendError(102, "Keine Kundengruppen gefunden.");
			}

			$kundengruppen = array();
			for($i = 0; $i < count($tkundengruppe); $i++) {
				$kKundengruppe = $tkundengruppe[$i]['kKundengruppe'];
				$cName =  $tkundengruppe[$i]['cName'];
				$kundengruppen[$kKundengruppe] = $cName;
			}



			if($oPlugin->oPluginEinstellungAssoc_arr['newsletter2go_syncgroup'] == 'All') {
				$kunden = $GLOBALS["DB"]->executeQuery("SELECT * FROM `tkunde`", 9); 
				// dErstellt - 2013-11-27

				$json['gruppe'] = 'alle';
				// Entschlüssel verschlüsselte Teile
				for($i = 0; $i < count($kunden); $i++) {
					$json['customers'][$i] = array(
						"mail" => $kunden[$i]['cMail'],
						"vorname" => $kunden[$i]['cVorname'],
						"nachname" => trim(entschluesselXTEA($kunden[$i]['cNachname'])),
						"anrede" => $kunden[$i]['cAnrede'],
						"kundengruppe" => $kundengruppen[$kunden[$i]['kKundengruppe']],
						"strasse" => trim(entschluesselXTEA($kunden[$i]['cStrasse'])),
						"firma" => trim(entschluesselXTEA($kunden[$i]['cFirma'])),
						"titel" =>  $kunden[$i]['cTitel'],
						"plz" => $kunden[$i]['cPLZ'],
						"ort" => $kunden[$i]['cOrt'],
						"bundesland" => $kunden[$i]['cBundesland'],
						"land" => $kunden[$i]['cLand'],
						"tel" => $kunden[$i]['cTel'],
						"mobil" => $kunden[$i]['cMobil'],
						"geburtstag" => $kunden[$i]['dGeburtstag'],
						"kundennummer" => $kunden[$i]['cKundenNr'],
						"istkunde" => "Y"
					);
				}

			} else {

				$newsletterempfaenger = $GLOBALS["DB"]->executeQuery("SELECT cEmail, cVorname, cNachname, kKunde, cAnrede FROM tnewsletterempfaenger WHERE nAktiv = 1", 9); 
				$json['gruppe'] = 'nurnewsletter';
				for($i = 0; $i < count($newsletterempfaenger); $i++) {
					$kKunde = $newsletterempfaenger[$i]['kKunde'];
					$json['customers'][$i] = array(
						"mail" => $newsletterempfaenger[$i]['cEmail'],
						"vorname" => $newsletterempfaenger[$i]['cVorname'],
						"nachname" => $newsletterempfaenger[$i]['cNachname'],
						"anrede" => $newsletterempfaenger[$i]['cAnrede'],
						"istkunde" => $kKunde > 0 ? "Y" : "N"
					);
					// Wenn kKunde == 0 -> Kein Kunde
					if($kKunde > 0) {
						$kunden = $GLOBALS["DB"]->executeQuery("SELECT * FROM tkunde WHERE kKunde = '$kKunde'", 9);
						$json['customers'][$i] = array(
							"mail" => $newsletterempfaenger[$i]['cEmail'], // Benutze die als Empfänger eingetragene Adresse
							"vorname" => $kunden[0]['cVorname'],
							"nachname" => trim(entschluesselXTEA($kunden[0]['cNachname'])),
							"anrede" => $kunden[0]['cAnrede'],
							"kundengruppe" => $kundengruppen[$kunden[0]['kKundengruppe']],
							"strasse" => trim(entschluesselXTEA($kunden[0]['cStrasse'])),
							"firma" => trim(entschluesselXTEA($kunden[0]['cFirma'])),
							"titel" =>  $kunden[0]['cTitel'],
							"plz" => $kunden[0]['cPLZ'],
							"ort" => $kunden[0]['cOrt'],
							"bundesland" => $kunden[0]['cBundesland'],
							"land" => $kunden[0]['cLand'],
							"tel" => $kunden[0]['cTel'],
							"mobil" => $kunden[0]['cMobil'],
							"geburtstag" => $kunden[0]['dGeburtstag'],
							"kundennummer" => $kunden[0]['cKundenNr'],
							"istkunde" => "Y"
						);
					}
				
				}

			}

			// Codierung mit UTF-8 wegen Umlauten
			for($i = 0; $i < sizeof($json['customers']); $i++) {
				foreach($json['customers'][$i] as &$attribute) {
					$attribute = utf8_encode($attribute);
				}
			}

			echo json_encode($json);
			exit();

		} else if($_POST['r'] == 'produkt') {
			//$itemid = mysql_real_escape_string($_POST['item']); - Funktioniert nich auf deren Server
			$itemid = $_POST['item'];
			$apiuser = $GLOBALS["DB"]->executeQuery("SELECT username, apikey FROM xplugin_newsletter2go_keys", 1); 


			// Query Artikel [tartikel]
			$query = "SELECT `kArtikel`, `cArtNr`, `cName`, `cBeschreibung`, `cKurzBeschreibung`, `kSteuerklasse`, `cSeo` FROM `tartikel` WHERE `cArtNr` = '$itemid'";
			$produkt = $GLOBALS["DB"]->executeQuery($query, 9);
			$kArtikel = $produkt[0]['kArtikel'];
			$kSteuerklasse = $produkt[0]['kSteuerklasse'];

			if(!$produkt) {
				sendError(200, "Keine Produkt mit der ID gefunden.");
			}

			$json = array(
					"itemID" => $produkt[0]['cArtNr'],
					"name" =>  utf8_encode($produkt[0]['cName']),
					"url" => gibShopURL(),
					"link" => '/' . $produkt[0]['cSeo'],
					"short_description" => utf8_encode($produkt[0]['cKurzBeschreibung']),
					"description" => utf8_encode($produkt[0]['cBeschreibung']),
					"images" => array()
			);

			// Bilder zum Artikel [tartikelpict]
			$query = "SELECT `cPfad` from `tartikelpict` WHERE `kArtikel` = $kArtikel";
			$bilder = $GLOBALS["DB"]->executeQuery($query, 9);
			for($i = 0; $i < count($bilder); $i++) {
			 	$json['images'][] = gibShopURL() . "/bilder/produkte/normal/" . $bilder[$i]['cPfad'];
			}

			// Preise zum Artikel [tpreise] 
			// TODO Berücksichtigung verschiedener Kundengruppen?
			
			$query = "SELECT `fVKNetto` FROM `tpreise` WHERE `kArtikel` = $kArtikel";
			$preis = $GLOBALS["DB"]->executeQuery($query, 9);
			$preis = $preis[0];
			$json['price'] = $preis['fVKNetto'];


			// Steuern zum Artikel [tsteuersatz]
			$query = "SELECT `fSteuersatz` FROM `tsteuersatz` WHERE `kSteuerklasse` = '$kSteuerklasse' AND `kSteuerzone` = 1";
			$steuersatz = $GLOBALS["DB"]->executeQuery($query, 9);
			$steuersatz = $steuersatz[0];
			$json['taxes'] = $steuersatz['fSteuersatz'] / 100;

			echo json_encode($json);
			exit();
		}


}