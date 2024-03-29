<?php

/**
 * A Zend_Controller_Front plugin, handles admin authentication and maintanance
 * mode, checks if requested module is enabled
 *
 * @package Am_Mvc_Controller
 */

class Am_Mvc_Controller_Plugin extends Zend_Controller_Plugin_Abstract
{
    private $di;

    public function __construct(Am_Di $di)
    {
        $this->di = $di;
    }

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        // check if we need to handle admin auth
        if (stripos($this->getRequest()->getControllerName(), 'admin')===0) {
            defined('AM_ADMIN') || define('AM_ADMIN', true);
            if (($this->di->authAdmin->getUserId() <= 0)
                && $request->getControllerName() != 'admin-auth')
            {
                $request->setControllerName('admin-auth')->setActionName('index')->setModuleName('default');
            }
        // check for maintenance mode
        } elseif ($msg = $this->di->config->get('maintenance')) {
            if (!$this->di->authAdmin->getUserId())
                return amMaintenance($msg);
        }
        // check if we are accessing disabled module
        $module = $request->getModuleName();
        if ($module != 'default') {
            if (!$this->di->modules->isEnabled($module))
                throw new Am_Exception_InputError(___('You are trying to access disabled module [%s]', htmlentities($module)));
        }

        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'upload' &&
            $request->getActionName() == 'get')
            return; //exception for theme logo
        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'direct' &&
            $request->getParam('plugin_id') == 'avatar')
            return; //exception for avatar
        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'login' &&
            $request->getActionName() == 'logout')
            return; //exception for logout

        if (!$this->di->authAdmin->getUserId()
            && $this->di->auth->getUserId()
            && $this->di->auth->getUser()->needForceChangePassword()) {

                $request->setControllerName('login')
                    ->setActionName('change-pass')
                    ->setModuleName('default');
        }

        if (!defined('AM_ADMIN')
            && ($_ = $this->di->auth->getUserId())
        ) {
            if ($request->getParam('plugin_id') == 'sales-notification') {
                return;
            }
            if ($request->getModuleName() == 'helpdesk'
                &&  $request->getControllerName() == 'faq'
                && $request->getActionName() == 'suggest'
            ) {
                return;
            }

            $this->di->accessLogTable->logOnce($_);
        }
    }
}