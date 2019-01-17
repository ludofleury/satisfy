<?php

namespace Playbloom\Satisfy\Service;

use PhpCollection\Map;
use PhpOption\None;
use Playbloom\Satisfy\Exception\MissingConfigException;
use Playbloom\Satisfy\Model\Configuration;
use Playbloom\Satisfy\Model\Repository;
use Playbloom\Satisfy\Model\RepositoryInterface;
use Playbloom\Satisfy\Persister\PersisterInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Lock\Lock;

/**
 * Satis configuration definition manager
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class Manager
{
    /** @var Lock */
    private $lock;

    /** @var PersisterInterface */
    private $persister;

    /** @var Configuration|null */
    private $configuration;

    /**
     * Constructor
     */
    public function __construct(Lock $lock, PersisterInterface $persister)
    {
        $this->lock = $lock;
        $this->persister = $persister;
    }

    /**
     * Find repositories
     *
     * @return Map A RepositoryInterface map
     */
    public function getRepositories()
    {
        return $this->getConfig()->getRepositories();
    }

    /**
     * Find one repository
     *
     * @param  string $id
     *
     * @return RepositoryInterface|null
     */
    public function findOneRepository($id)
    {
        $repository = $this->getRepositories()->get($id);
        if ($repository instanceof None) {
            return null;
        }

        return $repository->get();
    }

    /**
     * @return RepositoryInterface|null
     */
    public function findByUrl(string $pattern)
    {
        foreach ($this->getRepositories() as $repository) {
            if (preg_match($pattern, $repository->getUrl())) {
                return $repository;
            }
        }

        return null;
    }

    /**
     * Add a new repository
     */
    public function add(RepositoryInterface $repository)
    {
        $lock = $this->acquireLock();
        try {
            $this
                ->doAdd($repository)
                ->flush();
        } finally {
            $lock->release();
        }
    }

    /**
     * Adds a array of repositories.
     */
    public function addAll(array $repositories)
    {
        $lock = $this->acquireLock();
        try {
            foreach ($repositories as $repository) {
                $this->doAdd($repository);
            }
            $this->flush();
        } finally {
            $lock->release();
        }
    }

    /**
     * Update an existing repository
     *
     * @throws \RuntimeException
     */
    public function update(RepositoryInterface $repository, RepositoryInterface $updated)
    {
        $repos = $this->getRepositories();

        $option = $repos->get($repository->getId());
        if ($option instanceof None) {
            throw new \RuntimeException('Unknown repository');
        }

        $lock = $this->acquireLock();
        try {
            $repos->remove($repository->getId());
            $repos->set($updated->getId(), $this->cleanUpRepository($updated));
            $this->flush();
        } finally {
            $lock->release();
        }
    }

    /**
     * Delete a repository
     */
    public function delete(RepositoryInterface $repository)
    {
        $lock = $this->acquireLock();
        try {
            $this
                ->getConfig()
                ->getRepositories()
                ->remove($repository->getId());
            $this->flush();
        } finally {
            $lock->release();
        }
    }

    /**
     * Persist current configuration
     */
    public function flush()
    {
        $this->persister->flush($this->getConfig());
    }

    /**
     * Adds a single Repository without flush
     *
     * @return $this
     */
    private function doAdd(RepositoryInterface $repository)
    {
        $this
            ->getConfig()
            ->getRepositories()
            ->set($repository->getId(), $this->cleanUpRepository($repository));

        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfig()
    {
        if ($this->configuration) {
            return $this->configuration;
        }

        try {
            $this->configuration = $this->persister->load();
        } catch (MissingConfigException $e) {
            // use default config if file is missing or empty
            $this->configuration = new Configuration();
        }

        return $this->configuration;
    }

    public function acquireLock(): Lock
    {
        if (!$this->lock->acquire()) {
            throw new IOException('Cannot acquire lock for satis configuration file');
        }

        return $this->lock;
    }

    /**
     * Removes package details form repository for other types.
     *
     *
     * @return \Playbloom\Satisfy\Model\RepositoryInterface
     */
    private function cleanUpRepository(RepositoryInterface $repository)
    {
        if ('package' !== $repository->getType()) {
            $repository->setPackage(null);
        } else {
            $repository->setUrl(null);
        }

        return $repository;
    }
}
