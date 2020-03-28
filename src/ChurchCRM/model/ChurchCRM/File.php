<?php

namespace ChurchCRM;

use ChurchCRM\Base\File as BaseFile;
use ChurchCRM\dto\SystemURLs;
use DateTime;

class File extends BaseFile
{
    const CRM_FILE_HASH_ALGO = "sha256";

    public function preInsert(\Propel\Runtime\Connection\ConnectionInterface $con = null)
    {
        $this->setCreated(new DateTime());
        $this->setSize(mb_strlen($this->getContent()));
        parent::preSave($con);

        return true;
    }

    public function preSave(\Propel\Runtime\Connection\ConnectionInterface $con = null)
    {
        $this->setModified(new DateTime());
        $this->setSize(mb_strlen($this->getContent()));
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

    public function fromUpload($uploadedFilePath) {

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
}
