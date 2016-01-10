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
use Symfony\Component\Translation\Dumper\JsonFileDumper;

class JsonFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new JsonFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/resources.json', $dumper->formatCatalogue($catalogue, 'messages'));
    }

    public function testDumpWithCustomEncoding()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => '"bar"'));

        $dumper = new JsonFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../fixtures/resources.dump.json', $dumper->formatCatalogue($catalogue, 'messages', array('json_encoding' => JSON_HEX_QUOT)));
    }
}
