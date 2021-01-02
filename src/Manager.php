<?php
namespace Zodream\Database;

use Zodream\Infrastructure\Base\ConfigObject;

abstract class Manager extends ConfigObject {

    protected $engines = [];

    protected $currentName = '__default__';

    protected $defaultDriver;

    public function __construct() {
        $this->loadConfigs();
        $this->getCurrentName();
    }

    /**
     * ADD 2D ARRAY
     * @param array $args
     * @return $this
     */
    public function setConfigs(array $args) {
        if (!is_array(current($args))) {
            $args = [
                $this->currentName => $args
            ];
        }
        foreach ($args as $key => $item) {
            if (array_key_exists($key, $this->configs)) {
                $this->configs[$key] = array_merge($this->configs[$key], $item);
            } else {
                $this->configs[$key] = $item;
            }
        }
        return $this;
    }

    /**
     * @param string|array|mixed $name
     * @param array|mixed|null $configs
     * @return mixed
     * @throws \Exception
     */
    public function addEngine($name, $configs = null) {
        if (!is_string($name) && !is_numeric($name)) {
            $configs = $name;
            $name = $this->currentName;
        }
        if (array_key_exists($name, $this->engines) && method_exists($this->engines[$name], 'close')) {
            $this->engines[$name]->close();
        }
        if (is_object($configs)) {
            return $this->engines[$name] = $configs;
        }
        if (!empty($configs) && !array_key_exists('driver', $configs) || !class_exists($configs['driver'])) {
            $configs['driver'] = $this->defaultDriver;
        }
        $class = $configs['driver'];
        $this->engines[$name] = new $class($configs);
        $this->initEngineEvent($this->engines[$name]);
        return $this->engines[$name];
    }

    /**
     * GET DATABASE ENGINE
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function getEngine($name = null) {
        if (is_null($name)) {
            $name = $this->getCurrentName();
        }
        if (array_key_exists($name, $this->engines)) {
            return $this->engines[$name];
        }
        if (!$this->hasConfig($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                    __('%s DOES NOT HAVE CONFIG!')
                    , $name)
            );
        }
        $engine = $this->addEngine($name, $this->getConfig($name));
        $this->changeEngineEvent($engine);
        return $engine;
    }

    protected function initEngineEvent($engine) {}
    protected function changeEngineEvent($engine) {}

    public function getCurrentName() {
        if (!array_key_exists($this->currentName, $this->configs)) {
            $this->currentName = key($this->configs);
        }
        return $this->currentName;
    }

    public function getConfig($name = null) {
        if (is_null($name)) {
            $name = $this->getCurrentName();
        }
        return array_key_exists($name, $this->configs) ? $this->configs[$name] : [];
    }

    public function hasConfig($name = null) {
        if (is_null($name)) {
            return empty($this->configs);
        }
        return array_key_exists($name, $this->configs);
    }
}