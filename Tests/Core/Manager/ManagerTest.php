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
use Toyota\Component\Ldap\Exception\NotBoundException;
use Toyota\Component\Ldap\Platform\Test\Driver;
use Toyota\Component\Ldap\Tests\TestCase;

abstract class ManagerTest extends TestCase
{
    /**
     * This method is called before a test is executed
     *
     * @return void
     *
     */
    protected function setUp()
    {
        $this->driver = new Driver();

        $this->minimal = array(
            'hostname' => 'ldap.example.com',
            'base_dn'  => 'dc=example,dc=com'
        );
    }

    /**
     * Asserts exceptions get thrown when user is not bound before performing the
     * given method
     *
     * @param Manager $manager Instance of the manager to use
     * @param string  $method  Name of the Manager method to use
     * @param array   $params  Parameters to give on the method call (Optional)
     *
     * @return void
     */
    protected function assertBindingFirst($manager, $method, $params = array())
    {
        try {
            call_user_func_array(array($manager, $method), $params);
            $this->fail('Connection has to be active before working with the LDAP');
        } catch (NotBoundException $e) {
            $this->assertRegExp('/have to bind/', $e->getMessage());
        }
    }

    /**
     * Asserts manager parameters
     *
     * @param array   $params   Given parameters
     * @param string  $hostname Expected hostname
     * @param int     $port     Expected port
     * @param boolean $withSSL  Whether SSL is active
     * @param boolean $withTLS  Whether TLS is active
     *
     * @return void
     */
    protected function assertConfiguration($params, $hostname, $port, $withSSL, $withTLS)
    {
        $manager = new Manager($params, $this->driver);
        $manager->connect();

        $this->assertEquals($hostname, $this->driver->getHostname(), 'URL prefix is removed');
        $this->assertEquals($port, $this->driver->getPort(), 'Default LDAP port is applied');
        $this->assertEquals($withSSL, $this->driver->hasSSL());
        $this->assertEquals($withTLS, $this->driver->hasTLS());
    }

    /**
     * Asserts binding parameters
     *
     * @param array   $params      Given parameters
     * @param boolean $isBound     Expected hostname
     * @param boolean $isAnonymous Expected port
     * @param string  $dn          Bind dn (Default: null)
     * @param string  $password    Bind password (Default: null)
     *
     * @return void
     */
    protected function assertBinding($params, $isBound, $isAnonymous, $dn = null, $password = null)
    {
        $manager = new Manager($params, $this->driver);
        $manager->connect();
        $manager->bind();
        $instance = $this->driver->getConnection();

        $this->assertEquals($isBound, $instance->isBound());

        if ($isAnonymous) {
            $this->assertNull($instance->getBindDn(), 'Anonymous bind Dn');
            $this->assertNull($instance->getBindPassword(), 'Anonymous bind Password');
        } else {
            $this->assertEquals($dn, $instance->getBindDn(), 'Privileged bind Dn');
            $this->assertEquals($password, $instance->getBindPassword(), 'Privileged bind Password');
        }
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

    /**
     * Asserts a node got snapshot
     *
     * @param Node   $node Node to test
     * @param string $msg  Message logged with assertion (Optional)
     *
     * @return void
     */
    protected function assertSnapshot(Node $node, $msg = null)
    {
        $this->assertEquals(
            array(),
            array_merge(
                $node->getDiffAdditions(),
                $node->getDiffDeletions(),
                $node->getDiffReplacements()
            ),
            $msg
        );
    }

    /**
     * Node factory
     *
     * @param string $dn         Distinguished name for the node
     * @param array  $attributes Array of attributes
     *
     * @return Node node
     */
    protected function buildNode($dn, $attributes)
    {
        $node = new Node();
        $node->setDn($dn);
        foreach ($attributes as $name => $data) {
            $attr = new NodeAttribute($name);
            $attr->add($data);
            $node->mergeAttribute($attr);
        }
        return $node;
    }
}