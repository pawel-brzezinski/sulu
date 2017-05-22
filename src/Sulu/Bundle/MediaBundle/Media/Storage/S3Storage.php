<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Media\Storage;

use stdClass;
use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Exception\FilenameAlreadyExistsException;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyMediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Log\NullLogger;

class S3Storage implements StorageInterface
{
    /**
     * @var string
     */
    private $storageOption = null;

    /**
     * @var string
     */
    private $bucketName;

    /**
     * @var string
     */
    private $uploadPath;

    /**
     * @var int
     */
    private $segments;

    /**
     * @var DebugLoggerInterface
     */
    protected $logger;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        S3FilesystemBridge $filesystemBridge,
        $uploadPath,
        $segments,
        DebugLoggerInterface $logger = null
    ){
        $this->filesystem = $filesystemBridge->getFilesystem();
        $this->uploadPath = $uploadPath;
        $this->segments = $segments;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @inheritdoc
     */
    public function save($tempPath, $fileName, $version, $storageOption = null)
    {
        $this->storageOption = new stdClass();

        if ($storageOption) {
            $oldStorageOption = json_decode($storageOption);
            $segment = $oldStorageOption->segment;
        } else {
            $segment = sprintf('%0' . strlen($this->segments) . 'd', rand(1, $this->segments));
        }

        $segmentPath = $this->uploadPath . '/' . $segment;
        $fileName = $this->getUniqueFileName($segmentPath, $fileName);
        $filePath = $this->getPathByFolderAndFileName($segmentPath, $fileName);

        $this->logger->debug('Try to write File "' . $tempPath . '" to "' . $this->bucketName . '" S3 bucket in path"' . $filePath . '"');

        if ($this->filesystem->has($filePath)) {
            throw new FilenameAlreadyExistsException($filePath);
        }


        $this->filesystem->write($filePath, file_get_contents($tempPath));

        $this->addStorageOption('segment', $segment);
        $this->addStorageOption('fileName', $fileName);

        return json_encode($this->storageOption);
    }

    /**
     * @inheritdoc
     */
    public function load($fileName, $version, $storageOption)
    {
        $path = $this->getPath($fileName, $storageOption);

        if (!$path) {
            return false;
        }

        return $this->filesystem->getAdapter()->getUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function loadAsString($fileName, $version, $storageOption)
    {
        $path = $this->getPath($fileName, $storageOption);

        if (!$path || !$this->filesystem->has($path)) {
            throw new ImageProxyMediaNotFoundException(sprintf('Original media at path "%s" not found', $path));
        }

        return $this->filesystem->read($path);
    }

    /**
     * @inheritdoc
     */
    public function remove($storageOption)
    {
        $this->storageOption = json_decode($storageOption);

        $segment = $this->getStorageOption('segment');
        $fileName = $this->getStorageOption('fileName');

        if (!$segment || !$fileName) {
            return false;
        }

        $path = $this->getPathByFolderAndFileName($this->uploadPath . '/' . $segment, $fileName);

        try {
            if ($this->filesystem->has($path)) {
                $this->filesystem->delete($path);
            }
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * get a unique filename in path.
     *
     * @param $folder
     * @param $fileName
     * @param int $counter
     *
     * @return string
     */
    private function getUniqueFileName($folder, $fileName, $counter = 0)
    {
        $newFileName = $fileName;

        if ($counter > 0) {
            $fileNameParts = explode('.', $fileName, 2);
            $newFileName = $fileNameParts[0] . '-' . $counter;

            if (isset($fileNameParts[1])) {
                $newFileName .= '.' . $fileNameParts[1];
            }
        }

        $filePath = $this->getPathByFolderAndFileName($folder, $newFileName);

        $this->logger->debug('Check FilePath: ' . $filePath);

        if (!$this->filesystem->has($filePath)) {
            return $newFileName;
        }

        ++$counter;

        return $this->getUniqueFileName($folder, $fileName, $counter);
    }

    /**
     * @param $folder
     * @param $fileName
     *
     * @return string
     */
    private function getPathByFolderAndFileName($folder, $fileName)
    {
        return ltrim(rtrim($folder, '/'), '/') . '/' . ltrim($fileName, '/');
    }

    /**
     * @param string $fileName
     * @param string $storageOption
     * @return bool|string
     */
    private function getPath($fileName, $storageOption)
    {
        $this->storageOption = json_decode($storageOption);

        $segment = $this->getStorageOption('segment');
        $fileName = $this->getStorageOption('fileName');

        if (!$segment || !$fileName) {
            return false;
        }

        $segmentPath = $this->uploadPath . '/' . $segment;

        return $this->getPathByFolderAndFileName($segmentPath, $fileName);
    }

    /**
     * @param $key
     * @param $value
     */
    private function addStorageOption($key, $value)
    {
        $this->storageOption->$key = $value;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    private function getStorageOption($key)
    {
        return isset($this->storageOption->$key) ? $this->storageOption->$key : null;
    }
}