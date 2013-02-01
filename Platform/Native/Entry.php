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

use Toyota\Component\Ldap\API\EntryInterface;

/**
 * Implementation of the entry interface for php ldap extension
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Entry implements EntryInterface
{

    protected $entry = null;

    protected $connection = null;

    /**
     * Default constructor
     *
     * @param resource $connection Resource link identifier for Ldap connection
     * @param resource $entry      Resource link identifier for Ldap entry
     *
     * @return Entry
     */
    public function __construct($connection, $entry)
    {
        $this->connection = $connection;
        $this->entry      = $entry;
    }

    /**
     * Retrieves entry distinguished name
     *
     * @return string Distinguished name
     */
    public function getDn()
    {
        return @ldap_get_dn($this->connection, $this->entry);
    }

    /**
     * Retrieves entry attributes
     *
     * @return array(attribute => array(values))
     */
    public function getAttributes()
    {
        $data = @ldap_get_attributes($this->connection, $this->entry);

        $result = array();

        for ($i = 0; $i < $data['count']; $i++) {
            $key = $data[$i];
            $result[$key] = array();
            for ($j = 0; $j < $data[$key]['count']; $j++) {
                $result[$key][] = $data[$key][$j];
            }
        }

        return $result;
    }

}