<?php

namespace TorCDN\Middleware;

/**
 * Holds payload arbitrary data that can be processed by middleware
 */
class Payload
{

    /**
     * Undocumented variable
     *
     * @var array
     */
    protected $data = [];

    /**
     * Has payload data changed
     *
     * @var boolean
     */
    protected $dirty = false;

    /**
     *  Set payload data
     *
     * @param string $name
     * @param mixed $data
     */
    public function set($name, $data)
    {
      if (isset($this->data[$name]) && $this->data[$name] !== $data) {
        $this->dirty = true;
      }
      $this->data[$name] = $data;
    }

    /**
     *  Get payload name
     *
     * @param string $name
     */
    public function get($name)
    {
      return $this->data[$name];
    }

    /**
     * Has payload data changed
     *
     * @return boolean
     */
    public function isDirty()
    {
      return $this->dirty;
    }
}
