<?php
/**
 * Class represents records from table cc
 * {autogenerated}
 * @property int $cc_id
 * @property int $user_id
 * @property int $invoice_id
 * @property string $paysys_id
 * @property string $token
 * @property string $cc
 * @property string $cc_number
 * @property string $cc_expire
 * @property string $cc_name_f
 * @property string $cc_name_l
 * @property string $cc_country
 * @property string $cc_street
 * @property string $cc_street2
 * @property string $cc_city
 * @property string $cc_state
 * @property string $cc_zip
 * @property string $cc_company
 * @property string $cc_phone
 * @property string $cc_housenumber
 * @property string $cc_province
 * @property string $cc_issuenum
 * @property string $cc_startdate
 * @property string $cc_type
 * @see Am_Table
 */

class CcRecord extends Am_Record {
    protected $_key = 'cc_id';
    protected $_table = '?_cc';

    var $_encryptedFields = [
        'cc_number', 'cc_name_f', 'cc_name_l', 'cc_street',
             'cc_street2', 'cc_company', 'cc_phone', 'token'
    ];

    protected $_cc_code;

    function getCvv()
    {
        return $this->_cc_code;
    }
    function setCvv($code)
    {
        $this->_cc_code = filterId($code);
    }
    function maskCc($number)
    {
        $number = preg_replace('/\D+/', '', $number);
        if (strlen($number)<8)
            return '****************';
        return str_repeat('*', strlen($number)-4) .
                substr($number, -4, 4);
    }
    function toRow()
    {
        $arr = parent::toRow();
        // fields to encrypt
        if (isset($arr['cc_number']))
        {
            $arr['cc_number'] = preg_replace('/\D+/', '', $arr['cc_number']);
            if (empty($arr['cc']) || ($arr['cc_number'] != '0000000000000000'))
                $arr['cc'] = $this->maskCc($arr['cc_number']);
        }
        foreach ($this->_encryptedFields as $f)
            if (array_key_exists($f, $arr))
                $arr[$f] = $this->_table->encrypt($arr[$f]);
        return $arr;
    }

    public function fromRow(array $arr)
    {
        // fields to decrypt
        foreach ($this->_encryptedFields as $f)
            if (array_key_exists($f, $arr))
                $arr[$f] = $this->_table->decrypt($arr[$f]);
        return parent::fromRow($arr);
    }

    /**
     * Delete existing record for this user_id, then insert this one
     * @return CcRecord provides fluent interface
     */
    function replace()
    {
        if (empty($this->user_id) || $this->user_id <= 0)
            throw new Am_Exception_InternalError("this->user_id is empty in " . __METHOD__);
        if(!empty($this->paysys_id))
            $this->_table->deleteBy(['user_id' => $this->user_id, 'paysys_id' => $this->paysys_id]);
        else
            $this->_table->deleteByUserId($this->user_id);
        return $this->insert();
    }
    function getExpire($format = "%02d%02d")
    {
        if ("" == $this->cc_expire) return "";
        $m = substr($this->cc_expire, 0, 2);
        $y = substr($this->cc_expire, 2, 2);
        return sprintf($format, $m, $y);
    }
    
    function getToken()
    {
        return !empty($this->token) ? json_decode($this->token, true) : [];
    }
    
    function setToken($token)
    {
        if(is_string($token)){
            $token = [$token];
        }
        $this->token = json_encode($token);
        return $this;
    }
    function updateToken(array $values)
    {
        $token = $this->getToken();
        $this->setToken(is_array($token) ? array_merge($token , $values) : $values);
        return $this;
    }
}

class CcRecordTable extends Am_Table
{
    protected $_crypt;

    protected $_key = 'cc_id';
    protected $_table = '?_cc';
    protected $_recordClass = 'CcRecord';

    function encrypt($s){
        return $this->_getCrypt()->encrypt($s);
    }
    function decrypt($s){
        return $this->_getCrypt()->decrypt($s);
    }
    function _getCrypt(){
        if (empty($this->_crypt))
            $this->_crypt = Am_Di::getInstance ()->crypt;
        return $this->_crypt;
    }
    function setCrypt(Am_Crypt $crypt)
    {
        $this->_crypt = $crypt;
    }
    
    /**
     * Find ccRecord by invoice or create new one
     * @param Invoice $invoice
     * @return CcRecord $cc
     */
    function getRecordByInvoice(Invoice $invoice)
    {
        $cc = $this->findFirstBy(['invoice_id' => $invoice->pk(), 'paysys_id' => $invoice->paysys_id]);
        
        if(empty($cc))
        {
            $cc = $this->createRecord();
            $cc->user_id = $invoice->user_id;
            $cc->invoice_id = $invoice->pk();
            $cc->paysys_id = $invoice->paysys_id;
        }
        
        return $cc;
    }
    
    /**
     * Find ccRecord by user or create new one
     * @param Invoice $invoice
     * @return CcRecord $cc
     */
    function getRecordByUser(User $user, $paysys_id)
    {
        $cc = $this->findFirstBy(['user_id' => $user->pk(), 'paysys_id' => $paysys_id, 'invoice_id' => null]);
        
        if(empty($cc))
        {
            $cc = $this->createRecord();
            $cc->user_id = $user->pk();
            $cc->paysys_id = $paysys_id;
        }
        
        return $cc;
    }

    /**
     * Find ccRecord firt by Paysys ID + User ID, next by User ID + empty Paysys ID, next by User ID
     * @param $user_id
     * @param $paysys_id
     * @return CcRecord $cc
     */
    function findFirstByUserIdPaysysId($user_id, $paysys_id)
    {
        if($cc = $this->findFirstBy(['user_id' => $user_id, 'paysys_id' => $paysys_id]))
            return $cc;
        elseif($cc = $this->findFirstBy(['user_id' => $user_id, 'paysys_id' => '']))
            return $cc;
        return $this->findFirstByUserId($user_id);
    }
    
}

