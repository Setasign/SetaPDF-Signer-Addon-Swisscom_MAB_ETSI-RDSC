<?php

declare(strict_types=1);

namespace setasign\SetaPDF\Signer\Module\SwisscomMabEtsiRdsc;

use SetaPDF_Core_Document;
use SetaPDF_Core_SecHandler_Exception;
use SetaPDF_Core_Type_Dictionary;
use SetaPDF_Core_Type_Exception;
use SetaPDF_Core_Type_Name;
use SetaPDF_Signer_Exception;

class Module implements \SetaPDF_Signer_Signature_DocumentInterface, \SetaPDF_Signer_Signature_DictionaryInterface
{
    /**
     * Updates the document security store by the last received revoke information.
     *
     * @param \SetaPDF_Core_Document $document
     * @param string $fieldName The signature field, that was signed.
     * @param array{ocsp: array, crl: string[]} $validationInfo
     * @throws \SetaPDF_Signer_Asn1_Exception
     */
    public static function updateDss(\SetaPDF_Core_Document $document, string $fieldName, array $validationInfo)
    {
        $ocsps = [];
        $certificates = [];
        $crls = [];

        if (isset($validationInfo['crl'])) {
            $crlEntries = $validationInfo['crl'];
            foreach ($crlEntries as $crlEntry) {
                $crls[] = \base64_decode($crlEntry);
            }
        }

        if (isset($validationInfo['ocsp'])) {
            $ocspEntries = $validationInfo['ocsp'];
            foreach ($ocspEntries as $ocspEntry) {
                $ocsp = new \SetaPDF_Signer_Ocsp_Response(\base64_decode($ocspEntry));
                // extract certificates from OCSP response
                foreach ($ocsp->getCertificates()->getAll() as $certificate) {
                    $certificates[] = $certificate;
                }
                $ocsps[] = $ocsp;
            }
        }

        $dss = new \SetaPDF_Signer_DocumentSecurityStore($document);
        $dss->addValidationRelatedInfoByFieldName($fieldName, $crls, $ocsps, $certificates);
    }

    /**
     * Updates the signature dictionary.
     *
     * PAdES requires special Filter and SubFilter entries in the signature dictionary.
     *
     * @param SetaPDF_Core_Type_Dictionary $dictionary
     * @throws SetaPDF_Signer_Exception
     */
    public function updateSignatureDictionary(SetaPDF_Core_Type_Dictionary $dictionary)
    {
        /* do some checks:
         * - entry with the key M in the Signature Dictionary
         */
        if (!$dictionary->offsetExists('M')) {
            throw new SetaPDF_Signer_Exception(
                'The key M (the time of signing) shall be present in the signature dictionary to conform with PAdES.'
            );
        }

        $dictionary['SubFilter'] = new SetaPDF_Core_Type_Name('ETSI.CAdES.detached', true);
        $dictionary['Filter'] = new SetaPDF_Core_Type_Name('Adobe.PPKLite', true);
    }

    /**
     * Updates the document instance.
     *
     * @param SetaPDF_Core_Document $document
     * @throws SetaPDF_Core_SecHandler_Exception
     * @throws SetaPDF_Core_Type_Exception
     * @see ETSI TS 102 778-3 V1.2.1 - 4.7 Extensions Dictionary
     * @see ETSI EN 319 142-1 V1.1.0 - 5.6 Extension dictionary
     */
    public function updateDocument(SetaPDF_Core_Document $document)
    {
        $extensions = $document->getCatalog()->getExtensions();
        $extensions->setExtension('ESIC', '1.7', 2);
    }
}
