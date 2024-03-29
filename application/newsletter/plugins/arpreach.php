<?php

class Am_Newsletter_Plugin_Arpreach extends Am_Newsletter_Plugin
{

    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel("ArpReach Pro Url\n" .
                "http://www.example.com/ar/a.php/sub/");
        $el->addRule('required');
        $el->addRule('regex', 'URL must start with http:// or https://', '/^(http|https):\/\//');

        $ef = $form->addSelect('email_field')->setLabel('Choose Alternative E-Mail Field');
        $fields = $this->getDi()->userTable->getFields(true);
        $ef->loadOptions(array_combine($fields, $fields));
        $ef->addRule('required', true);
        $form->setDefault('email_field', 'email');

        $ff = $form->addMagicSelect('fields')->setLabel('Pass additional fields to ARP');
        $ff->loadOptions(array_combine($fields, $fields));
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");

    }

    function doRequest(array $vars, $dif_url)
    {
        $req = new Am_HttpRequest($this->getConfig('url').$dif_url, Am_HttpRequest::METHOD_POST);
        $req->addPostParameter($vars);
        $res = $req->send();
        $this->debug($req, $res);
        return $res;
    }

    public function isConfigured()
    {
        return strlen($this->getConfig('url'));
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $email = $user->get($this->getConfig('email_field', 'email'));
        if (empty($email))
            return true;
        // add custom fields info
        $fields = [];
        foreach ($this->getConfig('fields', []) as $fn)
            $fields['custom_' . $fn] = $user->get($fn);

        foreach ($addLists as $listId)
        {
            $ret = $this->doRequest([
                'first_name' => $user->name_f,
                'last_name' => $user->name_l,
                'email_address' => $email,
                ] + $fields, $listId);
            if (!$ret)
                return false;
        }
        foreach ($deleteLists as $listId)
        {
            $list = $this->getDi()->newsletterListTable->findFirstBy(['plugin_id' => $this->getId(), 'plugin_list_id' => $listId]);
            if(!$list)
                continue;
            $vars = unserialize($list->vars);
            $ret = $this->doRequest([
                'first_name' => $user->name_f,
                'last_name' => $user->name_l,
                'email_address' => $email,
            ], @$vars['unsub_list_id']);
            if (!$ret)
                return false;
        }
        return true;
    }

    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $ef = $this->getConfig('email_field', 'email');
        if ($ef != 'email') // else changeEmail will be called by Bootstrap
        {
            $oldEmail = $event->getOldUser()->get($ef);
            $newEmail = $event->getUser()->get($ef);
            if ($oldEmail != $newEmail)
                $this->changeEmail($event->getUser(), $oldEmail, $newEmail);
        }
    }

    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $ef = $this->getConfig('email_field', 'email');
        // fetch all user subscribed ARP lists, unsubscribe
        $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
        $lists = [];
        foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
        {
            if ($list->plugin_id != $this->getId())
                continue;
            $lists[] = $list->plugin_list_id;
        }
        $user->set($ef, $oldEmail)->toggleFrozen(true);
        $this->changeSubscription($user, [], $lists);
        // subscribe again
        $user->set($ef, $newEmail)->toggleFrozen(false);
        $this->changeSubscription($user, $lists, []);
    }


    public function getIntegrationFormElements(HTML_QuickForm2_Container $group)
    {
        $group->addText('unsub_list_id')
            ->setLabel("<span class=\"required\">*</span> ArpReach List Unsubscribe Id\n" .
                'you can get it from ArpReach Unsubscribe form');
    }

}
