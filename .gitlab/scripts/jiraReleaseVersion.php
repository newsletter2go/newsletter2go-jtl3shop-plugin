<?php

require __DIR__ . '/../vendor/autoload.php';


const JIRA_INTEGRATION = '22282279';
const JIRA_PROJECT_KEY = 'SI';
const CONFLUENCE_SPACEKEY = 'IT';
const GOOGLE_INTEGRATION = '0B9iwLT_tdQIiOFYwMTkzV1hkalU';
const GROUP = 'it@newsletter2go.com';
const LABEL = 'integration';
const LABEL_PREFIX = 'global';

/**
 * Created by PhpStorm.
 * User: Michael Moritz
 * Date: 15.05.2017
 * Time: 14:44
 */

$version = $argv[1];
$isPlugin = $argv[2];
$isConnector = $argv[3];
$fullname = $argv[4];
$abbreviation = $argv[5];


function getJiraClient(){
    $config = parse_ini_file(__DIR__.'/../config.ini', true);
    $client = new Nl2GoUtilsClients\Jira\Client($config["jira"]);
    return $client;
}

function getConfluenceClient(){
    $config = parse_ini_file(__DIR__.'/../config.ini', true);
    $client = new Nl2GoUtilsClients\Confluence\Client($config["confluence"]["baseUrl"],$config["confluence"]["username"], $config["confluence"]["password"] );
    return $client;
}

function getGoogleClient(){
    $config = json_decode(file_get_contents(__DIR__.'/GoogleDrive-Credentials.json'), true);
    $client = new \Nl2GoUtilsClients\GoogleDrive\Client($config);
    return $client;
}

//check if Jira Tickets with version_number are still open
function getOpenJiraTickets($version){
    echo "check for open tickets..".PHP_EOL;
    $jql = 'project='.JIRA_PROJECT_KEY.' AND status NOT IN ("Resolved", "Closed") AND fixVersion='.$version;
    $client = getJiraClient();
    $issues = $client->search($jql);

    if(count($issues)> 0){
        echo "there exists tickets, which are not setted to state 'checked':".PHP_EOL;
        foreach($issues as $issue){
                echo $issue->getKey().PHP_EOL;
        }
        _exit(403);
    }

}

// transist jira tickets with that version to deployed
function transistJiraTickets($version){
    $client = getJiraClient();
    echo "set tickets to deployed.." . PHP_EOL;
    $jql = 'project='.JIRA_PROJECT_KEY.' AND status IN ("Resolved") AND fixVersion=' . $version;
    $issues = $client->search($jql);

    foreach ($issues as $issue) {
        if($issue->getStatus()->name == "Resolved") {
            $issueKey = $issue->getKey();
            $transitionId = 111; // closed
            $comment = "version is released via auto-deploy";
            $client->transistIssue($issueKey, $transitionId, $comment);
            echo "transformed successfully:".$issueKey. PHP_EOL;
        }
        else{
            echo "found ticket:" . $issue->getKey(). PHP_EOL;
        }
    }
}

//update release Version for jira tickets

function releaseJiraVersion($version, $isPlugin, $isConnector){
    $client = getJiraClient();
    echo "release jira tickets.." . PHP_EOL;
    $releaseComment = '';
    if($isPlugin == 'YES'){
        $releaseComment .='[Plugin]';
    }
    if($isConnector == 'YES'){
        $releaseComment .='[Connector]';
    }
    $releasedVersion = $client->releaseVersion($version, JIRA_PROJECT_KEY, $releaseComment);
    echo "released jira tickets.." . PHP_EOL;

    return $releasedVersion;
}


//create notes for deployed jira tickets
function createReleaseNotes($version){
    $client = getJiraClient();
    $notes = $client->CreateReleaseNotes($version,JIRA_PROJECT_KEY, true);

    return $notes;
}

//create releasenotes in Cofluence for deloyed tickets
function createConfluenceNotes($versionName, $integrationAbbreviation, $fullname, $isPlugin, $isConnector, $content){
    $confluenceClient = getConfluenceClient();
    echo 'create release notes in Confluence..'.PHP_EOL;
    $integration = $integrationAbbreviation.'-'.$fullname;
    $pages = $confluenceClient->getPage(CONFLUENCE_SPACEKEY,$integration);
    $releasedPage= null;

    if(count($pages) > 0 ){
        $parentId = $pages[0]->getId();
        $cql = 'space='.CONFLUENCE_SPACEKEY.' AND title~"'.$versionName.'%" AND ancestor ='.$parentId;
        $res = $confluenceClient->search($cql);

        if(count($res) > 0){
            $releasedPage = $res[0];
        }

    }else {
        echo 'Parent not found, create '.$integration.PHP_EOL;
        $parent = $confluenceClient->CreatePage(CONFLUENCE_SPACEKEY, $integration, '', JIRA_INTEGRATION);
        $parentId = $parent->getId();
        echo 'Parent created'.PHP_EOL;
    }

    if($isPlugin == 'YES'){
        $versionName .=' [Plugin]';
    }
    if($isConnector == 'YES'){
        $versionName .=' [Connector]';
    }

    if($releasedPage== null){
        echo 'New Page Created:'.$versionName.PHP_EOL;
        $page = $confluenceClient->CreatePage(CONFLUENCE_SPACEKEY,$versionName,$content, $parentId);
        $pageId = $page->getId();
        $confluenceClient->addLabel($pageId,LABEL_PREFIX,LABEL);
        echo 'Created Confluence Release Notes for:'.$versionName.PHP_EOL;

        return $page;

    }else{
        echo 'Updated Page:'.$versionName.PHP_EOL;
        $pageId = $releasedPage->getId();
        $version = $releasedPage->getVersion() + 1;
        $page = $confluenceClient->UpdatePage($pageId,$content, $versionName,$version);
        echo 'Updated Confluence Release Notes for:'.$versionName.PHP_EOL;

        return $page;
    }
}

function _exit($exitCode = -1)
{
    exit($exitCode);
}


echo 'Start Releaseprocess for :'.$version.PHP_EOL;
getOpenJiraTickets($version);
transistJiraTickets($version);
releaseJiraVersion($version, $isPlugin, $isConnector);
$content = createReleaseNotes($version);
createConfluenceNotes($version, $abbreviation, $fullname, $isPlugin, $isConnector, $content);
