<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Tests\Unit\Media\Storage;

use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Exception\FilenameAlreadyExistsException;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyMediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;
use Sulu\Bundle\MediaBundle\Media\Storage\S3Storage;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class S3FilesystemBridgeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test save new file
     */
    public function testSaveNewFile()
    {
        $mediaPath = 'sulu/uploads/media/1/photo.jpeg';

        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'write'])
            ->getMock();
        $fsMock->expects($this->exactly(2))
            ->method('has')
            ->with($mediaPath)
            ->willReturn(false);
        $fsMock->expects($this->once())
            ->method('write')
            ->with($mediaPath, file_get_contents($this->getImagePath()))
            ->willReturn(1000);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);

        $storage = $this->getS3StorageInstance($bridgeMock);
        $result = $storage->save($this->getImagePath(), 'photo.jpeg', 1);

        $this->assertEquals(json_encode(['segment' => '1', 'fileName' => 'photo.jpeg']), $result);
    }

    /**
     * Test save existing file
     */
    public function testSaveExistingFile()
    {
        $mediaPath = 'sulu/uploads/media/1/photo.jpeg';
        $storageOption = json_encode(['segment' => '1', 'fileName' => 'photo.jpeg']);

        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'write'])
            ->getMock();
        $fsMock->expects($this->exactly(2))
            ->method('has')
            ->with($mediaPath)
            ->willReturn(false);
        $fsMock->expects($this->once())
            ->method('write')
            ->with($mediaPath, file_get_contents($this->getImagePath()))
            ->willReturn(1000);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);

        $storage = $this->getS3StorageInstance($bridgeMock);
        $result = $storage->save($this->getImagePath(), 'photo.jpeg', 1, $storageOption);

        $this->assertEquals(json_encode(['segment' => '1', 'fileName' => 'photo.jpeg']), $result);
    }

    /**
     * Test save file with existing filename in storage
     */
    public function testSaveWithExistingFileNameExist()
    {
        $mediaPath = 'sulu/uploads/media/1/photo.jpeg';
        $expectedPath = 'sulu/uploads/media/1/photo-1.jpeg';

        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has'])
            ->getMock();
        $fsMock->expects($this->at(0))
            ->method('has')
            ->with($mediaPath)
            ->willReturn(true);
        $fsMock->expects($this->at(2))
            ->method('has')
            ->with($expectedPath)
            ->willReturn(true);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);

        $storage = $this->getS3StorageInstance($bridgeMock);

        $this->setExpectedException(FilenameAlreadyExistsException::class);
        $storage->save($this->getImagePath(), 'photo.jpeg', 1);
    }

    public function testLoad()
    {
        $mediaPath = 'sulu/uploads/media/1/photo.jpeg';

        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAdapter'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('getAdapter')
            ->willReturn($this->getS3Adapter());
        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $storage = $this->getS3StorageInstance($bridgeMock);

        // Test correct storage option
        $storageOption = json_encode(['segment' => '1', 'fileName' => 'photo.jpeg']);
        $result = $storage->load($mediaPath, 1, $storageOption);
        $this->assertEquals(
            'https://s3.eu-central-1.amazonaws.com/sulu_bucket/sulu/uploads/media/1/photo.jpeg',
            $result
        );

        // Test wrong storage option
        $storageOption = json_encode(['fileName' => 'photo.jpeg']);
        $result = $storage->load($mediaPath, 1, $storageOption);
        $this->assertFalse($result);
    }

    public function testLoadAsStringWithCorrectStorageOption()
    {
        $mediaPath = 'sulu/uploads/media/1/photo.jpeg';
        $storageOption = json_encode(['segment' => '1', 'fileName' => 'photo.jpeg']);

        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'read'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('has')
            ->with($mediaPath)
            ->willReturn(true);
        $fsMock->expects($this->once())
            ->method('read')
            ->with($mediaPath)
            ->willReturn(file_get_contents($this->getImagePath()));

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $storage = $this->getS3StorageInstance($bridgeMock);
        $result = $storage->loadAsString('photo.jpeg', 1, $storageOption);

        $this->assertEquals(file_get_contents($this->getImagePath()), $result);
    }

    public function testLoadAsStringWithWrongStorageOption()
    {
        $storageOption = json_encode(['segment' => '1']);
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $storage = $this->getS3StorageInstance($bridgeMock);

        $this->setExpectedException(ImageProxyMediaNotFoundException::class);
        $storage->loadAsString('photo.jpeg', 1, $storageOption);
    }

    /**
     * @param \PHPUnit_Framework_MockObject_MockObject $fsMock
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getS3FilesystemBridgeMock(\PHPUnit_Framework_MockObject_MockObject $fsMock)
    {
        $bridgeMock = $this->getMockBuilder(S3FilesystemBridge::class)
            ->disableOriginalConstructor()
            ->setMethods(['getFilesystem'])
            ->getMock();
        $bridgeMock->expects($this->any())
            ->method('getFilesystem')
            ->willReturn($fsMock);

        return $bridgeMock;
    }

    /**
     * @param S3FilesystemBridge $bridgeMock
     * @return S3Storage
     */
    private function getS3StorageInstance(S3FilesystemBridge $bridgeMock)
    {
        $logger = $this->getMockBuilder(DebugLoggerInterface::class)->getMock();

        return new S3Storage($bridgeMock, '/sulu/uploads/media', 1, $logger);
    }

    /**
     * @return AwsS3Adapter
     */
    private function getS3Adapter()
    {
        $s3Client = new S3Client([
            'credentials' => [
                'key' => 'apiKey',
                'secret' => 'apiSecret',
            ],
            'version' => 'latest',
            'region' => 'eu-central-1',
        ]);

        return new AwsS3Adapter($s3Client, 'sulu_bucket');
    }

    /**
     * @return string
     */
    private function getImagePath()
    {
        return __DIR__ . '/../../../app/Resources/images/photo.jpeg';
    }
}
