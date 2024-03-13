<?php

/**
 * Class represents records from table sms_queue
 * @property int $queue_id
 * @property int $user_id;
 * @property string $to
 * @property string $added
 * @property string|null $after
 * @property string|null $status_date
 * @property string $body
 * @property int $attempt
 * @property string $priority
 * @property string $error
 * @property string $status
 * @see Am_Table
 */
class SmsQueue extends Am_Record
{
    const
        STATUS_NEW = "new", STATUS_SENT = "sent", STATUS_ERROR = "error", STATUS_RETRY = "retry";
    
    function getMessage(): Am_Sms_Message
    {
        $message = new Am_Sms_Message();
        $message->setTo($this->to)->setBody($this->body)->setPriority($this->priority)->setUserId($this->user_id);
        return $message;
    }
    
    /**
     * Message was sent
     * @return void
     * @throws Am_Exception_Db
     * @throws Am_Exception_InternalError
     */
    function statusOk(): self
    {
        $this->status_date = $this->getDi()->sqlDateTime;
        $this->status = self::STATUS_SENT;
        $this->update();
        return $this;
    }
    
    function statusError(string $error): self
    {
        $this->status_date = $this->getDi()->sqlDateTime;
        $this->error = $error;
        $this->status = self::STATUS_ERROR;
        $this->save();
        if ($this->getDi()->config->get('sms-queue-resend')) {
            if ($this->attempt < $this->getDi()->config->get('sms-queue-resend-retries', 2)) {
                /**
                 * @var SmsQueue $newMessage ;
                 */
                $newMessage = $this->getTable()->createRecord();
                $newMessage->to = $this->to;
                $newMessage->body = $this->body;
                $newMessage->status = self::STATUS_RETRY;
                $newMessage->attempt = $this->attempt++;
                $newMessage->user_id = $this->user_id;
                $newMessage->priority = $this->priority;
                $newMessage->after = $this->getDi()->dateTime->modify(sprintf("+%d hours",
                        $this->getDi()->config->get('sms-queue-resend-delay')))->format('Y-m-d  H:i:s');
                $newMessage->added = $this->getDi()->sqlDateTime;
                $newMessage->save();
            }
        }
        
        return $this;
        
    }
    
    
    function insert($reload = true)
    {
        $this->added = $this->getDi()->sqlDateTime;
        $this->status = $this->status ?? self::STATUS_NEW;
        return parent::insert($reload); // TODO: Change the autogenerated stub
    }
}

class SmsQueueTable extends Am_Table
{
    protected $_key = 'queue_id';
    protected $_table = '?_sms_queue';
    protected $_recordClass = 'SmsQueue';
    
    function selectLast($num, $dateThreshold = null)
    {
        return $this->selectObjects("SELECT *
            FROM ?_mail_queue
            {WHERE added > ?}
            ORDER BY added DESC LIMIT ?d", $dateThreshold ?: DBSIMPLE_SKIP, $num);
    }
    
    function createFromMessage(Am_Sms_Message $message)
    {
        /**
         * @var SmsQueue $record ;
         */
        $record = $this->createRecord();
        $record->to = $message->getTo();
        $record->body = $message->getBody();
        $record->priority = $message->getPriority();
        $record->user_id = $message->getUserId();
        $record->save();
        return $record;
    }
    
    function process()
    {
        $start = time();
        
        foreach ($this->selectObjects("
                    SELECT *
                    FROM ?_sms_queue
                    WHERE status in (?a) and (`after` is null or `after`<?) ORDER BY  priority desc, queue_id asc LIMIT 100",
            [SmsQueue::STATUS_NEW, SmsQueue::STATUS_RETRY], $this->getDi()->sqlDateTime) as $record)
        {
            if ((time() - $start) > 4 * 60) {
                return;
            }
            $this->getDi()->smsTransport->sendSaved($record);
            
        }
    }
}
