<?php

class Am_Newsletter_Plugin_Interspire extends Am_Newsletter_Plugin
{
    protected $fields = [
        'name_f' => 'First Name',
        'name_l' => 'Last Name',
        'phone' => 'Phone',
        'city' => 'City',
        'state' => 'State',
        'zip' => 'Zip',
        'country' => 'Country'
    ];

    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel('XML Path')
            ->addRule('required')
            ->addRule('regex', 'URL must start with http:// or https://', '/^(http|https):\/\//');

        $form->addText('username', ['class' => 'am-el-wide'])
            ->setLabel("XML Username\n" .
                'The user name used to login to the Interspire Email Marketer')
            ->addRule('required');

        $form->addSecretText('usertoken', ['class' => 'am-el-wide'])
            ->setLabel("XML Token\n" .
                'The unique token assigned to the user account used above')
            ->addRule('required');

        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");

        $form->addHidden('use_field_map')
            ->setValue(1);

        if ($this->isConfigured()) {
            $g = $form->addFieldset()
                ->setLabel("Fields Mapping");

            $res = $this->getApi()->call('GetCustomFields',  [
                    'username' => $this->getConfig('username'),
                    'usertoken' => $this->getConfig('usertoken')
            ]);
            if ($res->status == 'SUCCESS') {
                foreach ($res->data->item as $item) {
                    $options[(int)$item->fieldid] = (string)$item->name;
                }
            } else {
                $options = [];
            }

            $op = ['' => '-- None'] + $options;
            foreach($this->fields as $k => $v) {
                $g->addSelect("field_map_$k")
                    ->setLabel($v)
                    ->loadOptions($op);
            }
        } else {
            $form->addHtml()
                ->setLabel("Fields Mapping")
                ->setHtml('Please save access credentails first, and then you will be able to setup fields mapping');
        }
    }

    public function isConfigured()
    {
        return $this->getConfig('url');
    }

    function getApi()
    {
        return new Am_Interspire_Api($this->getConfig('url'), $this);
    }

    function getUserFields(User $user)
    {
        if ($this->getConfig('use_field_map')) {
            $fields = [];
            foreach ($this->fields as $k => $v) {
                if ($id = $this->getConfig("field_map_{$k}")) {
                    $fields[$id] = $user->{$k};
                }
            }
        } else {
            $fields = [
                2 => $user->name_f,
                3 => $user->name_l,
                4 => $user->phone,
                8 => $user->city,
                9 => $user->state,
                10 => $user->zip,
                11 => $user->country
            ];
        }
        return $fields;
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
                    'user' => $this->getUserFields($user)
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
        // fetch all user subscribed ARP lists, unsubscribe
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

    public function getLists()
    {
        $api = $this->getApi();
        $lists = [];
        $xml = $api->call(
            'GetLists',
            [
                'username' => $this->getConfig('username'),
                'usertoken' => $this->getConfig('usertoken')
            ]
        );
        foreach ($xml->data->item as $item) {
            $lists[(string)$item->listid] = [
                'title' => (string)$item->name,
            ];
        }
        return $lists;
    }

}

class Am_Interspire_Api extends Am_HttpRequest
{
    /**
     *
     * @var Am_Newsletter_Plugin_Interspire
     */
    protected $plugin;

    public function __construct($url, $plugin)
    {
        parent::__construct();
        $this->setMethod(self::METHOD_POST);
        $this->setHeader('Content-type: text/xml; charset=utf-8');
        $this->setUrl($url);
        $this->plugin = $plugin;
    }

    public function call($method,  $vars)
    {
        $xml_out=$this->prepCall($method, $vars);
        $this->setBody($xml_out);
        $response = parent::send();
        $this->plugin->debug($this, $response);
        if ($response->getStatus() != '200')
            throw new Am_Exception_InternalError("Interspire API Error, is configured API is wrong");

        $body = $response->getBody();
        $xml = simplexml_load_string($body);
        if (!$xml)
            throw new Am_Exception_InternalError("Interspire API Error, returned not xml: $body. Method: [$method]");
        if ($xml->status != 'SUCCESS')
            throw new Am_Exception_InternalError("Interspire API Error: $xml->errormessage. Method: [$method]");
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

            case 'GetLists':
                $xml->{'requesttype'} = 'user';
                $xml->{'details'}->perpage = 'all';
                break;

            case 'xmlapitest':
                $xml->{'requesttype'} = 'authentication';
                break;

            case 'DeleteSubscriber':
                $xml->{'requesttype'} = 'subscribers';
                $xml->{'details'}->{'emailaddress'} = $vars['email'];
                $xml->{'details'}->{'listid'} = $vars['listId'];
                break;

            case 'GetCustomFields':
                $xml->{'requesttype'} = 'customfields';
                $xml->{'details'}->perpage = 'all';
                break;
            default:
                throw new Am_Exception_InternalError("Interspire API Error: unknown method: $method");
                break;
        }
        return $xml->asXML();
    }
}