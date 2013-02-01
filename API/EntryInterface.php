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

/**
 * Represents a class that enables retrieving attributes for a LDAP entry
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
interface EntryInterface
{
    /**
     * Retrieves entry distinguished name
     *
     * @return string Distinguished name
     */
    public function getDn();

    /**
     * Retrieves entry attributes
     *
     * @return array(attribute => array(values))
     */
    public function getAttributes();
}