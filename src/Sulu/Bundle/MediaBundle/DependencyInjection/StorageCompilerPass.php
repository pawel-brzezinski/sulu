<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\DependencyInjection;

use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;
use Sulu\Bundle\MediaBundle\Media\FormatCache\S3FormatCache;
use Sulu\Bundle\MediaBundle\Media\Storage\S3Storage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Compiler pass for register media storage services
 */
class StorageCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritdoc
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $storageType = $container->getParameter('sulu_media.media.storage.type');

        switch ($storageType) {
            case 's3':
                $this->defineS3FilesystemBridgeService($container)
                    ->defineS3MediaStorageService($container)
                    ->defineS3MediaFormatCache($container);
                break;
            case 'local':
            default:
                // Local storage as default storage. Local storage services are defined inside services.xml file
        }
    }

    /**
     * Define S3 filesystem bridge service.
     *
     * @param ContainerBuilder $container
     *
     * @return $this
     */
    protected function defineS3FilesystemBridgeService(ContainerBuilder $container)
    {
        $bridgeDef = new Definition(S3FilesystemBridge::class);
        $bridgeDef->setArguments([
            $container->getParameter('sulu_media.media.storage.s3.bucket_name'),
            $container->getParameter('sulu_media.media.storage.s3.bucket_region'),
            $container->getParameter('sulu_media.media.storage.s3.api_key'),
            $container->getParameter('sulu_media.media.storage.s3.api_secret'),
        ]);

        $container->setDefinition('sulu_media.s3.filesystem_bridge', $bridgeDef);

        return $this;
    }

    /**
     * Define S3 media storage service.
     *
     * @param ContainerBuilder $container
     *
     * @return $this
     */
    protected function defineS3MediaStorageService(ContainerBuilder $container)
    {
        if (!$container->has('sulu_media.storage') ||
            !$container->has('sulu_media.s3.filesystem_bridge')
        ) {
            return $this;
        }

        $storageDef = $container->getDefinition('sulu_media.storage');
        $storageDef->setClass(S3Storage::class)
            ->setArguments([
                $container->getDefinition('sulu_media.s3.filesystem_bridge'),
                $container->getParameter('sulu_media.media.storage.s3.path'),
                $container->getParameter('sulu_media.media.storage.s3.segments'),
            ]);

        return $this;
    }

    /**
     * Define S3 media format cache service.
     *
     * @param ContainerBuilder $container
     *
     * @return $this
     */
    protected function defineS3MediaFormatCache(ContainerBuilder $container)
    {
        if (!$container->has('sulu_media.format_cache') ||
            !$container->has('sulu_media.s3.filesystem_bridge')
        ) {
            return $this;
        }

        $storageDef = $container->getDefinition('sulu_media.format_cache');
        $storageDef->setClass(S3FormatCache::class)
            ->setArguments([
                $container->getDefinition('sulu_media.s3.filesystem_bridge'),
                $container->getParameter('sulu_media.format_cache.path'),
                $container->getParameter('sulu_media.format_cache.media_proxy_path'),
                $container->getParameter('sulu_media.format_cache.segments'),
                $container->getParameter('sulu_media.image.formats'),
            ])
            ->addTag('sulu_media.format_cache', ['alias' => 's3']);

        return $this;
    }
}
