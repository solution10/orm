<?php

namespace Solution10\ORM\SQL;

/**
 * Update
 *
 * Generates an SQL query for an UPDATE operation.
 *
 * @package     Solution10\ORM\SQL
 * @author      Alex Gisby<alex@solution10.com>
 * @license     MIT
 */
class Update extends Query
{
    use TableName;
    use Values;
    use Where;
    use Paginate;

    /**
     * Generates the full SQL statement for this query with all the composite parts.
     *
     * @return  string
     */
    public function sql()
    {
        if ($this->table === null) {
            return '';
        }

        $candidateParts = [
            'UPDATE',
            $this->dialect->quoteTable($this->table),
            $this->valuesSQL(),
            $this->buildWhereSQL($this->dialect),
            $this->buildPaginateSQL()
        ];

        return implode(' ', $candidateParts);
    }

    /**
     * Returns the values part of a query for an UPDATE statement (so key = value, key = value)
     *
     * @return  string
     */
    protected function valuesSQL()
    {
        $sql = '';
        if (!empty($this->values)) {
            $sql .= 'SET ';
            $parts = [];
            foreach ($this->values as $field => $value) {
                $parts[] = $this->dialect->quoteField($field).' = ?';
            }
            $sql .= implode(', ', $parts);
        }
        return $sql;
    }

    /**
     * Returns all the parameters, in the correct order, to pass into PDO.
     *
     * @return  array
     */
    public function params()
    {
        return array_merge(array_values($this->values), $this->getWhereParams());
    }

    /**
     * Resets the entire query.
     *
     * @return  $this
     */
    public function reset()
    {
        $this->table = null;
        return $this;
    }
}
