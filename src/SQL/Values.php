<?php

namespace Solution10\ORM\SQL;

/**
 * Values
 *
 * Used by INSERT and UPDATE to se the values for the write-query.
 *
 * @package     Solution10\ORM\SQL
 * @author      Alex Gisby<alex@solution10.com>
 * @license     MIT
 */
trait Values
{
    protected $values = [];

    /**
     * Sets / Gets an array of values for the update/insert query
     *
     * @param   array|null   $values
     * @return  $this|array
     */
    public function values(array $values = null)
    {
        if ($values === null) {
            return $this->values;
        }

        $this->values = array_merge($this->values, $values);
        return $this;
    }

    /**
     * Sets/gets a single value for a given field
     *
     * @param   string          $field
     * @param   mixed|null      $value
     * @return  $this|mixed
     */
    public function value($field, $value = null)
    {
        if ($value === null) {
            return (array_key_exists($field, $this->values))? $this->values[$field] : null;
        }
        $this->values[$field] = $value;
        return $this;
    }
}
