<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: mareike
 * Date: 02.06.2017
 * Time: 10:42
 */


const GOOGLE_INTEGRATION = '0B9iwLT_tdQIiOFYwMTkzV1hkalU';
const GROUP = 'it@newsletter2go.com';
const CONFLUENCE_SPACEKEY = 'IT';


$pluginPath = $argv[1];
$fullname = $argv[2];
$version = $argv[3];
$isPlugin = $argv[4];
$isConnector = $argv[5];

function getGoogleClient()
{
    $config = json_decode(file_get_contents(__DIR__.'/GoogleDrive-Credentials.json'), true);
    $client = new \Nl2GoUtilsClients\GoogleDrive\Client($config);
    return $client;
}


function getConfluenceClient()
{
    $config = parse_ini_file(__DIR__.'/../config.ini', true);
    $client = new Nl2GoUtilsClients\Confluence\Client($config["confluence"]["baseUrl"],$config["confluence"]["username"], $config["confluence"]["password"] );
    return $client;
}


function createGooglePlugin($fullname,$pluginPath,$version,$isPlugin, $isConnector){

    echo 'push Plugin to Google-Drive :'.PHP_EOL;

    $pluginContent = file_get_contents($pluginPath);
    $fileName = pathinfo($pluginPath, PATHINFO_BASENAME);

    $client = getGoogleClient();

    $folderIntegration = $client->getFolder($fullname, GOOGLE_INTEGRATION, GROUP);

    $folderPlugin = $client->getFolder('Plugins', $folderIntegration, GROUP);

    $fileId = $client->getFileId($fileName, $folderPlugin);

    if($fileId !== false){

        echo 'Plugin already exist :'.$fileName.PHP_EOL;

        $client->deleteFile($fileName, $folderPlugin);

        echo 'Plugin will be updated :'.$fileName.PHP_EOL;
    }

    $newFile = $client->createFile($pluginContent, $fileName, 'application/zip', $folderPlugin, GROUP);

    $newFilePath = $client->getWebLinkForFile($newFile->getId());

    UpdateConfluencePageContent($version,$newFilePath,$isPlugin, $isConnector,true);

    echo 'Newest Plugin Version pushed to google drive :'.$fileName.PHP_EOL;

    return $newFile;
};

function UpdateConfluencePageContent($version,$linkToPlugin,$isPlugin, $isConnector,$markup = false){

    $confluenceClient = getConfluenceClient();

    $versionName = $version;

    if($isPlugin == 'YES'){
        $versionName .=' [Plugin]';
    }
    if($isConnector == 'YES'){
        $versionName .=' [Connector]';
    }

    $pages = $confluenceClient->getPage(CONFLUENCE_SPACEKEY,$versionName);

    if(count($pages) > 0 ){
        $pageId = $pages[0]->getId();
        $version = $pages[0]->getTitle();
        $pageVersion = $pages[0]->getVersion() + 1;
        $pageContent = $pages[0]->getBody();
        $pageContent.=  ($markup ? '* <b><a href="'.$linkToPlugin.'">[ ': '* [').'Link to Plugin'.($markup ? ']</a></b>' : ']');

        $confluenceClient->UpdatePage($pageId,$pageContent,$version,$pageVersion);

        echo 'Add Pluginpath to Confluence Release Notes for:'.$version.PHP_EOL;

        return true;
    }else{
        echo 'No Plugin found';

        return false;
    }

}

createGooglePlugin($fullname,$pluginPath,$version,$isPlugin, $isConnector);
