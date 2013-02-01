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
use Toyota\Component\Ldap\Platform\Test\Connection;
use Toyota\Component\Ldap\Exception\OptionException;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\NoResultException;
use Toyota\Component\Ldap\Exception\SizeLimitException;
use Toyota\Component\Ldap\Exception\MalformedFilterException;
use Toyota\Component\Ldap\Exception\SearchException;
use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Platform\Test\Search;
use Toyota\Component\Ldap\Platform\Test\Entry;

class ConnectionTest extends TestCase
{
    /**
     * Tests options accessors
     *
     * @return void
     */
    public function testOptionsAccessors()
    {
        $connection = new Connection();

        $connection->setOption('a', 1);
        $connection->setOption('b', 2);
        $connection->setOption('c', 3);

        $this->assertEquals(1, $connection->getOption('a'));
        $this->assertEquals(2, $connection->getOption('b'));
        $this->assertEquals(3, $connection->getOption('c'));

        $connection->setOption('b', 4);
        $this->assertEquals(4, $connection->getOption('b'));

        $connection->setFailure();
        try {
            $connection->setOption('c', 5);
            $this->fail('If any failure code is set, the next connection operation fails');
        } catch (OptionException $e) {
            $this->assertRegExp('/could not set option/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertEquals(3, $connection->getOption('c'), 'c was not changed');

        $connection->setFailure();
        try {
            $value = $connection->getOption('c');
            $this->fail('Failure codes also triggers exceptions when getting option');
        } catch (OptionException $e) {
            $this->assertRegExp('/could not retrieve option/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_SEARCH);
        $connection->setOption('c', 5);
        $this->assertEquals(5, $connection->getOption('c'), 'c got changed');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Search scope failures are ignored when accessing an option'
        );

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        $connection->setOption('c', 3);
        $this->assertEquals(3, $connection->getOption('c'), 'c got changed');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Persist scope failures are ignored when accessing an option'
        );
    }

    /**
     * Tests binding
     *
     * @return void
     */
    public function testBinding()
    {
        $connection = new Connection();

        $connection->setFailure();
        try {
            $connection->bind();
            $this->fail('Failure codes triggers exceptions when binding');
        } catch (BindException $e) {
            $this->assertRegExp('/could not bind user/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertFalse($connection->isBound(), 'On exceptions, binding does not happen');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_SEARCH);
        $connection->bind();
        $this->assertTrue($connection->isBound(), 'Binding is not impacted with search failures');
        $connection->close();
        $this->assertFalse($connection->isBound(), 'Closing is not either');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Search scope failures are ignored when binding/unbinding'
        );

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        $connection->bind();
        $this->assertTrue($connection->isBound(), 'Binding is not impacted with persist failures');
        $connection->close();
        $this->assertFalse($connection->isBound(), 'Closing is not either');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Persist scope failures are ignored when binding/unbinding'
        );

        try {
            $connection->bind(null, 'password');
            $this->fail('Either both are set or both are nulls - rdn only is null here');
        } catch (BindException $e) {
            $this->assertRegExp(
                '/For an anonymous binding, both rdn & passwords have to be null/',
                $e->getMessage()
            );
        }
        $this->assertFalse($connection->isBound());

        try {
            $connection->bind('rdn');
            $this->fail('Either both are set or both are nulls - password only is null here');
        } catch (BindException $e) {
            $this->assertRegExp(
                '/For an anonymous binding, both rdn & passwords have to be null/',
                $e->getMessage()
            );
        }
        $this->assertFalse($connection->isBound());

        $connection->bind();
        $this->assertNull($connection->getBindDn());
        $this->assertNull($connection->getBindPassword());
        $this->assertTrue($connection->isBound());

        $connection->bind('rdn', 'password');
        $this->assertEquals('rdn', $connection->getBindDn());
        $this->assertEquals('password', $connection->getBindPassword());
        $this->assertTrue($connection->isBound(), 'Rebinding is supported');

        $connection->close();
        $this->assertNull($connection->getBindDn());
        $this->assertNull($connection->getBindPassword());
        $this->assertFalse($connection->isBound());
        $connection->close();
        $this->assertFalse($connection->isBound(), 'Closing can be done multiple times');

        $connection->bind();
        $this->assertTrue($connection->isBound(), 'Connection is bound again');

        $connection->setFailure();
        try {
            $connection->close();
            $this->fail('Failure codes triggers exceptions when closing connection');
        } catch (BindException $e) {
            $this->assertRegExp('/could not unbind user/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertTrue($connection->isBound(), 'Connection is still bound');
    }

    /**
     * Tests basic persistence behaviour
     *
     * @return void
     */
    public function testBasicPersistence()
    {
        $connection = new Connection();

        $connection->addEntry('dn0', array('other', 'data'));
        $connection->deleteEntry('dn1');
        $connection->addAttributeValues('dn2', array('attr1', 'attr2'));
        $connection->replaceAttributeValues('dn3', array('attr3'));
        $connection->deleteAttributeValues('dn4', array('test'));

        $connection->addEntry('test', array('data'));
        $connection->deleteEntry('test');
        $connection->addAttributeValues('test', array('attr0'));
        $connection->replaceAttributeValues('test', array('attr1', 'attr4'));
        $connection->deleteAttributeValues('test', array());

        $this->assertActionLog($connection->shiftLog(), 'create', 'dn0', array('other', 'data'));
        $this->assertActionLog($connection->shiftLog(), 'delete', 'dn1');
        $this->assertActionLog($connection->shiftLog(), 'attr_add', 'dn2', array('attr1', 'attr2'));
        $this->assertActionLog($connection->shiftLog(), 'attr_rep', 'dn3', array('attr3'));
        $this->assertActionLog($connection->shiftLog(), 'attr_del', 'dn4', array('test'));
        $this->assertActionLog($connection->shiftLog(), 'create', 'test', array('data'));
        $this->assertActionLog($connection->shiftLog(), 'delete', 'test');
        $this->assertActionLog($connection->shiftLog(), 'attr_add', 'test', array('attr0'));
        $this->assertActionLog($connection->shiftLog(), 'attr_rep', 'test', array('attr1', 'attr4'));
        $this->assertActionLog($connection->shiftLog(), 'attr_del', 'test', array());
        $this->assertNull($connection->shiftLog(), 'No more logs available');
    }

    /**
     * Tests persistence exceptions handling
     *
     * @return void
     */
    public function testPersistenceExceptionHandling()
    {
        $connection = new Connection();

        // Overall scope failures
        $connection->setFailure();
        try {
            $connection->addEntry('test', array('test'));
            $this->fail('Failure codes triggers exceptions when adding entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add entry/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Adding entry ignored');

        $connection->setFailure();
        try {
            $connection->deleteEntry('test');
            $this->fail('Failure codes triggers exceptions when deleting entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not delete entry/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Deleting entry ignored');

        $connection->setFailure();
        try {
            $connection->addAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when adding attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Adding attribute ignored');

        $connection->setFailure();
        try {
            $connection->replaceAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when replacing attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not replace attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Replacing attribute ignored');

        $connection->setFailure();
        try {
            $connection->deleteAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when deleting attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not delete attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Deleting attribute ignored');

        // Persistence scope failures
        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        try {
            $connection->addEntry('test', array('test'));
            $this->fail('Failure codes triggers exceptions when adding entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add entry/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Adding entry ignored');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        try {
            $connection->deleteEntry('test');
            $this->fail('Failure codes triggers exceptions when deleting entry');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not delete entry/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Deleting entry ignored');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        try {
            $connection->addAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when adding attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not add attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Adding attribute ignored');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        try {
            $connection->replaceAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when replacing attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not replace attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Replacing attribute ignored');

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        try {
            $connection->deleteAttributeValues('test', array('test'));
            $this->fail('Failure codes triggers exceptions when deleting attributes');
        } catch (PersistenceException $e) {
            $this->assertRegExp('/could not delete attributes/', $e->getMessage());
        }
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');
        $this->assertNull($connection->shiftLog(), 'Deleting attribute ignored');

        // Search scope failures
        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_SEARCH);
        $connection->addEntry('test', array('test'));
        $connection->deleteEntry('test');
        $connection->addAttributeValues('test', array('test'));
        $connection->replaceAttributeValues('test', array('test'));
        $connection->deleteAttributeValues('test', array('test'));
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Failure code got ignored'
        );
    }

    /**
     * Tests performing searches
     *
     * @return void
     */
    public function testSearch()
    {
        $connection = new Connection();

        // Basic search with default parameters
        $search = $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
        $this->assertInstanceOf('Toyota\Component\Ldap\API\SearchInterface', $search);
        $this->assertEquals(SearchInterface::SCOPE_ALL, $search->getScope());
        $this->assertEquals('base', $search->getBaseDn());
        $this->assertEquals('filter', $search->getFilter());
        $this->assertNull($search->getAttributes());
        $this->assertEquals(array(), $search->getEntries());
        $this->assertSearchLog(
            $connection->shiftLog(),
            'base',
            'filter',
            SearchInterface::SCOPE_ALL,
            null,
            array()
        );
        $this->assertNull($connection->shiftLog(), 'No other search got performed');

        // Basic search with alternative parameters and result set stack
        $resultSet = array(new Entry('dn1'), new Entry('dn2'));
        $connection->stackResults($resultSet);
        $search = $connection->search(
            SearchInterface::SCOPE_ONE,
            'other_base',
            'other_filter',
            array('attr1', 'attr2')
        );
        $this->assertInstanceOf('Toyota\Component\Ldap\API\SearchInterface', $search);
        $this->assertEquals(SearchInterface::SCOPE_ONE, $search->getScope());
        $this->assertEquals('other_base', $search->getBaseDn());
        $this->assertEquals('other_filter', $search->getFilter());
        $this->assertEquals(array('attr1', 'attr2'), $search->getAttributes());
        $this->assertEquals($resultSet, $search->getEntries());
        $this->assertSearchLog(
            $connection->shiftLog(),
            'other_base',
            'other_filter',
            SearchInterface::SCOPE_ONE,
            array('attr1', 'attr2'),
            $resultSet
        );
        $this->assertNull($connection->shiftLog(), 'No other search got performed');

        // Typical unique entry search
        $search = $connection->search(
            SearchInterface::SCOPE_BASE,
            'dn',
            '(objectclass=*)',
            array('*', '+')
        );
        $this->assertInstanceOf('Toyota\Component\Ldap\API\SearchInterface', $search);
        $this->assertEquals(SearchInterface::SCOPE_BASE, $search->getScope());
        $this->assertEquals('dn', $search->getBaseDn());
        $this->assertEquals('(objectclass=*)', $search->getFilter());
        $this->assertEquals(array('*', '+'), $search->getAttributes());
        $this->assertEquals(array(), $search->getEntries());
        $this->assertSearchLog(
            $connection->shiftLog(),
            'dn',
            '(objectclass=*)',
            SearchInterface::SCOPE_BASE,
            array('*', '+'),
            array()
        );
        $this->assertNull($connection->shiftLog(), 'No other search got performed');
    }

    /**
     * Tests searches exception handling
     *
     * @return void
     */
    public function testSearchesExceptionHandling()
    {
        $connection = new Connection();

        $results = array(new Entry('test'));

        // Overall scope failures
        $connection->stackResults($results);
        $connection->setFailure();
        try {
            $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
            $this->fail('Failure codes triggers general search exceptions when searching');
        } catch (SearchException $e) {
            $this->assertRegExp('/Search failed/', $e->getMessage());
            $this->assertEquals(
                'Toyota\Component\Ldap\Exception\SearchException',
                get_class($e),
                'Not a class inherited from SearchException'
            );
        }
        $this->assertNull($connection->shiftLog(), 'No search got performed');
        $this->assertTrue(is_array($connection->shiftResults()), 'Results not unstacked');
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');

        // Malformed filter failures
        $connection->stackResults($results);
        $connection->setFailure(Connection::ERR_MALFORMED_FILTER);
        try {
            $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
            $this->fail('A MalformedFilterException is expected at this stage');
        } catch (MalformedFilterException $e) {
            $this->assertRegExp('/Malformed filter/', $e->getMessage());
        }
        $this->assertNull($connection->shiftLog(), 'No search got performed');
        $this->assertTrue(is_array($connection->shiftResults()), 'Results not unstacked');
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');

        // Size limit failures
        $connection->stackResults($results);
        $connection->setFailure(Connection::ERR_SIZE_LIMIT);
        try {
            $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
            $this->fail('A SizeLimitException is expected at this stage');
        } catch (SizeLimitException $e) {
            $this->assertRegExp('/Size limit reached/', $e->getMessage());
        }
        $this->assertNull($connection->shiftLog(), 'No search got performed');
        $this->assertTrue(is_array($connection->shiftResults()), 'Results not unstacked');
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');

        // No result failures
        $connection->stackResults($results);
        $connection->setFailure(Connection::ERR_NO_RESULT);
        try {
            $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
            $this->fail('A NoResultException is expected at this stage');
        } catch (NoResultException $e) {
            $this->assertRegExp('/No result retrieved/', $e->getMessage());
        }
        $this->assertNull($connection->shiftLog(), 'No search got performed');
        $this->assertTrue(is_array($connection->shiftResults()), 'Results not unstacked');
        $this->assertFalse($connection->isFailureExpected(), 'Failure code got consumed');

        // Search scope failures
        $connection->stackResults($results);
        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_SEARCH);
        try {
            $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
            $this->fail('Search scope for failure prevents searching');
        } catch (SearchException $e) {
            $this->assertRegExp('/Search failed/', $e->getMessage());
        }
        $this->assertNull($connection->shiftLog(), 'No search got performed');
        $this->assertTrue(is_array($connection->shiftResults()), 'Results not unstacked');
        $this->assertFalse(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Failure code got consumed'
        );

        // Persistence scope failures
        $connection->stackResults($results);
        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        $search = $connection->search(SearchInterface::SCOPE_ALL, 'base', 'filter');
        $this->assertSearchLog(
            $connection->shiftLog(),
            'base',
            'filter',
            SearchInterface::SCOPE_ALL,
            null,
            $results
        );
        $this->assertEquals($results, $search->getEntries());
        $this->assertNull($connection->shiftResults(), 'Results got unstacked');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Failure code got ignored'
        );
    }

    /**
     * Tests failure code & scope handling
     *
     * @return void
     */
    public function testFailureHandling()
    {
        $connection = new Connection();

        $this->assertFalse($connection->isFailureExpected(), 'No failure code set yet');
        $this->assertFalse(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'None planned for the search scope either'
        );
        $this->assertFalse(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'None planned for the persistence scope either'
        );

        $connection->setFailure();
        $this->assertTrue($connection->isFailureExpected(), 'Default failure registered');
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Defaults covers also search scope'
        );
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Defaults covers also persistence scope'
        );

        $connection->setFailure(Connection::ERR_SIZE_LIMIT);
        $this->assertTrue(
            $connection->isFailureExpected(),
            'Code is set but all scopes should still be covered'
        );
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Overall scope covers also search scope'
        );
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Overall scope covers also persistence scope'
        );

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_SEARCH);
        $this->assertFalse(
            $connection->isFailureExpected(),
            'Only the search scope will trigger the failure'
        );
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Search scope is indeed covered'
        );
        $this->assertFalse(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Persistence scope does not match the expected scope'
        );

        $connection->setFailure(Connection::ERR_DEFAULT, Connection::FAIL_COND_PERSIST);
        $this->assertFalse(
            $connection->isFailureExpected(),
            'Only the persistence scope will trigger the failure'
        );
        $this->assertFalse(
            $connection->isFailureExpected(Connection::FAIL_COND_SEARCH),
            'Search scope does not match the expected scope'
        );
        $this->assertTrue(
            $connection->isFailureExpected(Connection::FAIL_COND_PERSIST),
            'Persistence scope is indeed covered'
        );
    }

    /**
     * Tests stacking and logging
     *
     * @return void
     */
    public function testStackingAndLogging()
    {
        $connection = new Connection();


        $this->assertNull($connection->shiftLog(), 'No log to start with');
        $this->assertNull($connection->shiftLog(), 'Still no log, can be called at will');

        $sets = array();
        $sets[] = array(new Entry('dn1'), new Entry('ou1-dn1'), new Entry('ou11-ou1-dn1'));
        $sets[] = array(new Entry('ou1-dn2'), new Entry('ou1-dn1'));
        $sets[] = array(new Entry('dn3'));
        $connection->stackResults($sets[0]);
        $connection->stackResults($sets[1]);
        $connection->stackResults($sets[2]);

        $connection->search(SearchInterface::SCOPE_ALL, 'dn1', 'filter1', array('a1'));
        $connection->search(SearchInterface::SCOPE_ONE, 'dn2', 'filter2', array('a2'));
        $connection->search(SearchInterface::SCOPE_BASE, 'dn3', 'filter3', array('a3'));

        $connection->addEntry('dn4', array('a4' => 'v4'));
        $connection->deleteEntry('dn5');
        $connection->addAttributeValues('dn6', array('a6' => 'v6'));
        $connection->replaceAttributeValues('dn7', array('a7' => 'v7'));
        $connection->deleteAttributeValues('dn8', array('a8' => 'v8'));

        $this->assertSearchLog(
            $connection->shiftLog(),
            'dn1',
            'filter1',
            SearchInterface::SCOPE_ALL,
            array('a1'),
            $sets[0]
        );
        $this->assertSearchLog(
            $connection->shiftLog(),
            'dn2',
            'filter2',
            SearchInterface::SCOPE_ONE,
            array('a2'),
            $sets[1]
        );
        $this->assertSearchLog(
            $connection->shiftLog(),
            'dn3',
            'filter3',
            SearchInterface::SCOPE_BASE,
            array('a3'),
            $sets[2]
        );
        $this->assertNull($connection->shiftResults(), 'All result sets have been consumed');

        $this->assertActionLog($connection->shiftLog(), 'create', 'dn4', array('a4' => 'v4'));
        $this->assertActionLog($connection->shiftLog(), 'delete', 'dn5');
        $this->assertActionLog($connection->shiftLog(), 'attr_add', 'dn6', array('a6' => 'v6'));
        $this->assertActionLog($connection->shiftLog(), 'attr_rep', 'dn7', array('a7' => 'v7'));
        $this->assertActionLog($connection->shiftLog(), 'attr_del', 'dn8', array('a8' => 'v8'));

        $this->assertNull($connection->shiftLog(), 'No other logs got stacked');
    }

    /**
     * Asserts given log is the expected search log
     *
     * @param array  $log        Tested log entry
     * @param string $dn         Base dn for the search
     * @param string $filter     Search filter
     * @param int    $scope      Search scope (ALL, BASE, ONE)
     * @param array  $attributes Attributes searched (Optional)
     * @param array  $entries    Entries expected in the result set (Optional)
     *
     * @return void
     */
    protected function assertSearchLog(
        $log,
        $dn,
        $filter,
        $scope,
        $attributes = null,
        $entries = null) {

        $this->assertEquals('search', $log['type']);
        $this->assertInstanceOf('Toyota\Component\Ldap\Platform\Test\Search', $log['data']);
        $this->assertEquals($dn, $log['data']->getBaseDn());
        $this->assertEquals($filter, $log['data']->getFilter());
        $this->assertEquals($scope, $log['data']->getScope());
        if (null === $attributes) {
            $this->assertNull($log['data']->getAttributes());
        } else {
            $this->assertEquals($attributes, $log['data']->getAttributes());
        }
        if (null === $entries) {
            $this->assertEquals(array(), $log['data']->getEntries());
        } else {
            $this->assertEquals($entries, $log['data']->getEntries());
        }
    }

    /**
     * Asserts given log is the expected persistence action log
     *
     * @param array  $log    Tested log entry
     * @param string $action Name of persistence action (create, delete, attr_(add/rep/del))
     * @param string $dn     Dn of the entry subject for the action
     * @param array  $data   Attributes data passed along with the action (Optional)
     *
     * @return void
     */
    protected function assertActionLog($log, $action, $dn, $data = null)
    {
        $this->assertEquals($action, $log['type']);
        $this->assertEquals($dn, $log['data']['dn']);
        if (null === $data) {
            $this->assertArrayNotHasKey('attributes', $log['data']);
        } else {
            $this->assertEquals($data, $log['data']['attributes']);
        }
    }
}
