<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Tests\Core\Manager;

use Toyota\Component\Ldap\Core\Manager;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Core\NodeAttribute;
use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Platform\Test\Entry;
use Toyota\Component\Ldap\Platform\Test\Search;
use Toyota\Component\Ldap\Platform\Test\Connection;
use Toyota\Component\Ldap\Exception\NotBoundException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\NodeNotFoundException;
use Toyota\Component\Ldap\Exception\DeleteException;

class ManagerWriteTest extends ManagerTest
{
    /**
     * Tests exceptions handling while saving a Ldap node
     *
     * @return void
     */
    public function testSaveExceptionHandling()
    {
        $manager = new Manager($this->minimal, $this->driver);

        $node = new Node();
        $attr = new NodeAttribute('attr');
        $attr->add('value');
        $node->mergeAttribute($attr);

        $this->assertBindingFirst($manager, 'save', array($node));
        $manager->connect();
        $this->assertBindingFirst($manager, 'save', array($node));
        $manager->bind();

        try {
            $manager->save($node);
            $this->fail('Node Dn has to be set for saving it');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/Cannot save: dn missing for the entry/', $e->getMessage());
        }

        $node->setDn('test');
        $this->driver->getConnection()->setFailure(
            Connection::ERR_DEFAULT,
            Connection::FAIL_COND_PERSIST
        );
        try {
            $manager->save($node);
            $this->fail('Underlying Ldap connection failed to add the entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add entry/', $e->getMessage());
        }

        $this->assertEquals(
            array('attr' => array('value')),
            $node->getDiffAdditions(),
            'In case any failure happens while persisting, snapshot is not performed'
        );

        $this->driver->getConnection()->setFailure(
            Connection::ERR_DEFAULT,
            Connection::FAIL_COND_PERSIST
        );
        $entry = new Entry('test', array('other' => array('value2')));
        $this->driver->getConnection()->stackResults(array($entry));
        try {
            $manager->save($node);
            $this->fail('Underlying Ldap connection failed to update the entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add attributes/', $e->getMessage());
        }

        $this->assertEquals(
            array('attr' => array('value')),
            $node->getDiffAdditions(),
            'In case any failure happens while updating, snapshot is not performed'
        );

        $this->assertFalse(
            $manager->save($node),
            'No more Ldap failure - A correct node is created and saved in the Ldap store'
        );

        $this->assertEquals(
            array(),
            $node->getDiffAdditions(),
            'Save occured so snapshot took place'
        );
    }

    /**
     * Tests saving new nodes to the Ldap
     *
     * @return void
     */
    public function testSaveNewNodes()
    {
        $manager = new Manager($this->minimal, $this->driver);
        $manager->connect();
        $manager->bind();

        $data = array(
                'attr1' => array('value1'),
                'attr2' => array('value1', 'value2'),
                'attr3' => array('value3')
        );
        $node = $this->buildNode('test_dn', $data);

        $this->assertTrue(
            $manager->save($node),
            'A correct node is created and saved in the Ldap store'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_dn',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'create',
            'test_dn',
            $data
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'No other log');
        $this->assertSnapshot($node, 'A node is snapshot after save');

        try {
            $node->setDn('other');
            $this->fail('Saving is like hydrating and so dn should be locked');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/Dn cannot be updated manually/', $e->getMessage());
        }

        $node = $this->buildNode('test_dn', array());
        $this->assertTrue(
            $manager->save($node),
            'Empty nodes get saved as well'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_dn',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'create',
            'test_dn',
            array()
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'No other log');
    }

    /**
     * Tests saving existing nodes to the Ldap
     *
     * @return void
     */
    public function testSaveExistingNodes()
    {
        $manager = new Manager($this->minimal, $this->driver);
        $manager->connect();
        $manager->bind();

        // Basic node update with search first
        $data = array(
                'attr1' => array('value1'),
                'attr2' => array('value1', 'value2'),
                'attr3' => array('value3')
        );
        $node = $this->buildNode('test_dn', $data);

        $entry = new Entry('test_dn', array('attr' => array('value2')));
        $this->driver->getConnection()->stackResults(array($entry));

        $this->assertFalse(
            $manager->save($node),
            'Node persistence resulted in an update'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_dn',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            null,
            array($entry)
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_add',
            'test_dn',
            $data
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'No other log');
        $this->assertSnapshot($node, 'A node is snapshot after update');

        try {
            $node->setDn('other');
            $this->fail('Saving is like hydrating and so dn should be locked');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/Dn cannot be updated manually/', $e->getMessage());
        }

        // Node updated again without underlying search
        $node->removeAttribute('attr1');
        $node->get('attr2')->add('value4');
        $node->get('attr2')->remove('value1');

        $this->assertFalse(
            $manager->save($node),
            'Node has been marked hydrated so it is always an update - No need to feed a new entry'
        );

        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_add',
            'test_dn',
            array('attr2' => array('value4'))
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_del',
            'test_dn',
            array('attr1' => array(), 'attr2' => array('value1'))
        );
        $this->assertNull(
            $this->driver->getConnection()->shiftLog(),
            'No search performed, node was already hydrated'
        );
        $this->assertSnapshot($node, 'A node is snapshot after update');

        // Support for all kinds of attributes manipulation
        $node->removeAttribute('attr2');
        $attr = new NodeAttribute('attr2');
        $attr->add(array('new1', 'new2'));
        $node->mergeAttribute($attr);
        $node->get('attr3')->add('new3');
        $node->get('attr3')->remove('value3');

        $this->assertFalse($manager->save($node), 'Node got updated');
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_add',
            'test_dn',
            array('attr3' => array('new3'))
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_del',
            'test_dn',
            array('attr3' => array('value3'))
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_rep',
            'test_dn',
            array('attr2' => array('new1', 'new2'))
        );
        $this->assertNull(
            $this->driver->getConnection()->shiftLog(),
            'No search performed, node was already hydrated'
        );
        $this->assertSnapshot($node, 'A node is snapshot after update');
    }

    /**
     * Tests complex updates with changeset merging when saving
     *
     * @return void
     */
    public function testSaveMergesChanges()
    {
        $manager = new Manager($this->minimal, $this->driver);
        $manager->connect();
        $manager->bind();

        $entry = new Entry(
            'test_dn',
            array(
                'a' => array('a1', 'a2'),
                'b' => array('b1', 'b2'),
                'c' => array('c1', 'c2'),
                'd' => array('d1', 'd2'),
                'e' => array('e1', 'e2')
            )
        );
        $this->driver->getConnection()->stackResults(array($entry));

        $node = new Node();
        $node->setDn('test_dn');
        $node->get('a', true)->add(array('a2', 'a4'));
        $node->get('b', true)->add(array('b1', 'b3'));
        $node->get('c', true)->add(array('c1', 'c3'));
        $node->get('d', true)->add(array('d1', 'd2', 'd3', 'd4'));
        $node->get('g', true)->add('g1');
        $node->get('h', true)->add(array('h1', 'h2'));
        $node->get('i', true)->add(array('i1', 'i2'));
        $node->snapshot(false);

        $node->get('a')->add(array('a1', 'a3'));
        $node->removeAttribute('b');
        $node->get('c')->set(array('c4', 'c5'));
        $node->get('d')->remove('d2');
        $node->get('d')->remove('d3');
        $node->get('d')->add('d5');
        $node->get('f', true)->add(array('f1', 'f2'));
        $node->removeAttribute('g');
        $node->get('h')->set(array('h1', 'h3'));
        $node->get('i')->remove('i2');

        $this->assertFalse(
            $manager->save($node),
            'Node persistence resulted in an update'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_dn',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            null,
            array($entry)
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_add',
            'test_dn',
            array(
                'a' => array('a3'),
                'd' => array('d5'),
                'f' => array('f1', 'f2'),
                'h' => array('h1', 'h3')
            )
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_del',
            'test_dn',
            array(
                'b' => array(),
                'd' => array('d2')
            )
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'attr_rep',
            'test_dn',
            array(
                'c' => array('c4', 'c5')
            )
        );
        $this->assertNull(
            $this->driver->getConnection()->shiftLog(),
            'All logs have been parsed'
        );
        $this->assertSnapshot($node, 'A node is snapshot after update');
    }

    /**
     * Tests deletion of nodes
     *
     * @return void
     */
    public function testDelete()
    {
        $manager = new Manager($this->minimal, $this->driver);

        $node = $this->buildNode('ent1', array());

        $this->assertBindingFirst($manager, 'delete', array($node));
        $manager->connect();
        $this->assertBindingFirst($manager, 'delete', array($node));
        $manager->bind();

        $this->assertNull($this->driver->getConnection()->shiftLog(), 'Nothing happenned yet');

        // No corresponding entry in the Ldap
        try {
            $manager->delete($node);
            $this->fail('This entry is not in the Ldap store');
        } catch (NodeNotFoundException $e) {
            $this->assertRegExp('/ent1 not found/', $e->getMessage());
        }
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ent1',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'Nothing else');

        // Basic deletion
        $set = array(new Entry('ent1'));
        $this->driver->getConnection()->stackResults($set);

        $manager->delete($node);

        $this->assertNull($this->driver->getConnection()->shiftResults(), 'Node got pulled');

        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ent1',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            null,
            $set
        ); // Deleted node search
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ent1',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE
        ); // Deleted node children search
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'ent1'
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'nothing else');

        // Deletion does not search for the entry if the node is already hydrated
        $node =new Node();
        $node->hydrateFromEntry(new Entry('ent1', array()));
        $this->assertNull($this->driver->getConnection()->shiftResults(), 'No node in the stack');
        $manager->delete($node);
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ent1',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE
        ); // Only one children search
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'ent1'
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'nothing else');

        // Exception with node children and no recursion configured
        $node = $this->buildNode('ref', array());
        $sets   = array();
        $sets[] = array(new Entry('ref'));
        $sets[] = array(new Entry('a-ref'), new Entry('b-ref'), new Entry('c-ref'));
        $this->driver->getConnection()->stackResults($sets[0]); // The node we want to delete
        $this->driver->getConnection()->stackResults($sets[1]); // Search for children nodes

        try {
            $manager->delete($node);
            $this->fail('Cannot delete the node, it has children');
        } catch (DeleteException $e) {
            $this->assertRegExp('/ref cannot be deleted/', $e->getMessage());
            $this->assertRegExp('/it has some children left/', $e->getMessage());
        }
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ref',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            null,
            $sets[0]
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ref',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[1]
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'nothing else');

        // Recursive mode deletion
        $node = $this->buildNode('tst', array());

        $sets = array(
            array(new Entry('tst')), // root node to delete
            array(new Entry('a-tst'), new Entry('b-tst'), new Entry('c-tst')), // 1st level children
            array(), // a-tst 2nd level children
            array(new Entry('a-b-tst')), // b-tst 2nd level children
            array(), // a-b-tst 3rd level children
            array() // c-tst 2nd level children
        );

        for ($i=0;$i < count($sets);$i++) {
            $this->driver->getConnection()->stackResults($sets[$i]);
        }

        $manager->delete($node, true);

        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            null,
            $sets[0]
        ); // Deleted node search
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[1]
        ); // Deleted node children search
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'a-tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[2]
        ); // a-tst node children search
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'a-tst'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'b-tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[3]
        ); // b-tst node children search
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'a-b-tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[4]
        ); // a-b-tst node children search
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'a-b-tst'
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'b-tst'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'c-tst',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $sets[5]
        ); // b-tst node children search
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'c-tst'
        );
        $this->assertActionLog(
            $this->driver->getConnection()->shiftLog(),
            'delete',
            'tst'
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'nothing else');
    }
}