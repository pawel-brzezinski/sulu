<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Media\FormatCache;

use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyInvalidUrl;
use Sulu\Bundle\MediaBundle\Media\Exception\ImageProxyUrlNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;

/**
 * AWS S3 format cache implementation.
 */
class S3FormatCache implements FormatCacheInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $pathUrl;

    /**
     * @var int
     */
    protected $segments;

    /**
     * @var array
     */
    protected $formats;

    /**
     * S3FormatCache constructor.
     *
     * @param S3FilesystemBridge $filesystemBridge
     * @param string $path
     * @param string $pathUrl
     * @param int $segments
     * @param array $formats
     */
    public function __construct(S3FilesystemBridge $filesystemBridge, $path, $pathUrl, $segments, array $formats)
    {
        $this->filesystem = $filesystemBridge->getFilesystem();
        $this->path = $path;
        $this->pathUrl = $pathUrl;
        $this->segments = intval($segments);
        $this->formats = $formats;
    }

    /**
     * @inheritdoc
     */
    public function save($content, $id, $fileName, $options, $format)
    {
        $savePath = ltrim($this->getPath($this->path, $id, $fileName, $format), '/');

        try {
            $this->filesystem->write($savePath, $content);
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function purge($id, $fileName, $options)
    {
        foreach ($this->formats as $format) {
            $path = ltrim($this->getPath($this->path, $id, $fileName, $format['key']), '/');

            if ($this->filesystem->has($path)) {
                $this->filesystem->delete($path);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getMediaUrl($id, $fileName, $options, $format, $version, $subVersion)
    {
        $bucketUrl = ltrim($this->getPath($this->path, $id, $fileName, $format), '/');

        if ($this->filesystem->has($bucketUrl)) {
            $bucketFullUrl = $this->filesystem->getAdapter()->getUrl($bucketUrl);

            return $this->getBucketPathUrl($bucketFullUrl, $version, $subVersion);
        }

        return $this->getPathUrl($this->pathUrl, $id, $fileName, $format, $version, $subVersion);
    }

    /**
     * @inheritdoc
     */
    public function analyzedMediaUrl($url)
    {
        if (empty($url)) {
            throw new ImageProxyUrlNotFoundException('The given url was empty');
        }

        $id = $this->getIdFromUrl($url);
        $format = $this->getFormatFromUrl($url);

        return [$id, $format];
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $cacheDir = ltrim($this->path, '/');

        foreach ($this->filesystem->listKeys($cacheDir) as $file) {
            if ($this->filesystem->has($file)) {
                $this->filesystem->delete($file);
            }
        }
    }

    /**
     * @param string $prePath
     * @param int $id
     * @param string $fileName
     * @param string $format
     *
     * @return string
     */
    protected function getPath($prePath, $id, $fileName, $format)
    {
        $segment = $this->getSegment($id) . '/';
        $prePath = rtrim($prePath, '/');

        return $prePath . '/' . $format . '/' . $segment . $id . '-' . $fileName;
    }

    /**
     * @param $id
     *
     * @return string
     */
    protected function getSegment($id)
    {
        return sprintf('%0' . strlen($this->segments) . 'd', ($id % $this->segments));
    }

    /**
     * Return the id of by a given url.
     *
     * @param string $url
     *
     * @return int
     *
     * @throws ImageProxyInvalidUrl
     */
    protected function getIdFromUrl($url)
    {
        $fileName = basename($url);
        $idParts = explode('-', $fileName);

        if (count($idParts) < 2) {
            throw new ImageProxyInvalidUrl('No `id` was found in the url');
        }

        $id = $idParts[0];

        if (preg_match('/[^0-9]/', $id)) {
            throw new ImageProxyInvalidUrl('The founded `id` was not a valid integer');
        }

        return $id;
    }

    /**
     * Return the format by a given url.
     *
     * @param string $url
     *
     * @return string
     *
     * @throws ImageProxyInvalidUrl
     */
    protected function getFormatFromUrl($url)
    {
        $path = dirname($url);

        $formatParts = array_reverse(explode('/', $path));

        if (count($formatParts) < 2) {
            throw new ImageProxyInvalidUrl('No `format` was found in the url');
        }

        $format = $formatParts[1];

        return $format;
    }

    /**
     * @param string $bucketUrl
     * @param string $version
     * @param string $subVersion
     *
     * @return string
     */
    protected function getBucketPathUrl($bucketUrl, $version = '', $subVersion = '')
    {
        return $bucketUrl . '?v=' . $version . '-' . $subVersion;
    }

    /**
     * @param string $prePath
     * @param int $id
     * @param string $fileName
     * @param string $format
     * @param string $version
     * @param string $subVersion
     *
     * @return string
     */
    protected function getPathUrl($prePath, $id, $fileName, $format, $version = '', $subVersion = '')
    {
        $segment = $this->getSegment($id) . '/';
        $prePath = rtrim($prePath, '/');

        return str_replace(
            '{slug}',
            $format . '/' . $segment . $id . '-' . rawurlencode($fileName),
            $prePath
        ) . '?v=' . $version . '-' . $subVersion;
    }
}