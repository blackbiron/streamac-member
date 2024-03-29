<?php

/**
 * Represents an SQL query
 * @package Am_Query
 */
class Am_Query implements Am_Grid_DataSource_Interface_Editable
{
    /** @var DbSimple_Mysql */
    protected $db;
    protected $conditions = [];
    protected $order = [];
    protected $start;
    protected $count;
    protected $distinct = false;
    protected $fields = ['*'];
    protected $joins = [];
    protected $having = [];
    protected $where = [];
    protected $unions = [];
    protected $groupBy = [];
    protected $autoGroupBy = true;
    protected $foundRows;
    /** @var Am_Table */
    protected $table;
    protected $alias;
    protected $tableName;
    protected $keyField;

    function  __construct(Am_Table $table, $alias = 't')
    {
        $this->db = $table->getAdapter();
        $this->table = $table;
        $this->alias = $alias;
        $this->tableName = $table->getName();
        $this->keyField = $table->getKeyField();
    }

    /**
     * @param bool $flag
     * @return Am_Query
     */
    public function toggleAutoGroupBy($flag)
    {
        $this->autoGroupBy = $flag;
        return $this;
    }

    function setFromRequest($vars)
    {
        return;
    }

    /**
     * @return Zend_Cache_Core
     */
    function getCache()
    {
        return Am_Di::getInstance()->cache;
    }

    function getCacheId($prefix = 'total')
    {
        return $this->db->getPrefix().md5($prefix.'-'.$this->getSql());
    }

    /** @return PDOStatement */
    function query($start=null, $count=null)
    {
        $this->foundRows = null;
        $sql = $this->getSql($start, $count);

        if (!AM_HUGE_DB){
            $sql = preg_replace('/^SELECT /i', 'SELECT SQL_CALC_FOUND_ROWS ', $sql);
        }

        $ret = $this->db->queryResultOnly($sql);

        if (!AM_HUGE_DB){
            $this->foundRows = $this->db->selectCell("SELECT FOUND_ROWS()");
        }
        return $ret;
    }

    /*function query___($start=null, $count=null){
        $sql = $this->getSql($start, $count);
        $ret = $this->db->queryResultOnly($sql);
        $sql = preg_replace("/LIMIT (.)*$/","",$sql);
        $sql = preg_replace("/ORDER BY (.)*$/","",$sql);
        if(preg_match("/GROUP BY (.*)$/",$sql,$m))
        {
            $cnt = 'distinct '.$m[1];
            $sql = preg_replace("/GROUP BY (.)*$/","",$sql);
        }
        else
            $cnt = '*';
        $sql = preg_replace('/^SELECT (.)* FROM/i', "SELECT count($cnt) FROM", $sql);
        $this->foundRows = $this->db->selectCell($sql);
        return $ret;
    }*/

    function getFoundRows()
    {
        if ($this->foundRows === null)
        {
            if (AM_HUGE_DB){
                if ($count = $this->getCache()->load($this->getCacheId())) {
                    $this->foundRows = $count;
                }

                if (empty($this->foundRows)) {
                    $countRowsQuery = clone $this;
                    $countRowsQuery->clearOrder();
                    foreach($this->getUnions() as $union){
                        $union->clearOrder();
                    }
                    $this->foundRows = $countRowsQuery->db->selectCell("select count(*) from (".$countRowsQuery->getSql().") countQuery");

                    if ($this->foundRows > AM_HUGE_DB_MIN_RECORD_COUNT)
                    {
                        $this->getCache()->save($this->foundRows, $this->getCacheId(), [AM_HUGE_DB_CACHE_TAG], AM_HUGE_DB_CACHE_TIMEOUT);
                    }
                }
            } else {
                $this->query(0,1);
            }
        }
        return (int)$this->foundRows;
    }

    function getSql($start=null, $count=null)
    {
        $fields = "";
        foreach ($this->fields as $f){
            if (is_string($f))
                $f = [$f, null];
            if (preg_match('/^[a-zA-Z0-9*]+$/', $f[0]))
                $f[0] = $this->alias . '.' . $f[0];
            $fields .= $f[0];
            if ($f[1] !== null)
                $fields .= " AS " . $this->db->escape($f[1], true);
            $fields .= ",";
        }
        $fields = trim($fields, ',');

        $joins = [];
        foreach ($this->joins as $join) {
            if (empty($join[4])) // add condition
            {
                $key = $this->keyField;
                $join[4] = sprintf('%s.%s=%s.%s',
                    $this->alias, $key,
                    $join[2], $key);
            }
            $joins[] = join(' ', $join);
        }
        $distinct = $this->distinct ? 'DISTINCT ' : '';
        $ret = "SELECT {$distinct}{$fields} FROM {$this->tableName} {$this->alias}";
        $where = $this->where;
        $having = $this->having;
        foreach ($this->conditions as $c)
        {
            if ($j = $c->getJoin($this)) $joins[] = $j;
            if ($w = $c->getWhere($this)) $where[] = '('.$w.')';
            if ($h = $c->getHaving($this)) $having[] = '('.$h.')';
        }
        if ($joins) $ret .= " " . join(' ', $joins);
        if ($where) $ret .= " WHERE " . join(' AND ', $where);
        if ($joins && !$this->groupBy)
        {
            $this->addAutoGroupBy();
            $ret .= $this->getGroupBy();
        } elseif ($this->groupBy) {
            $ret .= $this->getGroupBy();
        }
        if ($having) $ret .= " HAVING " . join(' AND ', $having);
        if ($this->unions)
        {
            $ret  = "($ret)";
            foreach ($this->unions as $q) {
                $ret .= " UNION (" . $q->getSql() . ")";
            }
        }
        $ret .= $this->getSqlOrder();
        if ($count > 0) {
            $count = (int)$count;
            $start = (int)$start;
            $ret .= " LIMIT {$start},{$count}";
        }
        return $ret;
    }

    protected function getSqlOrder()
    {
        if (!$this->order) return "";
        $ret = " ORDER BY ";
        foreach ($this->order as $o)
        {
            if ($o[1] == 'RAW')
            {
                $ret .= $o[0];
            } else {
                $ret .= $this->db->escape($o[0],true);
                if ($o[1] == 'DESC') $ret .= " DESC";
            }
            $ret .= ",";
        }
        return trim($ret, ',');
    }

    /**
     * Select records on given "page"
     * @param int $pageNum page# to display, starting from 0
     * @param int $count records per page
     * @return array of Am_Record
     */
    function selectPageRecords($pageNum, $count)
    {
        if ($count <= 0)
            throw new Am_Exception_InternalError("count could not be empty in " . __METHOD__);
        $q = $this->query($pageNum * $count, $count);
        $ret = [];
        while ($row = $this->db->fetchRow($q))
            $ret[] = $this->table->createRecord($row);
        return $ret;
    }

    function selectAllRecords()
    {
        $q = $this->query();
        $ret = [];
        while ($row = $this->db->fetchRow($q))
            $ret[] = $this->table->createRecord($row);
        return $ret;
    }

    function selectRows($pageNum, $count)
    {
        if ($count <= 0)
            throw new Am_Exception_InternalError("count could not be empty in " . __METHOD__);
        $q = $this->query($pageNum * $count, $count);
        $ret = [];
        while ($row = $this->db->fetchRow($q))
            $ret[] = $row;
        return $ret;
    }

    function add(Am_Query_Condition $c)
    {
        $this->conditions[] = $c;
        return $c;
    }

    function getConditions()
    {
        return $this->conditions;
    }

    function clearConditions()
    {
        $this->conditions = [];
    }

    ///// order /////
    function clearOrder()
    {
        $this->order = [];
        return $this;
    }

    function addOrder($field, $desc=false)
    {
        $this->order[] = [$field, $desc ? 'DESC' : null];
        return $this;
    }

    function addOrderRaw($orderExpr)
    {
        $this->order[] = [$orderExpr, 'RAW'];
        return $this;
    }

    function setOrder($field, $dir=null)
    {
        $this->clearOrder();
        return $this->addOrder($field, $dir);
    }

    function setOrderRaw($string)
    {
        $this->clearOrder();
        $this->addOrderRaw($string);
        return $this;
    }

    /////////////////// fields /////////////////////
    function addField($expr, $alias=null)
    {
        $this->fields[] = [$expr, $alias];
        return $this;
    }

    function clearFields()
    {
        $this->fields = [];
        return $this;
    }

    ///////////////// where /////////////////////
    function clearWhere()
    {
        $this->where = [];
        return $this;
    }

    function addWhere($expr, $_=null)
    {
        $this->where[] = '(' . $this->db->expandPlaceholders(func_get_args()) . ')';
        return $this;
    }

    ///////////////// joins /////////////////////
    function clearJoins()
    {
        $this->joins = [];
        return $this;
    }

    function leftJoin($table, $alias, $onCondition = null)
    {
        $this->joins[] = ['LEFT JOIN', $table, $alias, 'ON', $onCondition];
        return $this;
    }

    function innerJoin($table, $alias, $onCondition = null)
    {
        $this->joins[] = ['INNER JOIN', $table, $alias, 'ON', $onCondition];
        return $this;
    }

    function fullOuterJoin($table, $alias, $onCondition = null)
    {
        $this->joins[] = ['FULL OUTER JOIN', $table, $alias, 'ON', $onCondition];
        return $this;
    }

    function crossJoin($table, $alias)
    {
        $this->joins[] = ['CROSS JOIN', $table, $alias, null, " "];
        return $this;
    }

    ///////////////// unions ////////////////////
    function addUnion(Am_Query $q)
    {
        $this->unions[] = $q;
        return $this;
    }

    function getUnions()
    {
        return $this->unions;
    }

    function clearUnions()
    {
        $this->unions = [];
        return $this;
    }

    /////////////// groups ////////////////////////
    function clearGroupBy()
    {
        $this->groupBy = [];
        return $this;
    }

    public function addAutoGroupBy()
    {
        if ($this->autoGroupBy)
            $this->groupBy($this->keyField, null, true);
    }

    function groupBy($field, $tableAlias=null, $auto = false)
    {
        $this->groupBy[] = ['fieldName' => $field, 'tableAlias' => $tableAlias, 'auto' => (bool)$auto];
        return $this;
    }

    function getGroupBy()
    {
        if (!count($this->groupBy)) return '';

        $ret = [];
        foreach ($this->groupBy as $g) {
            if ($g['tableAlias'] === '')
                $a = '';
            else
                $a = ($g['tableAlias'] ? $g['tableAlias'] : $this->alias) . '.';
            $ret[] = $a . $g['fieldName'];
        }
        return " GROUP BY " . implode(',', $ret);
    }

    function clearHaving()
    {
        $this->having = [];
        return $this;
    }

    function addHaving($expr, $_=null)
    {
        $queryAndArgs = func_get_args();
        if (count($queryAndArgs )>1)
            $this->db->_expandPlaceholders($queryAndArgs);
        $this->having[] = $queryAndArgs[0];
        return $this;
    }

    function distinct($flag=true)
    {
        $this->distinct = (bool)$flag;
        return $this;
    }

    /**
     * Proxies this request to @see $db
     */
    function escape($s, $isIndent=false)
    {
        return $this->db->escape($s, $isIndent);
    }

    function escapeWithPlaceholders($s, $_)
    {
        $args = func_get_args();
        return call_user_func_array([$this->db,'escapeWithPlaceholders'], $args);
    }

    /** @return Am_Table */
    function getTable()
    {
        return $this->table;
    }

    function getTableName()
    {
        return $this->tableName;
    }

    function getAlias()
    {
        return $this->alias;
    }

    public function getDataSourceQuery()
    {
        return $this;
    }

    public function createRecord()
    {
        return $this->getTable()->createRecord();
    }

    public function deleteRecord($id, $record)
    {
        $record->delete();
    }

    public function getIdForRecord($record)
    {
        return $record->pk();
    }

    public function getRecord($id)
    {
        return $this->getTable()->load($id);
    }

    public function insertRecord($record, $valuesFromForm)
    {
        $record->setForInsert($valuesFromForm)->insert();
    }

    public function updateRecord($record, $valuesFromForm)
    {
        $record->setForUpdate($valuesFromForm)->update();
    }

    public function exportXml(XMLWriter $xml, $options = [])
    {
        $xml->startElement('table_data');
        $xml->writeAttribute('name', $this->getTable()->getName(true));
        $q = $this->query();
        while ($row = $this->db->fetchRow($q))
        {
            $this->getTable()->createRecord($row)
                ->exportXml($xml, $options);
        }
        $xml->endElement();
    }

    public function exportReturnXml($options = [])
    {
        $x = new XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument();
        $this->exportXml($x, $options);
        return $x->flush();
    }
}

/**
 * Abstract query filter condition
 * @package Am_Query
 */
abstract class Am_Query_Condition
{
    protected $_or = [];

    /** @return string to be included into query WHERE part (without brackets), must be escaped using $db */
    function getJoin(Am_Query $db)
    {
        $join = [$this->_getJoin($db)];
        foreach ($this->_or as $or) {
            $join[] = $or->getJoin($db);
        }
        $join = array_filter($join, 'strlen');
        return implode(' ', $join);
    }

    final function getWhere(Am_Query $db)
    {
        $cond = [$this->_getWhere($db)];
        foreach ($this->_or as $or)
            $cond[] = $or->getWhere($db);
        if (count($cond) == 1) return $cond[0];
        $cond = array_filter($cond, 'strlen');
        foreach ($cond as $k=>$v) $cond[$k] = "($v)";
        return implode(' OR ', $cond);
    }

    function _getJoin(Am_Query $db) {}
    /**
     * This function can be overriden
     * @return string escaped where string
     */
    function _getWhere(Am_Query $db) {}

    function getHaving(Am_Query $db){}

    function _or(Am_Query_Condition $cond)
    {
        $this->_or[] = $cond;
        return $this;
    }
}

/**
 * Make sure table field has value
 */
class Am_Query_Condition_Field extends Am_Query_Condition
{
    static protected $validOperations = ['<','<>','=','>','<=','>=','<=>','IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE', 'REGEXP', 'IN', 'NOT IN', 'IS EMPTY', 'IS NOT EMPTY'];
    protected $field;
    protected $op;
    protected $value;
    protected $tableAlias;

    /**
     * Construct a query object
     * @param string $field
     * @param string $op any valid SQL operator from the list above
     * @param string $value to compare with
     */
    function __construct($field, $op, $value=null, $tableAlias = null)
    {
        $this->field = $field;
        $this->tableAlias = $tableAlias;
        $this->init($op, $value);
    }

    protected function init($op, $value)
    {
        if (!in_array($op, self::$validOperations))
            throw new Am_Exception_InternalError("Invalid operator provided: " . htmlentities($op) . " in ".__METHOD__);
        $this->op = $op;
        $this->value = $value;
    }

    function _getWhere(Am_Query $q)
    {
        if ($this->op == 'IS EMPTY') {
            $fn = $this->getAlias($q) . '.' . $q->escape($this->field, true);
            $ret = "($fn IS NULL OR CAST($fn AS CHAR)='0' OR CAST($fn AS CHAR)='' OR CAST($fn AS CHAR)='a:0:{}')";
        } elseif ($this->op == 'IS NOT EMPTY') {
            $fn = $this->getAlias($q) . '.' . $q->escape($this->field, true);
            $ret = "($fn IS NOT NULL AND CAST($fn AS CHAR)<>'0' AND CAST($fn AS CHAR)<>'' AND CAST($fn AS CHAR)<>'a:0:{}')";
        } elseif ($this->op == '=' && is_array($this->value)) {
            $ret = $this->getAlias($q) . '.' . $q->escape($this->field, true) . ' IN '
                . '(' . implode(',', array_map([$q,'escape'], $this->value)) . ')';
        } else {
            $fn = $this->getAlias($q) . '.' . $q->escape($this->field, true);
            if ($this->op == 'IN' || $this->op == 'NOT IN') {
                $val = '(' . implode(',', array_map([$q,'escape'], is_array($this->value) ? $this->value : explode(',', $this->value))) . ')';
                if ($this->op == 'IN') {
                    $ret = "$fn IN $val";
                } else {
                    $ret = "$fn IS NULL OR $fn NOT IN $val";
                }
            } elseif ($this->op == 'IS NULL' || $this->op == 'IS NOT NULL') {
                $ret = "$fn $this->op";
            } else {
                $ret = $fn . " $this->op " . $q->escape($this->value);
            }
        }
        return $ret;
    }

    private function getAlias(Am_Query $q)
    {
        return (is_null($this->tableAlias) ? $q->getAlias() : $this->tableAlias);
    }
}

/**
 * Make sure related ?_data records has given value
 */
class Am_Query_Condition_Data extends Am_Query_Condition
{
    static protected $validOperations = ['<','<>','=','>','<=','>=','<=>','IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE'];
    protected $field;
    protected $op;
    protected $value;
    protected $tableAlias;
    /** check am_data.blob field instead of am_data.value */
    protected $checkBlob = false;

    /**
     * Construct a query object
     * @param string $field
     * @param string $op any valid SQL operator from the list above
     * @param string $value to compare with
     */
    function  __construct($field, $op, $value=null, $tableAlias = null, $checkBlob = false)
    {
        $this->field = $field;
        $this->tableAlias = $tableAlias;
        $this->checkBlob = $checkBlob;
        $this->init($op, $value);
    }

    protected function init($op, $value)
    {
        if (!in_array($op, self::$validOperations))
            throw new Am_Exception_InternalError("Invalid operator provided: " . htmlentities($op) . " in ".__METHOD__);
        $this->op = $op;
        $this->value = $value;
    }

    function _getWhere(Am_Query $q)
    {
        $field = $this->checkBlob ? $this->selfAlias().'.`blob`' : $this->selfAlias().'.`value`';
        $ret = $field . ' ' . $this->op ;
        if ($this->op != 'IS NULL' && $this->op != 'IS NOT NULL')
            $ret .= ' ' . $q->escape($this->value);
        return $ret;
    }

    public function getJoin(Am_Query $db)
    {
        $a = $this->getAlias($db);
        $table = str_replace('?_', '', $db->getTableName());
        $pk = $db->getTable()->getKeyField();
        $key = $db->escape($this->field);
        $ret = "INNER JOIN ?_data ".$this->selfAlias()." ON ".$this->selfAlias().".`table`='$table' AND ".$this->selfAlias().".`id`=`$a`.`$pk` AND ".$this->selfAlias().".key=$key";
        return $ret;
    }

    private function getAlias(Am_Query $q)
    {
        return (is_null($this->tableAlias) ? $q->getAlias() : $this->tableAlias);
    }

    protected function selfAlias()
    {
        return 'dcd';
    }
}