<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Platform\Native;

use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\API\DriverInterface;

/**
 * Driver implementing interface for php ldap native extension
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Driver implements DriverInterface
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
    ) {
        if ($withSSL && $withTLS) {
            throw new ConnectionException('Cannot support both TLS & SSL for a given Ldap Connection');
        }

        if (! extension_loaded('ldap') && ! @dl('ldap.' . PHP_SHLIB_SUFFIX)) {
            throw new ConnectionException(
                'You do not have the required ldap-extension installed'
            );
        }

        if ($withSSL) {
            $hostname = 'ldaps://' . $hostname;
        }

        $connection = @ldap_connect($hostname, $port);
        if (false === $connection) {
            throw new ConnectionException('Could not successfully connect to the LDAP server');
        }

        if ($withTLS) {
            if (! (@ldap_start_tls($connection))) {
                $code = @ldap_errno($connection);
                throw new ConnectionException(
                    sprintf('Could not start TLS: Ldap Error Code=%s - %s', $code, ldap_err2str($code))
                );
            }
        }

        return new Connection($connection);
    }
}