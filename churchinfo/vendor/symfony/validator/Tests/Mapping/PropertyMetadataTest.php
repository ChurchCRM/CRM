<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class PropertyMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ValidatorException');

        new PropertyMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetPropertyValueFromPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromOverriddenPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::PARENTCLASS, 'data');

        $this->assertTrue($metadata->isPublic($entity));
        $this->assertEquals('Overridden data', $metadata->getPropertyValue($entity));
    }
}
