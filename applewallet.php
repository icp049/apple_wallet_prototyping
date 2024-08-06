<?php

require 'vendor/autoload.php'; // Include necessary libraries

use ZipArchive;

class AppleWalletPass {
    private $passData;
    private $certPath;
    private $keyPath;
    private $wwdrCertPath;
    private $tempDir;

    public function __construct($passData, $certPath, $keyPath, $wwdrCertPath) {
        $this->passData = $passData;
        $this->certPath = $certPath;
        $this->keyPath = $keyPath;
        $this->wwdrCertPath = $wwdrCertPath;
        $this->tempDir = sys_get_temp_dir() . '/' . uniqid('pass_', true);
        mkdir($this->tempDir);
    }

    private function createPassJson() {
        file_put_contents($this->tempDir . '/pass.json', json_encode($this->passData, JSON_PRETTY_PRINT));
    }

    private function createManifest() {
        $manifest = [];
        foreach (new DirectoryIterator($this->tempDir) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile()) continue;
            $manifest[$fileInfo->getFilename()] = hash_file('sha1', $fileInfo->getPathname());
        }
        file_put_contents($this->tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function createSignature() {
        $zip = new ZipArchive();
        $pkpassFile = $this->tempDir . '/pass.pkpass';
        $zip->open($pkpassFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (new DirectoryIterator($this->tempDir) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile()) continue;
            $zip->addFile($fileInfo->getPathname(), $fileInfo->getFilename());
        }
        $zip->close();

        $cert = file_get_contents($this->certPath);
        $key = file_get_contents($this->keyPath);
        $wwdrCert = file_get_contents($this->wwdrCertPath);

        openssl_pkcs7_sign($pkpassFile, $pkpassFile . '.signed', $cert, $key, [], $wwdrCert);
    }

    public function generatePass() {
        $this->createPassJson();
        $this->createManifest();
        $this->createSignature();

        return file_get_contents($this->tempDir . '/pass.pkpass.signed');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    $passData = [
        "formatVersion" => 1,
        "passTypeIdentifier" => "pass.com.yourcompany.yourpass",
        "serialNumber" => "123456",
        "teamIdentifier" => "YOUR_TEAM_ID",
        "organizationName" => "Regina Public Library",
        "description" => "Library Membership Card",
        "logoText" => "Regina Public Library",
        "backgroundColor" => "#7742d3",
        "barcode" => [
            "message" => "29085006805780",
            "format" => "PKBarcodeFormatQR",
            "altText" => "Your barcode"
        ],
        "storeCard" => [
            "primaryFields" => [
                [
                    "key" => "name",
                    "label" => "Name",
                    "value" => "Ian Pedeglorio"
                ]
            ],
            "secondaryFields" => [
                [
                    "key" => "accountNumber",
                    "label" => "Account Number",
                    "value" => "29085006805780"
                ]
            ]
        ]
    ];

    $certPath = 'path/to/your/Certificates.p12';  // Update with the path to your Pass Type ID certificate
    $keyPath = 'path/to/your/PrivateKey.pem';     // Update with the path to your private key
    $wwdrCertPath = 'path/to/AppleWWDRCA.pem';   // Update with the path to Apple WWDR certificate

    $appleWalletPass = new AppleWalletPass($passData, $certPath, $keyPath, $wwdrCertPath);
    echo $appleWalletPass->generatePass();
}
?>
