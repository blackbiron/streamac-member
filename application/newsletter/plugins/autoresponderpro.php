<?php

class Am_Newsletter_Plugin_Autoresponderpro extends Am_Newsletter_Plugin
{
    const PLUGIN_STATUS = self::STATUS_BETA;

    public function getTitle()
    {
        return 'Auto Responder Pro';
    }

    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('username', ['class' => 'am-el-wide'])
            ->setLabel("Username\n" .
                'The user name used to login to the AutoResponderPro Email Marketer')
            ->addRule('required');

        $form->addSecretText('usertoken', ['class' => 'am-el-wide'])
            ->setLabel("Token\n" .
                'The unique token assigned to the user account used above')
            ->addRule('required');

        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    public function isConfigured()
    {
        return $this->getConfig('username')
            && $this->getConfig('usertoken');
    }

    protected function getApi()
    {
        return new Am_Autoresponderpro_Api($this);
    }

    protected function getUserCustomFields(User $user)
    {
        $country = $this->getDi()->countryTable->findFirstByCountry($user->country);
        return [
            2 => $user->name_f,
            3 => $user->name_l,
            4 => $user->phone,
            8 => $user->city,
            9 => $user->state,
            10 => $user->zip,
            11 => $country->alpha3,
        ];
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        foreach ($addLists as $list_id)
        {
            $api->call(
                'AddSubscriberToList',
                [
                    'username' => $this->getConfig('username'),
                    'usertoken' => $this->getConfig('usertoken'),
                    'listId' => $list_id,
                    'email' => $user->email,
                    'user' => $this->getUserCustomFields($user)
                ]
            );
        }
        foreach ($deleteLists as $list_id)
        {
            $api->call(
                'DeleteSubscriber',
                [
                    'username' => $this->getConfig('username'),
                    'usertoken' => $this->getConfig('usertoken'),
                    'listId' => $list_id,
                    'email' => $user->email
                ]
            );
        }
        return true;
    }

    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $ef = $this->getConfig('email_field', 'email');
        $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
        $lists = [];
        foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
        {
            if ($list->plugin_id != $this->getId()) continue;
            $lists[] = $list->plugin_list_id;
        }
        $user->set($ef, $oldEmail)->toggleFrozen(true);
        $this->changeSubscription($user, [], $lists);
        // subscribe again
        $user->set($ef, $newEmail)->toggleFrozen(false);
        $this->changeSubscription($user, $lists, []);
    }

    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $user = $event->getUser();
        $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
        $api = $this->getApi();
        foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
        {
            if ($list->plugin_id != $this->getId()) continue;
            $subscribers = $api->call(
                'GetSubscribers',
                [
                    'username' => $this->getConfig('username'),
                    'usertoken' => $this->getConfig('usertoken'),
                    'listId' => $list->plugin_list_id,
                    'email' => $user->email
                ]
            );
            $api->call(
                'SaveSubscriberCustomField',
                [
                    'username' => $this->getConfig('username'),
                    'usertoken' => $this->getConfig('usertoken'),
                    'subscriberId' => $subscribers->data->subscriberlist->item->subscriberid,
                    'user' => $this->getUserCustomFields($user)
                ]
            );
        }
    }

//    public function getLists()
//    {
//        $api = $this->getApi();
//        $lists = array();
//        $xml = $api->call(
//                'GetLists',
//                array(
//                    'username' => $this->getConfig('username'),
//                    'usertoken' => $this->getConfig('usertoken')
//                )
//        );
//        foreach ($xml->data->item as $item)
//        {
//            $lists[(string)$item->listid] = array(
//                'title' => (string)$item->name,
//            );
//        }
//        return $lists;
//    }

}

class Am_Autoresponderpro_Api extends Am_HttpRequest
{

    protected $url = "http://autoresponderpro.it/email/xml.php";
    protected $plugin;

    public function __construct(Am_Newsletter_Plugin_Autoresponderpro $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
        $this->setMethod(self::METHOD_POST);
        $this->setHeader('Content-type: text/xml; charset=utf-8');
        $this->setUrl($this->url);
    }

    public function call($method,  $vars)
    {
        $xml_out=$this->prepCall($method, $vars);
        $this->setBody($xml_out);
        $response = parent::send();
        $this->plugin->debug($this, $response);
        if ($response->getStatus() != '200')
            throw new Am_Exception_InternalError("AutoResponderPro API Error, is configured API is wrong");

        $body = $response->getBody();
        $xml = simplexml_load_string($body);
        if (!$xml)
            throw new Am_Exception_InternalError("AutoResponderPro API Error, returned not xml: $body. Method: [$method]");

        if ($xml->status != 'SUCCESS')
            throw new Am_Exception_InternalError("AutoResponderPro API Error: $xml->errormessage. Method: [$method]");

        if ($this->plugin->getConfig('debuglog')) {
            $this->plugin->logDebug("XML-request:" .(string)$xml_out. ". XML-response: ". $body);
        }

        return $xml;
    }

    protected function prepCall($method,  $vars) {
        $xml = new SimpleXMLElement('<xmlrequest/>');
        $xml->{'username'} = $vars['username'];
        $xml->{'usertoken'} = $vars['usertoken'];
        $xml->{'requestmethod'} = $method;
        switch ($method){
            case 'AddSubscriberToList':
                $xml->{'requesttype'} = 'subscribers';
                $xml->{'details'}->{'emailaddress'} = $vars['email'];
                $xml->{'details'}->{'mailinglist'} = $vars['listId'];
                $xml->{'details'}->{'format'} = 'html';
                $xml->{'details'}->{'confirmed'} = 'yes';
                $i = 0;
                foreach ($vars['user'] as $key => $value)
                {
                    if(!empty($value)){
                        $xml->{'details'}->{'customfields'}->{'item'}[$i]->{'fieldid'} = $key;
                        $xml->{'details'}->{'customfields'}->{'item'}[$i]->{'value'} = $value;
                        $i++;
                    }
                }
                break;

            case 'DeleteSubscriber':
                $xml->{'requesttype'} = 'subscribers';
                $xml->{'details'}->{'emailaddress'} = $vars['email'];
                $xml->{'details'}->{'listid'} = $vars['listId'];
                break;

            case 'GetLists':
                $xml->{'requesttype'} = 'lists';
                $xml->{'details'} = true;
                break;

            case 'GetSubscribers':
                $xml->{'requesttype'} = 'subscribers';
                $xml->{'details'}->{'searchinfo'}->{'List'} = $vars['listId'];
                $xml->{'details'}->{'searchinfo'}->{'Email'} = $vars['email'];
                break;

            case 'SaveSubscriberCustomField':
                $xml->{'requesttype'} = 'subscribers';
                $xml->{'details'}->{'subscriberids'}->{'id'} = $vars['subscriberId'];
                $i = 0;
                foreach ($vars['user'] as $key => $value)
                {
                    if(!empty($value)){
                        $xml->{'details'}->{'customfields'}->{'item'}[$i]->{'fieldid'} = $key;
                        $xml->{'details'}->{'customfields'}->{'item'}[$i]->{'value'} = $value;
                        $i++;
                    }
                }
                break;

            default:
                throw new Am_Exception_InternalError("AutoResponderPro API Error: unknown method: $method");
                break;
        }
        return $xml->asXML();
    }
}