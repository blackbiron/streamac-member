<?php
class Am_Newsletter_Plugin_Autopilot extends Am_Newsletter_Plugin
{
    const AUTOPILOT_ID = 'autopilot-id';

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel('Autopilot API Key')
            ->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    public function isConfigured()
    {
        return $this->getConfig('api_key');
    }

    function getApi()
    {
        return new Am_Autopilot_Api($this);
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        $isJustCreated = false;
        if(!($id = $user->data()->get(self::AUTOPILOT_ID))) {
            $contact = $api->sendRequest('contact/' . urlencode($user->email));
            if($id = @$contact['contact_id']) {
                $user->data()->set(self::AUTOPILOT_ID, $id)->update();
            } else {
                $contact = $api->sendRequest('contact', [
                    'contact' => [
                    'Email' => $user->email,
                    'FirstName' => $user->name_f,
                    'LastName' => $user->name_l,
                    'Phone' => $user->phone,
                    'MailingStreet' => $user->street,
                    'MailingCity' => $user->city,
                    'MailingState' => $user->state,
                    'MailingPostalCode' => $user->zip,
                    'MailingCountry' => $user->country,
                    ]
                ], Am_HttpRequest::METHOD_POST);
                $id = @$contact['contact_id'];
                $user->data()->set(self::AUTOPILOT_ID, $id)->update();
                $isJustCreated = true;
            }
        }
        foreach ($addLists as $list_id) {
            $api->sendRequest("list/$list_id/contact/$id", [],  Am_HttpRequest::METHOD_POST);
        }
        if (!$isJustCreated) {
            foreach ($deleteLists as $list_id) {
                $api->sendRequest("list/$list_id/contact/$id", [],  Am_HttpRequest::METHOD_DELETE);
            }
        }
        return true;
    }

    public function getLists()
    {
        $api = $this->getApi();
        $res = $api->sendRequest('lists');
        $lists = [];
        foreach (array_shift($res) as $l)
            $lists[$l['list_id']] = ['title' => $l['title']];
        return $lists;
    }
}

class Am_Autopilot_Api extends Am_HttpRequest
{
    const API_ENDPOINT = 'https://api2.autopilothq.com/v1/';
    /** @var Am_Newsletter_Plugin_Autopilot */
    protected $plugin;

    public function __construct(Am_Newsletter_Plugin_Autopilot $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }
    public function sendRequest($path, $params = [], $method = self::METHOD_GET)
    {
        $this->setMethod($method);
        $this->setHeader([
            'autopilotapikey: '.$this->plugin->getConfig('api_key'),
            'Content-Type: application/json'
        ]);
        if($method == self::METHOD_GET) {
            $this->setUrl(self::API_ENDPOINT . $path);
        } else {
            $this->setUrl(self::API_ENDPOINT . $path);
            if($params) {
                $this->setBody(json_encode($params));
            }
        }
        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        if ($ret->getStatus() == '404') {
            if ($this->plugin->getConfig('debug')) {
                $this->plugin->getDi()->logger->error('Autopilot RESPONSE : STATUS '.$ret->getStatus().' - '.$ret->getBody().' - header: '.var_export($ret->getHeader(),true));
            }
            return [];
        }
        if ($ret->getStatus() != '200') {
            throw new Am_Exception_InternalError("Autopilot API Error, configured API Key is wrong");
        }
        return json_decode($ret->getBody(), true);
    }
}