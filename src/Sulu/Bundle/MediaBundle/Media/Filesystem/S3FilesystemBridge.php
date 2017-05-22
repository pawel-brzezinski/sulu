<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Media\Filesystem;

use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;

/**
 * Implementation of AWS S3 Filesystem bridge.
 */
class S3FilesystemBridge implements FilesystemBridgeInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * S3FilesystemBridge constructor.
     *
     * @param string $bucketName
     * @param string $bucketRegion
     * @param string $apiKey
     * @param string $apiSecret
     */
    public function __construct($bucketName, $bucketRegion, $apiKey, $apiSecret)
    {
        $s3Client = new S3Client([
            'credentials' => [
                'key' => $apiKey,
                'secret' => $apiSecret,
            ],
            'version' => 'latest',
            'region' => $bucketRegion,
        ]);

        $adapter = new AwsS3Adapter($s3Client, $bucketName, [], true);
        $this->filesystem = new Filesystem($adapter);

    }

    /**
     * @inheritdoc
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }
}