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
use Toyota\Component\Ldap\Exception\OptionException;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\NoResultException;
use Toyota\Component\Ldap\Exception\SizeLimitException;
use Toyota\Component\Ldap\Exception\MalformedFilterException;
use Toyota\Component\Ldap\Exception\SearchException;
use Toyota\Component\Ldap\API\ConnectionInterface;

/**
 * Connection implementing interface for test
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Connection implements ConnectionInterface
{
    const ERR_NO_RESULT        = 1;
    const ERR_SIZE_LIMIT       = 2;
    const ERR_MALFORMED_FILTER = 3;
    const ERR_DEFAULT          = 9;

    const FAIL_COND_SEARCH  = 1;
    const FAIL_COND_PERSIST = 2;
    const FAIL_COND_NONE    = 9;

    protected $failure = null;

    protected $logs = array();

    protected $stack = array();

    protected $options = array();

    protected $bindDn;

    protected $bindPassword;

    protected $isBound = false;

    /**
     * Set an option
     *
     * @param int   $option Ldap option name
     * @param mixed $value  Value to set on Ldap option
     *
     * @return void
     *
     * @throws OptionException if option cannot be set
     */
    public function setOption($option, $value)
    {
        if ($this->popFailure()) {
            throw new OptionException('could not set option');
        }
        $this->options[$option] = $value;
    }

    /**
     * Gets current value set for an option
     *
     * @param int $option Ldap option name
     *
     * @return mixed value set for the option
     *
     * @throws OptionException if option cannot be retrieved
     */
    public function getOption($option)
    {
        if ($this->popFailure()) {
            throw new OptionException('could not retrieve option');
        }
        return $this->options[$option];
    }

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
    public function bind($rdn = null, $password = null)
    {
        if ($this->popFailure()) {
            throw new BindException('could not bind user');
        }

        if ((null === $rdn) || (null === $password)) {
            if ((null !== $rdn) || (null !== $password)) {
                throw new BindException(
                    'For an anonymous binding, both rdn & passwords have to be null'
                );
            }
        }

        $this->bindDn       = $rdn;
        $this->bindPassword = $password;
        $this->isBound      = true;
    }

    /**
     * Closes the connection
     *
     * @return void
     *
     * @throws ConnectionException if connection could not be closed
     */
    public function close()
    {
        if ($this->popFailure()) {
            throw new BindException('could not unbind user');
        }

        $this->bindDn       = null;
        $this->bindPassword = null;
        $this->isBound      = false;
    }

    /**
     * Checks if user is bound with the connection
     *
     * @return boolean
     */
    public function isBound()
    {
        return $this->isBound;
    }

    /**
     * Retrieve bound user dn
     *
     * @return string
     */
    public function getBindDn()
    {
        return $this->bindDn;
    }

    /**
     * Retrieve bound user password
     *
     * @return string
     */
    public function getBindPassword()
    {
        return $this->bindPassword;
    }

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
    public function addEntry($dn, $data)
    {
        if ($this->popFailure(self::FAIL_COND_PERSIST)) {
            throw new PersistenceException('could not add entry');
        }

        $this->logPersistence('create', $dn, $data);
    }

    /**
     * Deletes an existing Ldap entry
     *
     * @param string $dn Distinguished name of the entry to delete
     *
     * @return void
     *
     * @throws PersistenceException if entry could not be deleted
     */
    public function deleteEntry($dn)
    {
        if ($this->popFailure(self::FAIL_COND_PERSIST)) {
            throw new PersistenceException('could not delete entry');
        }
        $this->logPersistence('delete', $dn);
    }

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
    public function addAttributeValues($dn, $data)
    {
        if ($this->popFailure(self::FAIL_COND_PERSIST)) {
            throw new PersistenceException('could not add attributes');
        }
        $this->logPersistence('attr_add', $dn, $data);
    }

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
    public function replaceAttributeValues($dn, $data)
    {
        if ($this->popFailure(self::FAIL_COND_PERSIST)) {
            throw new PersistenceException('could not replace attributes');
        }
        $this->logPersistence('attr_rep', $dn, $data);
    }

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
    public function deleteAttributeValues($dn, $data)
    {
        if ($this->popFailure(self::FAIL_COND_PERSIST)) {
            throw new PersistenceException('could not delete attributes');
        }
        $this->logPersistence('attr_del', $dn, $data);
    }

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
    public function search($scope, $baseDn, $filter, $attributes = null)
    {
        $this->processSearchFailure();

        $search = new Search();
        $search->setBaseDn($baseDn);
        $search->setFilter($filter);
        $search->setAttributes($attributes);
        $search->setScope($scope);
        $search->setEntries($this->shiftResults());

        $this->logSearch($search);

        return $search;
    }

    /**
     * Stacks a result set for next searches
     *
     * @param array(Entry) $entries An array of Ldap entries
     *
     * @return void
     */
    public function stackResults($entries)
    {
        $this->stack[] = $entries;
    }

    /**
     * Shifts a set of entries from the top to feed a search result set
     *
     * @return array(Entry) or null
     */
    public function shiftResults()
    {
        return array_shift($this->stack);
    }

    /**
     * Sets a failure for the next method calls
     *
     * @param int $code  Code of the failure to trigger (Optional)
     * @param int $scope Scope of the failure to trigger (Optional)
     *
     * @return void
     */
    public function setFailure($code = null, $scope = null)
    {
        $this->failure = array(
            'code'  => (null === $code)?self::ERR_DEFAULT:$code,
            'scope' => (null === $scope)?self::FAIL_COND_NONE:$scope
        );
    }

    /**
     * Is a failure expected for the next method call
     *
     * @param int $scope Scope of the failure to trigger (Optional)
     *
     * @return boolean
     */
    public function isFailureExpected($scope = null)
    {
        if ((self::FAIL_COND_NONE !== $this->failure['scope'])
            && ($scope != $this->failure['scope'])) {
            return false;
        }
        return is_array($this->failure);
    }

    /**
     * Shifts next logged action from the log stack
     *
     * @return array Log data (null if none available)
     */
    public function shiftLog()
    {
        return array_shift($this->logs);
    }

    /**
     * Shared exception handling method
     *
     * @return void
     *
     * @throws NoResultException if no result can be retrieved
     * @throws SizeLimitException if size limit got exceeded
     * @throws MalformedFilterException if filter is wrongly formatted
     * @throws SearchException if search failed otherwise
     */
    protected function processSearchFailure()
    {
        if (! ($code = $this->popFailure(self::FAIL_COND_SEARCH))) {
            return;
        }

        switch ($code) {

        case self::ERR_NO_RESULT:
            throw new NoResultException('No result retrieved for the given search');
            break;
        case self::ERR_SIZE_LIMIT:
            throw new SizeLimitException('Size limit reached while performing the expected search');
            break;
        case self::ERR_MALFORMED_FILTER:
            throw new MalformedFilterException('Malformed filter while searching');
            break;
        default:
            throw new SearchException('Search failed');
        }
    }

    /**
     * Retrieves a failure code if failure is expected
     *
     * @param int $scope Failure scope (Optional)
     *
     * @return int failure code or false if no failure is expected
     */
    protected function popFailure($scope = null)
    {
        if (! $this->isFailureExpected($scope)) {
            return false;
        }
        $code = $this->failure['code'];
        $this->failure = null;
        return $code;
    }

    /**
     * Logs a search for tests results analysis
     *
     * @param Search $search Search to log
     *
     * @return void
     */
    protected function logSearch(Search $search)
    {
        $this->logs[] = array(
            'type' => 'search',
            'data' => $search
        );
    }

    /**
     * Logs a persistence action for tests results analysis
     *
     * @param string $action     Name of action logged
     * @param string $dn         Distinguished name of the entry on which action is performed
     * @param array  $attributes Attributes used for the action (Optional)
     *
     * @return void
     */
    protected function logPersistence($action, $dn, $attributes = null)
    {
        $data = array(
            'dn' => $dn
        );
        if (null !== $attributes) {
            $data['attributes'] = $attributes;
        }
        $this->logs[] = array(
            'type' => $action,
            'data' => $data
        );
    }

}