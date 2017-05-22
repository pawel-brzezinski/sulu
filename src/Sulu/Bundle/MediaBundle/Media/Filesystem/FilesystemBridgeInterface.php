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

/**
 * Interface for implementing media filesystem bridge.
 */
interface FilesystemBridgeInterface
{
    /**
     * Get filesystem instance
     *
     * @return mixed
     */
    public function getFilesystem();
}