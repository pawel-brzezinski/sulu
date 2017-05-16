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
use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Exception\FilenameAlreadyExistsException;
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
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct($bucketName, $uploadPath, $segments, DebugLoggerInterface $logger = null)
    {
        $this->bucketName = $bucketName;
        $this->uploadPath = $uploadPath;
        $this->segments = $segments;
        $this->logger = $logger ?: new NullLogger();

        $this->s3Client = new S3Client([
            'credentials' => [
                'key' => 'AKIAJ26BO7BK5RREOGCQ',
                'secret' => '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',
            ],
            'version' => 'latest',
            'region' => 'eu-central-1',
        ]);

        $adapter = new AwsS3Adapter($this->s3Client, $this->bucketName, [], true);
        $this->filesystem = new Filesystem($adapter);
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
        print_r('s3 load');
        exit;
    }

    /**
     * @inheritdoc
     */
    public function loadAsString($fileName, $version, $storageOption)
    {
        print_r('s3 load as string');
        exit;
    }

    /**
     * @inheritdoc
     */
    public function remove($storageOption)
    {
        print_r('s3 remove');
        exit;
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
     * @param $key
     * @param $value
     */
    private function addStorageOption($key, $value)
    {
        $this->storageOption->$key = $value;
    }
}