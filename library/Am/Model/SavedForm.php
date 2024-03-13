<?php
/**
 * Class represents records from table saved_form
 * {autogenerated}
 * @property int $saved_form_id
 * @property string $title
 * @property string $comment
 * @property string $code
 * @property string $fields
 * @property string $tpl
 * @property string $type
 * @property string $default_for
 * @see Am_Table
 * @package Am_SavedForm
 */
class SavedForm extends Am_Record_WithData
{
    // default_for field values (set)
    const D_SIGNUP  = 'signup';
    const D_MEMBER  = 'member';
    const D_CART    = 'cart';
    const D_AFF     = 'aff';
    const D_PROFILE = 'profile';

    /// type values
    const T_SIGNUP  = 'signup';
    const T_CART    = 'cart';
    const T_PROFILE = 'profile';

    protected $_bricks = null;

    public function getTypeDef()
    {
        return $this->getTable()->getTypeDef($this->type);
    }
    public function isSignup()
    {
        $typeDef = $this->getTypeDef();
        return isset($typeDef['isSignup']) && $typeDef['isSignup'];
    }
    public function isCart()
    {
        return $this->type == self::T_CART;
    }
    public function isProfile()
    {
        return $this->type == self::T_PROFILE;
    }
    public function getDefaultFor()
    {
        return array_filter(explode(',',$this->default_for ?? ''));
    }
    public function setDefaultFor(array $values)
    {
        return $this->default_for = implode(',', array_unique(array_filter(array_map('filterId', $values))));
    }
    public function addDefaultFor($d)
    {
        $def = $this->getDefaultFor();
        $def[] = $d;
        $this->setDefaultFor($def);
        return $this;
    }
    public function delDefaultFor($d)
    {
        $def = $this->getDefaultFor();
        array_remove_value($def, $d);
        $this->setDefaultFor($def);
        return $this;
    }
    public function isDefault($dConst)
    {
        return in_array($dConst, $this->getDefaultFor());
    }
    public function getUrl($baseUrl = "")
    {
        $type = $this->getTypeDef();
        if (empty($type['urlTemplate']))
            return;
        if (is_callable($type['urlTemplate']))
            $add = call_user_func($type['urlTemplate'], $this);
        else
            $add = $type['urlTemplate'];
        return $baseUrl . $add;
    }
    /** @return Am_Form_Bricked */
    public function createForm()
    {
        $type = $this->getTypeDef();
        if (!$type['class']) throw new Am_Exception("Could not instantiate form - empty class in typeDef");
        return new $type['class'];
    }
    public function generateCode()
    {
        do {
            $this->code = $this->getDi()->security->randomString(rand(8,9));
        } while ($this->getTable()->findFirstByCode($this->code));
    }
    function getFields()
    {
        return (array)json_decode($this->fields, true);
    }
    function setFields(array $fields)
    {
        $this->fields = json_encode($fields);
    }
    /** @return array of Am_Form_Brick */
    function getBricks()
    {
        if (is_null($this->_bricks)) {
            $ret = [];
            foreach ($this->getFields() as $brickConfig)
            {
                if (strpos($brickConfig['id'],'PageSeparator')===0) continue;
                $brickConfig = array_merge_recursive($brickConfig, ['config' => ['saved_form_id' => $this->pk()]]);
                $b = Am_Form_Brick::createFromRecord($brickConfig);
                if (!$b) continue;
                $ret[] = $b;
            }

            $ret = $this->getDi()->hook->filter($ret, Am_Event::SAVED_FORM_GET_BRICKS, [
                'type' => $this->type,
                'code' => $this->code,
                'savedForm' => $this
            ]);
            foreach ($ret as $brick) {
                $brick->init();
            }
            $this->_bricks = $ret;
        }
        return $this->_bricks;
    }

    function isSingle()
    {
        $typeDef = $this->getTypeDef();
        return isset($typeDef['isSingle']) && $typeDef['isSingle'];
    }

    function canDelete()
    {
        if (!$this->type) return true;
        $typeDef = $this->getTypeDef();
        if (!empty($typeDef['noDelete'])) return false;
        if (!empty($this->default_for)) return false;
        return true;
    }
    public function setDefaultBricks()
    {
        $value = [];
        foreach ($this->createForm()->getDefaultBricks() as $brick)
            $value[] = $brick->getRecord();
        $this->setFields($value);
        return $this;
    }

    public function setDefaults()
    {
        if (empty($this->type))
            throw new Am_Exception_InternalError("Error in ".__METHOD__." could not set defaults without type");

        $typeDef = $this->getTypeDef();

        if (!empty($typeDef['generateCode']) && empty($this->code))
            $this->generateCode();

        if (empty($this->title) && !empty($typeDef['defaultTitle']))
            $this->title = $typeDef['defaultTitle'];

        if (empty($this->comment) && !empty($typeDef['defaultComment']))
            $this->comment = $typeDef['defaultComment'];

        $this->setDefaultBricks();
        return $this;
    }

    function findBrickConfigs($class, $id = null)
    {
        $ret = [];
        foreach ($this->getFields() as $row)
        {
            if (empty($row['class']) || ($row['class'] != $class)) continue;
            if (($id === null) || ($row['id'] == $id)) $ret[] = $row;
        }
        return $ret;
    }
    function addBrickConfig($row)
    {
        $fields = $this->getFields();
        $fields[] = $row;
        $this->setFields($fields);
        return $this;
    }
    function removeBrickConfig($class, $id = null)
    {
        $fields = $this->getFields();
        $count = 0;
        foreach ($fields as $k => $row)
        {
            if ($row['class'] != $class) continue;
            if (($id !== null) && ($row['id'] == $id))
            {
                $count++;
                unset($fields[$k]);
            }
        }
        if ($count)
            $this->setFields($fields);
        return $this;
    }
    /**
     * @return array|null
     */
    function findBrickById($id)
    {
        $fields = $this->getFields();
        foreach ($fields as $k => $row)
        {
            if (($id !== null) && ($row['id'] == $id))
            {
                return $row;
            }
        }
    }

    public function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $max = $this->getAdapter()->selectCell("SELECT MAX(sort_order) FROM {$table_name}");
        $this->sort_order = $max + 1;
        return parent::insert($reload);
    }
}

/**
 * @package Am_SavedForm
 */
class SavedFormTable extends Am_Table_WithData {
    protected $_key = 'saved_form_id';
    protected $_table = '?_saved_form';
    protected $_recordClass = 'SavedForm';

    protected $_eventCalled = false;

    private $types = [
        SavedForm::T_SIGNUP => [
            'type' => SavedForm::T_SIGNUP,
            'class' => 'Am_Form_Signup',
            'title' => 'Signup Form',
            'defaultTitle' => 'Signup Form',
            'defaultComment' => 'customer signup/payment form',
            'isSignup' => true,
            'generateCode' => true,
            'urlTemplate'  => ['Am_Form_Signup', 'getSavedFormUrl'],
        ],
        SavedForm::T_PROFILE => [
            'type' => SavedForm::T_PROFILE,
            'class' => 'Am_Form_Profile',
            'title' => 'Profile Form',
            'defaultTitle' => 'Customer Profile',
            'defaultComment' => 'customer profile form',
            'isSingle' => false,
            'isSignup' => false,
            'generateCode' => true,
            'urlTemplate'  => ['Am_Form_Profile', 'getSavedFormUrl'],
        ],
    ];

    public function getOptions($type = null)
    {
        return $this->_db->selectCol("SELECT saved_form_id as ARRAY_KEY, concat(title, ' (', comment, ')') FROM {$this->_table} {WHERE type=?} ORDER BY title",
            $type ? $type : DBSIMPLE_SKIP);
    }

    /**
     * @param int $d_const one from SavedForm::D_xxx constant
     * @return SavedForm|null
     */
    function getDefault($dConst)
    {
        $row = $this->_db->selectRow("
            SELECT * FROM {$this->_table} 
            WHERE FIND_IN_SET(?, default_for) 
            LIMIT 1",
            $dConst);
        if ($row) return $this->createRecord($row);
    }

    /**
     * @param string $type
     * @return SavedForm
     */
    function getByType($type)
    {
        return $this->findFirstByType($type);
    }

    function addTypeDef(array $typeDef)
    {
        $this->types[$typeDef['type']] = $typeDef;
        return $this;
    }
    /** @return array typedef
     *  @throws exception if not found */
    function getTypeDef($type)
    {
        $this->runEventOnce();
        if (empty($this->types[$type]))
            throw new Am_Exception_InternalError("Could not get typeDef for type [$type]");
        return $this->types[$type];
    }
    final public function getTypeDefs()
    {
        $this->runEventOnce();
        return $this->types;
    }

    protected function runEventOnce()
    {
        if (!$this->_eventCalled)
        {
            $this->_eventCalled = true;
            $this->getDi()->hook->call(Am_Event::SAVED_FORM_TYPES,
                ['table' => $this]);
        }
    }

    function getTypeOptions()
    {
        foreach ($this->getTypeDefs() as $t)
            $ret[$t['type']] = $t['title'];
        return $ret;
    }

    function setDefault($d, $saved_form_id)
    {
        switch ($d)
        {
            case SavedForm::D_SIGNUP:
            case SavedForm::D_MEMBER:
            case SavedForm::D_PROFILE:
                $f = $this->load($saved_form_id);
                if ($f->type != SavedForm::T_SIGNUP &&
                    $f->type != SavedForm::T_PROFILE)
                    throw new Am_Exception_InputError("Could not set default form - it has no 'signup' or 'profile' type");
                $f->addDefaultFor($d)->update();
                // now remove default from all other forms, there should not be too many
                foreach ($this->selectObjects("
                    SELECT * FROM {$this->_table} 
                    WHERE saved_form_id<>?d AND FIND_IN_SET(?, default_for)", $saved_form_id, $d) as $f)
                    $f->delDefaultFor($d)->update();
                break;
            default:
                throw new Am_Exception_InputError("Could not ".__METHOD__." for " . $d->$saved_form_id);
        }
    }

    public function getExistingTypes()
    {
        return $this->_db->selectCol("SELECT DISTINCT `type` FROM {$this->_table} WHERE type <> ''");
    }
}
