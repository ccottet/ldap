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
use Toyota\Component\Ldap\Platform\Test\Search;

class SearchTest extends TestCase
{
    /**
     * Tests iterating search results
     *
     * @return void
     */
    public function testIterating()
    {
        $search = new Search();
        $search->setEntries(array(1, 2, 3, false));

        $this->assertEquals(1, $search->next());
        $this->assertEquals(2, $search->next());
        $this->assertEquals(3, $search->next());
        $this->assertNull($search->next());
        $this->assertNull($search->next());
        $this->assertNull($search->next(), 'Does not reset when end of array is reached');

        $search->reset();
        $this->assertEquals(1, $search->next());
        $this->assertEquals(2, $search->next());

        $search->reset();

        $this->assertEquals(1, $search->next());
    }

    /**
     * Tests freeing result set
     *
     * @return void
     */
    public function testFree()
    {
        $search = new Search();
        $search->setEntries(array(1, 2, 3));

        $search->free();
        $this->assertEquals(array(), $search->getEntries());

        $search->free();
        $this->assertEquals(
            array(),
            $search->getEntries(),
            'Repeating free on a freed search is not an issue'
        );
    }
}
