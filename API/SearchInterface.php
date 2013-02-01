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

use Toyota\Component\Ldap\API\EntryInterface;

/**
 * Represents a class that enables handling result set for a LDAP search
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
interface SearchInterface
{

    const SCOPE_ALL  = 1;
    const SCOPE_ONE  = 2;
    const SCOPE_BASE = 3;

    /**
     * Retrieves next available entry from the search result set
     *
     * @return EntryInterface next entry if available, null otherwise
     */
    public function next();

    /**
     * Resets entry iterator
     *
     * @return void
     */
    public function reset();

    /**
     * Frees memory for current result set
     *
     * @return void
     */
    public function free();

}