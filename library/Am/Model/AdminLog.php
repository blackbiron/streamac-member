<?php

/**
 * Class represents records from table admin_log
 * {autogenerated}
 * @property int $log_id
 * @property datetime $dattm
 * @property int $admin_id
 * @property string $ip
 * @property string $tablename
 * @property int $record_id
 * @property string $message
 * @property string $admin_login
 * @see Am_Table
 */
class AdminLog extends Am_Record
{
}

class AdminLogTable extends Am_Table
{
    protected $_key = 'log_id';
    protected $_table = '?_admin_log';
    protected $_recordClass = 'AdminLog';

    function clearOld($date)
    {
        $this->_db->query("DELETE FROM ?_admin_log WHERE dattm < ? ", "$date 00:00:00");
    }

    function log($message, $tablename='', $record_id=0, $admin_id=0)
    {
        $admin_id = $admin_id ?: $this->getDi()->authAdmin->getUserId();
        $admin_login = $admin_id ? $this->getDi()->adminTable->load($admin_id)->login : $this->getDi()->authAdmin->getUsername();
        $this->_db->query(
            "INSERT INTO ?_admin_log SET ?a",
            [
                'dattm' => $this->getDi()->sqlDateTime,
                'admin_id' => $admin_id,
                'admin_login' => $admin_login,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'tablename' => $tablename,
                'record_id' => $record_id,
                'message' => $message,
            ]
        );
    }
}