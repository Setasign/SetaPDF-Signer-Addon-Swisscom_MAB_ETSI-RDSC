<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\HttpFactory;
use Ramsey\Uuid\Uuid;
use setasign\SetaPDF\Signer\Module\SwisscomMabEtsiRdsc\Client as SwisscomClient;
use setasign\SetaPDF\Signer\Module\SwisscomMabEtsiRdsc\Module;

date_default_timezone_set('Europe/Berlin');
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');

// require the autoload class from Composer
require_once('../vendor/autoload.php');

if (!file_exists(__DIR__ . '/settings/settings.php')) {
    throw new RuntimeException('Missing settings/settings.php!');
}

$settings = require(__DIR__ . '/settings/settings.php');

$fileToSign = __DIR__ . '/Laboratory-Report.pdf';
$resultPath = 'signed.pdf';
$parRedirectUri = 'https://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . '?action=sign';

$guzzleOptions = [
    'handler' => new CurlHandler(),
    'http_errors' => false,
    'cert' => $settings['cert'],
    'ssl_key' => $settings['privateKey']
];

$httpClient = new Client($guzzleOptions);
$httpFactory = new HttpFactory();
$client = new SwisscomClient(
    $settings['clientId'],
    $settings['secret'],
    $httpClient,
    $httpFactory,
    $httpFactory
);
$credentialId = 'OnDemand-Advanced4.1-EU';
//$credentialId = 'OnDemand-Advanced4';
//$credentialId = 'OnDemand-Qualified4';

session_start();
if (isset($_GET['restart']) && $_GET['restart'] === '1') {
    $_GET['action'] = 'preview';
}

switch ($_GET['action'] ?? 'preview') {
    case 'preview':
        if (isset($_SESSION[__FILE__]['sessionTmpFile']) && file_exists($_SESSION[__FILE__]['sessionTmpFile'])) {
            unlink($_SESSION[__FILE__]['sessionTmpFile']);
        }
        unset($_SESSION[__FILE__]);
        echo '<iframe src="?action=previewDocument" style="width: 90%; height: 90%;"></iframe><br/><br/>'
            . '<div style="text-align: right;"><a href="?action=preSign" style="background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; border-radius: 8px;">Sign</a></div>';
        break;

    case 'previewDocument':
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($fileToSign, '.pdf') . '.pdf"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $data = file_get_contents($fileToSign);
        header('Content-Length: ' . strlen($data));
        echo $data;
        flush();
        exit();

    case 'preSign':
        // create the document instance
        $document = SetaPDF_Core_Document::loadByFilename($fileToSign);

        $signer = new SetaPDF_Signer($document);

        $signer->setSignatureContentLength(35000);
        $module = new Module();
        // we need a path for a temporary file which needs to be hold during the whole process
        // in a real world scenario you should use a static path under your control
        $sessionTempFile = SetaPDF_Core_Writer_TempFile::createTempPath();
        $tmpDocument = $signer->preSign(
            new SetaPDF_Core_Writer_File($sessionTempFile),
            $module
        );
        $hashAlgorithm = SetaPDF_Signer_Digest::SHA_256;
        $hashData = base64_encode(\hash_file($hashAlgorithm, $tmpDocument->getHashFile()->getPath(), true));
        $hashAlgorithmOID = SetaPDF_Signer_Digest::$oids[$hashAlgorithm];

        $state = (string) Uuid::uuid4();
        $nonce = (string) Uuid::uuid4();
        $url = $client->parRequest(
            $state,
            $nonce,
            $parRedirectUri,
            [
                'credentialID' => $credentialId,
                'documentDigests' => [
                    ['hash' => $hashData, 'label' => basename($fileToSign)],
                ],
                'hashAlgorithmOID' => $hashAlgorithmOID
            ],
            [
//                'login_hint' => [
//                    'namespace' => 'msisdn',
//                    'identifier' => '+49123456789'
//                ]
            ]
        );

        $_SESSION[__FILE__] = [
            'state' => $state,
            'nonce' => $nonce,
            'sessionTmpFile' => $sessionTempFile,
            'tmpDocument' => $tmpDocument,
            'module' => $module,
            'hashData' => $hashData,
            'hashAlgorithmOID' => $hashAlgorithmOID
        ];

        header('Location: ' . $url);
        exit();

    case 'sign':
        if (isset($_GET['state'], $_GET['error'], $_GET['error_description'])) {
            echo 'Error! ' . htmlspecialchars($_GET['error_description']);
            echo '<br/><hr/>If you want to restart the signature process click here: <a href="?restart=1">Restart</a>';
            exit();
        }

        if (!isset($_SESSION[__FILE__])) {
            echo 'Error: Cannot find existing session!';
            echo '<br/><hr/>If you want to restart the signature process click here: <a href="?restart=1">Restart</a>';
            exit();
        }
        if ($_GET['state'] !== $_SESSION[__FILE__]['state']) {
            echo 'Error: State doesn\'t match with session ' . $_SESSION[__FILE__]['state'];
            echo '<br/><hr/>If you want to restart the signature process click here: <a href="?restart=1">Restart</a>';
            exit();
        }

        $hashData = $_SESSION[__FILE__]['hashData'];
        $hashAlgorithmOID = $_SESSION[__FILE__]['hashAlgorithmOID'];

        /**
         * @var SetaPDF_Signer_TmpDocument $tmpDocument
         * @var Module $module
         */
        $tmpDocument = $_SESSION[__FILE__]['tmpDocument'];
        $module = $_SESSION[__FILE__]['module'];

        $token = $client->generateToken($_GET['code']);

        $requestId = (string) Uuid::uuid4();
        $responseData = $client->sign($token['access_token'], $requestId, [
            'hashAlgorithmOID' => $hashAlgorithmOID,
            'hashes' => [$hashData]
        ], $credentialId);
        $signatureValue = (string) \base64_decode($responseData['SignatureObject'][0]);

        $reader = new SetaPDF_Core_Reader_File($fileToSign);
        $tmpWriter = new SetaPDF_Core_Writer_TempFile();

        $document = SetaPDF_Core_Document::load($reader, $tmpWriter);
        $signer = new SetaPDF_Signer($document);

        $field = $signer->getSignatureField();
        $fieldName = $field->getQualifiedName();
        $signer->setSignatureFieldName($fieldName);

        // save the signature to the temporary document
        $signer->saveSignature($tmpDocument, $signatureValue);
        $document->finish();

        $writer = new SetaPDF_Core_Writer_String();
        $document = \SetaPDF_Core_Document::loadByFilename($tmpWriter->getPath(), $writer);

        Module::updateDss($document, $fieldName, $responseData['validationInfo']);

        // save and finish the final document
        $document->save()->finish();

        $_SESSION[__FILE__]['result'] = $writer->getBuffer();

        echo 'The file was successfully signed. You can <a href="?action=downloadSigned" download="signed.pdf" target="_blank">download the result here</a>.<hr/>'
            . ' If you want to restart the signature process click here: <a href="?reset=1">Restart</a>';
        exit();

    case 'downloadSigned':
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="signed.pdf"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $data = $_SESSION[__FILE__]['result'];
        header('Content-Length: ' . strlen($data));
        echo $data;
        flush();
        exit();

    default:
        echo 'Unknown action';
        exit();
}