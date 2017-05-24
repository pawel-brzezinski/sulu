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

use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3 as AwsS3Adapter;
use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyInvalidUrl;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyUrlNotFoundException;
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
    }

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

    public function testGetMediaUrlAlreadyCached()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has', 'getAdapter'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('has')
            ->willReturn(true);
        $fsMock->expects($this->once())
            ->method('getAdapter')
            ->willReturn($this->getS3Adapter());

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $expected = $this->getS3Adapter()->getUrl('sulu/web/uploads/media/640x480/0/1-photo.jpeg') . '?v=1-0';
        $result = $formatCache->getMediaUrl(1, 'photo.jpeg', [], '640x480', 1, 0);

        $this->assertEquals($expected, $result);
    }

    public function testGetMediaUrlNotCached()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['has'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('has')
            ->willReturn(false);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $expected = '/uploads/media/640x480/0/1-photo.jpeg?v=1-0';
        $result = $formatCache->getMediaUrl(1, 'photo.jpeg', [], '640x480', 1, 0);

        $this->assertEquals($expected, $result);
    }

    public function testAnalyzedMediaUrl()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $result = $formatCache->analyzedMediaUrl('/uploads/media/640x480/0/1-photo.jpeg');

        $this->assertEquals([1, '640x480'], $result);
    }

    public function testAnalyzedMediaUrlWithEmptyUrl()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $this->setExpectedException(ImageProxyUrlNotFoundException::class);
        $formatCache->analyzedMediaUrl(null);
    }

    public function testAnalyzedMediaUrlWithNoId()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $this->setExpectedException(ImageProxyInvalidUrl::class);
        $formatCache->analyzedMediaUrl('/uploads/media/640x480/0/photo.jpeg');
    }

    public function testAnalyzedMediaUrlWithWrongId()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $this->setExpectedException(ImageProxyInvalidUrl::class);
        $formatCache->analyzedMediaUrl('/uploads/media/640x480/0/foo-photo.jpeg');
    }

    public function testAnalyzedMediaUrlWithWrongFormat()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $this->setExpectedException(ImageProxyInvalidUrl::class);
        $formatCache->analyzedMediaUrl('1-photo.jpeg');
    }

    public function testClear()
    {
        $fsMock = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->setMethods(['listKeys', 'has', 'delete'])
            ->getMock();
        $fsMock->expects($this->once())
            ->method('listKeys')
            ->willReturn([
                'sulu/web/uploads/media/640x480/0/1-photo.jpeg',
                'sulu/web/uploads/media/640x480/0/2-image.jpeg',
            ]);
        $fsMock->expects($this->exactly(2))
            ->method('has')
            ->willReturn(true);
        $fsMock->expects($this->exactly(2))
            ->method('delete')
            ->willReturn(true);

        $bridgeMock = $this->getS3FilesystemBridgeMock($fsMock);
        $formatCache = $this->getS3FormatCacheInstance($bridgeMock);

        $formatCache->clear();
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