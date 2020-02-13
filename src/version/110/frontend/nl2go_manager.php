<?php

require_once(PFAD_ROOT . PFAD_BLOWFISH . "xtea.class.php");
require_once(PFAD_ROOT.PFAD_CLASSES.'class.JTL-Shop.Kundengruppe.php');
require_once(PFAD_ROOT.PFAD_CLASSES.'class.JTL-Shop.Kunde.php');

/**
 * Api core for handling requests.
 */
class Nl2goManager
{
	const CUSTOMERS_TABLE = '`tkunde`';
	const PRODUCTS_TABLE = '`tartikel`';
	const LANGUAGE_TABLE = '`tsprache`';
	const NEWSLETTER_SUBSCRIBER_TABLE = '`tnewsletterempfaenger`';
	const NEWSLETTER_SUBSCRIBER_GROUP_ID = 'only_subscribers';
	const NEWSLETTER_SUBSCRIBER_GROUP_NAME = 'Newsletter subscribers';
    // returntypes for NiceDB::executeQuery (list not exhaustive, see NiceDB implementation for more options)
    const RETURN_FETCH_ONE = 1;
    const RETURN_ROW_COUNT = 3;
    const RETURN_LAST_INSERT_ID = 7;
    const RETURN_FETCH_ALL_ASSOC = 9;

	/**
	 * @var array List of methods supported on API.
	 */
	public static $supportedMethods = array(
		'testConnection',
		'getPluginVersion',
		'getLanguages',
		'getGroups',
		'getFields',
		'getCustomers',
		'getCustomerCount',
		'getProductAttributes',
		'getProductInfo',
		'setUnsubscribe',
		'setSubscribe',
	);

	/**
	 * Determines whether API is available.
	 *
	 * @return boolean
	 */
	public function testConnection()
	{
		return true;
	}

	/**
	 * Returns the current plugin version.
	 *
	 * @return int
	 */
	public function getPluginVersion()
	{
		return 4005;
	}

	/**
	 * Get the languages.
	 *
	 * @return array All valid languages.
	 */
	public function getLanguages()
	{
		$query = 'SELECT * FROM ' . self::LANGUAGE_TABLE;
		$languages = $GLOBALS["DB"]->executeQuery($query, 9);

		$languagesToReturn = array();

		if (empty($languages)) {
			return $languagesToReturn;
		}

		foreach ($languages as $language) {
			// Check if language id exists.
			if (array_key_exists('kSprache', $language) === false) {
				continue;
			}

			// First try to add german than english language name.
			if (array_key_exists('cNameDeutsch', $language) === true) {
				$languagesToReturn[$language['kSprache']] = $language['cNameDeutsch'];
			} else if (array_key_exists('cNameEnglisch', $language) === true) {
				$languagesToReturn[$language['kSprache']] = $language['cNameEnglisch'];
			} else {
				$languagesToReturn[$language['kSprache']] = $language['kSprache'];
			}
		}

		return $languagesToReturn;
	}

	/**
	 * Get the customer groups.
	 *
	 * @return array Array of customer groups.
	 */
	public function getGroups()
	{
		// Get customer groups from db.
		$customerGroups = Kundengruppe::getGroups();

		$customerGroupsToReturn = array();

		// Add newsletter subscriber virtual group.
		$customerGroupsToReturn[] = self::getNewsletterSubscriberGroup();

		if (empty($customerGroups)) {
			return $customerGroupsToReturn;
		}

		// Create customers groups.
		foreach ($customerGroups as $customerGroup) {
			// How many customers belong to this group.
			$query = 'SELECT COUNT(*) as count FROM ' . self::CUSTOMERS_TABLE . " WHERE kKundengruppe = {$customerGroup->getKundengruppe()} AND cAktiv = 'Y'";
			$count = $GLOBALS["DB"]->executeQuery($query, 1);

			// Add group to the return list.
			$customerGroupsToReturn[] = array(
				'id' => $customerGroup->getKundengruppe(),
				'name' => $customerGroup->getName(),
				'description' => $customerGroup->getName(),
				'count' => $count->count
			);
		}

		return $customerGroupsToReturn;
	}

	/**
	 * Get the field definitions.
	 *
	 * @return type Array of field definitions.
	 */
	public function getFields()
	{
		return self::getFieldDefinitions();
	}

	/**
	 * Get all customers.
	 *
	 * @return array Array of customers.
	 */
	public function getCustomers()
	{
		$subscriber = filter_input(INPUT_POST, 'subscriber', FILTER_VALIDATE_BOOLEAN);
		$group = filter_input(INPUT_POST, 'group');

        // Get subscribed customer only if group is newsletter subscriber group.
        if ($group === self::NEWSLETTER_SUBSCRIBER_GROUP_ID) {
            $customers = $this->getNewsletterSubscribers();
        } else {
            $customers = $this->getRealCustomers($subscriber);
        }

		return $customers;
	}

	/**
	 * Get customer count.
	 *
	 * @return int Customers count.
	 */
	public function getCustomerCount()
	{
		return count($this->getCustomers());
	}

	/**
	 * Get subscribed customers. Only subcribers which are not customers will be returned.
	 *
	 * @return array Array of customers.
	 */
	private function getNewsletterSubscribers()
	{
		$query = "SELECT '" . self::NEWSLETTER_SUBSCRIBER_GROUP_NAME . "' as kKundengruppe,
						 ns.kKunde,
						 ns.kSprache as kSprache,
						 CASE ns.cAnrede
							WHEN 'w' THEN 'f'
							WHEN 'm' THEN 'm'
						 END as cAnrede,
						 ns.cVorname as cVorname,
						 ns.cNachname as cNachname,
						 1 as cNewsletter,
						 cEmail as cMail
					FROM " . self::NEWSLETTER_SUBSCRIBER_TABLE . " ns LEFT JOIN ".self::CUSTOMERS_TABLE." c ON ns.kKunde = c.kKunde
				   WHERE nAktiv = 1 AND (ns.kKunde = 0 OR c.kKunde IS NULL)";

		$emails = json_decode(filter_input(INPUT_POST, 'emails'));

		// Get only customers for requested emails.
		if ($emails !== null && count($emails) > 0) {
			$query .= "AND cEmail IN ('" . implode("','", $emails) . "')";
		}

		$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
		$offset = filter_input(INPUT_POST, 'offset', FILTER_VALIDATE_INT);
		if ($limit) {
			$offset = $offset ? $offset : 0;
			$query .= " LIMIT $offset, $limit ";
		}

		// Only subscribers without customers.
		$subscribers = $GLOBALS['DB']->executeQuery($query, 9);
        $languages = $this->getLanguages();

		$subscribersToReturn = array();

		if (empty($subscribers)) {
			return $subscribersToReturn;
		}

		foreach ($subscribers as $subscriber) {
			$subscriberToReturn = array();

			// Create subscriber using the field definition.
			foreach (self::getFieldDefinitions() as $fieldDefinition) {
				// Name of the field in db.
				$fieldName = $fieldDefinition['id'];

				// If field is not requested, don't add it. Include cMail to build uniqueId in connector.
				if (self::isFieldRequested($fieldName) === false && $fieldName !== 'cMail') {
					continue;
				}

				// Determine whether the field exists in table and has a value.
				$fieldValid = array_key_exists($fieldName, $subscriber) && empty($subscriber[$fieldName]) === false;

                // if current field is language, map the language and go to next field.
                if ($fieldValid === true && $fieldName === 'kSprache') {
                    $subscriberToReturn[$fieldName] = $languages[$subscriber[$fieldName]];
                    continue;
                }

				// Read field value.
				$subscriberToReturn[$fieldName] = $fieldValid === true ? $subscriber[$fieldName] : '';
			}

			// Add subscriber to the return list.
			$subscribersToReturn[] = $subscriberToReturn;
		}

		return $subscribersToReturn;
	}

	/**
	 * Get all customers.
	 *
	 * @return array Array of customers.
	 */
	private function getRealCustomers($onlySubscribed)
	{
		$where = array("c.cAktiv = 'Y'");
		$join = '';

		$query = "SELECT c.kKunde,
						 c.kKundengruppe as kKundengruppe,
						 c.kSprache,
						 c.cKundenNr,
						 CASE c.cAnrede
							WHEN 'w' THEN 'f'
							WHEN 'm' THEN 'm'
						 END as cAnrede,
						 c.cTitel,
						 if(ns.cVorname is null or ns.cVorname = '', c.cVorname, ns.cVorname) as cVorname,
						 if(c.cNachname is not null and c.cNachname != '', c.cNachname, ns.cNachname) as cNachname,
						 c.cFirma,
						 c.cZusatz,
						 c.cStrasse,
						 c.cHausnummer,
						 c.cAdressZusatz,
						 c.cPLZ,
						 c.cOrt,
						 c.cBundesland,
						 c.cLand,
						 c.cTel,
						 c.cMobil,
						 c.cFax,
						 if(ns.cEmail is null or ns.cEmail = '', cMail, ns.cEmail) as cMail,
						 c.cUSTID,
						 c.cWWW,
						 c.cSperre,
						 c.fGuthaben,
						 if(ns.nAktiv, 1, 0) as cNewsletter,
						 c.dGeburtstag,
						 c.fRabatt,
						 c.cHerkunft,
						 c.dErstellt,
						 c.dVeraendert,
						 c.cAktiv,
						 c.cAbgeholt,
						 c.nRegistriert
                         FROM " . self::CUSTOMERS_TABLE . ' c
                         LEFT JOIN ' . self::NEWSLETTER_SUBSCRIBER_TABLE . ' ns ON c.kKunde = ns.kKunde';


		$hours = filter_input(INPUT_POST, 'hours');

		// Get only customer modified in $hours hours.
		if (empty($hours) === false) {
			$where[] = "(c.dVeraendert >= DATE_SUB(NOW(), INTERVAL $hours HOUR) OR c.dVeraendert = '0000-00-00 00:00:00')";
		}

		$group = filter_input(INPUT_POST, 'group');

		// Get only customers from requested group.
		if (empty($group) === false) {
			$where[] = "c.kKundengruppe = $group";
		}

		$emails = json_decode(filter_input(INPUT_POST, 'emails'));

		// Get only customers for requested emails.
		if ($emails !== null && count($emails) > 0) {
			$where[] = "cMail IN ('" . implode("','", $emails) . "')";
		}

		// Filter by subscribed only.
		if ($onlySubscribed === true) {
			$where[] = 'ns.nAktiv = 1';
		}

		// Add where if customers should be filtered.
		if (empty($where) === false) {
			$query .= $join . ' WHERE ' . implode(' AND ', $where);
		}

		$limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
		$offset = filter_input(INPUT_POST, 'offset', FILTER_VALIDATE_INT);
		if ($limit) {
			$offset = $offset ? $offset : 0;
			$query .= " LIMIT $offset, $limit ";
		}

		$customers = $GLOBALS['DB']->executeQuery($query, 9);
        $languages = $this->getLanguages();

		$customersToReturn = array();

		if (empty($customers)) {
			return $customersToReturn;
		}

		foreach ($customers as $customer) {
			$customer = $this->decryptData($customer);
			$customerToReturn = array();

			// Create customer using the field definition.
			foreach (self::getFieldDefinitions() as $fieldDefinition) {
				// Name of the field in db.
				$fieldName = $fieldDefinition['id'];

				// If field is not requested, don't add it.
				if (self::isFieldRequested($fieldName) === false) {
					continue;
				}

				// Determine whether the field exists in table and has a value.
				$fieldValid = array_key_exists($fieldName, $customer) && empty($customer[$fieldName]) === false;

				// If current field is customer group, get a name of group and go to the next field.
				if ($fieldValid === true && $fieldName === 'kKundengruppe') {
					$customerGroup = new Kundengruppe($customer[$fieldName]);
					$customerToReturn[$fieldName] = $customerGroup->getName();
					continue;
				}

                // if current field is language, map the language and go to next field.
                if ($fieldValid === true && $fieldName === 'kSprache') {
                    $customerToReturn[$fieldName] = $languages[$customer[$fieldName]];
                    continue;
                }

				// Read field value.
				$customerToReturn[$fieldName] = $fieldValid === true ? $customer[$fieldName] : '';
			}

			// Add customer to the return list.
			$customersToReturn[] = $customerToReturn;
		}

		return $customersToReturn;
	}

	/**
	 * Get the product attribute definitions.
	 *
	 * @return array Array of field definitions.
	 */
	public function getProductAttributes()
	{
		return self::getProductAttributeDefinitions();
	}

	/**
	 * Get product informations.
	 *
	 * @return mixed Product info.
	 */
	public function getProductInfo()
	{
		// Article number
		$identifier = filter_input(INPUT_POST, 'identifier');

		if ($identifier === null) {
			self::sendError('Indentifier not found.');
		}

		$query = 'SELECT kArtikel FROM ' . self::PRODUCTS_TABLE . " WHERE cArtNr = '$identifier'";
		$productInfoFromDb = $GLOBALS["DB"]->executeQuery($query, 9);

		if (empty($productInfoFromDb)) {
			self::sendError(sprintf('Product with article number %d has not been found.', $identifier));
		}

		$language = filter_input(INPUT_POST, 'language');
        $languages = $this->getLanguages();
        if (!empty($language) && !array_key_exists($language, $languages)) {
            self::sendError('Product not found in this language!');
        }
		$productInfo = $productInfoFromDb[0]['kArtikel'];
		$product = new Artikel();
		$product->fuelleArtikel($productInfo, false, 0, $language);
		if ($product->cArtNr != $identifier) {
			self::sendError('Product not found in this language!');
		}

        // For instances where customer has setting enabled that prices are only visible to logged in users
        if ($_SESSION['Kundengruppe']->darfPreiseSehen !== 1) {
            $_SESSION['Kundengruppe']->darfPreiseSehen = 1;
        }

		$productPrice = $product->gibPreis(1, false);
		$productTax = ((float) gibUst($product->kSteuerklasse)) / 100;

		$productToReturn = array();

		foreach (self::getProductAttributeDefinitions() as $attributeDefinition) {
			$attributeName = $attributeDefinition['id'];

			// If attribute is not requested, don't add it.
			if (self::isProductAttributeRequested($attributeName) === false) {
				continue;
			}

            if ($attributeName === 'oldPrice') {
                $productToReturn[$attributeName] = (isset($product->Preise->alterVK[0]) && $product->Preise->alterVKNetto !== null) ? round($product->Preise->alterVK[0], 2) : false;
                continue;
            }

            if ($attributeName === 'newPrice') {
                $productToReturn[$attributeName] = isset($productPrice) ? round($productPrice * ($productTax + 1), 2) : false;
                continue;
            }

            if ($attributeName === 'oldPriceNet') {
                $productToReturn[$attributeName] = (isset($product->Preise->alterVK[1]) && $product->Preise->alterVKNetto !== null) ? round($product->Preise->alterVK[1], 2) : false;
                continue;
            }

            if ($attributeName === 'newPriceNet') {
                $productToReturn[$attributeName] = isset($productPrice) ? round($productPrice, 2) : false;
                continue;
            }

			if ($attributeName === 'url') {
				$productToReturn[$attributeName] = gibShopURL();
				continue;
			}

			if ($attributeName === 'images') {
				$product->holBilder();
				foreach($product->Bilder as $bild) {
					$productToReturn[$attributeName][] = gibShopURL() . '/' . $bild->cPfadNormal;
				}
				continue;
			}

			if ($attributeName === 'fMwSt') {
				$productToReturn[$attributeName] = $productTax;
				continue;
			}

			$productToReturn[$attributeName] = isset($product->$attributeName) ? $product->$attributeName : '';
		}

		return $productToReturn;
	}

	/**
	 * Unsubscribe a customer by email.
	 *
	 * @return boolean
	 */
	public function setUnsubscribe()
	{
		$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

		if ($email === null) {
			self::sendError('EMAILNOTPROVIDED');
		}

        $affectedRows = $GLOBALS["DB"]->executeQuery('DELETE FROM ' . self::NEWSLETTER_SUBSCRIBER_TABLE . " WHERE cEmail = '$email'", self::RETURN_ROW_COUNT);

        return $affectedRows > 0;
	}

	/**
	 * Unsubscribe a customer by email.
	 *
	 * @return boolean
	 */
	public function setSubscribe()
	{
		$email = filter_input(INPUT_POST, 'email');

		if ($email === null) {
			self::sendError('EMAILADDRESSNOTPROVIDED');
		}

		// Get customer to subscribe.
		$customer = $GLOBALS["DB"]->executeQuery('SELECT * FROM ' . self::CUSTOMERS_TABLE . " WHERE cMail = '$email'", 1);

		if (empty($customer) === true) {
			self::sendError('EMAILADDRESSNOTFOUND');
		}

		$query = 'INSERT INTO ' . self::NEWSLETTER_SUBSCRIBER_TABLE . " (
			`kSprache`,
			`kKunde`,
			`nAktiv`,
			`cAnrede`,
			`cVorname`,
			`cNachname`,
			`cEmail`,
			`cOptCode`,
			`cLoeschCode`,
			`dEingetragen`
		) VALUES (
			(SELECT kSprache FROM " . self::LANGUAGE_TABLE . " WHERE cISO = 'ger'),
			$customer->kKunde,
			1,
			'$customer->cAnrede',
			'$customer->cVorname',
			'$customer->cNachname',
			'$email',
			'OptCode',
			'LoeschCode',
			now()
		);";

        $lastInsertId = $GLOBALS["DB"]->executeQuery($query, self::RETURN_LAST_INSERT_ID);

        return isset($lastInsertId) && is_numeric($lastInsertId);
	}

	/**
	 * Returns the newsletter subscriber virtual group.
	 * There are newsletter subscribers which are not customers and doesn't belong to any group, so virtual group must be added.
	 */
	private static function getNewsletterSubscriberGroup()
	{
		// Subscribers count.
		$count = $GLOBALS['DB']->executeQuery('SELECT COUNT(*) as count FROM ' . self::NEWSLETTER_SUBSCRIBER_TABLE . ' WHERE nAktiv = 1 AND kKunde = 0', 1);

		return array(
			'id' => self::NEWSLETTER_SUBSCRIBER_GROUP_ID,
			'name' => self::NEWSLETTER_SUBSCRIBER_GROUP_NAME,
			'description' => 'Newsletter subscribers',
			'count' => $count->count
		);
	}

	/**
	 * Determine whether or not field should be returned.
	 */
	private static function isFieldRequested($fieldName)
	{
		$fields = json_decode(filter_input(INPUT_POST, 'fields'));

		if ($fields !== null) {
			// If fields are passed and this field is included.
			return array_search($fieldName, $fields) !== false;
		} else {
			return true;
		}
	}

	/**
	 * Get the field definitions.
	 *
	 * @return array - Array of fields from customers table.
	 */
	private static function getFieldDefinitions()
	{
		return array(
			array('id' => 'kKunde',			'name' => 'Customer id',		'description' => 'Customers id.',					'type' => 'Integer'),
			array('id' => 'kKundengruppe',	'name' => 'Customer group',		'description' => 'Customers group.',				'type' => 'String'),
			array('id' => 'kSprache',		'name' => 'Language',			'description' => 'Customers language.',				'type' => 'Integer'),
			array('id' => 'cKundenNr',		'name' => 'Customer number',	'description' => 'Customers number.',				'type' => 'String'),
			array('id' => 'cAnrede',		'name' => 'Salutation',			'description' => 'Customers salutation.',			'type' => 'String'),
			array('id' => 'cTitel',			'name' => 'Title',				'description' => 'Customers title.',				'type' => 'String'),
			array('id' => 'cVorname',		'name' => 'First name',			'description' => 'Customers first name.',			'type' => 'String'),
			array('id' => 'cNachname',		'name' => 'Last name',			'description' => 'Customers last name.',			'type' => 'String'),
			array('id' => 'cFirma',			'name' => 'Company',			'description' => 'Customers company.',				'type' => 'String'),
			array('id' => 'cZusatz',		'name' => 'Addition',			'description' => 'Customers addition.',				'type' => 'String'),
			array('id' => 'cStrasse',		'name' => 'Street',				'description' => 'Customers street address.',		'type' => 'String'),
			array('id' => 'cHausnummer',	'name' => 'House number',		'description' => 'Customers house number.',			'type' => 'String'),
			array('id' => 'cAdressZusatz',	'name' => 'Additional address',	'description' => 'Customers additional address.',	'type' => 'String'),
			array('id' => 'cPLZ',			'name' => 'Zip code',			'description' => 'Customers zip code.',				'type' => 'String'),
			array('id' => 'cOrt',			'name' => 'Place',				'description' => 'Customers place.',				'type' => 'String'),
			array('id' => 'cBundesland',	'name' => 'State',				'description' => 'Customers state.',				'type' => 'String'),
			array('id' => 'cLand',			'name' => 'Country',			'description' => 'Customers country.',				'type' => 'String'),
			array('id' => 'cTel',			'name' => 'Phone',				'description' => 'Customers phone.',				'type' => 'String'),
			array('id' => 'cMobil',			'name' => 'Mobile phone',		'description' => 'Customers mobile phone.',			'type' => 'String'),
			array('id' => 'cFax',			'name' => 'Fax',				'description' => 'Customers fax.',					'type' => 'String'),
			array('id' => 'cMail',			'name' => 'Email',				'description' => 'Customers email.',				'type' => 'String'),
			array('id' => 'cUSTID',			'name' => 'Tax ID',				'description' => 'Customers tax ID.',				'type' => 'String'),
			array('id' => 'cWWW',			'name' => 'Web site',			'description' => 'Customers web site.',				'type' => 'String'),
			array('id' => 'cSperre',		'name' => 'Barrier',			'description' => 'Customers barrier.',				'type' => 'String'),
			array('id' => 'fGuthaben',		'name' => 'Credit',				'description' => 'Customers credit.',				'type' => 'Float'),
			array('id' => 'cNewsletter',	'name' => 'Newsletter',			'description' => 'Customers newsletter.',			'type' => 'String'),
			array('id' => 'dGeburtstag',	'name' => 'Birthday',			'description' => 'Customers birthday.',				'type' => 'Date'),
			array('id' => 'fRabatt',		'name' => 'Discount',			'description' => 'Customers discount.',				'type' => 'Float'),
			array('id' => 'cHerkunft',		'name' => 'Origin',				'description' => 'Customers origin.',				'type' => 'String'),
			array('id' => 'dErstellt',		'name' => 'Created date',		'description' => 'Customers created date.',			'type' => 'Date'),
			array('id' => 'dVeraendert',	'name' => 'Modified date',		'description' => 'Customers modified date.',		'type' => 'Date'),
			array('id' => 'cAbgeholt',		'name' => 'Picked',				'description' => 'Customers picked.',				'type' => 'String'),
			array('id' => 'nRegistriert',	'name' => 'Join',				'description' => 'Customers join.',					'type' => 'Integer'),
		);
	}

	/**
	 * Determine whether or not product attribute should be returned.
	 */
	private static function isProductAttributeRequested($attributeName)
	{
		$attributes = json_decode(filter_input(INPUT_POST, 'attributes'));

		if ($attributes !== null) {
			// If product attributes are passed and this field is included.
			return array_search($attributeName, $attributes) !== false;
		} else {
			return true;
		}
	}

	/**
	 * Get the product attributes definitions.
	 *
	 * @return array - Array of product attributes from article table.
	 */
	private static function getProductAttributeDefinitions()
	{
		return array(
			array('id' => 'oldPrice',					'name' => 'Old price',				'description' => 'Product old price.',				'type' => 'Float'),
			array('id' => 'newPrice',					'name' => 'New price',				'description' => 'Product new price.',				'type' => 'Float'),
			array('id' => 'oldPriceNet',				'name' => 'Old price net',			'description' => 'Product old price net.',			'type' => 'Float'),
			array('id' => 'newPriceNet',				'name' => 'New price net',			'description' => 'Product new price net.',			'type' => 'Float'),
			array('id' => 'url',						'name' => 'Url',					'description' => 'Shop url.',						'type' => 'String'),
			array('id' => 'images',						'name' => 'Images',					'description' => 'Product images.',					'type' => 'String'),
			array('id' => 'cHersteller',				'name' => 'Manufacturer',			'description' => 'Product manufacturer.',			'type' => 'String'),
			array('id' => 'model',						'name' => 'Model',					'description' => 'Product model.',					'type' => 'String'),
			array('id' => 'cLieferstatus',				'name' => 'Delivery status',		'description' => 'Product delivery status.',		'type' => 'String'),
			array('id' => 'kSteuerklasse',				'name' => 'Tax class',				'description' => 'Product tax class.',				'type' => 'Integer'),
			array('id' => 'kEinheit',					'name' => 'Unit',					'description' => 'Product unit.',					'type' => 'Integer'),
			array('id' => 'kVersandklasse',				'name' => 'Shipping class',			'description' => 'Product shipping class.',			'type' => 'Integer'),
			array('id' => 'kEigenschaftKombi',			'name' => 'Combined property',		'description' => 'Product combined property.',		'type' => 'Integer'),
			array('id' => 'kStueckliste',				'name' => 'Part list',				'description' => 'Product part list.',				'type' => 'Integer'),
			array('id' => 'kWarengruppe',				'name' => 'Trading group',			'description' => 'Product trading group.',			'type' => 'Integer'),
			array('id' => 'cSeo',						'name' => 'SEO',					'description' => 'Product SEO.',					'type' => 'String'),
			array('id' => 'cArtNr',						'name' => 'Product number',			'description' => 'Product number.',					'type' => 'String'),
			array('id' => 'cName',						'name' => 'Name',					'description' => 'Product name.',					'type' => 'String'),
			array('id' => 'cBeschreibung',				'name' => 'Description',			'description' => 'Product description.',			'type' => 'String'),
			array('id' => 'cAnmerkung',					'name' => 'Note',					'description' => 'Product note.',					'type' => 'String'),
			array('id' => 'fLagerbestand',				'name' => 'Stock',					'description' => 'Product stock.',					'type' => 'Float'),
			array('id' => 'fMwSt',						'name' => 'VAT',					'description' => 'Product VAT.',					'type' => 'Float'),
			array('id' => 'fMindestbestellmenge',		'name' => 'Minimum order quanity',	'description' => 'Product minimum order quanity.',	'type' => 'Float'),
			array('id' => 'fLieferantenlagerbestand',	'name' => 'Supplier stock',			'description' => 'Product supplier stock.',			'type' => 'Float'),
			array('id' => 'fLieferzeit',				'name' => 'Delivery time',			'description' => 'Product delivery time.',			'type' => 'Float'),
			array('id' => 'cBarcode',					'name' => 'Barcode',				'description' => 'Product barcode.',				'type' => 'String'),
			array('id' => 'cTopArtikel',				'name' => 'Top article',			'description' => 'Product top article.',			'type' => 'String'),
			array('id' => 'fGewicht',					'name' => 'Weight',					'description' => 'Weight.',							'type' => 'Float'),
			array('id' => 'fArtikelgewicht',			'name' => 'Product weight',			'description' => 'Product weight.',					'type' => 'Float'),
			array('id' => 'cNeu',						'name' => 'Is new',					'description' => 'Is Product new.',					'type' => 'String'),
			array('id' => 'cKurzBeschreibung',			'name' => 'Short description',		'description' => 'Product short description.',		'type' => 'String'),
			array('id' => 'fUVP',						'name' => 'RRP',					'description' => 'Product RRP.',					'type' => 'Float'),
			array('id' => 'cLagerBeachten',				'name' => 'Bearing note',			'description' => 'Product bearing note.',			'type' => 'String'),
			array('id' => 'cLagerKleinerNull',			'name' => 'Small stock zero',		'description' => 'Product small stock zero.',		'type' => 'String'),
			array('id' => 'cTeilbar',					'name' => 'Divisible',				'description' => 'Product divisible.',				'type' => 'String'),
			array('id' => 'fPackeinheit',				'name' => 'Packaging',				'description' => 'Product packaging.',				'type' => 'Float'),
			array('id' => 'fAbnahmeintervall',			'name' => 'Purchase',				'description' => 'Product purchase interval.',		'type' => 'Float'),
			array('id' => 'fZulauf',					'name' => 'Intake',					'description' => 'Product intake.',					'type' => 'Float'),
			array('id' => 'cVPE',						'name' => 'VPE',					'description' => 'Product VPE.',					'type' => 'String'),
			array('id' => 'fVPEWert',					'name' => 'Unit value',				'description' => 'Product unit VPE.',				'type' => 'Float'),
			array('id' => 'cVPEEinheit',				'name' => 'PU unit',				'description' => 'Product unit VPE.',				'type' => 'String'),
			array('id' => 'cSuchbegriffe',				'name' => 'Searches',				'description' => 'Product searches.',				'type' => 'String'),
			array('id' => 'nSort',						'name' => 'Sort',					'description' => 'Product sort.',					'type' => 'Integer'),
			array('id' => 'dErscheinungsdatum',			'name' => 'Release date',			'description' => 'Product release date.',			'type' => 'Date'),
			array('id' => 'dErstellt',					'name' => 'Created date',			'description' => 'Product created date.',			'type' => 'Date'),
			array('id' => 'dZulaufDatum',				'name' => 'Expiration date',		'description' => 'Product expiration date.',		'type' => 'Date'),
			array('id' => 'dMHD',						'name' => 'Best before date',		'description' => 'Product best before date.',		'type' => 'Date'),
			array('id' => 'cSerie',						'name' => 'Series',					'description' => 'Product series.',					'type' => 'String'),
			array('id' => 'cISBN',						'name' => 'ISBN',					'description' => 'Product ISBN.',					'type' => 'String'),
			array('id' => 'cASIN',						'name' => 'ASIN',					'description' => 'Product ASIN.',					'type' => 'String'),
			array('id' => 'cHAN',						'name' => 'HAN',					'description' => 'Product HAN.',					'type' => 'String'),
			array('id' => 'cUNNummer',					'name' => 'UN Number',				'description' => 'Product UN number.',				'type' => 'String'),
			array('id' => 'cGefahrnr',					'name' => 'Gefahrnr',				'description' => 'Product Gefahrnr.',				'type' => 'String'),
			array('id' => 'cTaric',						'name' => 'Taric',					'description' => 'Product Taric.',					'type' => 'String'),
			array('id' => 'cUPC',						'name' => 'UPC',					'description' => 'Product UPC.',					'type' => 'String'),
			array('id' => 'cHerkunftsland',				'name' => 'Country of origin',		'description' => 'Product country of origin.',		'type' => 'String'),
			array('id' => 'cEPID',						'name' => 'EPID',					'description' => 'Product EPID.',					'type' => 'String'),
			array('id' => 'Grundpreis',					'name' => 'Grundpreis',				'description' => 'Grundpreis pro Einheit',			'type' => 'Float')
		);
	}

	/**
	 * Returns error as a result of API call.
	 *
	 * @param string $message Error message.
	 */
	public static function sendError($message)
	{
		// Create error response.
		$response = array(
			'status' => 'error',
			'message' => $message
		);

		// Return result in json form.
		echo json_encode($response);

		// Stop rendering contact form.
		exit();
	}

	/**
	 * Returns data as a result of API call.
	 *
	 * @param mixed $result Result.
	 */
	public static function sendData($result)
	{
		// Encodes result to UTF8.
		Nl2goManager::utf8Encode($result);

		// Create ok response.
		$response = array(
			'status' => 'ok',
			'data' => $result,
		);

		// Return result in json form.
		echo json_encode($response);

		// Stop rendering contact form.
		exit();
	}

	/**
	 * Encodes the string or object and/or subobject properties which are strings to UTF8.
	 *
	 * @param mixed $input string or object to encode.
	 */
	public static function utf8Encode(&$input) {
		if (is_string($input)) {
			$input = utf8_encode($input);
		} else if (is_array($input)) {
			foreach ($input as &$value) {
				self::utf8Encode($value);
			}

			unset($value);
		} else if (is_object($input)) {
			$vars = array_keys(get_object_vars($input));

			foreach ($vars as $var) {
				self::utf8Encode($input->$var);
			}
		}
	}

	/**
	 * Determine whether credentials are valid.
	 */
	public static function checkAuthentication()
	{
		$apiUser = $GLOBALS['DB']->executeQuery('SELECT username, apikey FROM `xplugin_newsletter2go_keys`', 1);

		$user = filter_input(INPUT_POST, 'user');
		$key = filter_input(INPUT_POST, 'key');

		// Return authentication error message if user info in db doesn't exists or username or api key are not correct.
		if (empty($apiUser) === true || $user !== $apiUser->username || $key !== $apiUser->apikey) {
			self::sendError('USERNOTAUTHENTICATED');
		}
	}

	/**
	 * Decrypts encrypted fields so that they are readable
	 *
	 * @param array $customer
	 * @return array - decrypted customer
	 */
	private function decryptData($customer)
	{
		$customer['cNachname'] = trim(entschluesselXTEA($customer['cNachname']));
		$customer['cFirma'] = trim(entschluesselXTEA($customer['cFirma']));
		$customer['cZusatz'] = trim(entschluesselXTEA($customer['cZusatz']));
		$customer['cStrasse'] = trim(entschluesselXTEA($customer['cStrasse']));

		return $customer;
	}

}
