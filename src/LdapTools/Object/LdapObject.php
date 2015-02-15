<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LdapTools\Object;

/**
 * Represents a LDAP Object.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class LdapObject
{
    /**
     * @var array These are the expected "magic" function calls for the attributes.
     */
    protected $functions = [
        'get',
        'set',
        'remove',
        'add',
        'reset',
    ];

    /**
     * @var string The type as defined in the schema, if any.
     */
    protected $type = '';

    /**
     * @var array The objectClass values for this object.
     */
    protected $class = [];

    /**
     * @var string The objectCategory for this object.
     */
    protected $category = '';

    /**
     * @var array The attributes and values that represent this LDAP object.
     */
    protected $attributes = [];

    /**
     * @var array All of the batch modifications that have been made.
     */
    protected $modifications = [];

    /**
     * @param array $attributes
     * @param array $class
     * @param string $category
     * @param string $type
     */
    public function __construct(array $attributes, array $class = [], $category = '', $type = '')
    {
        $this->attributes = $attributes;
        $this->class = $class;
        $this->category = $category;
        $this->type = $type;
    }

    /**
     * Check if this LDAP Object is a specific type (Schema type).
     *
     * @param string $type
     * @return bool
     */
    public function isType($type)
    {
        return ($this->type == $type);
    }

    /**
     * Check if this LDAP Object is of a specific objectClass.
     *
     * @param string $class
     * @return bool
     */
    public function isClass($class)
    {
        return in_array($class, $this->class);
    }

    /**
     * Check if this LDAP Object is of a specific objectCategory.
     *
     * @param string $category
     * @return bool
     */
    public function isCategory($category)
    {
        return ($this->category == $category);
    }

    /**
     * Check to see if a specific attribute exists.
     *
     * @param string $attribute
     * @return bool
     */
    public function hasAttribute($attribute)
    {
        return array_key_exists(strtolower($attribute), array_change_key_case($this->attributes));
    }

    /**
     * Get the value of an attribute. An attribute with multiple values will return an array of values.
     *
     * @param string $attribute
     * @return mixed
     */
    public function get($attribute)
    {
        if ($this->hasAttribute($attribute)) {
            return array_change_key_case($this->attributes)[strtolower($attribute)];
        } else {
            throw new \InvalidArgumentException(
                sprintf('Attribute "%s" is not defined for this LDAP object.', $attribute)
            );
        }
    }

    /**
     * Set a value for an attribute. If a value already exists it will be replaced.
     *
     * @param string $attribute
     * @param mixed $value
     * @return $this
     */
    public function set($attribute, $value)
    {
        if ($this->hasAttribute($attribute)) {
            $attribute = $this->resolveAttributeName($attribute);
            $this->attributes[$attribute] = $value;
        } else {
            $this->attributes[$attribute] = $value;
        }
        $this->addBatchModification($attribute, LDAP_MODIFY_BATCH_REPLACE, $value);

        return $this;
    }

    /**
     * Remove a specific value from an attribute.
     *
     * @param string $attribute
     * @param mixed $value
     * @return $this
     */
    public function remove($attribute, $value)
    {
        if ($this->hasAttribute($attribute)) {
            $attribute = $this->resolveAttributeName($attribute);
            $this->attributes[$attribute] = $this->removeAttributeValue($this->attributes[$attribute], $value);
        }
        $this->addBatchModification($attribute, LDAP_MODIFY_BATCH_REMOVE, $value);

        return $this;
    }

    /**
     * Resets the attribute, which effectively removes any values it may have.
     *
     * @param string $attribute
     * @return $this
     */
    public function reset($attribute)
    {
        if ($this->hasAttribute($attribute)) {
            $attribute = $this->resolveAttributeName($attribute);
            unset($this->attributes[$attribute]);
        }
        $this->addBatchModification($attribute, LDAP_MODIFY_BATCH_REMOVE_ALL, null);

        return $this;
    }

    /**
     * Add an additional value to an attribute.
     *
     * @param string $attribute
     * @param mixed $value
     * @return $this
     */
    public function add($attribute, $value)
    {
        if ($this->hasAttribute($attribute)) {
            $attribute = $this->resolveAttributeName($attribute);
            $this->attributes[$attribute] = $this->addAttributeValue($this->attributes[$attribute], $value);
        } else {
            $this->attributes[$attribute] = $value;
        }
        $this->addBatchModification($attribute, LDAP_MODIFY_BATCH_ADD, $value);

        return $this;
    }

    /**
     * The array representation of the LDAP object.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Get the batch modifications array.
     *
     * @return array
     */
    public function getBatchModifications()
    {
        return $this->modifications;
    }

    /**
     * Determines which function, if any, should be called.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (!preg_match('/^('.implode('|', $this->functions).')(.*)$/', $method, $matches)) {
            throw new \RuntimeException(sprintf('The method "%s" is unknown.', $method));
        }
        $method = $matches[1];
        $attribute = lcfirst($matches[2]);

        if ('get' == $method) {
            return $this->get($attribute);
        } elseif ('set' == $method) {
            return $this->set($attribute, ...$arguments);
        } elseif ('remove' == $method) {
            return $this->remove($attribute, ...$arguments);
        } elseif ('reset' == $method) {
            return $this->reset($attribute);
        } else {
            return $this->add($attribute, ...$arguments);
        }
    }

    /**
     * Magic property setter.
     *
     * @param string $attribute
     * @param mixed $value
     * @return $this
     */
    public function __set($attribute, $value)
    {
       return $this->set($attribute, $value);
    }

    /**
     * Magic property getter.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
       return $this->get($attribute);
    }

    /**
     * Magically check for an attributes existence on an isset call.
     *
     * @param string $attribute
     * @return bool
     */
    public function __isset($attribute)
    {
        return $this->hasAttribute($attribute);
    }

    /**
     * Retrieve the attribute in the case it exists in the array.
     *
     * @param string $attribute
     * @return string
     */
    protected function resolveAttributeName($attribute)
    {
        return reset(preg_grep("/^$attribute$/i", array_keys($this->attributes)));
    }

    /**
     * Adds a batch modification for the ldap_batch_modify method.
     *
     * @param string $attribute
     * @param int $modType
     * @param mixed $value
     */
    protected function addBatchModification($attribute, $modType, $value)
    {
        $modification = [
            'attrib' => $attribute,
            'modtype' => $modType,
        ];
        if (!is_null($value)) {
            $modification['values'] = is_array($value) ? $value : [ $value ];
        }
        $this->modifications[] = $modification;
    }

    /**
     * Given the original value, remove if it's present.
     *
     * @param mixed $value
     * @param mixed $valueToRemove
     * @return mixed
     */
    protected function removeAttributeValue($value, $valueToRemove)
    {
        $valueToRemove = is_array($valueToRemove) ? $valueToRemove : [ $valueToRemove ];

        foreach ($valueToRemove as $remove) {
            if (is_array($value) && (($key = array_search($remove, $value)) !== false)) {
                unset($value[$key]);
            } elseif (!is_array($value) && ($value == $remove)) {
                $value = '';
            }
        }

        return $value;
    }

    /**
     * Adds an additional value to an existing LDAP attribute value.
     *
     * @param mixed $value
     * @param mixed $valueToAdd
     * @return array
     */
    protected function addAttributeValue($value, $valueToAdd)
    {
        $valueToAdd = is_array($valueToAdd) ? $valueToAdd : [ $valueToAdd ];
        $value = is_array($value) ? $value : [ $value ];

        return array_merge($value, $valueToAdd);
    }
}