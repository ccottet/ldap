<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Core;

use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Core\Node;

/**
 * Class to handle Ldap queries
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class SearchResult implements \Iterator
{

    protected $search = null;

    protected $current = null;

    /**
     * Setter for search
     *
     * @param SearchInterface $search Backend search result set
     *
     * @return void
     */
    public function setSearch(SearchInterface $search)
    {
        if (null !== $this->search) {
            $this->search->free();
        }
        $this->search = $search;
        $this->rewind();
    }

    /**
     * Iterator rewind
     *
     * @return void
     */
    public function rewind()
    {
        $this->current = null;
        if (null !== $this->search) {
            $this->search->reset();
            $this->next();
        }
    }

    /**
     * Iterator key
     *
     * @return string dn of currently pointed at entry
     */
    public function key()
    {
        if ($this->valid()) {
            return $this->current->getDn();
        }
        return;
    }

    /**
     * Iterator current
     *
     * @return Node or false
     */
    public function current()
    {
        if ($this->valid()) {
            return $this->current;
        }
        return false;
    }

    /**
     * Iterator next
     *
     * @return void
     */
    public function next()
    {
        $this->current = null;
        if (null !== $this->search) {
            $entry = $this->search->next();
            if (null !== $entry) {
                $this->current = new Node();
                $this->current->hydrateFromEntry($entry);
            }
        }
    }

    /**
     * Iterator valid
     *
     * @return boolean
     */
    public function valid()
    {
        return (null !== $this->current);
    }
}