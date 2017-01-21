<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;

class AppIntegrityService
{
  public static function verifyApplicationIntegrity()
    {
        $signatureFile = SystemURLs::getDocumentRoot() . '/signatures.json';
        $signatureFailures = [];
        if (file_exists($signatureFile)) {
            $signatureData = json_decode(file_get_contents($signatureFile));
            if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) == $signatureData->sha1) {
                foreach ($signatureData->files as $file) {
                    $currentFile = SystemURLs::getDocumentRoot() . '/' . $file->filename;
                    if (file_exists($currentFile)) {
                        $actualHash = sha1_file($currentFile);
                        if ($actualHash != $file->sha1) {
                            array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'Hash Mismatch', 'expectedhash' => $file->sha1, 'actualhash' => $actualHash]);
                        }
                    } else {
                        array_push($signatureFailures, ['filename' => $file->filename, 'status' => 'File Missing']);
                    }
                }
            } else {
                return ['status' => 'failure', 'message' => gettext('Signature definition file signature failed validation')];
            }
        } else {
            return ['status' => 'failure', 'message' => gettext('Signature definition File Missing')];
        }

        if (count($signatureFailures) > 0) {
            return ['status' => 'failure', 'message' => gettext('One or more files failed signature validation'), 'files' => $signatureFailures];
        } else {
            return ['status' => 'success'];
        }
    }
    

}

?>