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
use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Platform\Test\Connection;
use Toyota\Component\Ldap\Platform\Test\Entry;
use Toyota\Component\Ldap\Platform\Test\Search;
use Toyota\Component\Ldap\Exception\NotBoundException;
use Toyota\Component\Ldap\Exception\SizeLimitException;
use Toyota\Component\Ldap\Exception\MalformedFilterException;
use Toyota\Component\Ldap\Exception\SearchException;
use Toyota\Component\Ldap\Exception\NodeNotFoundException;

class ManagerReadTest extends ManagerTest
{
    /**
     * Tests retrieving node with their Dn
     *
     * @return void
     */
    public function testGetNode()
    {
        $manager = new Manager($this->minimal, $this->driver);

        // Exception handling
        $this->assertBindingFirst($manager, 'getNode', array('test'));
        $manager->connect();
        $this->assertBindingFirst($manager, 'getNode', array('test'));
        $manager->bind();

        $this->driver->getConnection()->setFailure(Connection::ERR_SIZE_LIMIT);
        try {
            $manager->getNode('test');
            $this->fail('Size limit exception should be populated');
        } catch (SizeLimitException $e) {
            $this->assertRegExp('/Size limit reached/', $e->getMessage());
        }

        $this->driver->getConnection()->setFailure(Connection::ERR_MALFORMED_FILTER);
        try {
            $manager->getNode('test');
            $this->fail('Malformed filter exception should be populated');
        } catch (MalformedFilterException $e) {
            $this->assertRegExp('/Malformed filter/', $e->getMessage());
        }

        $this->driver->getConnection()->setFailure(Connection::ERR_DEFAULT);
        try {
            $manager->getNode('test');
            $this->fail('Any other search exception should be populated');
        } catch (SearchException $e) {
            $this->assertEquals('Toyota\Component\Ldap\Exception\SearchException', get_class($e));
            $this->assertRegExp('/Search failed/', $e->getMessage());
        }

        // Empty result set handling
        $this->driver->getConnection()->setFailure(Connection::ERR_NO_RESULT);
        try {
            $manager->getNode('test');
            $this->fail('Node cannot be retrieved as search result set is empty');
        } catch (NodeNotFoundException $e) {
            $this->assertRegExp('/test not found/', $e->getMessage());
        }

        try {
            $node = $manager->getNode('CN=TEST,DC=EXAMPLE,DC=COM');
            $this->fail('No exception get thrown by backend but no entry is retrieved either');
        } catch (NodeNotFoundException $e) {
            $this->assertSearchLog(
                $this->driver->getConnection()->shiftLog(),
                'CN=TEST,DC=EXAMPLE,DC=COM',
                '(objectclass=*)',
                Search::SCOPE_BASE
            );
            $this->assertRegExp('/CN=TEST,DC=EXAMPLE,DC=COM not found/', $e->getMessage());
        }

        $this->driver->getConnection()->stackResults(null);
        try {
            $node = $manager->getNode('CN=TEST,DC=EXAMPLE,DC=COM');
            $this->fail('A null entry is not a valid entry');
        } catch (NodeNotFoundException $e) {
            $this->assertSearchLog(
                $this->driver->getConnection()->shiftLog(),
                'CN=TEST,DC=EXAMPLE,DC=COM',
                '(objectclass=*)',
                Search::SCOPE_BASE
            );
            $this->assertRegExp('/CN=TEST,DC=EXAMPLE,DC=COM not found/', $e->getMessage());
        }

        // Basic search
        $entry = new Entry('cn=test,dc=example,dc=com', array('attr' => array('value')));
        $this->driver->getConnection()->stackResults(array($entry));

        $node = $manager->getNode('CN=TEST,DC=EXAMPLE,DC=COM');
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'CN=TEST,DC=EXAMPLE,DC=COM',
            '(objectclass=*)',
            Search::SCOPE_BASE,
            null,
            array($entry)
        );
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $node);

        $this->assertEquals(
            'cn=test,dc=example,dc=com',
            $node->getDn(),
            'The right node got retrieved and hydrated'
        );
        $this->assertNull(
            $this->driver->getConnection()->shiftResults(),
            'Node got pulled from the stack'
        );

        // Alternative parameters search
        $entry = new Entry('cn=cyril,dc=example,dc=com', array('attr' => array('value2')));
        $this->driver->getConnection()->stackResults(array($entry));

        $node = $manager->getNode(
            'CN=OTHER,DC=EXAMPLE,DC=COM',
            array('*', '+'),
            '(objectclass=other)'
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'CN=OTHER,DC=EXAMPLE,DC=COM',
            '(objectclass=other)',
            Search::SCOPE_BASE,
            array('*', '+'),
            array($entry)
        );
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $node);

        $this->assertEquals(
            'cn=cyril,dc=example,dc=com',
            $node->getDn(),
            'The right node got retrieved and hydrated'
        );
        $this->assertNull(
            $this->driver->getConnection()->shiftResults(),
            'Node got pulled from the stack'
        );
    }

    /**
     * Tests querying a Ldap directory
     *
     * @return void
     */
    public function testSearch()
    {
        $manager = new Manager($this->minimal, $this->driver);

        // Exception handling
        $this->assertBindingFirst($manager, 'search');
        $manager->connect();
        $this->assertBindingFirst($manager, 'search');
        $manager->bind();

        $this->driver->getConnection()->setFailure(Connection::ERR_MALFORMED_FILTER);
        try {
            $res = $manager->search();
            $this->fail('Filter malformed, query shall fail');
        } catch (MalformedFilterException $e) {
            $this->assertRegExp('/Malformed filter/', $e->getMessage());
        }

        // Basic search
        $set = array(new Entry('a'), new Entry('b'), new Entry('c'));
        $this->driver->getConnection()->stackResults($set);
        $result = $manager->search();

        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'dc=example,dc=com',
            '(objectclass=*)',
            SearchInterface::SCOPE_ALL,
            null,
            $set
        );

        $this->assertInstanceOf('Toyota\Component\Ldap\Core\SearchResult', $result);

        $data = array();
        foreach ($result as $key => $value) {
            $this->assertInstanceOf('Toyota\Component\Ldap\Core\Node', $value);
            $data[$key] = $value->getAttributes();
        }

        $this->assertArrayHasKey('a', $data);
        $this->assertArrayHasKey('b', $data);
        $this->assertArrayHasKey('c', $data);
        $this->assertEquals(
            3,
            count($data),
            'The right search result got retrieved'
        );

        // Empty result set search
        $this->driver->getConnection()->setFailure(Connection::ERR_NO_RESULT);
        $this->driver->getConnection()->stackResults($set);
        $result = $manager->search();
        $this->assertInstanceOf(
            'Toyota\Component\Ldap\Core\SearchResult',
            $result,
            'Query did not fail - Exception got handled'
        );

        $data = array();
        foreach ($result as $key => $value) {
            $data[$key] = $value->getAttributes();
        }
        $this->assertEquals(
            0,
            count($data),
            'The exception got handled and the search result set has not been set in the query'
        );

        // Alternative parameters search
        $result = $manager->search(
            'ou=other,dc=example,dc=com',
            '(objectclass=test)',
            false,
            array('attr1', 'attr2')
        );
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'ou=other,dc=example,dc=com',
            '(objectclass=test)',
            SearchInterface::SCOPE_ONE,
            array('attr1', 'attr2'),
            $set
        );
        $this->assertInstanceOf('Toyota\Component\Ldap\Core\SearchResult', $result);
    }

    /**
     * Retrieve children Ldap nodes
     *
     * @return void
     */
    public function testGetChildren()
    {
        $manager = new Manager($this->minimal, $this->driver);

        $node = new Node();
        $node->setDn('test_node');

        $set = array();
        $set[] = new Entry('ent1', array('val1'));
        $set[] = new Entry('ent2', array('val2'));
        $set[] = new Entry('ent3', array('val3'));

        // Binding exception handling
        $this->assertBindingFirst($manager, 'getChildrenNodes', array($node));
        $manager->connect();
        $this->assertBindingFirst($manager, 'getChildrenNodes', array($node));
        $manager->bind();

        // Basic behaviour
        $this->driver->getConnection()->stackResults($set);

        $nodes = $manager->getChildrenNodes($node);
        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_node',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE,
            null,
            $set
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'No other log');

        $this->assertTrue(is_array($nodes), 'An array of nodes is retrieved');
        $this->assertCount(3, $nodes);
        $this->assertEquals('ent1', $nodes[0]->getDn());
        $this->assertEquals('ent2', $nodes[1]->getDn());
        $this->assertEquals('ent3', $nodes[2]->getDn());

        // Successful search with no entry in the result set
        $nodes = $manager->getChildrenNodes($node);

        $this->assertSearchLog(
            $this->driver->getConnection()->shiftLog(),
            'test_node',
            '(objectclass=*)',
            SearchInterface::SCOPE_ONE
        );
        $this->assertNull($this->driver->getConnection()->shiftLog(), 'No other log');

        $this->assertTrue(is_array($nodes), 'An array of nodes is retrieved');
        $this->assertCount(0, $nodes);

        // Handling of NoResultException
        $this->driver->getConnection()->setFailure(Connection::ERR_NO_RESULT);

        $nodes = $manager->getChildrenNodes($node);
        $this->assertTrue(is_array($nodes), 'An array of nodes is retrieved');
        $this->assertCount(0, $nodes);

        // Handling of other search exceptions
        $this->driver->getConnection()->setFailure();

        try {
            $nodes = $manager->getChildrenNodes($node);
            $this->fail('Other search exceptions do not get processed and are populated');
        } catch (SearchException $e) {
            $this->assertRegExp('/Search failed/', $e->getMessage());
        }
    }
}