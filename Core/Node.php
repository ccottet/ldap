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

use Toyota\Component\Ldap\API\EntryInterface;
use Toyota\Component\Ldap\Exception\RebaseException;

/**
 * Class to handle Ldap nodes (entries)
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Node
{
    protected $dn;

    protected $attributes = array();

    public $tracker = null;

    protected $isHydrated = false;

    /**
     * Default Node Constructor
     *
     * @param DiffTracker $tracker Utility for tracking changes (Optional)
     *
     * @return Node
     */
    public function __construct(DiffTracker $tracker = null)
    {
        $this->tracker = (null === $tracker)?(new DiffTracker()):$tracker;
    }

    /**
     * Hydrate from a LDAP entry
     *
     * @param EntryInterface $entry Entry to use for loading
     *
     * @return void
     */
    public function hydrateFromEntry(EntryInterface $entry)
    {
        $this->dn = $entry->getDn();

        $this->attributes = array();

        foreach ($entry->getAttributes() as $name => $data) {
            $attr = new NodeAttribute($name);
            $attr->add($data);
            $this->mergeAttribute($attr);
        }

        $this->snapshot();
    }

    /**
     * Getter for distinguished name
     *
     * @return string distinguished name
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Setter for distinguished name
     *
     * @param string $dn     Distinguished name
     * @param boolean $force Whether to force dn change (Default: false)
     *
     * @return void
     *
     * @throws InvalidArgumentException If entry is bound
     */
    public function setDn($dn, $force = false)
    {
        if (($this->isHydrated) && (! $force)) {
            throw new \InvalidArgumentException('Dn cannot be updated manually');
        }
        $this->dn = $dn;
    }

    /**
     * Getter for attributes
     *
     * @return array attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets an attribute in the node store (replace existing ones with same name)
     *
     * @param NodeAttribute $attribute Attribute to be set
     *
     * @return void
     */
    public function setAttribute(NodeAttribute $attribute)
    {
        $this->attributes[$attribute->getName()] = $attribute;
        $this->tracker->logReplacement($attribute->getName());
    }

    /**
     * Merges an attribute in the node store (add if it is new)
     *
     * @param NodeAttribute $attribute Attribute to be added
     *
     * @return void
     */
    public function mergeAttribute(NodeAttribute $attribute)
    {
        if (! $this->has($attribute->getName())) {
            $this->attributes[$attribute->getName()] = $attribute;
            $this->tracker->logAddition($attribute->getName());
            return;
        }
        $backup = $attribute;
        $attribute = $this->get($attribute->getName());
        $attribute->add($backup->getValues());
        $this->attributes[$attribute->getName()] = $attribute;
    }

    /**
     * Removes an attribute from the node store
     *
     * @param string $name Attribute name
     *
     * @return boolean true on success
     */
    public function removeAttribute($name)
    {
        if (! $this->has($name)) {
            return false;
        }
        unset($this->attributes[$name]);
        $this->tracker->logDeletion($name);
        return true;
    }

    /**
     * Retrieves an attribute from its name
     *
     * @param string  $name   Name of the attribute to look for
     * @param boolean $create Whether to create a new instance if it is not set (Default: false)
     *
     * @return NodeAttribute or null if it does not exist and $create is false
     */
    public function get($name, $create = false)
    {
        if (! $this->has($name)) {
            if (! $create) {
                return null;
            }
            $this->mergeAttribute(new NodeAttribute($name));
        }
        return $this->attributes[$name];
    }

    /**
     * Checks if an attribute is set in the store
     *
     * @param string $name Name of the attribute to look for
     *
     * @return boolean true if it is set
     */
    public function has($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Retrieves diff additions for the attribute
     *
     * @return array Added values
     */
    public function getDiffAdditions()
    {
        $data = $this->getValueDiffData('getDiffAdditions');
        foreach ($this->tracker->getAdditions() as $name) {
            $data[$name] = $this->get($name)->getValues();
        }
        return $data;
    }

    /**
     * Retrieves diff deletions for the attribute
     *
     * @return array Deleted values
     */
    public function getDiffDeletions()
    {
        $data = $this->getValueDiffData('getDiffDeletions');
        foreach ($this->tracker->getDeletions() as $name) {
            $data[$name] = array();
        }
        return $data;
    }

    /**
     * Retrieve attribute value level diff information
     *
     * @param string $method Name of the diff method to use on the attribute
     *
     * @return array Diff data
     */
    protected function getValueDiffData($method)
    {
        $data = array();
        foreach ($this->getSafeAttributes() as $attribute) {
            $buffer = call_user_func(array($attribute, $method));
            if (count($buffer) > 0) {
                $data[$attribute->getName()] = $buffer;
            }
        }
        return $data;
    }

    /**
     * Retrieves safe attributes (those which have not been changed at node level)
     *
     * @return array(NodeAttribute)
     */
    protected function getSafeAttributes()
    {
        $ignored = array_merge(
            $this->tracker->getAdditions(),
            $this->tracker->getDeletions(),
            $this->tracker->getReplacements()
        );
        $attributes = array();
        foreach ($this->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), $ignored)) {
                continue;
            }
            $attributes[$attribute->getName()] = $attribute;
        }
        return $attributes;
    }

    /**
     * Retrieves diff replacements for the attribute
     *
     * @return array Replaced values
     */
    public function getDiffReplacements()
    {
        $data = array();
        foreach ($this->getSafeAttributes() as $attribute) {
            if ($attribute->isReplaced()) {
                $data[$attribute->getName()] = $attribute->getValues();
            }
        }
        foreach ($this->tracker->getReplacements() as $name) {
            $data[$name] = $this->get($name)->getValues();
        }
        return $data;
    }

    /**
     * Snapshot resets diff tracking
     *
     * @param boolean $isHydrated Whether snapshot is due to hydration (Default: true)
     *
     * @return void
     */
    public function snapshot($isHydrated = true)
    {
        $this->tracker->reset();
        foreach ($this->attributes as $attribute) {
            $attribute->snapshot();
        }
        if ($isHydrated) {
            $this->isHydrated = true;
        }
    }

    /**
     * Is this node hydrated with the relevant Ldap entry
     *
     * @return boolean
     */
    public function isHydrated()
    {
        return $this->isHydrated;
    }

    /**
     * Retrieves attribute data in a raw format for persistence operations
     *
     * @return array Raw data of attributes
     */
    public function getRawAttributes()
    {
        $data = array();
        foreach ($this->attributes as $name => $attribute) {
            $data[$name] = $attribute->getValues();
        }
        return $data;
    }

    /**
     * Rebase diff based on source node as an origin
     *
     * @param Node $node Node to use as a source for origin
     *
     * @return void
     *
     * @throws RebaseException If source of origin node has uncommitted changes
     */
    public function rebaseDiff(Node $node)
    {
        $changes = array_merge(
            $node->getDiffAdditions(),
            $node->getDiffDeletions(),
            $node->getDiffReplacements()
        );
        if (count($changes) > 0) {
            throw new RebaseException(
                sprintf(
                    '%s has some uncommitted changes - Cannot rebase %s on %s',
                    $node->getDn(),
                    $this->getDn(),
                    $node->getDn()
                )
            );
        }
        $additions = $this->getDiffAdditions();
        $deletions = $this->getDiffDeletions();
        $replacements = $this->getDiffReplacements();
        $this->snapshot();
        $this->attributes = $node->getAttributes();
        foreach ($additions as $attribute => $values) {
            $this->get($attribute, true)->add($values);
        }
        foreach ($deletions as $attribute => $values) {
            if (count($values) == 0) {
                $this->removeAttribute($attribute);
            } else {
                if ($this->has($attribute)) {
                    $this->get($attribute)->remove($values);
                }
            }
        }
        foreach ($replacements as $attribute => $values) {
            $this->get($attribute, true)->set($values);
        }
    }
}