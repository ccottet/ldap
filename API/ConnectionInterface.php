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

use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\Exception\OptionException;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\NoResultException;
use Toyota\Component\Ldap\Exception\SizeLimitException;
use Toyota\Component\Ldap\Exception\MalformedFilterException;
use Toyota\Component\Ldap\Exception\SearchException;
use Toyota\Component\Ldap\API\SearchInterface;

/**
 * Represents a class that enables interacting with a LDAP Connection
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
interface ConnectionInterface
{
    const OPT_DEREF            = LDAP_OPT_DEREF;
    const OPT_SIZELIMIT        = LDAP_OPT_SIZELIMIT;
    const OPT_TIMELIMIT        = LDAP_OPT_TIMELIMIT;
    const OPT_NETWORK_TIMEOUT  = LDAP_OPT_NETWORK_TIMEOUT;
    const OPT_PROTOCOL_VERSION = LDAP_OPT_PROTOCOL_VERSION;
    const OPT_REFERRALS        = LDAP_OPT_REFERRALS;
    const OPT_RESTART          = LDAP_OPT_RESTART;
    const OPT_SERVER_CONTROLS  = LDAP_OPT_SERVER_CONTROLS;
    const OPT_CLIENT_CONTROLS  = LDAP_OPT_CLIENT_CONTROLS;

    const DEREF_NEVER     = LDAP_DEREF_NEVER;
    const DEREF_SEARCHING = LDAP_DEREF_SEARCHING;
    const DEREF_FINDING   = LDAP_DEREF_FINDING;
    const DEREF_ALWAYS    = LDAP_DEREF_ALWAYS;

    /**
     * Set an option
     *
     * @param int      $option     Ldap option name
     * @param mixed    $value      Value to set on Ldap option
     *
     * @return void
     *
     * @throws OptionException if option cannot be set
     */
    public function setOption($option, $value);

    /**
     * Gets current value set for an option
     *
     * @param int      $option     Ldap option name
     *
     * @return mixed value set for the option
     *
     * @throws OptionException if option cannot be retrieved
     */
    public function getOption($option);

    /**
     * Binds to the LDAP directory with specified RDN and password
     *
     * @param string   $rdn        Rdn to use for binding (Default: null)
     * @param string   $password   Plain or hashed password for binding (Default: null)
     *
     * @return void
     *
     * @throws BindException if binding fails
     */
    public function bind($rdn = null, $password = null);

    /**
     * Closes the connection
     *
     * @return void
     *
     * @throws ConnectionException if connection could not be closed
     */
    public function close();

    /**
     * Adds a Ldap entry
     *
     * @param string $dn   Distinguished name to register entry for
     * @param array  $data Ldap attributes to save along with the entry
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be added
     */
    public function addEntry($dn, $data);

    /**
     * Deletes an existing Ldap entry
     *
     * @param string $dn Distinguished name of the entry to delete
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be deleted
     */
    public function deleteEntry($dn);

    /**
     * Adds some value(s) to some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be added for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function addAttributeValues($dn, $data);

    /**
     * Replaces value(s) for some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be set for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function replaceAttributeValues($dn, $data);

    /**
     * Delete value(s) for some entry attribute(s)
     *
     * The data format for attributes is as follows:
     *     array(
     *         'attribute_1' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *         'attribute_2' => array(
     *             'value_1',
     *             'value_2'
     *          ),
     *          ...
     *     );
     *
     * @param string $dn   Distinguished name of the entry to modify
     * @param array  $data Values to be removed for each attribute
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be updated
     */
    public function deleteAttributeValues($dn, $data);

    /**
     * Searches for entries in the directory
     *
     * @param int     $scope      Search scope (ALL, ONE or BASE)
     * @param string  $baseDn     Base distinguished name to look below
     * @param string  $filter     Filter for the search
     * @param array   $attributes Names of attributes to retrieve (Default: All)
     *
     * @return SearchInterface Search result set
     *
     * @throws NoResultException if no result can be retrieved
     * @throws SizeLimitException if size limit got exceeded
     * @throws MalformedFilterException if filter is wrongly formatted
     * @throws SearchException if search failed otherwise
     */
    public function search($scope, $baseDn, $filter, $attributes = null);
}