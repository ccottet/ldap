<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Tests\Core;

use Toyota\Component\Ldap\Tests\TestCase;
use Toyota\Component\Ldap\Platform\Test\Search;
use Toyota\Component\Ldap\Platform\Test\Entry;
use Toyota\Component\Ldap\Core\SearchResult;

class SearchResultTest extends TestCase
{

    /**
     * Tests iterator implementation
     *
     * @return void
     */
    public function testIteratorImplementation()
    {
        $result = new SearchResult();
        $result->rewind();
        $this->assertFalse($result->valid());
        $this->assertFalse($result->current());
        $this->assertNull($result->key());
        $result->next();
        $this->assertFalse($result->valid());

        $search = new Search();
        $search->setEntries(array(new Entry('a'), new Entry('b'), new Entry('c')));

        $result->setSearch($search);

        $result->rewind();
        $this->assertTrue($result->valid());
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $result->current());
        $this->assertEquals('a', $result->current()->getDn());
        $this->assertEquals('a', $result->key());
        $result->next();
        $this->assertTrue($result->valid());
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $result->current());
        $this->assertEquals('b', $result->current()->getDn());
        $this->assertEquals('b', $result->key());
        $result->next();
        $this->assertTrue($result->valid());
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $result->current());
        $this->assertEquals('c', $result->current()->getDn());
        $this->assertEquals('c', $result->key());
        $result->next();
        $this->assertFalse($result->valid());
        $this->assertFalse($result->current());
        $this->assertNull($result->key());

        $result->rewind();
        $this->assertTrue($result->valid());
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $result->current());
        $this->assertEquals('a', $result->current()->getDn());
        $this->assertEquals('a', $result->key());

        $search = new Search();
        $search->setEntries(array(new Entry('d'), new Entry('e'), new Entry('f')));

        $result->setSearch($search);
        $this->assertTrue($result->valid());
        $this->assertEquals('d', $result->key(), 'Iterator is rewinded when new search is set');
    }

    /**
     * Tests setting search frees memory
     *
     * @return void
     */
    public function testSetSearch()
    {
        $result = new SearchResult();

        $search = new Search();
        $search->setEntries(array(new Entry('a'), new Entry('b'), new Entry('c')));

        $result->setSearch($search);

        $other = new Search();
        $other->setEntries(array(new Entry('d'), new Entry('e'), new Entry('f')));

        $result->setSearch($search);

        $this->assertEquals(
            0,
            count($search->getEntries()),
            'When old search got released from search result, it was freed from memory'
        );

    }
}
