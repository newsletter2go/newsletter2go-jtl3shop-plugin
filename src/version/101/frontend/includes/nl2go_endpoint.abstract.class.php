<?php

abstract class Nl2goEndpoint {
	abstract public function getJSON();

	protected function getError($code) {

		$messages = array(
			100 => "Konnte keine Logininformationen in der Datenbank finden. Bitte installieren Sie das JTL-Plugin erneut.",
			101 => "Authentifizierung fehlgeschlagen.",
			102 => "Keine Kundengruppen gefunden.",
			103 => "Kein Endpoint",
			200 => "Keine Produkt mit der ID gefunden.",
			300 => "Keine Kunden gefunden"
		);

		return json_encode(array(
			"error" => true,
			"errorCode" => $code,
			"errorMsg" => isset($messages[$code]) ? $messages[$code] : 'Unbekannter Fehler'
		));
	}
}