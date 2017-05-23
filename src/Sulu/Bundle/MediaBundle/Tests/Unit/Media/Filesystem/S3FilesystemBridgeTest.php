<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Tests\Unit\Media\Filesystem;

use Gaufrette\Filesystem;
use Sulu\Bundle\MediaBundle\Media\Filesystem\S3FilesystemBridge;

class S3FilesystemBridgeTest extends \PHPUnit_Framework_TestCase
{
    public function testGetFilesystem()
    {
        $bridge = $this->getS3FilesystemBridgeInstance();

        $this->assertInstanceOf(Filesystem::class, $bridge->getFilesystem());
    }

    /**
     * @return S3FilesystemBridge
     */
    protected function getS3FilesystemBridgeInstance()
    {
        return new S3FilesystemBridge('foobar', 'eu-central-1', 'someApiKey', 'someSecretKey');
    }
}
