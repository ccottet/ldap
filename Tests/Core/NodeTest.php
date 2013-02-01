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
use Toyota\Component\Ldap\Platform\Test\Entry;
use Toyota\Component\Ldap\Platform\Test\Search;
use Toyota\Component\Ldap\Platform\Test\Driver;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Core\NodeAttribute;
use Toyota\Component\Ldap\Exception\RebaseException;

class NodeTest extends TestCase
{
    /**
     * Tests hydration from entry
     *
     * @return void
     */
    public function testHydrateFromEntry()
    {
        $node = new Node();
        $this->assertNull($node->getDn());
        $this->assertEquals(array(), $node->getAttributes());

        $entry = new Entry(
            'test_dn',
            array(
                'attr1' => array(
                    'value1',
                    'value2',
                    'value2',
                    'value3'
                ),
                'attr4' => null,
                'attr2' => 'value1',
                'attr3' => array()
            )
        );
        $node->hydrateFromEntry($entry);

        $this->assertEquals('test_dn', $node->getDn());

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertArrayHasKey('attr3', $attributes);
        $this->assertArrayHasKey('attr4', $attributes);
        $this->assertEquals(4, count($attributes), 'Only the given attributes have been registered');

        $attr = $attributes['attr1'];
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $attr);
        $this->assertEquals('attr1', $attr->getName());

        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertTrue(false !== array_search('value3', $values, true), 'value3 found');
        $this->assertEquals(3, count($values), 'Value2 duplicate got cleaned up');

        $attr = $attributes['attr2'];
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $attr);
        $this->assertEquals('attr2', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertEquals(1, count($values), 'Only the relevant value got hydrated');

        $attr = $attributes['attr3'];
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $attr);
        $this->assertEquals('attr3', $attr->getName());
        $values = $attr->getValues();
        $this->assertEquals(0, count($values), 'Attribute got registered with no value');

        $attr = $attributes['attr4'];
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $attr);
        $this->assertEquals('attr4', $attr->getName());
        $values = $attr->getValues();
        $this->assertEquals(0, count($values), 'Attribute got registered with no value');

        $this->assertEquals(
            array(),
            array_merge($node->getDiffAdditions(), $node->getDiffDeletions(), $node->getDiffReplacements()),
            'Hydrating a node results in a diff snapshot'
        );

        $entry = new Entry('updated_dn', array());
        $node->hydrateFromEntry($entry);
        $this->assertEquals('updated_dn', $node->getDn());
        $this->assertEquals(0, count($node->getAttributes()));
    }

    /**
     * Tests merging a node attribute
     *
     * @return void
     */
    public function testMergeAttribute()
    {
        $node = new Node();
        $node->setDn('test_dn');

        // Tests basic mergeAttribute behaviour
        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value2'));
        $node->mergeAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertEquals(1, count($attributes), 'attr1 has been registered');
        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertEquals(2, count($values), 'Only the expected values are stored');

        // Tests merging an attribute to an existing stack
        $attr = new NodeAttribute('attr2');
        $attr->add(array('value3', 'value1'));
        $node->mergeAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertEquals(2, count($attributes), 'attr2 has been added');

        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertEquals(2, count($values), 'Attr1 remains unchanged');

        $attr = $attributes['attr2'];
        $this->assertEquals('attr2', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value3', $values, true), 'value3 found');
        $this->assertEquals(2, count($values), 'Attr2 is ok');

        // Tests merging an attribute with an already existing name
        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value4'));
        $node->mergeAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertEquals(2, count($attributes), 'attr2 has been added');

        $attr = $attributes['attr2'];
        $this->assertEquals('attr2', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value3', $values, true), 'value3 found');
        $this->assertEquals(2, count($values), 'Attr2 is ok');

        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertTrue(false !== array_search('value4', $values, true), 'value4 found');
        $this->assertEquals(3, count($values), 'Attr1 changes have been merged in');
    }

    /**
     * Tests retrieving attribute
     *
     * @return void
     */
    public function testGet()
    {
        $node = new Node();
        $this->assertNull($node->get('a'));

        $attr = new NodeAttribute('a');
        $attr->add('value1');
        $node->setAttribute($attr);

        $attr = new NodeAttribute('b');
        $attr->add('value2');
        $node->setAttribute($attr);

        $test = $node->get('a');
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $test);
        $this->assertEquals('a', $test->getName());
        $this->assertEquals(array('value1'), $test->getValues());

        $test = $node->get('b');
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $test);
        $this->assertEquals('b', $test->getName());
        $this->assertEquals(array('value2'), $test->getValues());

        $this->assertNull($node->get('c'));
        $test = $node->get('c', true);
        $this->assertInstanceOf(
            'Toyota\Component\Ldap\Core\NodeAttribute',
            $test,
            'A new node attribute has been instantiated'
        );
        $this->assertEquals('c', $test->getName());
        $this->assertEquals(array(), $test->getValues());
        $node->get('c')->add(array('val1', 'val2'));

        $test = $node->get('c', true);
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\NodeAttribute', $test);
        $this->assertEquals('c', $test->getName());
        $this->assertEquals(
            array('val1', 'val2'),
            $test->getValues(),
            'Our instance of c got retrieved, not a new Node Attribute'
        );
    }

    /**
     * Tests setting attribute
     *
     * @return void
     */
    public function testSetAttribute()
    {
        $node = new Node();
        $node->setDn('test_dn');

        // Tests basic setAttribute behaviour
        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value2'));
        $node->setAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertEquals(1, count($attributes), 'attr1 has been registered');
        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertEquals(2, count($values), 'Only the expected values are stored');

        // Tests setting an additional attribute to an existing stack
        $attr = new NodeAttribute('attr2');
        $attr->add(array('value3', 'value1'));
        $node->setAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertEquals(2, count($attributes), 'attr2 has been added');

        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value2', $values, true), 'value2 found');
        $this->assertEquals(2, count($values), 'Attr1 remains unchanged');

        $attr = $attributes['attr2'];
        $this->assertEquals('attr2', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value3', $values, true), 'value3 found');
        $this->assertEquals(2, count($values), 'Attr2 is ok');

        // Tests setting an attribute with an already existing name (replacing)
        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value4'));
        $node->setAttribute($attr);

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertEquals(2, count($attributes), 'attr2 has been added');

        $attr = $attributes['attr2'];
        $this->assertEquals('attr2', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value3', $values, true), 'value3 found');
        $this->assertEquals(2, count($values), 'Attr2 is ok');

        $attr = $attributes['attr1'];
        $this->assertEquals('attr1', $attr->getName());
        $values = $attr->getValues();
        $this->assertTrue(false !== array_search('value1', $values, true), 'value1 found');
        $this->assertTrue(false !== array_search('value4', $values, true), 'value4 found');
        $this->assertEquals(2, count($values), 'Attr1 has been replaced');
    }

    /**
     * Tests removing attribute
     *
     * @return void
     */
    public function testRemoveAttribute()
    {
        $node = new Node();
        $node->setDn('test_dn');

        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value2'));
        $node->setAttribute($attr);
        $attr = new NodeAttribute('attr2');
        $attr->add(array('value3', 'value1'));
        $node->setAttribute($attr);
        $attr = new NodeAttribute('attr3');
        $attr->add(array('value5'));
        $node->setAttribute($attr);

        $this->assertTrue($node->removeAttribute('attr2'));

        $attributes = $node->getAttributes();
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr3', $attributes);
        $this->assertEquals(2, count($attributes), 'attr2 has been removed');

        $this->assertFalse($node->removeAttribute('invalid'));
        $this->assertEquals(2, count($attributes), 'No change in attributes');
    }

    /**
     * Tests node level diff tracking
     *
     * @return void
     */
    public function testNodeLevelDiffTracking()
    {
        $node = new Node();
        $node->setDn('test_dn');

        $attr = new NodeAttribute('attr0');
        $attr->add('value0');
        $node->setAttribute($attr);

        $node->snapshot();

        $attr = new NodeAttribute('attr1');
        $attr->add(array('value1', 'value2'));
        $node->mergeAttribute($attr);

        $attr = new NodeAttribute('attr2');
        $attr->add(array('value3', 'value1'));
        $node->mergeAttribute($attr);

        $attr = new NodeAttribute('attr3');
        $attr->add('value5');
        $node->mergeAttribute($attr);

        $node->removeAttribute('attr0');
        $node->removeAttribute('attr4');

        $this->assertEquals(
            array(
                'attr1' => array('value1', 'value2'),
                'attr2' => array('value3', 'value1'),
                'attr3' => array('value5')
            ),
            $node->getDiffAdditions(),
            'Basic node additions tracking works'
        );

        $this->assertEquals(
            array('attr0' => array()),
            $node->getDiffDeletions(),
            'Basic node deletions tracking works and inexistant attributes are not logged'
        );

        $this->assertEquals(
            array(),
            $node->getDiffReplacements(),
            'No replacement done at this stage'
        );

        $attr = new NodeAttribute('attr0');
        $attr->add('value6');
        $node->mergeAttribute($attr);
        $node->removeAttribute('attr2');
        $this->assertEquals(
            array(),
            $node->getDiffDeletions(),
            'attr0 is now replaced and attr2 should no more be an addition'
        );
        $this->assertEquals(
            array(
                'attr1' => array('value1', 'value2'),
                'attr3' => array('value5')
            ),
            $node->getDiffAdditions(),
            'attr2 is not part of additions anymore'
        );
        $this->assertEquals(
            array('attr0' => array('value6')),
            $node->getDiffReplacements(),
            'attr0 has been marked for replacement'
        );

        $node->snapshot();
        $this->assertEquals(
            array(),
            array_merge(
                $node->getDiffDeletions(),
                $node->getDiffAdditions(),
                $node->getDiffReplacements()
            ),
            'Diff has been reset'
        );

        $attr = new NodeAttribute('attr4');
        $attr->add(array('value7', 'value8', 'value9'));
        $node->setAttribute($attr);

        $attr = new NodeAttribute('attr0');
        $attr->add(array('value6', 'value10'));
        $node->setAttribute($attr);

        $this->assertEquals(
            array(
                'attr4' => array('value7', 'value8', 'value9'),
                'attr0' => array('value6', 'value10')
            ),
            $node->getDiffReplacements(),
            'Replacements enforced for both attributes'
        );
        $this->assertEquals(
            array(),
            $node->getDiffDeletions(),
            'No new deletion logged'
        );
        $this->assertEquals(
            array(),
            $node->getDiffAdditions(),
            'No new addition logged'
        );
    }

    /**
     * Tests diff tracking at node attribute level
     *
     * @return void
     */
    public function testAttributeLevelDiffTracking()
    {
        $node = new Node();
        $node->setDn('test_dn');
        $node->get('attr0', true)->add(array('value0', 'value1'));
        $node->get('attr1', true)->add(array('value1', 'value2', 'value3', 'value4'));
        $node->get('attr2', true)->add(array('value5', 'value2'));
        $node->get('attr3', true)->add('value6');
        $node->snapshot();

        $node->get('attr1')->remove(array('value1', 'value2'));
        $node->get('attr1')->add(array('value7', 'value1'));

        $this->assertEquals(
            array('attr1' => array('value2')),
            $node->getDiffDeletions(),
            'Value level deletion is tracked'
        );
        $this->assertEquals(
            array('attr1' => array('value7')),
            $node->getDiffAdditions(),
            'Value level addition is tracked'
        );
        $this->assertEquals(
            array(),
            $node->getDiffReplacements(),
            'Value level replacement are ignored'
        );

        $node->snapshot();
        $this->assertEquals(
            array(),
            array_merge(
                $node->getDiffDeletions(),
                $node->getDiffAdditions(),
                $node->getDiffReplacements()
            ),
            'Value level diff got snapshot'
        );

        $node->get('attr2')->add(array('value9', 'value2', 'value4'));
        $node->get('attr2')->remove('value2');
        $this->assertEquals(
            array('attr2' => array('value9', 'value4')),
            $node->getDiffAdditions(),
            'Only 2 values actually got added on attr2 already registered'
        );
        $this->assertEquals(
            array('attr2' => array('value2')),
            $node->getDiffDeletions(),
            'value 2 actually got deleted'
        );
        $this->assertEquals(
            array(),
            $node->getDiffReplacements(),
            'Value level replacements ignored'
        );

        $node->snapshot();

        //value level addition on a deleted attribute
        $node->get('attr3')->add('value');
        $node->removeAttribute('attr3');

        //value level deletion on an added attribute
        $node->get('attr4', true)->add(array('value1', 'value2'));
        $node->get('attr4')->snapshot();
        $node->get('attr4')->remove('value2');

        //value level addition & deletion on a replaced attribute
        $attr = $node->get('attr1');
        $node->removeAttribute('attr1');
        $attr->add('value99');
        $attr->remove('value7');
        $node->mergeAttribute($attr);

        //actual value level changes to be merged with node level diffs
        $node->get('attr0')->add('test');
        $node->get('attr0')->remove('value0');

        //value level replacement of the complete node attribute
        $node->get('attr2')->set(array('new', 'set', 'values'));
        $node->get('attr2')->add('of');
        $node->get('attr2')->remove('new');

        $this->assertEquals(
            array(
                'attr0' => array('test'),
                'attr4' => array('value1')
            ),
            $node->getDiffAdditions(),
            'We got a mix of value level and node level additions tracked'
        );
        $this->assertEquals(
            array(
                'attr0' => array('value0'),
                'attr3' => array()
            ),
            $node->getDiffDeletions(),
            'We got a mix of value level and node level additions tracked'
        );
        $this->assertEquals(
            array(
                'attr1' => array(
                    2 => 'value3',
                    3 => 'value4',
                    5 => 'value1',
                    6 => 'value99'
                ),
                'attr2' => array(
                    1 => 'set',
                    2 => 'values',
                    3 => 'of'
                )
            ),
            $node->getDiffReplacements(),
            'attr1 & attr2 got correctly processed and was not messed up with all other changes'
        );

        $node->snapshot();
        $this->assertEquals(
            array(),
            array_merge(
                $node->getDiffDeletions(),
                $node->getDiffAdditions(),
                $node->getDiffReplacements()
            ),
            'Value level diff got snapshot'
        );
    }

    /**
     * Tests retrieving raw node attributes for Ldap persistence
     *
     * @return void
     */
    public function testGetRawAttributes()
    {
        $node = new Node();
        $this->assertEquals(
            array(),
            $node->getRawAttributes(),
            'No attributes set so an empty array is retrieved'
        );

        $node->mergeAttribute(new NodeAttribute('attr1'));

        $attr = new NodeAttribute('attr2');
        $attr->add(array('value1', 'value2'));
        $node->mergeAttribute($attr);

        $attr = new NodeAttribute('attr3');
        $attr->add('value3');
        $node->mergeAttribute($attr);

        $this->assertEquals(
            array(
                'attr1' => array(),
                'attr2' => array('value1', 'value2'),
                'attr3' => array('value3')
            ),
            $node->getRawAttributes(),
            'All combinations are parsed correctly'
        );

    }

    /**
     * Tests setting Dn for a node
     *
     * @return void
     */
    public function testSetDn()
    {
        $node = new Node();

        $this->assertNull($node->getDn());

        $node->setDn('test');
        $this->assertEquals('test', $node->getDn());

        $node = new Node();
        $entry = new Entry('test_dn', array());
        $node->hydrateFromEntry($entry);
        $this->assertEquals('test_dn', $node->getDn());

        try {
            $node->setDn('other_dn');
            $this->fail('Cannot manually set dn when node is bound to an existing entry');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/Dn cannot be updated manually/', $e->getMessage());
        }
        $this->assertEquals('test_dn', $node->getDn());
        $node->setDn('other_dn', true);
        $this->assertEquals('other_dn', $node->getDn());
    }

    /**
     * Tests rebasing a node changes on an existing node
     *
     * @return void
     */
    public function testRebaseDiff()
    {
        $rebasedNode = new Node();
        $rebasedNode->setDn('rebased');
        $rebasedNode->get('a', true)->add(array('a2', 'a4'));
        $rebasedNode->get('b', true)->add(array('b1', 'b3'));
        $rebasedNode->get('c', true)->add(array('c1', 'c3'));
        $rebasedNode->get('d', true)->add(array('d1', 'd2', 'd3', 'd4'));
        $rebasedNode->get('g', true)->add('g1');
        $rebasedNode->get('h', true)->add(array('h1', 'h2'));
        $rebasedNode->get('i', true)->add(array('i1', 'i2'));
        $rebasedNode->snapshot();

        $rebasedNode->get('a')->add(array('a1', 'a3'));
        $rebasedNode->removeAttribute('b');
        $rebasedNode->get('c')->set(array('c4', 'c5'));
        $rebasedNode->get('d')->remove('d2');
        $rebasedNode->get('d')->remove('d3');
        $rebasedNode->get('d')->add('d5');
        $rebasedNode->get('f', true)->add(array('f1', 'f2'));
        $rebasedNode->removeAttribute('g');
        $rebasedNode->get('h')->set(array('h1', 'h3'));
        $rebasedNode->get('i')->remove('i2');

        $this->assertEquals(
            array(
                'a' => array('a2', 'a4', 'a1', 'a3'),
                'c' => array('c4', 'c5'),
                'd' => array(0 => 'd1', 3 => 'd4', 4 => 'd5'),
                'f' => array('f1', 'f2'),
                'h' => array('h1', 'h3'),
                'i' => array('i1')
            ),
            $rebasedNode->getRawAttributes(),
            'All attributes according to plan'
        );
        $this->assertEquals(
            array(
                'a' => array('a1', 'a3'),
                'd' => array('d5'),
                'f' => array('f1', 'f2')
            ),
            $rebasedNode->getDiffAdditions(),
            'Regular additions tracking'
        );
        $this->assertEquals(
            array(
                'b' => array(),
                'd' => array('d2', 'd3'),
                'g' => array(),
                'i' => array('i2')
            ),
            $rebasedNode->getDiffDeletions(),
            'Regular deletions tracking'
        );
        $this->assertEquals(
            array(
                'c' => array('c4', 'c5'),
                'h' => array('h1', 'h3')
            ),
            $rebasedNode->getDiffReplacements(),
            'Regular replacements tracking'
        );

        $origNode = new Node();
        $origNode->setDn('origin');
        $origNode->get('a', true)->add(array('a1', 'a2'));
        $origNode->get('b', true)->add(array('b1', 'b2'));
        $origNode->get('c', true)->add(array('c1', 'c2'));
        $origNode->get('d', true)->add(array('d1', 'd2'));
        $origNode->get('e', true)->add(array('e1', 'e2'));

        try {
            $rebasedNode->rebaseDiff($origNode);
            $this->fail('Cannot rebase on a node which is not snapshot');
        } catch (RebaseException $e) {
            $this->assertRegExp(
                '/origin has some uncommitted changes - Cannot rebase rebased on origin/',
                $e->getMessage()
            );
        }

        $this->assertEquals(
            array(
                'a' => array('a2', 'a4', 'a1', 'a3'),
                'c' => array('c4', 'c5'),
                'd' => array(0 => 'd1', 3 => 'd4', 4 => 'd5'),
                'f' => array('f1', 'f2'),
                'h' => array('h1', 'h3'),
                'i' => array('i1')
            ),
            $rebasedNode->getRawAttributes(),
            'Rebased node values are unchanged'
        );

        $origNode->snapshot();
        $backupNode = clone $origNode;
        $rebasedNode->rebaseDiff($origNode);

        $this->assertEquals(
            array(
                'a' => array('a1', 'a2', 'a3'),
                'c' => array('c4', 'c5'),
                'd' => array(0 => 'd1', 2 => 'd5'),
                'e' => array('e1', 'e2'),
                'f' => array('f1', 'f2'),
                'h' => array('h1', 'h3')
            ),
            $rebasedNode->getRawAttributes(),
            'Rebased diff got applied on origin node values'
        );
        $this->assertEquals(
            array(
                'a' => array('a3'),
                'd' => array('d5'),
                'f' => array('f1', 'f2'),
                'h' => array('h1', 'h3')
            ),
            $rebasedNode->getDiffAdditions(),
            'A new additions diff has been computed (h did not exist in origin node so it is added)'
        );
        $this->assertEquals(
            array(
                'b' => array(),
                'd' => array('d2')
            ),
            $rebasedNode->getDiffDeletions(),
            'g and i deletions got ignored in the new deletion diff as they were not set on origin'
        );
        $this->assertEquals(
            array(
                'c' => array('c4', 'c5')
            ),
            $rebasedNode->getDiffReplacements(),
            'h replacement was computed as an addition on origin node'
        );
    }
}
