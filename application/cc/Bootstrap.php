<?php

/**
 * @title Credit Card Billing
 * @desc This module provides ability to bill credit cards directly on your website<
 */
class Bootstrap_Cc extends Am_Module
{
    const EVENT_CC_FORM = 'ccForm';
    const EVENT_CC_AFTER_REBILL = 'ccAfterRebill';

    protected $isEnabledByAdminPlugins = false;

    function setEnabledByAdminPlugins($flag)
    {
        $this->isEnabledByAdminPlugins = $flag;
    }

    function onUserMerge(Am_Event $event)
    {
        $target = $event->getTarget();
        $source = $event->getSource();
        if(!$this->getDi()->db->selectCell("SELECT count(*) FROM ?_cc WHERE user_id = ?d", $target->pk()))
            $this->getDi()->db->query('UPDATE ?_cc SET user_id=? WHERE user_id=?',
                $target->pk(), $source->pk());
    }

    function onSetupEmailTemplateTypes(Am_Event $event)
    {
        $event->addReturn([
                'id' => 'cc.admin_rebill_stats',
                'title' => 'Cc Rebill Rebill Stats',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
        ], 'cc.admin_rebill_stats');
        $event->addReturn([
                'id' => 'cc.rebill_failed',
                'title' => 'Cc Rebill Failed',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => [
                    'user',
                    'invoice',
                    'product_title'=> ___('Product(s) Title'),
                    'prorate' => ___('Information about next billing attempt, if applicable'),
                    'manual_rebill_link' => ___('Information about manual rebill link, if applicable')
                ]
        ], 'cc.rebill_failed');
        $event->addReturn([
                'id' => 'cc.rebill_success',
                'title' => 'Cc Rebill Success',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'invoice', 'product_title'=> ___('Product(s) Title')],
        ], 'cc.rebill_success');
    }

    public function onAdminWarnings(Am_Event $event)
    {
        $plugins = $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled();
        $key_pl = '';
        foreach($plugins as $pl)
            if($pl instanceof Am_Paysystem_CreditCard)
                $key_pl = $pl->getId();
        $setupUrl = $this->getDi()->url('admin-setup');

        ///check for configuration problems
        if (class_exists('Am_Paysystem_CreditCard', false))
        {
            if(!$this->getDi()->config->get('use_cron'))
            {
                $event->addReturn(___('%sEnable%s and %sconfigure%s external cron if you are using credit card payment plugins',
                        '<a class="link" href="'.$this->getDi()->url('admin-setup/advanced'). '">', '</a>', '<a class="link" href="http://www.amember.com/docs/Cron">', '</a>'));
            }
            try
            {
                $crypt = $this->getDi()->crypt;
            } catch (Am_Exception_Crypt_Key $e)
            {
                if($key_pl)
                    $event->addReturn("Encryption subsystem error: " . '<a class="link" href="'.$setupUrl. '/'.$key_pl.'">' . $e->getMessage(). '</a>');
                else
                    $event->addReturn("Encryption subsystem error: " . $e->getMessage());

            } catch (Am_Exception_Crypt $e)
            {
                $event->addReturn("Encryption subsystem error: " . $e->getMessage());
            }
        }
    }

    public function onHourly(Am_Event $event)
    {
        if ($this->isEnabledByAdminPlugins) return;
        foreach ($this->getPlugins() as $ps) {
            $ps->ccRebill($this->getDi()->sqlDate);
        }
    }

    /** @return array of Am_Paysystem_CreditCard */
    public function getPlugins()
    {
        $this->getDi()->plugins_payment->loadEnabled();
        $ret = [];
        foreach ($this->getDi()->plugins_payment->getAllEnabled() as $ps) {
            if ($ps instanceof Am_Paysystem_CreditCard || $ps instanceof Am_Paysystem_Echeck) {
                $ret[] = $ps;
            }
        }
        return $ret;
    }

    public function onUserAfterDelete(Am_Event_UserAfterDelete $event)
    {
        $this->getDi()->ccRecordTable->deleteByUserId($event->getUser()->pk());
        $this->getDi()->echeckRecordTable->deleteByUserId($event->getUser()->pk());
    }

    function onUserTabs(Am_Event_UserTabs $event)
    {
        if ($event->getUserId() > 0)
        {
            $event->getTabs()->addPage([
                'id' => 'cc',
                'module' => 'cc',
                'controller' => 'admin',
                'action' => 'info-tab',
                'params' => [
                    'user_id' => $event->getUserId(),
                ],
                'label' => ___('Credit Cards'),
                'order' => 900,
                'resource' => 'cc',
            ]);
            foreach ($this->getPlugins() as $ps)
            {
                if($ps instanceof Am_Paysystem_Echeck)
                {
                    $event->getTabs()->addPage([
                        'id' => 'echeck',
                        'module' => 'cc',
                        'controller' => 'admin',
                        'action' => 'info-tab-echeck',
                        'params' => [
                            'user_id' => $event->getUserId(),
                        ],
                        'label' => ___('Echeck'),
                        'order' => 901,
                        'resource' => 'cc',
                    ]);
                    break;
                }
            }
        }
    }

    function onAdminMenu(Am_Event $event)
    {
        if ($this->isEnabledByAdminPlugins) return;
        $parent = $event->getMenu()->findBy('id', 'utilites');
        if (!$parent) $parent = $event->getMenu();
        $parent->addPage([
            'id' => 'ccrebills',
            'module' => 'cc',
            'controller' => 'admin-rebills',
            'label' => ___('Credit Card Rebills'),
            'resource' => 'cc',
        ]);
        /* disabled  until real-life tested
        if (count($this->getPlugins()) > 1)
        {
            $parent->addPage(array(
                'id' => 'cc-change',
                'module' => 'cc',
                'controller' => 'admin',
                'action' => 'change-paysys',
                'label' => 'Change Paysystem',
            ));
        }
         *
         */
    }

    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___("Can view/edit customer Credit Card information and rebills"), 'cc');
    }

    function onGetMemberLinks(Am_Event $event)
    {
        $user = $event->getUser();
        if ($user->status == User::STATUS_PENDING) return;
        foreach ($this->getPlugins() as $pl)
        {
            if ($pl instanceof Am_Paysystem_CreditCard && ($link = $pl->getUpdateCcLink($user))) {
                $event->addReturn(___("Update Credit Card Info"), $link);
            } elseif ($pl instanceof Am_Paysystem_Echeck && ($link = $pl->getUpdateEcheckLink($user))) {
                $event->addReturn(___("Update Echeck Info"), $link);
            }
        }
    }
}