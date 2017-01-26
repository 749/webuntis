<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace Webuntis\Repositories;

use Doctrine\Common\Cache\MemcachedCache;
use Webuntis\Exceptions\RepositoryException;
use Webuntis\Models\Interfaces\CachableModelInterface;
use Webuntis\Util\ExecutionHandler;
use Webuntis\Models\AbstractModel;
use Webuntis\Webuntis;
use Webuntis\WebuntisFactory;

/**
 * Class Repository
 * @package Webuntis\Repositorys
 * @author Tobias Franek <tobias.franek@gmail.com>
 */
class Repository {

    /**
     * @var string
     */
    protected $model;

    /**
     * @var Webuntis
     */
    protected $instance;

    /**
     * @var MemcachedCache
     */
    protected $cache;

    /**
     * Repository constructor.
     * @param $model
     */
    public function __construct($model) {
        $this->model = $model;
        $this->instance = WebuntisFactory::create($model);
        $this->cache = $this->initMemcached();
    }

    /**
     * return all objects that have been searched for
     * @param array $params
     * @param array $sort
     * @param null $limit
     * @return AbstractModel[]
     */
    public function findBy(array $params, array $sort = [], $limit = null) {
        if (empty($params)) {
            throw new RepositoryException('missing parameters');
        }
        $data = $this->findAll();

        if (!empty($sort)) {
            $field = array_keys($sort)[0];
            $sortingOrder = $sort[$field];
            $data = $this->find($data, $params);
            $data = $this->sort($data, $field, $sortingOrder);
        } else {
            $data = $this->find($data, $params);
        }
        if ($limit != null) {
            return array_slice($data, 0, $limit);
        }
        return $data;
    }

    /**
     * returns all objects it could find
     * @param array $sort
     * @param null $limit
     * @return AbstractModel[]
     */
    public function findAll(array $sort = [], $limit = null) {
        $data = ExecutionHandler::execute($this, []);

        if (!empty($sort)) {
            $field = array_keys($sort)[0];
            $sortingOrder = $sort[$field];
            $data = $this->sort($data, $field, $sortingOrder);
        }
        if ($limit != null) {
            $data = array_slice($data, 0, $limit);
        }
        return $data;
    }

    /**
     * parses the given objects
     * @param array $result
     * @return AbstractModel[]
     */
    public function parse($result) {
        $data = [];
        foreach ($result as $key => $value) {
            /** @var AbstractModel $newObj */
            $newObj = new $this->model($value);
            $data[] = $newObj;
        }
        return $data;
    }

    /**
     * searches the $data array with the given params
     * @param AbstractModel[] $data
     * @param array $params
     * @return AbstractModel[]
     */
    protected function find($data, $params) {
        if(!empty($data)) {
            foreach ($params as $key => $value) {
                $temp = [];
                $keys = explode(":", $key);
                $key = $keys[0];
                if (isset($data[0])) {
                    if (isset($data[0]->serialize('php')[$key])) {
                        foreach ($data as $key2 => $value2) {
                            if (count($keys) > 1) {
                                $tempKeys = $keys;
                                $tempKeys = array_splice($tempKeys, 1, count($tempKeys) - 1);
                                $tempKeys = implode(':', $tempKeys);
                                if (!empty($this->find($value2->get($key), [$tempKeys => $value]))) {
                                    $temp[] = $value2;
                                }
                            } else if ($value2->serialize('php')[$key] == $value) {
                                $temp[] = $value2;
                            } else if($this->endsWith($value, '%') && $this->startsWith($value, '%')){
                                if($this->contains($value2->serialize('php')[$key], substr($value, 1, strlen($value) - 2))) {
                                    $temp[] = $value2;
                                }
                            } else if($this->startsWith($value, '%')) {
                                if($this->startsWith($value2->serialize('php')[$key], substr($value,1, strlen($value)))){
                                    $temp[] = $value2;
                                }
                            } else if($this->endsWith($value, '%')) {
                                if($this->endsWith($value2->serialize('php')[$key], substr($value, 0, strlen($value) - 1))){
                                    $temp[] = $value2;
                                }
                            }
                        }
                        $data = $temp;
                    } else {
                        throw new RepositoryException('the parameter ' . $key . ' doesn\'t exist');
                    }
                }
            }
        }
        return $data;
    }

    /**
     * sorts the given array according to the bubble sort algorithm
     * @param AbstractModel[] $array
     * @param string $key
     * @param string $sortOrder (ASC, DESC)
     * @return AbstractModel[]
     */
    protected function sort(array $array, $key, $sortOrder) {
        if(!empty($array)) {
            if (isset($array[0]->serialize('php')[$key])) {
                if (!$length = count($array)) {
                    return $array;
                }
                for ($outer = 0; $outer < $length; $outer++) {
                    for ($inner = 0; $inner < $length; $inner++) {
                        if ($array[$outer]->serialize('php')[$key] < $array[$inner]->serialize('php')[$key]) {
                            $tmp = $array[$outer];
                            $array[$outer] = $array[$inner];
                            $array[$inner] = $tmp;
                        }
                    }
                }
                if ($sortOrder == 'ASC') {
                    return array_reverse($array);
                } else if ($sortOrder == 'DESC') {
                    return $array;
                } else {
                    throw new RepositoryException('sort order ' . $sortOrder . ' does not exist');
                }
            } else {
                throw new RepositoryException('the parameter ' . $key . ' doesn\'t exist');
            }
        }
        return $array;
    }

    /**
     * returns the Memcache instance
     * @return MemcachedCache
     */
    public function initMemcached() {
        $memcached = new \Memcached();
        $memcached->addServer('localhost', 11211);
        $cacheDriver = new MemcachedCache();
        $cacheDriver->setMemcached($memcached);
        return $memcached;
    }

    /**
     * @return Webuntis
     */
    public function getInstance() {
        return $this->instance;
    }

    /**
     * @return string
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function startsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) == $needle;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function endsWith($haystack, $needle) {
        return substr($haystack, strlen($haystack) - strlen($needle), strlen($haystack)) == $needle;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function contains($haystack, $needle) {
        return strpos($haystack, $needle) != false;
    }
}