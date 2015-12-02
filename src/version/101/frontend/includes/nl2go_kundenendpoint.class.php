<?php

require_once('nl2go_endpoint.abstract.class.php');
require_once(PFAD_ROOT.PFAD_CLASSES.'class.JTL-Shop.Kundengruppe.php');
require_once(PFAD_ROOT.PFAD_CLASSES.'class.JTL-Shop.Kunde.php');

class Nl2goKundenEndpoint extends Nl2goEndpoint {

	var $allCustomers;
	var $page;
	var $row_count;

	public function __construct($allCustomers = true, $page = 0, $row_count = 1) {
		$this->allCustomers = $allCustomers;
		if(is_int($page)) {
			$this->page = $page;
		} else {
			$this->page = 0;
		}
		if(is_int($row_count)) {
			$this->row_count = $row_count;
		}
	}

	public function getJSON() {

		$json['customers'] = array();
		$row_count = $this->row_count;
		$offset = $this->page * $row_count;


		if($this->allCustomers) {

			$json['gruppe'] = 'alle';

			// Wo kann man so eine Liste durch Klassen bekommen? Kein Static Helper fuer Kundenliste.
			$kunden = $GLOBALS["DB"]->executeQuery("SELECT `kKunde` FROM `tkunde` LIMIT $offset, $row_count", 9); 

			if(empty($kunden)) {
				return $this->getError(300);
			}


			foreach($kunden as $kunde) {
				$json['customers'][] = $this->getKundenAttribute( $kunde['kKunde'] );
			}

		} else {

			$json['gruppe'] = 'nurnewsletter';

			$newsletterempfaenger = $GLOBALS["DB"]->executeQuery("SELECT cEmail, cVorname, cNachname, kKunde, cAnrede FROM tnewsletterempfaenger WHERE nAktiv = 1 LIMIT $offset,$row_count", 9); 
			if(empty($newsletterempfaenger)) {
				return $this->getError(300);
			}
			for($i = 0; $i < count($newsletterempfaenger); $i++) {

				$kKunde = $newsletterempfaenger[$i]['kKunde'];

				if($kKunde > 0) {
					$jsoncustomer = $this->getKundenAttribute($kKunde);
					$jsoncustomer['mail'] = $newsletterempfaenger[$i]['cEmail']; // Benutze die als Empfaenger eingetragene Adresse
					$json['customers'][$i] = $jsoncustomer;

				} else {
					$json['customers'][$i] = array(
						"mail" => $newsletterempfaenger[$i]['cEmail'],
						"vorname" => $newsletterempfaenger[$i]['cVorname'],
						"nachname" => $newsletterempfaenger[$i]['cNachname'],
						"anrede" => $newsletterempfaenger[$i]['cAnrede'],
						"kundengruppe" => "Newsletter",
						"istkunde" => "N"
					);
				}

			
			}

		}


		$json['nextpage'] = $this->page + 1;

		// Codierung mit UTF-8 wegen Umlauten
		for($i = 0; $i < sizeof($json['customers']); $i++) {
			foreach($json['customers'][$i] as &$attribute) {
				$attribute = utf8_encode($attribute);
			}
		}		

		return json_encode($json);
	}

	// Holt die Kundenattribute und mappt sie fuer den JSON Response
	private function getKundenAttribute($kKunde) {
		$kunde = new Kunde($kKunde);
		$kundengruppe = new Kundengruppe($kunde->kKundengruppe);
		return array(
					"mail" => $kunde->cMail,
					"vorname" => $kunde->cVorname,
					"nachname" => $kunde->cNachname,
					"anrede" => $kunde->cAnrede,
					"kundengruppe" => $kundengruppe->getName(),
					"strasse" => $kunde->cStrasse,
					"firma" => $kunde->cFirma,
					"titel" =>  $kunde->cTitel,
					"plz" => $kunde->cPLZ,
					"ort" => $kunde->cOrt,
					"bundesland" => $kunde->cBundesland,
					"land" => $kunde->cLand,
					"tel" => $kunde->cTel,
					"mobil" => $kunde->cMobil,
					"geburtstag" => $kunde->dGeburtstag,
					"kundennummer" => $kunde->cKundenNr,
					"istkunde" => "Y"
				);
	}

}