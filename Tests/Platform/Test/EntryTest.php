<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Tests\Platform\Test;

use Toyota\Component\Ldap\Tests\TestCase;
use Toyota\Component\Ldap\Platform\Test\Entry;

class EntryTest extends TestCase
{
    /**
     * Test accessors
     *
     * @return void
     */
    public function testAccessors()
    {
        $entry = new Entry('test', array('val1', 'val2'));
        $this->assertEquals('test', $entry->getDn());
        $this->assertEquals(array('val1', 'val2'), $entry->getAttributes());

        $entry = new Entry('other');
        $this->assertEquals('other', $entry->getDn());
        $this->assertEquals(array(), $entry->getAttributes());

        $entry->setDn('changed');
        $entry->setAttributes(array('new'));
        $this->assertEquals('changed', $entry->getDn());
        $this->assertEquals(array('new'), $entry->getAttributes());
    }
}
