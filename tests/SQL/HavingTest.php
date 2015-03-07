<?php

namespace Solution10\ORM\Tests\SQL;

use PHPUnit_Framework_TestCase;
use Solution10\ORM\SQL\ConditionBuilder;

class HavingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @return \Solution10\ORM\SQL\Having
     */
    protected function havingObject()
    {
        return $this->getMockForTrait('Solution10\\ORM\\SQL\\Having');
    }

    public function testNoHaving()
    {
        $w = $this->havingObject();
        $this->assertEquals('', $w->buildHavingSQL());

        // Test return type:
        $this->assertEquals([], $w->having());
    }

    public function testSimpleHaving()
    {
        $w = $this->havingObject();
        $this->assertEquals($w, $w->having('name', '=', 'Alex'));
        $this->assertEquals('HAVING name = ?', $w->buildHavingSQL());
        $this->assertEquals(['Alex'], $w->getHavingParams());

        // Test return type:
        $having = $w->having();
        $this->assertCount(1, $having);
        $this->assertEquals([
            'join' => 'AND',
            'field' => 'name',
            'operator' => '=',
            'value' => 'Alex'
        ], $having[0]);
    }

    public function testSimpleOR()
    {
        $w = $this->havingObject();
        $w->having('name', '=', 'Alex')
            ->orHaving('name', '=', 'Alexander');

        $this->assertEquals('HAVING name = ? OR name = ?', $w->buildHavingSQL());
        $this->assertEquals(['Alex', 'Alexander'], $w->getHavingParams());

        // Test return type:
        $having = $w->having();
        $this->assertCount(2, $having);
        $this->assertEquals([
            'join' => 'AND',
            'field' => 'name',
            'operator' => '=',
            'value' => 'Alex'
        ], $having[0]);

        $this->assertEquals([
            'join' => 'OR',
            'field' => 'name',
            'operator' => '=',
            'value' => 'Alexander'
        ], $having[1]);
    }

    public function testOnlyOR()
    {
        $w = $this->havingObject();
        $w->orHaving('name', '=', 'Alex');

        $this->assertEquals('HAVING name = ?', $w->buildHavingSQL());
        $this->assertEquals(['Alex'], $w->getHavingParams());

        $where = $w->orHaving();
        $this->assertEquals([
            'join' => 'OR',
            'field' => 'name',
            'operator' => '=',
            'value' => 'Alex'
        ], $where[0]);
    }

    public function testSimpleGroup()
    {
        $w = $this->havingObject();
        $w->having('name', '=', 'Alex');
        $w->having(function (ConditionBuilder $q) {
            $q->andWith('city', '=', 'London');
            $q->orWith('city', '=', 'Toronto');
        });

        $this->assertEquals('HAVING name = ? AND (city = ? OR city = ?)', $w->buildHavingSQL());
        $this->assertEquals(['Alex', 'London', 'Toronto'], $w->getHavingParams());

        $having = $w->having();
        $this->assertEquals([
            ['join' => 'AND', 'field' => 'name', 'operator' => '=', 'value' => 'Alex'],
            [
                'join' => 'AND',
                'sub' => [
                    ['join' => 'AND', 'field' => 'city', 'operator' => '=', 'value' => 'London'],
                    ['join' => 'OR', 'field' => 'city', 'operator' => '=', 'value' => 'Toronto']
                ]
            ]
        ], $having);
    }

    /**
     * Time for a final, large and complex query to test the having() and orHaving() clauses.
     */
    public function testHavingComplex()
    {
        $w = $this->havingObject();

        $w
            ->having('name', '=', 'Alex')
            ->orHaving('name', '=', 'Lucie')
            ->having(function (ConditionBuilder $query) {
                $query
                    ->andWith('city', '=', 'London')
                    ->andWith('country', '=', 'GB');
            })
            ->orHaving(function (ConditionBuilder $query) {
                $query
                    ->andWith('city', '=', 'Toronto')
                    ->andWith('country', '=', 'CA')
                    ->orWith(function (ConditionBuilder $query) {
                        $query->andWith('active', '!=', true);
                    });
            });

        $this->assertEquals(
            'HAVING name = ? OR name = ? AND (city = ? AND country = ?) OR (city = ? AND country = ? OR (active != ?))',
            $w->buildHavingSQL()
        );
        $this->assertEquals(
            ['Alex', 'Lucie', 'London', 'GB', 'Toronto', 'CA', true],
            $w->getHavingParams()
        );

        // Check the return types:
        $having = $w->having();
        $this->assertCount(4, $having);
        $this->assertEquals([
            ['join' => 'AND', 'field' => 'name', 'operator' => '=', 'value' => 'Alex'],
            ['join' => 'OR', 'field' => 'name', 'operator' => '=', 'value' => 'Lucie'],
            [
                'join' => 'AND',
                'sub' => [
                    ['join' => 'AND', 'field' => 'city', 'operator' => '=', 'value' => 'London'],
                    ['join' => 'AND', 'field' => 'country', 'operator' => '=', 'value' => 'GB']
                ]
            ],
            [
                'join' => 'OR',
                'sub' => [
                    ['join' => 'AND', 'field' => 'city', 'operator' => '=', 'value' => 'Toronto'],
                    ['join' => 'AND', 'field' => 'country', 'operator' => '=', 'value' => 'CA'],
                    [
                        'join' => 'OR',
                        'sub' => [
                            ['join' => 'AND', 'field' => 'active', 'operator' => '!=', 'value' => true],
                        ]
                    ]
                ]
            ]
        ], $having);
    }
}
