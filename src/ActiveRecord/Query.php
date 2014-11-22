<?php

namespace Solution10\ORM\ActiveRecord;

use Solution10\ORM\ActiveRecord\Exception\QueryException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Connection;

class Query
{
    /**
     * @var     Connection
     */
    protected $conn;

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
     * @var     array   Values for a writey type query
     */
    protected $values = [];

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
     * Gets/Sets the connection for the query builder to use
     *
     * @param   Connection|null     $conn   Connection instance to set, null to get
     * @return  $this|null|Connection
     */
    public function connection(Connection $conn = null)
    {
        if ($conn === null) {
            return $this->conn;
        }
        $this->conn = $conn;
        return $this;
    }

    /**
     * ------------------- Query Building -------------------
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
     * @throws  QueryException
     */
    public function from($table = null, $alias = null)
    {
        if ($table === null) {
            return (array_key_exists('FROM', $this->parts))? $this->parts['FROM'] : [];
        }

        if ($table !== null && $alias === null) {
            throw new QueryException('You must provide a table alias', QueryException::MISSING_ALIAS);
        }

        $this->parts['FROM'][$table] = $alias;

        return $this;
    }

    /**
     * Gets/Sets a JOIN.
     *
     * @param   string|null     $left       String to set the left table (or the alias in from()), null to get
     * @param   string|null     $right      Name of the right table
     * @param   string|null     $alias      Right table alias, if any
     * @param   string|null     $predicate  String expression of how to make the join (u.id = p.u_id)
     * @param   string          $type       Type (LEFT, RIGHT, INNER)
     * @return  $this|array                 $this on set, array on get
     * @throws  Exception\QueryException    On unknown $type
     */
    public function join($left = null, $right = null, $alias = null, $predicate = null, $type = 'INNER')
    {
        if ($left === null) {
            return (array_key_exists('JOIN', $this->parts))? $this->parts['JOIN'] : [];
        }

        if (!in_array($type, ['LEFT', 'RIGHT', 'INNER'])) {
            throw new QueryException(
                'Unknown join type "'.$type.'"',
                QueryException::BAD_JOIN_TYPE
            );
        }

        $this->parts['JOIN'][] = [
            'type' => $type,
            'left' => $left,
            'right' => $right,
            'rightAlias' => $alias,
            'predicate' => $predicate,
        ];

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
     * @param   string|\Closure|null    $field      Fieldname|callback for group|to return
     * @param   string|null             $operator   Operator (=, !=, <>, <= etc)
     * @param   mixed|null              $value      Value to test against
     * @return  $this|array                         $this on set, array on get
     */
    public function where($field = null, $operator = null, $value = null)
    {
        return $this->applyWhere('AND', $field, $operator, $value);
    }

    /**
     * Adds a new 'OR ' predicate to the query. Same rules for types as where() so check
     * the docs there.
     *
     * @param   string|\Closure|null    $field      Fieldname|callback for group|to return
     * @param   string|null             $operator   Operator (=, !=, <>, <= etc)
     * @param   mixed|null              $value      Value to test against
     * @return  $this|array                         $this on set, array on get
     */
    public function orWhere($field = null, $operator = null, $value = null)
    {
        return $this->applyWhere('OR', $field, $operator, $value);
    }

    /**
     * Actually applies the where() clause. See docs on where() for field descriptions.
     *
     * @param   string                  $join       AND or OR
     * @param   string|\Closure|null    $field      Fieldname|callback for group|to return
     * @param   string|null             $operator   Operator (=, !=, <>, <= etc)
     * @param   mixed|null              $value      Value to test against
     * @return  $this|array                         $this on set, array on get
     */
    protected function applyWhere($join, $field = null, $operator = null, $value = null)
    {
        if ($field === null) {
            return (array_key_exists('WHERE', $this->parts))? $this->parts['WHERE'] : [];
        }

        if ($field instanceof \Closure) {
            // Return and merge the result of these queries
            $subQuery = new self($this->model);
            $field($subQuery);
            $this->parts['WHERE'][] = [
                'join' => $join,
                'sub' => $subQuery->where()
            ];
        } else {
            $this->parts['WHERE'][] = [
                'join' => $join,
                'field' => $field,
                'operator' => $operator,
                'value' => $value
            ];
        }

        return $this;
    }

    /**
     * Get/Set the ORDER BY component of the query.
     *
     * @param   string|array|null       $field      Field name or an array of field => direction. Null to get
     * @param   string|null             $direction  ASC by default
     * @return  $this|array
     */
    public function orderBy($field = null, $direction = 'ASC')
    {
        if ($field === null) {
            return (array_key_exists('ORDER BY', $this->parts))? $this->parts['ORDER BY'] : [];
        }

        if (!is_array($field)) {
            $field = [$field => $direction];
        }

        foreach ($field as $f => $d) {
            $this->parts['ORDER BY'][$f] = $d;
        }

        return $this;
    }

    /**
     * Get/Set the limit of the query
     *
     * @param   int|null    $limit  Int to set, null to get
     * @return  $this|int
     */
    public function limit($limit = null)
    {
        if ($limit === null) {
            return (array_key_exists('LIMIT', $this->parts))? $this->parts['LIMIT'] : null;
        }

        $this->parts['LIMIT'] = (int)$limit;
        return $this;
    }

    /**
     * Get/Set the offset of the query
     *
     * @param   int|null    $offset     Int to set, null to get
     * @return  $this|int
     */
    public function offset($offset = null)
    {
        if ($offset === null) {
            return (array_key_exists('OFFSET', $this->parts))? $this->parts['OFFSET'] : 0;
        }

        $this->parts['OFFSET'] = (int)$offset;
        return $this;
    }

    /**
     * Get/Set the values for a write-type query
     *
     * @param   array|null  $values     field => value for set, null for get
     * @return  $this|null
     */
    public function values($values = null)
    {
        if ($values === null) {
            return $this->values;
        }
        $this->values = $values;
        return $this;
    }

    /**
     * Get/Set the group by clause
     *
     * @param   string|null     $clause     String to set, null to get
     * @return  $this|string|null
     */
    public function groupBy($clause = null)
    {
        if ($clause === null) {
            return (array_key_exists('GROUP BY', $this->parts))? $this->parts['GROUP BY'] : null;
        }

        $this->parts['GROUP BY'] = $clause;
        return $this;
    }

    /**
     * Get/Set the having clause
     *
     * @param   string|null     $having     String to set, null to get
     * @return  $this|string|null
     */
    public function having($having = null)
    {
        if ($having === null) {
            return (array_key_exists('HAVING', $this->parts))? $this->parts['HAVING'] : null;
        }

        $this->parts['HAVING'] = $having;
        return $this;
    }

    /**
     * Returns the SQL query as a string
     *
     * @return  string
     * @throws  QueryException  If there's no connection, you'll get an exception
     */
    public function sql()
    {
        if ($this->conn === null) {
            throw new QueryException(
                'No connection set! Use connection() to set a connection on the query',
                QueryException::CONNECTION_MISSING
            );
        }

        $builder = new QueryBuilder($this->conn);

        if (array_key_exists('SELECT', $this->parts)) {
            $builder->select($this->parts['SELECT']);
        }
        if (array_key_exists('FROM', $this->parts)) {
            foreach ($this->parts['FROM'] as $table => $alias) {
                $builder->from($table, $alias);
            }
        }
        if (array_key_exists('JOIN', $this->parts)) {
            foreach ($this->parts['JOIN'] as $join) {
                switch ($join['type']) {
                    case 'INNER':
                        $builder->join($join['left'], $join['right'], $join['rightAlias'], $join['predicate']);
                        break;

                    case 'LEFT':
                        $builder->leftJoin($join['left'], $join['right'], $join['rightAlias'], $join['predicate']);
                        break;

                    case 'RIGHT':
                        $builder->rightJoin($join['left'], $join['right'], $join['rightAlias'], $join['predicate']);
                        break;
                }
            }
        }
        if (array_key_exists('WHERE', $this->parts)) {
            $where = $this->buildWhere($this->parts['WHERE'], $builder);
            $builder->where($where);
        }
        if (array_key_exists('ORDER BY', $this->parts)) {
            foreach ($this->parts['ORDER BY'] as $field => $direction) {
                $builder->addOrderBy($field, $direction);
            }
        }
        if (array_key_exists('LIMIT', $this->parts)) {
            $builder->setMaxResults($this->parts['LIMIT']);
        }
        if (array_key_exists('OFFSET', $this->parts)) {
            $builder->setFirstResult($this->parts['OFFSET']);
        }
        if (array_key_exists('HAVING', $this->parts)) {
            $builder->having($this->parts['HAVING']);
        }
        if (array_key_exists('GROUP BY', $this->parts)) {
            $builder->groupBy($this->parts['GROUP BY']);
        }

        return $builder->getSQL();
    }

    /**
     * Where is a complex, recursive beast, so this builds up each layer of the query.
     *
     * @param   array        $conditions
     * @param   QueryBuilder $builder
     * @return  string
     */
    protected function buildWhere(array $conditions, QueryBuilder $builder)
    {
        $where = '';
        foreach ($conditions as $c) {
            $where .= ' '.$c['join'].' ';
            if (array_key_exists('sub', $c)) {
                $where .= '(';
                $where .= $this->buildWhere($c['sub'], $builder);
                $where .= ')';
            } else {
                $where .= $c['field'].' '.$c['operator'].' '.$builder->createNamedParameter($c['value']);
            }
        }
        $where = trim(preg_replace('/^(AND|OR) /', '', trim($where)));
        return $where;
    }

    /**
     * toString is a shortcut for sql()
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->sql();
    }
}
