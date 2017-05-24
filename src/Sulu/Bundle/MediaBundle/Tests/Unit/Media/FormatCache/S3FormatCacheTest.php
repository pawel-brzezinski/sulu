<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Tests\Unit\Media\FormatCache;

use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;
use Sulu\Bundle\MediaBundle\Media\FormatCache\S3FormatCache;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class S3FormatCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $formats = [];

    /**
     * (@inheritdoc)
     */
    public function setUp()
    {
        $this->formats = [
            '640x480' => [
                'key' => '640x480',
                'meta' => [
                    'title' => [
                        'en' => 'My image format for testing',
                        'de' => 'Mein Bildformat zum Testen',
                    ],
                ],
                'scale' => [
                    'x' => 640,
                    'y' => 480,
                    'mode' => 'outbound',
                ],
                'transformations' => [],
                'options' => [
                    'jpeg_quality' => 70,
                    'png_compression_level' => 6,
                ],
            ],
            '50x50' => [
                'key' => '50x50',
                'meta' => [
                    'title' => [],
                ],
                'scale' => [
                    'x' => 640,
                    'y' => 480,
                    'mode' => 'outbound',
                ],
                'transformations' => [],
                'options' => [
                    'jpeg_quality' => 70,
                    'png_compression_level' => 6,
                ],
            ],
        ];
    }

    /**
     * Test save without exception
     */
    public function testSaveWithoutException()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['write'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('write')
            ->willReturn(1000);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $result = $formatCache->save(file_get_contents($this->getImagePath()), 1, 'photo.jpeg', [], '640x480');

        $this->assertTrue($result);

//        /sulu/web/uploads/media/640x480/0/1-photo.jpeg
    }

    /**
     * Test save with exception
     */
    public function testSaveWithException()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['write'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('write')
            ->will($this->throwException(new \Exception()));

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $result = $formatCache->save(file_get_contents($this->getImagePath()), 1, 'photo.jpeg', [], '640x480');

        $this->assertFalse($result);
    }

    /**
     * Test purge formats
     */
    public function testPurge()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'delete'])
            ->getMock();
        $fsMock->expects($this->exactly(2))
            ->method('has')
            ->willReturn(true);
        $fsMock->expects($this->exactly(2))
            ->method('delete')
            ->willReturn(true);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $this->assertTrue($formatCache->purge(1, 'photo.jpeg', []));
    }

    /**
     * Test get media format
     */
    public function testCachedGetMediaUrl()
    {

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
     * @return S3FormatCache
     */
    private function getS3FormatCacheInstance(S3FilesystemBridge $bridgeMock)
    {
        $logger = $this->getMockBuilder(DebugLoggerInterface::class)->getMock();

        return new S3FormatCache(
            $bridgeMock,
            '/sulu/web/uploads/media',
            '/uploads/media/{slug}',
            1,
            $this->formats
        );
    }

    /**
     * @return string
     */
    private function getImagePath()
    {
        return __DIR__ . '/../../../app/Resources/images/photo.jpeg';
    }
}