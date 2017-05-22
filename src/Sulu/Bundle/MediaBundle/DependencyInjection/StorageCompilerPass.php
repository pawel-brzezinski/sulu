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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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
        var_dump('elo');exit;
    }
}