<?php

namespace MediaWiki\Extension\BatchIngestion;

use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * @param mixed $obj
 * @param string[] $properties
 * @param string[] $methods
 * @return callable[]
 */
class Accessor {
    /** @var object */
    private $obj;
    /** @var ReflectionObject */
    private $reflObj;
    /** @var ReflectionProperty[] */
    private $properties;
    /** @var ReflectionMethod[] */
    private $methods;
    /**
     * @param object $obj
     */
    public function __construct( $obj ) {
        $this->obj = $obj;
        $this->reflObj = new ReflectionObject($obj);
    }
    /**
     * @param string $property
     * @return ReflectionProperty
     */
    private function getProperty( $property ) {
        if (!array_key_exists($property, $this->properties)) {
            $prop = $this->reflObj->getProperty($property);
            $prop->setAccessible(true);
            $this->properties[$property] = $prop;
        }
        return $this->properties[$property];
    }
    /**
     * @param string $method
     * @return ReflectionMethod
     */
    private function getMethod( $method ) {
        if (!array_key_exists($method, $this->methods)) {
            $meth = $this->reflObj->getMethod($method);
            $meth->setAccessible(true);
            $this->methods[$method] = $meth;
        }
        return $this->methods[$method];
    }
    /**
     * @param string $property
     * @return mixed
     */
    public function get( $property ) {
        return $this
            ->getProperty($property)
            ->getValue($this->obj);
    }
    /**
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function set( $property, $value ) {
        $this
            ->getProperty($property)
            ->setValue($this->obj, $value);
    }
    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    public function call( $method, ...$args ) {
        return $this
            ->getMethod($method)
            ->invoke($this->obj, ...$args);
    }
    /**
     * @param string $method
     * @param mixed ...$args
     * @return mixed
     */
    public function callStatic( $method, ...$args ) {
        return $this
            ->getMethod($method)
            ->invoke(null, ...$args);
    }
}
