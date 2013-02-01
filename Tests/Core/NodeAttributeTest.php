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
use Toyota\Component\Ldap\Core\NodeAttribute;

class NodeAttributeTest extends TestCase
{
    /**
     * Tests iterator implementation
     *
     * @return void
     */
    public function testIteratorImplementation()
    {
        $attribute = new NodeAttribute('test');

        $this->assertInstanceOf('\Iterator', $attribute);

        $attribute->rewind();
        $this->assertFalse($attribute->valid());
        $this->assertFalse($attribute->current());
        $this->assertNull($attribute->key());
        $attribute->next();
        $this->assertFalse($attribute->valid());

        $attribute->add('value1');
        $attribute->add('value2');
        $attribute->add('value3');

        $attribute->rewind();
        $this->assertTrue($attribute->valid());
        $this->assertEquals('value1', $attribute->current());
        $this->assertEquals(0, $attribute->key());
        $attribute->next();

        $this->assertTrue($attribute->valid());
        $this->assertEquals('value2', $attribute->current());
        $this->assertEquals(1, $attribute->key());
        $attribute->next();

        $this->assertTrue($attribute->valid());
        $this->assertEquals('value3', $attribute->current());
        $this->assertEquals(2, $attribute->key());
        $attribute->next();

        $this->assertFalse($attribute->valid());
        $this->assertFalse($attribute->current());
        $this->assertNull($attribute->key());

        $attribute->add('value4');
        $this->assertTrue($attribute->valid());
        $this->assertEquals('value4', $attribute->current());
        $this->assertEquals(3, $attribute->key());
        $attribute->next();

        $this->assertFalse($attribute->valid());
        $this->assertFalse($attribute->current());
        $this->assertNull($attribute->key());

        $attribute->rewind();
        $this->assertTrue($attribute->valid());
        $this->assertEquals('value1', $attribute->current());
        $this->assertEquals(0, $attribute->key());
    }

    /**
     * Tests countable interface implementation
     *
     * @return void
     */
    public function testCountableImplementation()
    {
        $attribute = new NodeAttribute('test');

        $this->assertInstanceOf('\Countable', $attribute);

        $this->assertEquals(0, $attribute->count());

        $attribute->add('value1');
        $this->assertEquals(1, $attribute->count());
    }

    /**
     * Tests array access implementation
     *
     * @return void
     */
    public function testArrayAccessImplementation()
    {
        $attribute = new NodeAttribute('test');

        $this->assertInstanceOf('\ArrayAccess', $attribute);

        $this->assertNull($attribute->offsetGet(0));
        $this->assertFalse($attribute->offsetExists(0));
        $attribute->offsetUnset(0);
        $this->assertNull($attribute->offsetGet(0));

        $attribute->add('value1');
        $this->assertEquals('value1', $attribute->offsetGet(0));
        $this->assertTrue($attribute->offsetExists(0));
        $this->assertFalse($attribute->offsetExists(1));

        $attribute->offsetSet(1, 'value2');
        $attribute->offsetSet(0, 'value3');
        $this->assertEquals(2, $attribute->count(), 'value1 should have been replaced');

        $this->assertEquals('value3', $attribute->offsetGet(0));
        $this->assertTrue($attribute->offsetExists(0));
        $this->assertEquals('value2', $attribute->offsetGet(1));
        $this->assertTrue($attribute->offsetExists(1));

        $attribute->offsetUnset(0);
        $this->assertNull($attribute->offsetGet(0));
        $this->assertFalse($attribute->offsetExists(0));
        $this->assertEquals('value2', $attribute->offsetGet(1));
        $this->assertTrue($attribute->offsetExists(1));
        $this->assertEquals(1, $attribute->count(), 'value3 has really been removed');

        $attribute->offsetSet(null, 'value4');
        $attribute->offsetSet(null, 'value5');
        $this->assertEquals(3, $attribute->count(), 'values have been added');
        $this->assertEquals('value2', $attribute->offsetGet(1));
        $this->assertEquals('value4', $attribute->offsetGet(2));
        $this->assertEquals('value5', $attribute->offsetGet(3));
    }

    /**
     * Tests adding & removing basic scalar values
     *
     * @return void
     */
    public function testBasicValuesHandling()
    {
        $attribute = new NodeAttribute('test');

        $this->assertTrue($attribute->add('value1'));
        $this->assertTrue($attribute->add('value2'));
        $this->assertTrue($attribute->add('value3'));
        $this->assertFalse($attribute->add(''), 'Empty string is not valid');
        $this->assertFalse($attribute->add(null), 'null is not valid');
        $this->assertEquals('value1', $attribute[0]);
        $this->assertEquals('value2', $attribute[1]);
        $this->assertEquals('value3', $attribute[2]);
        $this->assertEquals(3, count($attribute), 'All values have been checked');

        $this->assertFalse(
            $attribute->add('value2'),
            'Value2 has not been added as it is a duplicate'
        );
        $this->assertEquals(3, count($attribute));

        $this->assertTrue($attribute->remove('value2'));

        $this->assertEquals('value1', $attribute[0]);
        $this->assertEquals('value3', $attribute[2]);
        $this->assertEquals(2, count($attribute), 'All values have been checked');

        $this->assertFalse($attribute->remove('value2'));
        $this->assertEquals(2, count($attribute), 'No change in value set');

        $this->assertFalse($attribute->remove(null), 'Null is not relevant');
        $this->assertFalse($attribute->remove(''), 'Empty string is not relevant');
        $this->assertEquals('value1', $attribute[0]);
        $this->assertEquals('value3', $attribute[2]);
        $this->assertEquals(2, count($attribute), 'All values have been checked');

        $attribute = new NodeAttribute('test');
        $this->assertFalse($attribute->add(null));
        $this->assertEquals(0, count($attribute), 'No values have been stored');
    }

    /**
     * Tests adding removing arrays of values
     *
     * @return void
     */
    public function testArrayValuesHandling()
    {
        $attribute = new NodeAttribute('test');

        $this->assertTrue($attribute->add(array('value1', 'value2', 'value3')));
        $this->assertEquals('value1', $attribute[0]);
        $this->assertEquals('value2', $attribute[1]);
        $this->assertEquals('value3', $attribute[2]);
        $this->assertEquals(3, count($attribute), 'All values have been checked');

        $this->assertFalse(
            $attribute->add(array('value3', 'value1')),
            'When none of the array values are added, false is returned'
        );
        $this->assertEquals(3, count($attribute), 'No change in the values');

        $this->assertFalse(
            $attribute->add(array()),
            'No values have been added again'
        );
        $this->assertEquals(3, count($attribute), 'No change in the values');

        $this->assertFalse(
            $attribute->add('value2'),
            'Value2 has not been added as it is a duplicate'
        );
        $this->assertEquals(3, count($attribute));

        $this->assertFalse(
            $attribute->add(array('', null)),
            'Empty string and null are not valid values'
        );
        $this->assertEquals(3, count($attribute), 'No change in the values');

        $this->assertTrue(
            $attribute->add(array('value3', 'value1', 'value4', null, '')),
            'When at least one value gets added, true is returned'
        );
        $this->assertEquals('value1', $attribute[0]);
        $this->assertEquals('value2', $attribute[1]);
        $this->assertEquals('value3', $attribute[2]);
        $this->assertEquals('value4', $attribute[3]);
        $this->assertEquals(4, count($attribute), 'All values have been checked');

        $this->assertFalse(
            $attribute->remove(array()),
            'No value removed so false is returned'
        );
        $this->assertEquals(4, count($attribute), 'No change in the values');

        $this->assertFalse(
            $attribute->remove(array('value5', '', null, 'value6')),
            'No value removed so false is returned'
        );
        $this->assertEquals(4, count($attribute), 'No change in the values');

        $this->assertTrue(
            $attribute->remove(array('value3', '', 'value1')),
            'Some values have been removed so true is returned'
        );
        $this->assertEquals('value2', $attribute[1]);
        $this->assertEquals('value4', $attribute[3]);
        $this->assertEquals(2, count($attribute), 'All values have been checked');

        $this->assertTrue($attribute->remove('value4'));
        $this->assertEquals('value2', $attribute[1]);
        $this->assertEquals(1, count($attribute), 'All values have been checked');

        $this->assertTrue(
            $attribute->remove(array('value1', 'value4', 'value2')),
            'Some values have been removed so true is returned'
        );
        $this->assertEquals(0, count($attribute), 'No values are stored anymore');
    }

    /**
     * Tests setting values
     *
     * @return void
     */
    public function testSet()
    {
        $test = new NodeAttribute('test');

        $this->assertTrue($test->set('v1'));
        $this->assertEquals(
            array('v1'),
            $test->getValues(),
            'Our value got added'
        );
        $this->assertEquals(
            array(),
            array_merge(
                $test->getDiffAdditions(),
                $test->getDiffDeletions(),
                $test->getDiffReplacements()
            ),
            'Attribute is marked as overridden, changes are not tracked anymore'
        );

        $this->assertTrue($test->set(array('v2', 'v3')));
        $this->assertEquals(
            array(),
            array_merge(
                $test->getDiffAdditions(),
                $test->getDiffDeletions(),
                $test->getDiffReplacements()
            ),
            'Attribute is still marked as overridden, no changes got tracked'
        );

        $test->add('v4');
        $test->remove('v3');
        $test->remove('v2');
        $test->add('v3');
        $this->assertEquals(
            array(),
            array_merge(
                $test->getDiffAdditions(),
                $test->getDiffDeletions(),
                $test->getDiffReplacements()
            ),
            'Even regular add and remove operations get ignored'
        );

        $test->snapshot();
        $test->add('v5');
        $this->assertEquals(
            array('v5'),
            $test->getDiffAdditions(),
            'Tracking is working again'
        );
    }

    /**
     * Tests checking if attribute has to be replaced on persistence
     *
     * @return void
     */
    public function testIsReplaced()
    {
        $test = new NodeAttribute('test');
        $test->add(array('v1', 'v2', 'v3', 'v4'));
        $test->snapshot();

        $test->add('v5');
        $test->remove('v3');
        $test->remove('v2');
        $test->add('v3');
        $this->assertEquals(
            array('v5'),
            $test->getDiffAdditions(),
            'Tracking works as usual'
        );
        $this->assertEquals(
            array('v2'),
            $test->getDiffDeletions(),
            'Tracking works as usual'
        );
        $this->assertEquals(
            array('v3'),
            $test->getDiffReplacements(),
            'Tracking works as usual'
        );
        $this->assertFalse($test->isReplaced(), 'We have just been adding & removing values');

        $test->set(array('v2', 'v6'));
        $this->assertEquals(
            array(),
            array_merge(
                $test->getDiffAdditions(),
                $test->getDiffDeletions(),
                $test->getDiffReplacements()
            ),
            'Diff got cleared'
        );
        $this->assertTrue($test->isReplaced(), 'Object is marked for a complete replacement');
        $test->add('v5');
        $test->remove('v2');
        $this->assertEquals(
            array(),
            array_merge(
                $test->getDiffAdditions(),
                $test->getDiffDeletions(),
                $test->getDiffReplacements()
            ),
            'Diff not updated'
        );
        $this->assertTrue($test->isReplaced(), 'Object is still marked for a replacement');

        $test->snapshot();
        $test->add('v2');
        $this->assertEquals(
            array('v2'),
            $test->getDiffAdditions(),
            'Diff tracked again'
        );
        $this->assertFalse($test->isReplaced(), 'We are no more in a replacement case');
    }

    /**
     * Tests retrieving attribute name
     *
     * @return void
     */
    public function testGetName()
    {
        $test = new NodeAttribute('test');
        $other = new NodeAttribute('other');
        $this->assertEquals('test', $test->getName());
        $this->assertEquals('other', $other->getName());
    }

    /**
     * Tests attributes diff tracking
     *
     * @return void
     */
    public function testDiffTracking()
    {
        $test = new NodeAttribute('test');
        $test->add(array('value4', 'value5'));

        $test->snapshot();

        $test->add(array('value1', 'value2', 'value3'));
        $test->remove(array('value4', 'value1', 'value5'));
        $test[] = 'value6';
        $test[] = 'value4';
        $test[] = 'value7';
        unset($test[5]); //value6

        $this->assertEquals(
            array('value2', 'value3', 'value7'),
            $test->getDiffAdditions(),
            'Additions have been tracked'
        );

        $this->assertEquals(
            array('value5'),
            $test->getDiffDeletions(),
            'Deletions have been tracked'
        );

        $this->assertEquals(
            array('value4'),
            $test->getDiffReplacements(),
            'Replacements have been tracked'
        );

        $test->snapshot();
        $this->assertEquals(
            array(),
            $test->getDiffAdditions(),
            'Diff tracking has been reset'
        );
        $this->assertEquals(
            array(),
            $test->getDiffDeletions(),
            'Diff tracking has been reset'
        );
        $this->assertEquals(
            array(),
            $test->getDiffReplacements(),
            'Diff tracking has been reset'
        );
    }
}