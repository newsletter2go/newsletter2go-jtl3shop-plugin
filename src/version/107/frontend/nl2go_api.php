<?php

require_once (__DIR__ . DIRECTORY_SEPARATOR . 'nl2go_manager.php');

$api = filter_input(INPUT_POST, 'api');

// Ensure this is API call.
if ($api !== 'nl2go') {
	return;
}

$method = filter_input(INPUT_POST, 'method');

// Ensure that method is supported.
if ($method === null || array_search($method, Nl2goManager::$supportedMethods) === false) {
	Nl2goManager::sendError('METHODNOTFOUND');
}

// Check if user is authenticated.
Nl2goManager::checkAuthentication();

// Call method.
$apiManager = new Nl2goManager();
$result = $apiManager->$method();

// Returns JSON response
Nl2goManager::sendData($result);
