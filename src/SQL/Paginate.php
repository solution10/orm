<?php

namespace Solution10\ORM\SQL;

/**
 * Paginate
 *
 * Allows pagination of results with a limit() and offset().
 * This works across database engines, abstracting away the differences.
 *
 * @package     Solution10\ORM\SQL
 * @author      Alex Gisby<alex@solution10.com>
 * @license     MIT
 */
trait Paginate
{
    /**
     * @var     int     Limit or results per page
     */
    protected $limit;

    /**
     * @var     int     Offset
     */
    protected $offset = 0;

    /**
     * Set/Get the limit of the query
     *
     * @param   int|null    $limit  Int to set, null to get
     * @return  $this|int
     */
    public function limit($limit = null)
    {
        if ($limit === null) {
            return $this->limit;
        }

        $this->limit = (int)$limit;
        return $this;
    }

    /**
     * Set/Get the offset of the query
     *
     * @param   int|null    $offset     Int to set, null to get
     * @return  $this|int
     */
    public function offset($offset = null)
    {
        if ($offset === null) {
            return $this->offset;
        }

        $this->offset = (int)$offset;
        return $this;
    }

    /**
     * Builds the SQL for the pagination, based on the DB engine
     *
     * @return  string
     */
    public function buildPaginateSQL()
    {
        if (!isset($this->limit)) {
            return '';
        }

//        $ret = $this->limit;
//        $ret = ($this->offset != 0)? $this->offset.', '.$ret : $ret;

        return 'LIMIT '.(($this->offset != 0)? $this->offset.', '.$this->limit : $this->limit);
    }
}