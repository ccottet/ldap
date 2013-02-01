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

use Toyota\Component\Ldap\API\SearchInterface;

/**
 * Implementation of the search interface for php ldap extension
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Search implements SearchInterface
{

    protected $resultSet = null;

    protected $connection = null;

    protected $previous = null;

    protected $isEndReached = false;

    /**
     * Default constructor
     *
     * @param resource $connection Resource link identifier for Ldap connection
     * @param resource $set        Resource link identifier for Ldap search result set
     *
     * @return Search
     */
    public function __construct($connection, $set)
    {
        $this->connection   = $connection;
        $this->resultSet    = $set;
        $this->previous     = null;
        $this->isEndReached = false;
    }

    /**
     * Retrieves next available entry from the search result set
     *
     * @return EntryInterface next entry if available, null otherwise
     */
    public function next()
    {
        if ($this->isEndReached) {
            return null;
        }
        if (null === $this->previous) {
            $this->previous = @ldap_first_entry($this->connection, $this->resultSet);
        } else {
            $this->previous = @ldap_next_entry($this->connection, $this->previous);
        }
        if (false === $this->previous) {
            $this->previous = null;
            $this->isEndReached = true;
            return null;
        }
        return new Entry($this->connection, $this->previous);
    }

    /**
     * Resets entry iterator
     *
     * @return void
     */
    public function reset()
    {
        $this->previous     = null;
        $this->isEndReached = false;
    }

    /**
     * Frees memory for current result set
     *
     * @return void
     */
    public function free()
    {
        @ldap_free_result($this->resultSet);
    }

}