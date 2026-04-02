<?php

namespace Fire1\AxFormBundle\Traits;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * Simplify the Symfony cache
 */
trait FilesystemCacheTrait
{
    /**
     * @var FilesystemAdapter
     */
    private $__cache;
    private $__cacheLifetime = 3600;
    private $__cacheDirectory = '/dev/shm/ax-form-cache/';

    /**
     * @return string
     */
    private function getClassNamespace(): string
    {
        return (new \ReflectionClass(self::class))->getNamespaceName();
    }

    /**
     * Change default lifetime of cache globally for trait
     * @param int $lifetime
     * @return void
     */
    protected function traitCacheLifetime(int $lifetime)
    {
        $this->__cacheLifetime = $lifetime;
    }

    /**
     * @param $directory
     * @return void
     */
    protected function traitUseCacheDirectory($directory)
    {
        $this->__cacheDirectory = $directory;
    }

    /**
     * @param int|null $lifetime
     * @param string|null $namespace
     * @return void
     */
    protected function initCache(int $lifetime = null, string $namespace = null)
    {
        $namespace = $namespace ?? crc32($this->getClassNamespace());
        $this->__cache = new FilesystemAdapter($namespace, $lifetime ?? $this->__cacheLifetime, $this->__cacheDirectory);
    }

    /**
     * @param CacheItem $item
     * @return void
     * @throws InvalidArgumentException
     */
    protected function traitDeleteCache(CacheItem $item)
    {
        $this->__cache->deleteItem($item->getKey());
    }

    /**
     * @param string $name
     * @param int|null $lifetime
     * @param string|null $prefix
     * @param string|null $namespace
     * @return CacheItem
     * @throws InvalidArgumentException
     */
    public function traitCacheItem(string $name, int $lifetime = null, string $prefix = null, string $namespace = null): CacheItem
    {
        if(!$this->__cache instanceof FilesystemAdapter) $this->initCache($lifetime, $namespace);
        return $this->__cache->getItem($prefix . '-' . $name . '-' . $_SERVER['APP_ENV']);
    }

    /**
     * @param CacheItem $item
     * @return void
     */
    protected function traitCacheSave(CacheItem $item)
    {
        $this->__cache->save($item);
    }

}
