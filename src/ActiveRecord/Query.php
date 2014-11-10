<?php

namespace Solution10\ORM\ActiveRecord;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Solution10\ORM\ActiveRecord\Exception\QueryException;

class Query
{
    /**
     * @var     string
     */
    protected $model;

    /**
     * @var     QueryBuilder
     */
    protected $query;

    /**
     * @var     array   Holds the query in an array form before we use the QueryBuilder to do it for real.
     */
    protected $parts = [];

    /**
     * Starts a brand new query
     *
     * @param   string  $model  The Model class that this query is for
     * @throws  QueryException
     */
    public function __construct($model)
    {
        $this->model($model);
    }

    /**
     * Gets/Sets the model name for this query
     *
     * @param   string|null     $model  NULL for get, string for set
     * @return  $this|string
     * @throws  QueryException
     */
    public function model($model = null)
    {
        if ($model === null) {
            return $this->model;
        }

        if (!class_exists($model)) {
            throw new QueryException(
                'Model provided for query "'.$model.'" does not exist!',
                QueryException::UNKNOWN_MODEL
            );
        }
        $this->model = $model;

        return $this;
    }

    /**
     * ------------------- Query Building Passthroughs -------------------
     */

    /**
     * Get/Set the Select columns.
     *
     * @param   string|array|null   $columns
     * @return  $this|array
     */
    public function select($columns = null)
    {
        if ($columns === null) {
            return (array_key_exists('SELECT', $this->parts))? $this->parts['SELECT'] : [];
        }

        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $this->parts['SELECT'] = $columns;
        return $this;
    }

    /**
     * Get/Set Table to pull from.
     *
     * @param   string|null     $table      String to set, NULL to return
     * @param   string|null     $alias
     * @return  $this|string|array
     */
    public function from($table = null, $alias = null)
    {
        if ($table === null) {
            return (array_key_exists('FROM', $this->parts))? $this->parts['FROM'] : [];
        }

        ($alias !==  null)? $this->parts['FROM'][$table] = $alias : $this->parts['FROM'][] = $table;

        return $this;
    }

    /**
     * Get/Set an "AND WHERE" clause on the query. You can either pass a simple
     * comparison ('name', '=', 'Alex') or a function to append multiple queries
     * in a group:
     *
     *  $query->where(function($query) {
     *          $query
     *              ->where('user', '=', 'Alex')
     *              ->where('country', '=', 'GB');
     *      })
     *      ->orWhere(function($query) {
     *          $query->where('user', '=', 'Lucie');
     *          $query->where('country', '=', 'CA');
     *      });
     *
     * Would generate:
     *
     *  WHERE (name = 'Alex' AND country = 'GB')
     *  OR (name = 'Lucie' AND country = 'CA')
     *
     * @param   string|function|null    Fieldname|callback for group|to return
     * @param   string|null             Operator (=, !=, <>, <= etc)
     * @param   mixed|null              Value to test against
     * @return  $this|array             $this on set, array on get
     */
    public function where($field = null, $operator = null, $value = null)
    {
        if ($field === null) {
            return (array_key_exists('WHERE', $this->parts))? $this->parts['WHERE'] : [];
        }

        if ($field instanceof \Closure) {
            // Return and merge the result of these queries
        } else {
//            $this->query->where($field.' '.$operator.' '.$this->query->createNamedParameter($value));
        }

        return $this;
    }
}
