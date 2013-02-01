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

use Toyota\Component\Ldap\API\EntryInterface;

/**
 * Implementation of the entry interface for test
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Entry implements EntryInterface
{

    protected $dn;

    protected $data = array();

    /**
     * Default constructor
     *
     * @param string $dn   Dn for the entry
     * @param array  $data Attributes
     *
     * @return Entry
     */
    public function __construct($dn, $data = array())
    {
        $this->dn   = $dn;
        $this->data = $data;
    }

    /**
     * Retrieves entry distinguished name
     *
     * @return string Distinguished name
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Retrieves entry attributes
     *
     * @return array(attribute => array(values))
     */
    public function getAttributes()
    {
        return $this->data;
    }

    /**
     * Setter for entry distinguished name
     *
     * @param string $dn Dn to set
     *
     * @return void
     */
    public function setDn($dn)
    {
        $this->dn = $dn;
    }

    /**
     * Setter for entry attributes
     *
     * @param array $attributes Attributes to set
     *
     * @return void
     */
    public function setAttributes($attributes)
    {
        $this->data = $attributes;
    }

}