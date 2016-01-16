<?php

namespace Solution10\ORM\Tests\ActiveRecord\Stubs;

use Solution10\ORM\ActiveRecord\Model;
use Solution10\ORM\ActiveRecord\Meta;
use Solution10\ORM\ActiveRecord\Field;

class User extends Model
{
    public static function init(Meta $meta)
    {
        return $meta
                ->field('name', new Field\Text);
    }
}
