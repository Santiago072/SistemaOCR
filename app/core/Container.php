<?php

namespace App\Core;

/**
 * Contenedor de Inyección de Dependencias
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        $this->instances[$abstract] = null;
    }

    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if (is_callable($concrete)) {
                $instance = $concrete($this);
            } elseif (is_string($concrete) && class_exists($concrete)) {
                $instance = $this->build($concrete);
            } else {
                $instance = $concrete;
            }
        } else {
            $instance = $this->build($abstract);
        }

        if (array_key_exists($abstract, $this->instances)) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    public function build(string $className)
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Clase no encontrada: {$className}");
        }

        $reflector = new \ReflectionClass($className);
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("La clase {$className} no es instanciable.");
        }

        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException("No se puede resolver el parámetro {$parameter->getName()} en {$className}");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
