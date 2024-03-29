<?php

class Am_Newsletter_Plugin_Mailjet extends Am_Newsletter_Plugin
{
    const API_URL = 'https://api.mailjet.com/v3/REST/';

    protected $_isDebug = false;

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('apikey_public', ['class' => 'am-el-wide'])
            ->setLabel('API Public Key')
            ->addRule('required');

        $form->addSecretText('apikey_private', ['class' => 'am-el-wide'])
            ->setLabel('API Private Key')
            ->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $ef = 'email';
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

    function isConfigured()
    {
        return $this->getConfig('apikey_public') && $this->getConfig('apikey_private');
    }

    function getLists()
    {
        $resp = $this->doRequest('contactslist?Limit=1000');
        $ret = [];
        foreach ($resp['Data'] as $l) {
            $ret[$l['ID']] = ['title' => $l['Name']];
        }
        return $ret;
    }

    function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $ID) {
            $this->doRequest("contactslist/{$ID}/managecontact", [
                'Email' => $user->email,
                'Name' => $user->getName(),
                'Action' => 'addforce'
            ]);
        }
        foreach ($deleteLists as $ID) {
            $this->doRequest("contactslist/{$ID}/managecontact", [
                'Email' => $user->email,
                'Name' => $user->getName(),
                'Action' => 'remove'
            ]);
        }
        return true;
    }

    function doRequest($method, $params = [])
    {
        $req = new Am_HttpRequest();
        $req->setAuth($this->getConfig('apikey_public'), $this->getConfig('apikey_private'));
        $req->setMethod($params ? 'POST' : 'GET');
        $req->setUrl(self::API_URL . $method);
        if ($params)
        {
            $req->setHeader('Content-Type: application/json');
            $req->setBody(json_encode($params));
        }
        $resp = $req->send();
        $this->debug($req, $resp);
        if (!$body = $resp->getBody())
            return [];

        return json_decode($body, true);
    }

}
