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

use Toyota\Component\Ldap\API\DriverInterface;
use Toyota\Component\Ldap\API\SearchInterface;
use Toyota\Component\Ldap\Exception\NodeNotFoundException;
use Toyota\Component\Ldap\Exception\NoResultException;
use Toyota\Component\Ldap\Exception\NotBoundException;
use Toyota\Component\Ldap\Exception\PersistenceException;
use Toyota\Component\Ldap\Exception\DeleteException;
use Toyota\Component\Ldap\Core\Node;

/**
 * Class to handle Ldap operations
 *
 * @author Cyril Cottet <cyril.cottet@toyota-industries.eu>
 */
class Manager
{

    protected $connection = null;

    protected $isBound = false;

    protected $configuration = array();

    protected $driver = null;

    /**
     * Default constructor
     *
     * Parameters structure:
     *     Required:
     *         hostname      => ldap.example.com
     *         base_dn       => dc=example,dc=com
     *     Optional:
     *         port          => 389
     *         security      => one of SSL or TLS
     *         bind_dn       => cn=admin,dc=example,dc=com (bind will be anonymous if not given)
     *         bind_password => secret (can be plain or a hash)
     *         options       => array of options according to driver specifications
     *
     * @param array           $params Connection parameters
     * @param DriverInterface $driver Driver to use for execution
     *
     * @return Manager
     *
     * @throws \InvalidArgumentException if parameters are incorrect
     */
    public function __construct(array $params, DriverInterface $driver)
    {
        $this->configure($params);
        $this->driver = $driver;
    }

    /**
     * Connects to the Ldap store
     *
     * @return void
     *
     * @throws Toyota\Component\Ldap\Exception\ConnectionException if connection fails
     * @throws Toyota\Component\Ldap\Exception\OptionException if connection configuration fails
     */
    public function connect()
    {
        $this->isBound = false;
        $this->connection = $this->driver->connect(
            $this->configuration['hostname'],
            $this->configuration['port'],
            $this->configuration['withSSL'],
            $this->configuration['withTLS']
        );

        foreach ($this->configuration['options'] as $key => $value) {
            $this->connection->setOption($key, $value);
        }
    }

    /**
     * Binds to the Ldap connection
     *
     * @param string $name     Bind rdn (Default: null)
     * @param string $password Bind password (Default: null)
     *
     * @return void
     *
     * @throws Toyota\Component\Ldap\Exception\BindException if binding fails
     */
    public function bind($name = null, $password = null)
    {
        if (strlen(trim($name)) > 0) {
            $password = (null === $password)?'':$password;
            $this->connection->bind($name, $password);
            $this->isBound = true;
            return;
        }

        if ($this->configuration['bind_anonymous']) {
            $this->connection->bind();
            $this->isBound = true;
            return;
        }

        $this->connection->bind(
            $this->configuration['bind_dn'],
            $this->configuration['bind_password']
        );
        $this->isBound = true;
    }

    /**
     * Complete and validates given parameters with default settings
     *
     * @param array $params Parameters to be cleaned
     *
     * @return array Cleaned parameters
     *
     * @throws \InvalidArgumentException
     */
    protected function configure(array $params)
    {
        $required = array('hostname', 'base_dn');
        $missing = array();
        foreach ($required as $key) {
            if (! array_key_exists($key, $params)) {
                $missing[] = $key;
            }
        }
        if (count($missing) > 0) {
            throw new \InvalidArgumentException(
                'Required parameters missing: ' . implode(', ', $missing)
            );
        }

        $idx = strpos($params['hostname'], '://');
        $enforceSSL = false;
        if (false !== $idx) {
            $prefix = strtolower(substr($params['hostname'], 0, $idx));
            if ($prefix === 'ldaps') {
                $enforceSSL = true;
            }
            $params['hostname'] = substr($params['hostname'], $idx+3);
        }

        $params['withSSL'] = false;
        $params['withTLS'] = false;
        if (array_key_exists('security', $params)) {
            switch($params['security']) {
            case 'SSL':
                $params['withSSL'] = true;
                break;
            case 'TLS':
                $params['withTLS'] = true;
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Security mode %s not supported - only SSL or TLS are supported',
                        $params['security']
                    )
                );
            }
        } elseif ($enforceSSL) {
            $params['withSSL'] = true;
        }

        if (! array_key_exists('port', $params)) {
            if ($params['withSSL']) {
                $params['port'] = 636;
            } else {
                $params['port'] = 389;
            }
        }

        if (! array_key_exists('options', $params)) {
            $params['options'] = array();
        }

        $params['bind_anonymous'] = false;
        if ((! array_key_exists('bind_dn', $params)) || (strlen(trim($params['bind_dn'])) == 0)) {
            $params['bind_anonymous'] = true;
            $params['bind_dn']        = '';
            $params['bind_password']  = '';
        }
        if (! array_key_exists('bind_password', $params)) {
            $params['bind_password']  = '';
        }

        $this->configuration = $params;
    }

    /**
     * Retrieve a node knowing its dn
     *
     * @param string $dn         Distinguished name of the node to look for
     * @param array  $attributes Filter attributes to be retrieved (Optional)
     * @param string $filter     Ldap filter according to RFC4515 (Optional)
     *
     * @return Node
     *
     * @throws NodeNotFoundException if node cannot be retrieved
     */
    public function getNode($dn, $attributes = null, $filter = null)
    {
        $this->validateBinding();

        $attributes = (is_array($attributes))?$attributes:null;
        $filter = (null === $filter)?'(objectclass=*)':$filter;

        try {
            $search = $this->connection->search(
                SearchInterface::SCOPE_BASE,
                $dn,
                $filter,
                $attributes
            );
        } catch (NoResultException $e) {
            throw new NodeNotFoundException(sprintf('Node %s not found', $dn));
        }

        if (null === ($entry = $search->next())) {
            throw new NodeNotFoundException(sprintf('Node %s not found', $dn));
        }

        $node = new Node();
        $node->hydrateFromEntry($entry);

        return $node;
    }

    /**
     * Execites a search on the ldap
     *
     * @param string  $baseDn     Base distinguished name to search in (Default = configured dn)
     * @param string  $filter     Ldap filter according to RFC4515 (Default = null)
     * @param boolean $inDepth    Whether to search through all subtree depth (Default = true)
     * @param array   $attributes Filter attributes to be retrieved (Default: null)
     *
     * @return SearchResult
     */
    public function search(
        $baseDn = null,
        $filter = null,
        $inDepth = true,
        $attributes = null
    ) {
        $this->validateBinding();

        $result = new SearchResult();

        $baseDn = (null === $baseDn)?$this->configuration['base_dn']:$baseDn;
        $filter = (null === $filter)?'(objectclass=*)':$filter;
        $attributes = (is_array($attributes))?$attributes:null;
        $scope = $inDepth?SearchInterface::SCOPE_ALL:SearchInterface::SCOPE_ONE;

        try {
            $search = $this->connection->search($scope, $baseDn, $filter, $attributes);
        } catch (NoResultException $e) {
            return $result;
        }
        $result->setSearch($search);

        return $result;
    }

    /**
     * Validates that Ldap is bound before performing some kind of operation
     *
     * @return void
     *
     * @throws NotBoundException if binding has not occured yet
     */
    protected function validateBinding()
    {
        if (! $this->isBound) {
            throw new NotBoundException('You have to bind to the Ldap first');
        }
    }

    /**
     * Saves a node to the Ldap store
     *
     * @param Node $node Node to be saved
     *
     * @return boolean True if node got created, false if it was updated
     *
     * @throws PersistenceException If saving operation fails writing to the Ldap
     */
    public function save(Node $node)
    {
        $this->validateBinding();
        if (strlen(trim($node->getDn())) == 0) {
            throw new PersistenceException('Cannot save: dn missing for the entry');
        }

        if (! $node->isHydrated()) {
            try {
                $origin = $this->getNode($node->getDn());
                $node->rebaseDiff($origin);
            } catch(NodeNotFoundException $e) {
                $this->connection->addEntry($node->getDn(), $node->getRawAttributes());
                $node->snapshot();
                return true;
            }
        }

        if (count($data = $node->getDiffAdditions()) > 0) {
            $this->connection->addAttributeValues($node->getDn(), $data);
        }
        if (count($data = $node->getDiffDeletions()) > 0) {
            $this->connection->deleteAttributeValues($node->getDn(), $data);
        }
        if (count($data = $node->getDiffReplacements()) > 0) {
            $this->connection->replaceAttributeValues($node->getDn(), $data);
        }

        $node->snapshot();
        return false;
    }

    /**
     * Retrieves immediate children for the given node
     *
     * @param Node $node Node to retrieve children for
     *
     * @return array(Node) a set of Nodes
     */
    public function getChildrenNodes(Node $node)
    {
        $result = $this->search($node->getDn(), null, false);

        $nodes = array();
        foreach ($result as $node) {
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Deletes a node from the Ldap store
     *
     * @param Node    $node        Node to delete
     * @param boolean $isRecursive Whether to delete node with its children (Default: false)
     *
     * @return void
     *
     * @throws DeletionException If node to delete has some children and recursion disabled
     */
    public function delete(Node $node, $isRecursive = false)
    {
        if (! $node->isHydrated()) {
            $node = $this->getNode($node->getDn());
        }
        $children = $this->getChildrenNodes($node);
        if (count($children) > 0) {
            if (! $isRecursive) {
                throw new DeleteException(
                    sprintf('%s cannot be deleted - it has some children left', $node->getDn())
                );
            }
            foreach ($children as $child) {
                $this->delete($child, true);
            }
        }
        $this->connection->deleteEntry($node->getDn());

    }
}
