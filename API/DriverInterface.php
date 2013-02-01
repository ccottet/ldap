<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\API;

use Toyota\Component\Ldap\API\ConnectionInterface;
use Toyota\Component\Ldap\Exception\ConnectionException;

/**
 * Represents a class that enables interacting with an LDAP server
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
interface DriverInterface
{
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
    );
}