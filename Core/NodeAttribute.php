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
 * Class to handle Ldap entries attributes
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class NodeAttribute implements \Iterator, \Countable, \ArrayAccess
{

    protected $values = array();

    protected $name;

    protected $iterator = 0;

    protected $tracker = null;

    /**
     * Default constructor
     *
     * @param string      $name    Name of the attribute
     * @param DiffTracker $tracker Utility to track diff (Optional)
     *
     * @return NodeAttribute
     */
    public function __construct($name, DiffTracker $tracker = null)
    {
        $this->name = $name;

        $this->tracker = (null === $tracker)?(new DiffTracker()):$tracker;
    }

    /**
     * Getter for name
     *
     * @return string name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for values array
     *
     * @return array values
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Add a value as an instance of this attribute
     *
     * @param mixed $value Value to add to the attribute instances
     *
     * @return boolean true if success
     */
    public function add($value)
    {
        if (is_array($value)) {
            return $this->handleArray('add', $value);
        }
        if (is_null($value) || strlen($value) == 0) {
            return false;
        }
        if (false !== array_search($value, $this->values)) {
            return false;
        }
        $this->offsetSet(null, $value);
        return true;
    }

    /**
     * Sets a set of value replacing any existing values registered
     *
     * @param mixed $values Values to set for the attribute instances
     *
     * @return boolean true if success
     */
    public function set($values)
    {
        if (! is_array($values)) {
            $values = array($values);
        }
        $this->values = $values;
        $this->snapshot();
        $this->tracker->markOverridden();
        return true;
    }

    /**
     * Checks if the whole attribute was replaced in the diff
     *
     * @return boolean True if replaced
     */
    public function isReplaced()
    {
        return $this->tracker->isOverridden();
    }

    /**
     * Handle action for an array of values to the attribute
     *
     * @param string $method Name of the method to use for handling
     * @param array  $values Values to be added
     *
     * @return boolean True if success
     */
    protected function handleArray($method, array $values)
    {
        $result = false;
        foreach ($values as $value) {
            $flag = call_user_func(array($this, $method), $value);
            $result = $result || $flag;
        }
        return $result;
    }

    /**
     * Removes a value from the attribute stack
     *
     * @param mixed $value Value to be removed
     *
     * @return boolean True if success
     */
    public function remove($value)
    {
        if (is_array($value)) {
            return $this->handleArray('remove', $value);
        }
        $key = array_search($value, $this->values);
        if (false === $key) {
            return false;
        }
        $this->offsetUnset($key);
        return true;
    }

    /**
     * Iterator rewind
     *
     * @return void
     */
    public function rewind()
    {
        reset($this->values);
    }

    /**
     * Iterator key
     *
     * @return string dn of currently pointed at entry
     */
    public function key()
    {
        if ($this->valid()) {
            return key($this->values);
        }
        return null;
    }

    /**
     * Iterator current
     *
     * @return Node or false
     */
    public function current()
    {
        if ($this->valid()) {
            return current($this->values);
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
        next($this->values);
    }

    /**
     * Iterator valid
     *
     * @return boolean
     */
    public function valid()
    {
        return (null !== key($this->values));
    }

    /**
     * Count implementation
     *
     * @return int number of values stored
     */
    public function count()
    {
        return count($this->values);
    }

    /**
     * Arrayaccess setter
     *
     * @param int   $offset Offset (index)
     * @param mixed $value  Value to add as an instance
     *
     * @return void
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->values[] = $value;
        } else {
            $this->values[$offset] = $value;
        }
        $this->tracker->logAddition($value);
    }

    /**
     * Arrayaccess exists
     *
     * @param int $offset Offset (index)
     *
     * @return boolean
     */
    public function offsetExists($offset) {
        return isset($this->values[$offset]);
    }

    /**
     * Arrayaccess remove
     *
     * @param int $offset Offset (index)
     *
     * @return void
     */
    public function offsetUnset($offset) {
        if (! $this->offsetExists($offset)) {
            return;
        }
        $value = $this->offsetGet($offset);
        unset($this->values[$offset]);
        $this->tracker->logDeletion($value);
    }

    /**
     * Arrayaccess retrieve
     *
     * @param int $offset Offset (index)
     *
     * @return boolean
     */
    public function offsetGet($offset) {
        return ($this->offsetExists($offset)) ? $this->values[$offset] : null;
    }

    /**
     * Retrieves diff additions for the attribute
     *
     * @return array Added values
     */
    public function getDiffAdditions()
    {
        return $this->tracker->getAdditions();
    }

    /**
     * Retrieves diff deletions for the attribute
     *
     * @return array Deleted values
     */
    public function getDiffDeletions()
    {
        return $this->tracker->getDeletions();
    }

    /**
     * Retrieves diff replacements for the attribute
     *
     * @return array Replaced values
     */
    public function getDiffReplacements()
    {
        return $this->tracker->getReplacements();
    }

    /**
     * Snapshot resets diff tracking
     *
     * @return void
     */
    public function snapshot()
    {
        $this->tracker->reset();
    }
}