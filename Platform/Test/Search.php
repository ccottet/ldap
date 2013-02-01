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

use Toyota\Component\Ldap\API\SearchInterface;

/**
 * Implementation of the search interface for test
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Search implements SearchInterface
{
    protected $baseDn;

    protected $filter;

    protected $attributes;

    protected $scope;

    protected $entries = array();

    /**
     * Retrieves next available entry from the search result set
     *
     * @return EntryInterface next entry if available, null otherwise
     */
    public function next()
    {
        list($key, $entry) = each($this->entries);
        if (false === $entry) {
            return null;
        }
        return $entry;
    }

    /**
     * Resets entry iterator
     *
     * @return void
     */
    public function reset()
    {
        reset($this->entries);
    }

    /**
     * Frees memory for current result set
     *
     * @return void
     */
    public function free()
    {
        $this->entries = array();
    }

    /**
     * Setter for search returned entries
     *
     * @param array(Entry) $entries Entries to be tight to the search
     *
     * @return void
     */
    public function setEntries($entries)
    {
        if (null === $entries) {
            $entries = array();
        }
        $this->entries = $entries;
    }

    /**
     * Getter for search returned entries
     *
     * @return array(Entry)
     */
    public function getEntries()
    {
        return $this->entries;
    }

    /**
     * Setter for base dn
     *
     * @param string $dn Base dn
     *
     * @return void
     */
    public function setBaseDn($dn)
    {
        $this->baseDn = $dn;
    }

    /**
     * Accessor for base dn
     *
     * @return string base dn
     */
    public function getBaseDn()
    {
        return $this->baseDn;
    }

    /**
     * Setter for filter
     *
     * @param string $filter Filter
     *
     * @return void
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * Accessor for filter
     *
     * @return string filter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Setter for attributes
     *
     * @param array $attributes Attributes
     *
     * @return void
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Accessor for searched attributes
     *
     * @return array attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Setter for scope
     *
     * @param int $scope Scope
     *
     * @return void
     */
    public function setScope($scope)
    {
        $this->scope = $scope;
    }

    /**
     * Accessor for scope
     *
     * @return int scope
     */
    public function getScope()
    {
        return $this->scope;
    }

}