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

/**
 * Class to track differentials while maintaining Nodes & NodeAttributes
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class DiffTracker
{
    protected $added       = array();

    protected $deleted     = array();

    protected $replaced    = array();

    protected $ignored     = array();

    protected $isOverridden = false;

    /**
     * Resets tracking
     *
     * @return void
     */
    public function reset()
    {
        $this->added        = array();
        $this->deleted      = array();
        $this->replaced     = array();
        $this->ignored      = array();
        $this->isOverridden = false;
    }

    /**
     * Logs an addition in the diff tracker
     *
     * @param string $value Added value
     *
     * @return void
     */
    public function logAddition($value)
    {
        if ($this->isOverridden()) {
            return;
        }
        if (isset($this->deleted[$value])) {
            unset($this->deleted[$value]);
            $this->replaced[$value] = $value;
            return;
        }
        if (! isset($this->replaced[$value])) {
            $this->added[$value] = $value;
        }
    }

    /**
     * Logs a deletion in the diff tracker
     *
     * @param string $value Added value
     *
     * @return void
     */
    public function logDeletion($value)
    {
        if ($this->isOverridden()) {
            return;
        }
        if (isset($this->added[$value])) {
            unset($this->added[$value]);
            $this->ignored[$value] = $value;
            return;
        }
        if (isset($this->replaced[$value])) {
            unset($this->replaced[$value]);
        }
        if (! isset($this->ignored[$value])) {
            $this->deleted[$value] = $value;
        }
    }

    /**
     * Logs a replacement in the diff tracker
     *
     * @param string $value Replaced value
     *
     * @return void
     */
    public function logReplacement($value)
    {
        if ($this->isOverridden()) {
            return;
        }
        if (isset($this->added[$value])) {
            unset($this->added[$value]);
        }
        if (isset($this->deleted[$value])) {
            unset($this->deleted[$value]);
        }
        $this->replaced[$value] = $value;
    }

    /**
     * Checks if complete item has been overriden in the change process
     *
     * @return boolean
     */
    public function isOverridden()
    {
        return $this->isOverridden;
    }

    /**
     * Marks the object as overridden
     *
     * @return void
     */
    public function markOverridden()
    {
        $this->reset();
        $this->isOverridden = true;
    }

    /**
     * Retrieves additions tracked
     *
     * @return array Additions
     */
    public function getAdditions()
    {
        return array_values($this->added);
    }

    /**
     * Retrieves deletions tracked
     *
     * @return array Deletions
     */
    public function getDeletions()
    {
        return array_values($this->deleted);
    }

    /**
     * Retrieves replacements tracked
     *
     * @return array Replacements
     */
    public function getReplacements()
    {
        return array_values($this->replaced);
    }
}