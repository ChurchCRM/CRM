<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Dumper;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Dumper\IcuResFileDumper;

class IcuResFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new IcuResFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/resourcebundle/res/en.res', $dumper->formatCatalogue($catalogue, 'messages'));
    }
}
