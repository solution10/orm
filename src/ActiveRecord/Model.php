<?php

namespace Solution10\ORM\ActiveRecord;

use Solution10\ORM\ActiveRecord\Exception\ValidationException;
use Solution10\ORM\Connection;
use Solution10\SQL\Expression;
use Valitron\Validator;

/**
 * Model
 *
 * Base model class. Represents instances and runs queries.
 *
 * @package     Solution10\ORM\ActiveRecord
 * @author      Alex Gisby<alex@solution10.com>
 * @license     MIT
 */
abstract class Model
{
    /**
     * @var     array
     */
    protected $original = array();

    /**
     * @var     array
     */
    protected $changed = array();

    /**
     * @var     Meta
     */
    protected $meta;

    /**
     * Constructor.
     *
     * @param   Meta    $meta   Meta information for this model.
     */
    public function __construct(Meta $meta)
    {
        $this->meta = $meta;
    }

    /**
     * Factory, the easiest way of building models.
     *
     * @param   string  $className  Class name of the model you want (or null to infer from get_called_class())
     * @return  Model
     */
    public static function factory($className = null)
    {
        $className = ($className !== null)? $className : get_called_class();

        // Build the meta object:
        $meta = new Meta($className);
        $meta = $className::init($meta);

        return new $className($meta);
    }

    /**
     * This function is responsible for setting up the models fields etc.
     *
     * @param   Meta    $meta
     * @throws  Exception\ModelException
     */
    public static function init(Meta $meta)
    {
        throw new Exception\ModelException(
            'You must define init() in your model',
            Exception\ModelException::NO_INIT
        );
    }

    /**
     * Returns the meta information for this model
     *
     * @return  Meta
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Sets a value into the object. Will cause the object to be marked as changed.
     * You can also pass an associative array if you like.
     *
     * @param   string|array    $key
     * @param   mixed           $value
     * @return  $this
     */
    public function set($key, $value = null)
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }

        foreach ($key as $k => $v) {
            // Perform the field transform on it:
            $field = $this->meta->field($k);
            if ($field) {
                $v = $field->set($this, $k, $v);
            }

            $this->changed[$k] = $v;
        }
        return $this;
    }

    /**
     * Sets data on the model without using the set() callbacks on the
     * fields.
     *
     * @param   array   $data
     * @return  $this
     */
    public function setRaw(array $data)
    {
        foreach ($data as $key => $value) {
            $this->changed[$key] = $value;
        }
        return $this;
    }

    /**
     * Gets a value out of the object. Will return the 'newest' value for this possible,
     * so if you load from a Repo, but change the value, this function returns the new value
     * that you set. To get the old value, use original(). You can also pass a default if you want.
     *
     * @param   string  $key
     * @param   mixed   $default
     * @return  null
     */
    public function get($key, $default = null)
    {
        $value = $default;
        if (array_key_exists($key, $this->changed)) {
            $value = $this->changed[$key];
        } elseif (array_key_exists($key, $this->original)) {
            $value = $this->original[$key];
        }

        // Perform the field transform on it:
        $field = $this->meta->field($key);
        if ($field) {
            $value = $field->get($this, $key, $value);
        }

        return $value;
    }

    /**
     * Returns the original (non-changed) value of the given key. For example:
     *
     *  $o = new ClassUsingRepoItem();
     *  $o->loadFromRepoResource(['name' => 'Alex']);
     *  $o->setValue('name', 'Jake');
     *  $name = $o->getOriginal('name');
     *
     * $name will be 'Alex'.
     *
     * NOTE: Calling setAsSaved() will cause 'Jake' to overwrite 'Alex'! This is not
     * a changelog function, merely a way of uncovering a pre-save-but-changed value.
     *
     * @param   string  $key
     * @return  null
     */
    public function original($key)
    {
        $value = (array_key_exists($key, $this->original))? $this->original[$key] : null;

        if ($value !== null) {
            // Perform the field transform on it:
            $field = $this->meta->field($key);
            if ($field) {
                $value = $field->get($this, $key, $value);
            }
        }

        return $value;
    }

    /**
     * Returns whether a value is set. Equivalent of isset().
     *
     * @param   string  $key
     * @return  bool
     */
    public function isValueSet($key)
    {
        return array_key_exists($key, $this->changed) || array_key_exists($key, $this->original);
    }

    /**
     * Returns a key/value array of changed properties on this object.
     *
     * @return  array
     */
    public function changes()
    {
        return $this->changed;
    }

    /**
     * Returns whether this object has changes waiting for save or not.
     *
     * @return  bool
     */
    public function hasChanges()
    {
        return !empty($this->changed);
    }

    /**
     * Marks this object as saved, clearing the changes and overwriting the original values.
     * Repositories should call this on objects they save.
     *
     * @return  $this
     */
    public function setAsSaved()
    {
        foreach ($this->changed as $key => $value) {
            $this->original[$key] = $value;
        }
        $this->changed = array();

        return $this;
    }

    /**
     * Whether this item has been loaded (or saved previously) to the database.
     * Handy for assessing the state of findById() queries in repos.
     *
     * @return  bool
     */
    public function isLoaded()
    {
        return !empty($this->original);
    }

    /**
     * -------------------- Saving / Updating -------------------------
     */

    /**
     * Saving a model. If loaded from the database, it'll update, if not
     * it'll create.
     *
     * This function will run validation rules against your data and throw
     * a ValidationException if things go wrong.
     *
     * @return  $this
     * @throws  Exception\ValidationException
     */
    public function save()
    {
        // Work out if this is create or update.
        return (array_key_exists($this->meta->primaryKey(), $this->original))?
            $this->doUpdate()
            : $this->doCreate();
    }

    /**
     * Performs a create operation.
     *
     * @return  $this
     */
    protected function doCreate()
    {
        $createData = $this->prepareDataForSave($this->changed);

        $conn = $this->meta->connectionInstance();
        $iid = $conn->insert($this->meta->table(), $createData);

        // Mark it as saved and add in the ID
        $this->setAsSaved();
        $this->original[$this->meta->primaryKey()] = $iid;

        return $this;
    }

    /**
     * Performs an update operation
     *
     * @return  $this
     */
    protected function doUpdate()
    {
        if (empty($this->changed)) {
            return $this;
        }

        $pkField = $this->meta->primaryKey();
        $updateData = $this->prepareDataForSave($this->changed);

        $conn = $this->meta->connectionInstance();
        $conn->update($this->meta->table(), $updateData, [$pkField => $this->original[$pkField]]);

        // Mark it as saved
        $this->setAsSaved();

        return $this;
    }

    /**
     * Processes each of the fields, running save() and validate on them
     * ready for a save operation
     *
     * @param   array   $input
     * @return  array
     */
    protected function prepareDataForSave(array $input)
    {
        $processed = [];
        foreach ($input as $key => $value) {
            $field = $this->meta->field($key);
            $processed[$key] = $field->save($this, $key, $value);
        }
        return $processed;
    }

    /**
     * ------------------ Validation ----------------------
     */

    /**
     * Validates a model based on it's current form. That means changes and
     * original data are merged together to ensure a correct representation.
     *
     * If you want extra, one-shot validation rules, you can pass in an array of
     * rules in the format: {field}: [{rules}] like so:
     *
     *  ->validate([
     *      'password' => [['match', 'password_repeat'], ['lengthMin', 8]]
     *  ]);
     *
     *
     * @param   array   $extra      Extra validation rules.
     * @param   string  $lang       Language to use for validation (default en)
     * @param   string  $langDir    Directory containing translations (@see valitron structure)
     * @return  bool
     * @throws  ValidationException
     */
    public function validate(array $extra = [], $lang = 'en', $langDir = null)
    {
        $input = array_replace_recursive($this->original, $this->changed);
        $input = $this->prepareDataForSave($input);

        $v = new Validator($input, [], $lang, $langDir);
        $class = get_called_class();
        $v = $class::validatorHook($v);

        $fields = $this->meta->fields();
        foreach ($fields as $name => $field) {
            $rules = $field->validation();
            foreach ($rules as $rule) {
                $type = array_shift($rule);

                $params = $rule;
                array_unshift($params, $name);
                array_unshift($params, $type);
                call_user_func_array([$v, 'rule'], $params);
            }
        }

        // Add in the extra validation:
        foreach ($extra as $field => $rules) {
            foreach ($rules as $rule) {
                $type = array_shift($rule);

                $params = $rule;
                array_unshift($params, $field);
                array_unshift($params, $type);
                call_user_func_array([$v, 'rule'], $params);
            }
        }

        if (!$v->validate()) {
            $e = new ValidationException();
            $e->setMessages($v->errors());
            throw $e;
        }
        return true;
    }

    /**
     * Hook for adding in your own custom rules for Valitron. Simply override this function
     * in a subclass of Model.
     *
     * @param   Validator   $v
     * @return  Validator
     */
    public static function validatorHook(Validator $v)
    {
        return $v;
    }


    /**
     * ------------------- Read / Delete -----------------------
     */

    /**
     * Retrieve an item by it's unique identifier
     *
     * @param   mixed   $id     The PK of this item
     * @return  Model
     */
    public static function findById($id)
    {
        $thisClass = get_called_class();
        $instance = self::factory($thisClass);

        $meta = $instance->meta();

        return self::query()
            ->where($meta->primaryKey(), '=', $id)
            ->limit(1)
            ->fetch();
    }

    /**
     * Deleting this item from the database.
     *
     * @return  $this
     */
    public function delete()
    {
        if ($this->isLoaded()) {
            $pkField = $this->meta->primaryKey();

            $conn = $this->meta->connectionInstance();
            $conn->delete($this->meta->table(), [$pkField => $this->original[$pkField]]);
        }

        return $this;
    }

    /**
     * -------------------- Querying -------------------
     */

    /**
     * Provides the starting point for a query against this model with
     * the select() and from() portions already filled in.
     *
     * @return  Select
     */
    public static function query()
    {
        $thisClass = get_called_class();
        $instance = self::factory($thisClass);

        $meta = $instance->meta();

        $q = new Select();
        $q
            ->select('*')
            ->from($meta->table())
            ->flag('model', $thisClass)
        ;
        return $q;
    }

    /**
     * Runs a pre-made query against the database and returns the result.
     * Will not add anything for you, assumes you've done what you need to.
     *
     * @param   Select  $select
     * @return  Model|Resultset|array
     */
    public static function fetchQuery(Select $select)
    {
        $thisClass = get_called_class();
        $instance = self::factory($thisClass);

        $meta = $instance->meta();
        /* @var $conn Connection */
        $conn = $meta->connectionInstance();

        $result = $conn->fetchAll((string)$select, $select->params());
        $fetchMode = ($select->flag('fetch') == 'one')? 'one' : 'all';
        if ($fetchMode === 'one') {
            if (count($result) > 0) {
                $instance->setRaw($result[0]);
                $instance->setAsSaved();
            }
            return $instance;
        }

        $c = new Resultset($result);
        $c->resultModel($instance);
        return $c;
    }

    /**
     * Performs a count query. This will reset certain fields and modify select() to
     * contain only this models table.
     *
     * @param   Select  $select
     * @return  int
     */
    public static function fetchCount(Select $select)
    {
        $thisClass = get_called_class();
        $instance = self::factory($thisClass);

        $meta = $instance->meta();
        /* @var $conn Connection */
        $conn = $meta->connectionInstance();

        $select
            ->resetSelect()
            ->select(new Expression('COUNT('.$meta->primaryKey().')'), 'aggr')
            ->resetOrderBy()
            ->resetLimit()
            ->resetOffset()
        ;

        $result = $conn->fetch((string)$select, $select->params());
        return array_key_exists('aggr', $result)? $result['aggr'] : 0;
    }
}
