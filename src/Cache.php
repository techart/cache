<?php

namespace Techart;
use Techart\Core;
use Techart\Core\Service;
use Techart\IO\FS;

class Cache extends Service
{
    protected $path;
    protected $timeout;

    public function init()
    {
        $this->path = $this->getOption('path', '../cache');
        $this->timeout = $this->getOption('timeout', 10000);
    }

    public function set($name, $value, $timeout = null)
    {
        $path = rtrim($this->path, '/');
        $timeout = is_null($timeout)? $this->timeout : $timeout;
        $name = str_replace(':', '/', $name);
        if ($m = Core::regexp('{^(.+)/([^/]+)$}', $name)) {
            $path .= '/'. trim($m[1], '/');
            $name = $m[2];
            if (!is_dir($path)) {
                FS::mkdir($path);
            }
        }
        $time = $timeout==0? PHP_INT_MAX : (time() + $timeout);
        $content = $time . '|' . serialize($value);
        $file = "{$path}/{$name}";
        file_put_contents($file, $content);
        FS::chmod($file);
    }

    public function get($name, $default = null)
    {
        list($time, $value) = $this->loadValue($name);
        if ($time == 0) {
            return $default;
        }
        return $value;
    }

    public function has($name)
    {
        list($time, $value) = $this->loadValue($name);
        return $time > 0;
    }

    public function delete($name)
    {
        $path = rtrim($this->path, '/');
        $name = str_replace(':', '/', $name);
        $file = "{$path}/{$name}";
        FS::rm($file);
    }

    public function flush()
    {
        $path = rtrim($this->path, '/');
        FS::rm($path);
    }

    public function classModified($class, $autosave = true)
    {
        $key = $this->cacheKeyForModif($class);
        $time = $this->get($key, 0);
        if ($time==0) {
            if ($autosave) {
                $this->saveClassModified($class);
            }
            return true;
        }
        $ref = new \ReflectionClass($class);
        $files = $this->getClassFilesForModif($ref);
        foreach($files as $file) {
            $mtime = filemtime($file);
            if ($mtime >= $time) {
                if ($autosave) {
                    $this->saveClassModified($class);
                }
                return true;
            }
        }
        return false;
    }

    public function saveClassModified($class)
    {
        $key = $this->cacheKeyForModif($class);
        $this->set($key, time(), 0);
    }

    protected function cacheKeyForModif($class)
    {
        return 'class-modified/'.str_replace('\\','_',get_class($class));
    }

    protected function getClassFilesForModif($ref = null)
    {
        if (is_null($ref)) {
            $ref = new \ReflectionClass($this);
        }
        $path = $ref->getFileName();
        $parent = $ref->getParentClass();
        if ($parent) {
            return array_merge([$path], $this->getClassFilesForModif($parent));
        }
        return [$path];
    }


    public function loadValue($name)
    {
        $path = rtrim($this->path, '/');
        $name = str_replace(':', '/', $name);
        $file = "{$path}/{$name}";
        if (is_file($file)) {
            $content = file_get_contents($file);
            if ($m = Core::regexp('{^(\d+)\|(.*)$}', $content)) {
                $time = (int)$m[1];
                if ($time > time()) {
                    $value = unserialize($m[2]);
                    return array($time, $value);
                }
            }
        }
        return array(0, null);
    }
}
