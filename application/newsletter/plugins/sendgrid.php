<?php
class Am_Newsletter_Plugin_Sendgrid extends Am_Newsletter_Plugin
{
    function _initSetupForm(Am_Form_Setup $form)
    {

        $form->addText('api_user')->setLabel('SendGrid API User'."\nThis is the same credential used for your SMTP settings, and for logging into the website.");
        $el = $form->addSecretText('api_key', ['size' => 40])->setLabel('SendGrid API Key'.
            "\n This is the same password to authenticate over SMTP, and for logging into the website.");
        $el->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function isConfigured()
    {
        return $this->getConfig('api_user') && $this->getConfig('api_key');
    }

    function getAPI()
    {
        return new Am_Sendgrid_Api($this);
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = [];
        $lists = $api->sendRequest('lists/get');
        foreach ($lists as $l)
            $ret[$l['list']] = [
                    'title' => $l['list'],
            ];
        return $ret;
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {

        $api = $this->getApi();
        foreach ($addLists as $list_id)
        {
            $ret = $api->sendRequest('lists/email/add', [
                'list' => $list_id,
                'data' => json_encode([
                    'email' =>$user->email,
                    'name'  =>$user->getName()
                ])
            ]);
//            if (!@$ret['inserted']) return false;
        }

        foreach ($deleteLists as $list_id)
        {
            $ret = $api->sendRequest('lists/email/delete', [
                'list' => $list_id,
                'email' => $user->email
            ]);
//            if (!@$ret['removed']) return false;
        }
        return true;
    }
}

class Am_Sendgrid_Api extends Am_HttpRequest
{
    /** @var Am_Newsletter_Plugin_Sendgrid */
    protected $plugin;
    protected $vars = []; // url params
    protected $params = []; // request params\
    const API_URL = 'https://api.sendgrid.com/api/newsletter/';

    public function __construct(Am_Newsletter_Plugin_Sendgrid $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
        $this->setMethod(self::METHOD_POST);
    }
    public function sendRequest($method, $params= [])
    {
        $this->vars = $params;
        $this->vars['api_key'] = $this->plugin->getConfig('api_key');
        $this->vars['api_user'] = $this->plugin->getConfig('api_user');



        $this->setUrl(self::API_URL . '/'.$method.'.json');
        foreach($this->vars as $k=>$v){
            $this->addPostParameter($k, $v);
        }

        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        if ($ret->getStatus() != '200')
        {
            throw new Am_Exception_InternalError("SendGrid  API Error:".$ret->getBody());
        }
        $body = $ret->getBody();

        if(!$body) return [];

        $arr = json_decode($body, true);
        if (!$arr)
            throw new Am_Exception_InternalError("SendGrid API Error - unknown response [" . $ret->getBody() . "]");
        if(@$arr['message']=='error')
        {
            Am_Di::getInstance()->logger->error("Sendgrid API Error - [" . implode(', ', $arr['errors']) ."]");
            return false;
        }
        return $arr;
    }
}