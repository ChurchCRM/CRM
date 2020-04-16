<?php

namespace ChurchCRM;

use ChurchCRM\Base\File as BaseFile;
use ChurchCRM\dto\SystemURLs;
use DateTime;
use Slim\Http\Response;
use Slim\Http\Request;
use Slim\Http\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

class File extends BaseFile
{
    const CRM_FILE_HASH_ALGO = "sha256";

    public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
    {
        $this->setCreated(new DateTime());
        $this->setSize(filesize($this->getFilesystemPath()));
        parent::preSave($con);

        return true;
    }

    public function preSave(\Propel\Runtime\Connection\ConnectionInterface $con = null)
    {
        $this->setModified(new DateTime());
        $this->setSize(filesize($this->getFilesystemPath()));
        parent::preUpdate($con);

        return true;
    }

    public function setContent($content){
        $hash = hash(File::CRM_FILE_HASH_ALGO,$content);
        $this->setHash($hash);
        $targetFolder = $this->getFilesystemPath();
        try {
            FileSystemUtils::ensureDir(dirname($targetFolder));
            file_put_contents($targetFolder,$content);
        }
        catch(\Exception $e) {
            echo $e;
        }
        $this->save();
    }

    public static function fromSlimRequest(Request $request) {
        $uploadedFiles = $request->getUploadedFiles();
        $CRMFiles = array();
        foreach ($uploadedFiles as $uploadedFile) {
            $file = File::fromTempUpload($uploadedFile);
            array_push($CRMFiles, $file);
        }
        return $CRMFiles;
    }

    private static function fromTempUpload(UploadedFile $uploadedFile) {

        $file = new File();
        $fileHash = hash_file(File::CRM_FILE_HASH_ALGO,$uploadedFile->file);
        $file->setHash($fileHash);
        $file->setFileName($uploadedFile->getClientFilename());
        if (is_file($file->getFilesystemPath()))
        {
            throw new \Exception("This file already exists");
        }
        FileSystemUtils::ensureDir(dirname($file->getFilesystemPath()));
        //echo "Would upload to " . $file->getFilesystemPath();
        rename($uploadedFile->file,$file->getFilesystemPath());
        $file->save();
        return $file;
    }

    public function getContent(){
        $contents = file_get_contents($this->getFilesystemPath());
        if (hash(File::CRM_FILE_HASH_ALGO,$contents) == $this->getHash())
        {
            return $contents;
        }
        else {
            throw new \Exception("File hash does not match contents");
        }
    }

    private function getFilesystemPath() {
        $hash = $this->getHash();
        return SystemURLs::getUserDataRoot().DIRECTORY_SEPARATOR.substr($hash,0,1).DIRECTORY_SEPARATOR.substr($hash,1,1).DIRECTORY_SEPARATOR.$hash;
    }

    public function serveRequest(Response $response) {
        return $response
            ->write($this->getContent())
            ->withHeader('Content-type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename='.$this->getFileName())
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Expires','0')
            ->withHeader('Cache-Contro', 'must-revalidate, post-check=0, pre-check=0');
    }
}
