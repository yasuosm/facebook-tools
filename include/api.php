<?php

defined('BASE_DS') or define('BASE_DS', DIRECTORY_SEPARATOR);
defined('BASE_DIR') or define('BASE_DIR', dirname(dirname(__FILE__)) . BASE_DS);

define('TOKEN_KEY', 'token');
define('TOKEN_KEY_HTTP', 'HTTP_TOKEN');

header('Access-Control-Allow-Headers: token, content-type');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Request-Method: GET, POST');

require_once BASE_DIR . 'ini.php';
require_once BASE_DIR . 'include' . BASE_DS . 'service.php';

/**
 * @var string
 */

// Params
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') 
{
    exit('{}');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
    if (empty($_POST)) 
    {
        $_POST = (array)json_decode(file_get_contents('php://input'), true);
    }
}

$aParams = array_merge($_GET, $_POST);

// Method
$sUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$sUri = trim($sUri, '/');

if ($pos = strpos($sUri, '.php')) 
{
    $sMethod = substr($sUri, $pos + 5);
}

// Access Token
global $sAccessToken;

if (isset($aData[TOKEN_KEY])) 
{
    $sAccessToken = $aData[TOKEN_KEY];
} 
else if (!$sAccessToken && isset($_SERVER[TOKEN_KEY_HTTP])) 
{
    $sAccessToken = $_SERVER[TOKEN_KEY_HTTP];
} 
else if (!$sAccessToken && function_exists('apache_request_headers')) 
{
    $headers = apache_request_headers();
    
    if (isset($headers[TOKEN_KEY])) 
    {
        $sAccessToken = $headers[TOKEN_KEY];
    }
    
    $key = strtolower(TOKEN_KEY);
    
    if (isset($headers[TOKEN_KEY])) 
    {
        $sAccessToken = $headers[$key];
    }
}

if (!$sAccessToken)
{
    $aRes = array(
        'error' => array(
            'message' => '(#1) Missing Acccess Token',
            'type' => 'OAuthException',
            'code' => 1,
        )
    );
}
else
{
    $oService = Service::getInstance();
    $oService->setAccessToken($sAccessToken);
    $aRes = $oService->{$sMethod}($aParams);
}

echo json_encode($aRes);
exit();
