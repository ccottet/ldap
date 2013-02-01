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
use Toyota\Component\Ldap\API\ConnectionInterface;

class ManagerConnectTest extends ManagerTest
{
    /**
     * Tests error handling for missing parameters at construction
     *
     * @return void
     */
    public function testConstructionErrorHandling()
    {
        try {
            $manager = new Manager(array(), $this->driver);
            $this->fail('hostname and base_dn are required parameters');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/hostname/', $e->getMessage());
            $this->assertRegExp('/base_dn/', $e->getMessage());
            $this->assertRegExp('/parameters missing/', $e->getMessage());
        }

        try {
            $manager = new Manager(array('hostname' => 'ldap.example.com'), $this->driver);
            $this->fail('hostname alone is not enough are required parameters');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotRegExp('/hostname/', $e->getMessage());
            $this->assertRegExp('/base_dn/', $e->getMessage());
            $this->assertRegExp('/parameters missing/', $e->getMessage());
        }

        try {
            $manager = new Manager(array('base_dn' => 'dc=example,dc=com'), $this->driver);
            $this->fail('base_dn alone is not enough are required parameters');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/hostname/', $e->getMessage());
            $this->assertNotRegExp('/base_dn/', $e->getMessage());
            $this->assertRegExp('/parameters missing/', $e->getMessage());
        }

        $params = $this->minimal;
        $params['security'] = 'UNSUPPORTED';
        try {
            $manager = new Manager($params, $this->driver);
            $this->fail('Only TLS or SSL are supported for security setting');
        } catch (\InvalidArgumentException $e) {
            $this->assertRegExp('/UNSUPPORTED not supported/', $e->getMessage());
            $this->assertRegExp('/only SSL or TLS/', $e->getMessage());
        }

        $manager = new Manager($this->minimal, $this->driver);
        $manager->connect();
        $this->assertInstanceOf(
            'Toyota\Component\Ldap\API\ConnectionInterface',
            $this->driver->getConnection(),
            'Connection has been started'
        );
    }

    /**
     * Tests connection sequence with all parameters
     *
     * @return void
     */
    public function testConnectionSequence()
    {
        $params                  = $this->minimal;
        $params['port']          = 999;
        $params['security']      = 'SSL';
        $params['bind_dn']       = 'cn=admin,dc=example,dc=com';
        $params['bind_password'] = 'secret';
        $options = array(
            ConnectionInterface::OPT_REFERRALS => 0,
            ConnectionInterface::OPT_TIMELIMIT => 100
        );
        $params['options'] = $options;

        $manager = new Manager($params, $this->driver);
        $manager->connect();

        $this->assertEquals('ldap.example.com', $this->driver->getHostname());
        $this->assertEquals(999, $this->driver->getPort());
        $this->assertTrue($this->driver->hasSSL());
        $this->assertFalse($this->driver->hasTLS());

        $instance = $this->driver->getConnection();
        $this->assertInstanceOf(
            'Toyota\Component\Ldap\API\ConnectionInterface',
            $instance,
            'Connection has been started'
        );
        $this->assertEquals(0, $instance->getOption(ConnectionInterface::OPT_REFERRALS));
        $this->assertEquals(100, $instance->getOption(ConnectionInterface::OPT_TIMELIMIT));

        $this->assertFalse($instance->isBound());
        $manager->bind();
        $this->assertTrue($instance->isBound());
        $this->assertEquals('cn=admin,dc=example,dc=com', $instance->getBindDn());
        $this->assertEquals('secret', $instance->getBindPassword());
    }

    /**
     * Tests cleaning protocol configuration parameters
     *
     * @return void
     */
    public function testCleaningProtocolParameters()
    {
        $params = $this->minimal;
        $params['hostname'] = 'ldap://ldap.example.com';
        $this->assertConfiguration($params, 'ldap.example.com', 389, false, false);

        $params['security'] = 'SSL';
        $this->assertConfiguration($params, 'ldap.example.com', 636, true, false);

        $params['port'] = 999;
        $this->assertConfiguration($params, 'ldap.example.com', 999, true, false);

        $params = $this->minimal;
        $params['hostname'] = 'ldaps://ldap.example.com';
        $this->assertConfiguration($params, 'ldap.example.com', 636, true, false);

        $params['port'] = 389;
        $this->assertConfiguration($params, 'ldap.example.com', 389, true, false);

        $params['security'] = 'SSL';
        $this->assertConfiguration($params, 'ldap.example.com', 389, true, false);

        $params['security'] = 'TLS';
        $this->assertConfiguration($params, 'ldap.example.com', 389, false, true);

        $params = $this->minimal;
        $params['hostname'] = 'ldap://ldap.example.com';
        $params['security'] = 'TLS';
        $this->assertConfiguration($params, 'ldap.example.com', 389, false, true);

        $params['port'] = 999;
        $this->assertConfiguration($params, 'ldap.example.com', 999, false, true);
    }

    /**
     * Tests cleaning binding parameters
     *
     * @return void
     */
    public function testCleaningBindingParameters()
    {
        $params = $this->minimal;
        $this->assertBinding($params, true, true);

        $params['bind_password'] = '';
        $this->assertBinding($params, true, true);

        $params['bind_password'] = 'test';
        $this->assertBinding($params, true, true);

        $params['bind_dn'] = '';
        $this->assertBinding($params, true, true);

        $params['bind_dn'] = 'bind_test';
        $this->assertBinding($params, true, false, 'bind_test', 'test');

        $params['bind_password'] = '';
        $this->assertBinding($params, true, false, 'bind_test', '');

        unset($params['bind_password']);
        $this->assertBinding($params, true, false, 'bind_test', '');
    }

    /**
     * Tests alternative binding
     *
     * @return void
     */
    public function testAlternativeBinding()
    {
        $params = $this->minimal;
        $params['bind_dn']       = 'default_dn';
        $params['bind_password'] = 'default_password';
        $manager = new Manager($params, $this->driver);
        $manager->connect();

        $instance = $this->driver->getConnection();

        $manager->bind();
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'default_dn',
            $instance->getBindDn(),
            'Default credential got used'
        );
        $this->assertEquals(
            'default_password',
            $instance->getBindPassword(),
            'Default credential got used'
        );

        $manager->bind(null, '');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'default_dn',
            $instance->getBindDn(),
            'Default credential got used'
        );
        $this->assertEquals(
            'default_password',
            $instance->getBindPassword(),
            'Default credential got used'
        );

        $manager->bind(null, 'alt_pass');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'default_dn',
            $instance->getBindDn(),
            'Default credential got used'
        );
        $this->assertEquals(
            'default_password',
            $instance->getBindPassword(),
            'Default credential got used'
        );

        $manager->bind('', 'alt_pass');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'default_dn',
            $instance->getBindDn(),
            'Default credential got used'
        );
        $this->assertEquals(
            'default_password',
            $instance->getBindPassword(),
            'Default credential got used'
        );

        $manager->bind('alt_dn', 'alt_pass');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'alt_dn',
            $instance->getBindDn(),
            'Now alternative binding occurs'
        );
        $this->assertEquals(
            'alt_pass',
            $instance->getBindPassword(),
            'Alternative password got used'
        );

        $manager->bind('alt_dn', '');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'alt_dn',
            $instance->getBindDn(),
            'Now alternative binding occurs'
        );
        $this->assertEquals(
            '',
            $instance->getBindPassword(),
            'Alternative password got used'
        );

        $manager->bind('alt_dn');
        $this->assertTrue($instance->isBound(), 'Binding occured');
        $this->assertEquals(
            'alt_dn',
            $instance->getBindDn(),
            'Now alternative binding occurs'
        );
        $this->assertEquals(
            '',
            $instance->getBindPassword(),
            'Default empty password got used'
        );
    }
}