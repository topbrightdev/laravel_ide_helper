<?php
/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2013 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Barryvdh\LaravelIdeHelper;

class Alias
{

    protected $alias;
    protected $facade;
    protected $extends = null;
    protected $classTYpe = 'class';
    protected $namespace = '__root';
    protected $root = null;
    protected $classes = array();
    protected $methods = array();
    protected $used_methods = array();
    protected $valid = false;
    protected $interfaces = array();

    public function __construct($alias, $facade, $interfaces = array())
    {
        $this->alias = $alias;
        $this->interfaces = $interfaces;

        // Make the class absolute
        $facade = '\\' . ltrim($facade, '\\');
        $this->facade = $facade;


        $this->detectRoot();

        if ((!$this->isTrait() && $this->root)) {
            $this->valid = true;
        } else {
            return;
        }

        $this->addClass($this->root);
        $this->detectNamespace();
        $this->detectClassType();


    }

    /**
     * Add one or more classes to analyze
     *
     * @param array|string $classes
     */
    public function addClass($classes)
    {
        $classes = (array)$classes;
        foreach ($classes as $class) {
            if (class_exists($class) || interface_exists($class)) {
                $this->classes[] = $class;
            }
        }

    }

    /**
     * Check if this class is valid to process.
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * Get the classtype, 'interface' or 'class'
     *
     * @return string
     */
    public function getClasstype()
    {
        return $this->classType;
    }

    /**
     * Get the class which this alias extends
     *
     * @return null|string
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * Get the Alias by which this class is called
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Get the namespace from the alias
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get the methods found by this Alias
     *
     * @return array
     */
    public function getMethods()
    {
        $this->detectMethods();
        return $this->methods;

    }

    /**
     * Detect the namespace
     */
    protected function detectNamespace()
    {
        if (strpos($this->alias, '\\')) {
            $nsParts = explode('\\', $this->alias);
            $this->short = array_pop($nsParts);
            $this->namespace = implode('\\', $nsParts);
        }
    }

    /**
     * Detect the class type
     */
    protected function detectClassType()
    {
        //Some classes extend the facade
        if (interface_exists($this->facade)) {
            $this->classType = 'interface';
            $this->extends = $this->facade;
        } else {
            $this->classType = 'class';
            if (class_exists($this->facade)) {
                $this->extends = $this->facade;
            }
        }
    }

    /**
     * Get the real root of a facade
     *
     * @return bool|string
     */
    protected function detectRoot()
    {
        $facade = $this->facade;

        try {
            //If possible, get the facade root
            if (method_exists($facade, 'getFacadeRoot')) {
                $root = get_class($facade::getFacadeRoot());
            } else {
                $root = $facade;
            }

            //If it doesn't exist, skip it
            if (!class_exists($root) && !interface_exists($root)) {
                $this->error("Class $this->root is not found.");
                return;
            }

            $this->root = $root;

            //When the database connection is not set, some classes will be skipped
        } catch (\PDOException $e) {
            $this->error(
                "PDOException: " . $e->getMessage() . "\nPlease configure your database connection correctly, or use the sqlite memory driver (-M). Skipping $facade."
            );

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage() . "\nSkipping $facade.");
        }

    }

    /**
     * Detect if this class is a trait or not.
     *
     * @return bool
     */
    protected function isTrait()
    {
        // Check if the facade is not a Trait
        if (function_exists('trait_exists') && trait_exists($this->facade)) {
            return true;
        }
        return false;
    }

    /**
     * Get the methods for one or multiple classes.
     *
     * @return string
     */
    protected function detectMethods()
    {

        foreach ($this->classes as $class) {
            $reflection = new \ReflectionClass($class);

            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                foreach ($methods as $method) {
                    if (!in_array($method->name, $this->used_methods)) {
                        // Only add the methods to the output when the root is not the same as the facade.
                        // And don't add the __*() methods
                        if ($this->facade !== $this->root && substr($method->name, 0, 2) !== '__') {
                            $this->methods[] = new Method($method, $this->alias, $reflection, $this->interfaces);
                        }
                        $this->used_methods[] = $method->name;
                    }
                }
            }
        }
    }

    // Helper class to log errors
    protected function error($msg)
    {
        echo $msg . "\r\n";
    }

}
