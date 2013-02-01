<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Platform\Test;

use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\API\DriverInterface;

/**
 * Driver implementing interface for test purpose
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Driver implements DriverInterface
{
    protected $failureFlag = false;

    protected $connection;

    protected $hostname;

    protected $port;

    protected $withSSL;

    protected $withTLS;

    /**
     * Connects to a Ldap directory without binding
     *
     * @param string  $hostname Hostname to connect to
     * @param int     $port     Port to connect to (Default: 389)
     * @param boolean $withSSL  Whether to connect with SSL support (Default: false)
     * @param boolean $withTLS  Whether to connect with TLS support (Default: false)
     *
     * @return ConnectionInterface connection instance
     *
     * @throws ConnectionException if connection fails
     */
    public function connect(
        $hostname,
        $port = 389,
        $withSSL = false,
        $withTLS = false
    ) {
        if ($this->failureFlag) {
            throw new ConnectionException('Cannot connect');
        }

        $this->hostname = $hostname;
        $this->port = $port;
        $this->withSSL = $withSSL;
        $this->withTLS = $withTLS;
        $this->connection = new Connection();

        return $this->connection;
    }

    /**
     * Sets connection failure flag
     *
     * @param boolean $enabled True for enabling failure, false otherwise (Default: true)
     *
     * @return void
     */
    public function setFailureFlag($enabled = true)
    {
        $this->failureFlag = $enabled;
    }

    /**
     * Accessor for hostname
     *
     * @return string Hostname
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Accessor for port
     *
     * @return int Port
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Checks if SSL is active
     *
     * @return boolean
     */
    public function hasSSL()
    {
        return $this->withSSL;
    }

    /**
     * Checks if TLS is active
     *
     * @return boolean
     */
    public function hasTLS()
    {
        return $this->withTLS;
    }

    /**
     * Retrieves built connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}