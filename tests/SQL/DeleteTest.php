<?php

namespace Solution10\ORM\Tests\SQL;

use PHPUnit_Framework_TestCase;
use Solution10\ORM\SQL\Delete;

class DeleteTest extends PHPUnit_Framework_TestCase
{
    public function testTable()
    {
        $q = new Delete();
        $this->assertNull($q->table());
        $this->assertEquals($q, $q->table('users'));
        $this->assertEquals('users', $q->table());
    }

    public function testBasicDeleteSQL()
    {
        $q = new Delete();
        $q
            ->table('users')
            ->where('id', '=', 27)
            ->limit(1)
        ;

        $this->assertEquals('DELETE FROM "users" WHERE "id" = ? LIMIT 1', (string)$q);
        $this->assertEquals([27], $q->params());
    }

    public function testResetDelete()
    {
        $q = new Delete();
        $q
            ->table('users')
            ->where('id', '=', 1)
            ->limit(1)
        ;

        $this->assertEquals($q, $q->reset());
        $this->assertEquals('', (string)$q);
        $this->assertEquals(null, $q->table());
        $this->assertEquals([], $q->getWhereParams());
    }
}