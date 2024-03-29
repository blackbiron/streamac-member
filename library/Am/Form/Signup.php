<?php

// -- // require_once 'HTML/QuickForm2/Exception.php';

/**
 * @package Am_SavedForm
 */
class Am_Form_Signup extends Am_Form_Controller implements Am_Form_Bricked
{
    protected $defaultPageConfig = [];
    protected $pageNum = 0;
    protected $savedForm;
    protected $addValidateEmail = false;

    public function __construct($id = 'signup', $wizard = true, $propagateId = false)
    {
        parent::__construct($id, $wizard, $propagateId);

        $this->defaultPageConfig = [
            'title' => 'Signup Form',
            'back' => 'Back',
            'next' => 'Next',
        ];
    }

    public function isMultiPage()
    {
        return true;
    }

    public function isHideBricks()
    {
        return true;
    }

    public function isValid(HTML_QuickForm2_Controller_Page $reference = null)
    {
        return parent::isValid($reference);
    }

    public function addBrickedPage(array $bricks, $pageConfig = null)
    {
        if ($pageConfig) {
            foreach ($pageConfig as $k => $v) if (empty($v)) unset($pageConfig[$k]);
        } else {
            $pageConfig = [];
        }
        $pageConfig = array_merge($this->defaultPageConfig, $pageConfig);
        $page = new Am_Form_Signup_Page($pageConfig, $bricks, $this->pageNum++, $this->savedForm);
        $this->addPage($page);
        $bricks = array_reduce($bricks, function($_, $item) {
            return array_merge($_, [$item], $item->getChilds());
        }, []);
        foreach ($bricks as $brick)
        {
            if ($brick->getClass() == 'email' && $brick->getConfig('validate'))
            {
                if ($brick->getConfig('validate_mode') == 'last_step') {
                    $this->addValidateEmail = true;
                } else {
                    $page = $this->addEmailCode($page);
                }
            }
        }
        return $page;
    }

    protected function addEmailCode($page)
    {
        $page->addHandler('next', new Am_Form_Signup_Action_SendEmailCode);
        $this->addPage($_ = new Am_Form_Signup_Page_EmailCode(
                ['id' => 'EmailCode', 'title' => ___('E-Mail Verification')],
                [],
                $this->pageNum++,
                $this->savedForm));
        return $_;
    }

    public function initFromSavedForm(SavedForm $record, $excludeBricks = [])
    {
        $this->savedForm = $record;
        if ($record->title)
            $this->defaultPageConfig['title'] = $record->title;
        $bricks = [];
        $loggedIn = Am_Di::getInstance()->auth->getUserId() > 0;
        $amOrderData = $this->getSessionContainer()->getOpaque('am-order-data') ?: [];
        $childs = [];
        $root = [];
        foreach ($record->getBricks() as $brick)
        {
            if (in_array($brick->getClass(), $excludeBricks))
                continue; // exclude some bricks - used for buynow signup
            if ($amOrderData)
            {
                if (false === $brick->applyOrderData($amOrderData))
                    continue;
            }
            if ($loggedIn && $brick->hideIfLoggedIn())
                continue; // skip brick as user is logged-in
            if ($_ = $brick->getContainer()) {
                $childs[$_][] = $brick;
            } else {
                $root[$brick->getId()] = $brick;
            }
        }
        foreach ($childs as $id => $items) {
            if (isset($root[$id])) { //handle hide if logged in for fieldset
                $root[$id]->setChilds($items);
            }
        }

        foreach ($root as $brick)
        {
            if ($brick->getClass() == 'page-separator')
            {
                if ($bricks)
                {
                    $page = $this->addBrickedPage($bricks, $brick->getCustomLabels());
                    $bricks = [];
                }
            } else
                $bricks[] = $brick;
        }
        // last page
        if ($bricks) {
            $page = $this->addBrickedPage($bricks);
        }
        if ($this->addValidateEmail) {
            $this->addEmailCode($page);
        }
    }

    public function restoreStateAfterEmailVerification($em)
    {
        $blob = Am_Di::getInstance()->store->getBlob(Am_Form_Signup_Action_SendEmailCode::STORE_PREFIX . $em);
        if (!$blob)
            throw new Am_Exception_InputError(___("Wrong code or code expired - please start signup process again"));
        $this->getSessionContainer()->unserialize($blob);
        $page = $this->getPage('EmailCode');
        $page->populateFormOnce();
        $page->getForm()->setDataSources([new Am_Mvc_Request(['code' => $em])]);
        $page->storeValues();

        $this->getSessionContainer()->email_confirmed=true;

        Am_Di::getInstance()->hook->call(Am_Event::SIGNUP_STATE_LOAD, ['code' => $em]);
        Am_Di::getInstance()->cache->remove('getNotConfirmedCount');
        if ($next = $this->nextPage($page))
        {
            $next->handle('jump');
        } else {
            $page->handle('process');
        }
        return true;
    }

    public function run()
    {
        if ($this->pageNum == 1 && ($aod = $this->getSessionContainer()->getOpaque('am-order-data')) && !empty($aod['button'])){
            $this->getFirstPage()->getForm()->addHidden('_button')
                ->setValue($aod['button']);
        }
        $em = Am_Di::getInstance()->request->getFiltered('em');
        if ($em)
        {
            $blob = Am_Di::getInstance()->store->getBlob(Am_Form_Signup_Action_SendEmailCode::STORE_PREFIX . $em);
            if (preg_match('/^\d+$/', $blob))
            {
                $user = Am_Di::getInstance()->userTable->load($blob);
                Am_Mvc_Response::redirectLocation(Am_Di::getInstance()->url('login', false));
                return;
            }
            if ($blob/*!$this->getSessionContainer()->getValidationStatus('EmailCode')*/)
            {
                $this->restoreStateAfterEmailVerification($em);
            } else {
                throw new Am_Exception_InputError(___("Wrong code or code expired - please start signup process again"));
            }
        } else
            return parent::run();
    }

    public function getDefaultBricks()
    {
        return [
            new Am_Form_Brick_Product,
            new Am_Form_Brick_Paysystem,
            new Am_Form_Brick_Name,
            new Am_Form_Brick_Email,
            new Am_Form_Brick_Login,
            new Am_Form_Brick_Password,
        ];
    }

    public function getAvailableBricks()
    {
        return Am_Form_Brick::getAvailableBricks($this);
    }

    function getFirstPage()
    {
        return $this->getIterator()->current();
    }

    /** @return HTML_QuickForm2_Controller_Page */
    public function findPageByElementName($name)
    {
        foreach ($this->getIterator() as $page)
        {
            /* @var $page HTML_QuickForm2_Controller_Page */
            $page->populateFormOnce();
            if ($page->getForm()->getElementsByName($name))
                return $page;
        }
    }

    static function getSavedFormUrl(SavedForm $record)
    {
        if ($record->isDefault(SavedForm::D_SIGNUP))
            return "signup";
        elseif ($record->isDefault(SavedForm::D_MEMBER))
            return "member/add-renew";
        else
            return "signup/" . urlencode($record->code);
    }
}

/**
 * This action sends e-mail address confirmation email
 * stores current form entries to Am_Store and redirects
 * to 'emailcode' page
 *
 * @package Am_SavedForm
 */
class Am_Form_Signup_Action_SendEmailCode implements HTML_QuickForm2_Controller_Action
{
    /**
     * @todo make it correct, encrypt info in session
     */
    const EMAIL_CODE_LEN = 20;
    const STORE_PREFIX = 'signup_record-';

    public function storeState(HTML_QuickForm2_Controller_Page $page)
    {
        $code = Am_Di::getInstance()->security->randomString(self::EMAIL_CODE_LEN);
        $signupUrl = $page->getController()->getParentController()->getCurrentUrl() . '?em=' . $code;
        $page->getController()->getSessionContainer()->storeOpaque('EmailCode', $code);
        $page->getController()->getSessionContainer()->storeOpaque('ConfirmUrl', $signupUrl);
        $data = $page->getController()->getSessionContainer()->serialize();
        $vars = $page->getController()->getValue();
        Am_Di::getInstance()->store->setBlob(self::STORE_PREFIX . $code, $data, '+36 hours');
        Am_Di::getInstance()->db->query("UPDATE ?_store SET `value`=? WHERE name=?",
            $vars['email'], self::STORE_PREFIX . $code);
        Am_Di::getInstance()->cache->remove('getNotConfirmedCount');
        return $code;
    }

    public function perform(HTML_QuickForm2_Controller_Page $page, $name)
    {
        $valid = $page->storeValues();
        if (!$valid)
            return $page->handle('display');

        $tpl = Am_Mail_Template::load('verify_email_signup', null, true);

        $vars = $page->getController()->getValue();
        $u = Am_Di::getInstance()->userRecord;
        foreach($vars as $k => $v)
            $u->{$k} = $v;
        if(empty($u->name_l)) $u->name_l = @$vars['name_l'];
        if(empty($u->name_f)) $u->name_f = @$vars['name_f'];
        if(empty($u->email)) $u->email = @$vars['email'];
        $tpl->setUser($u);

        $code = $this->storeState($page);
        Am_Di::getInstance()->hook->call(Am_Event::SIGNUP_STATE_SAVE, ['code' => $code]);
        $signupUrl = $page->getController()->getParentController()->getCurrentUrl();
        $tpl->setCode($code);
        $tpl->setUrl($signupUrl . '?em=' . $code);

        $tpl->send($u);

        // the $page is never the last page, because emailcode is always inserted after
        $next = $page->getController()->nextPage($page);
        return $next->handle('jump');
    }
}

/**
 * A page from multi-page signup controller
 * @package Am_SavedForm
 */
class Am_Form_Signup_Page extends HTML_QuickForm2_Controller_Page
{
    protected $bricks = [];
    protected $pageNum;
    protected $pageConfig = [];
    protected $savedForm;

    public function __construct(array $pageConfig, $bricks, $pageNum, $savedForm)
    {
        $id = !empty($pageConfig['id']) ? $pageConfig['id'] : 'page-' . $pageNum;
        $this->savedForm = $savedForm;
        parent::__construct(new Am_Form($id));
        $this->bricks = $bricks;
        $this->pageNum = $pageNum;
        $this->pageConfig = $pageConfig;
    }

    public function getTitle()
    {
        return ___($this->pageConfig['title']);
    }

    public function handle($actionName)
    {
        if ($actionName == 'next')
        {
            $paysysId = $this->getController()->getParentController()->getRequest()->get('paysys_id');
            if ($paysysId)
            {
                if ($ps = Am_Di::getInstance()->plugins_payment->loadGet($paysysId))
                {
                    $hideBricks = $ps->hideBricks();
                    $this->getController()->getSessionContainer()->storeOpaque('hideBricks', $hideBricks);
                }
            }
        } elseif ($actionName == 'display')
        {
            if (!empty($this->bricks))
                foreach ($this->bricks as $brick)
                {
                    if ($brick->getId() == 'paysystem')
                    {
                        $this->getController()->getSessionContainer()->storeOpaque ('hideBricks', []);
                    }
                }
        }
        return parent::handle($actionName);
    }

    protected function populateForm()
    {
        $jsData = [];
        $hideBricks = (array)$this->getController()->getSessionContainer()->getOpaque('hideBricks');
        foreach ($this->bricks as $brick) {
            if (!in_array($brick->getId(), $hideBricks)) {
                $_ = $brick->insertBrick($this->form);
                $jsData = array_merge_recursive($jsData, $brick->getJsData());
                foreach ($brick->getChilds() as $child) {
                    $child->insertBrick($_);
                    $jsData = array_merge_recursive($jsData, $child->getJsData());
                }
            }
        }

        $this->form->setAttribute('data-bricks', json_encode($jsData));
        $this->form->setAttribute('data-saved_form_id', $this->savedForm->pk());
        $this->form->setAttribute('class', 'am-signup-form');
        $ds = $this->form->getDataSources();

        if ($user = Am_Di::getInstance()->auth->getUser()) {
            $info = $user->toArray();
            unset($info['pass']);
            array_unshift($ds, new HTML_QuickForm2_DataSource_Array($info));
        }

        array_unshift($ds, $this->getController()->getParentController()->getRequest());
        $this->form->setDataSources($ds);

        $group = $this->form->addGroup(null, 'id="buttons"');
        $group->setSeparator(' ');
        if ($this->pageNum > 0)
        {
            $url = $this->getController()->getParentController()->getRequest()->getRequestUri();
            $url = preg_replace('/\?.*/', '', $url);
            $link =
                Am_Html::escape($url) .
                '?' . Am_Html::escape($this->getButtonName('back')) . '=1';
            $group->addHtml('back')->setHtml("<a href='$link'>" . ___($this->pageConfig['back']) . "</a>");
        }
        $group->addSubmit($this->getButtonName('next'), ['value' => ___($this->pageConfig['next']), 'class' => 'am-cta-signup']);
        $this->form->addRule('callback', '--form error--', [$this, 'validate']);
        $this->setDefaultAction('next', 'data:image/gif;base64,R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs='); // that was "empty.gif"
    }

    public function validate()
    {
        $error = Am_Di::getInstance()->banTable->checkBan(['ip' => $_SERVER['REMOTE_ADDR']]);
        if ($error)
            throw new Am_Exception_InputError($error);

        // ** validate hooks */
        $event = new Am_Event_ValidateSavedForm($this->form->getValue(), $this->form, $this->savedForm);
        Am_Di::getInstance()->hook->call($event);
        if ($errors = $event->getErrors())
        {
            $this->form->setError($errors[0]);
            return false;
        }
        return true;
    }

    /**
     * Sets the default action invoked on page-form submit
     *
     * This is necessary as the user may just press Enter instead of
     * clicking one of the named submit buttons and thn no action name will
     * be passed to the script.
     *
     * @param  string    Default action name
     * @param  string    Path to a 1x1 transparent GIF image
     * @return object    Returns the image input used for default action
     */
    public function setDefaultAction($actionName, $imageSrc = '')
    {
        parent::setDefaultAction($actionName, $imageSrc);
        $image = $this->form->getElementById('_qf_default');
        if (!$image)
            return;
        $image->setAttribute('style', 'display: none;');
    }
}

/**
 * This page displays e-mail code entry form
 * and handles links click in e-mail address confirmation e-mail
 */
class Am_Form_Signup_Page_EMailCode extends Am_Form_Signup_Page
{
    protected function populateForm()
    {
        $this->getController()->getParentController()->view->noLoginOffer = true;

        $this->form->setDataSources([$this->getController()->getParentController()->getRequest()]);

        $this->form->addHtml('email-confirm-message', ['class'=>'am-no-label'])->setHtml(___(
                "Confirmation link has been sent to your e-mail address.\n" .
                "Please check your mailbox. If you have not received e-mail\n" .
                "within 5 minutes, please check also the 'Spam' folder - our\n" .
                "message may be classified as spam by mistake."
            ));

        $this->form->addHidden('code', ['size' => 22,])
//            ->setLabel('Verification Code')
            ->addFilter('filterId')
            ->addRule('eq', ___('Wrong verification code'), $this->getCode());

        //$this->form->addSubmit($this->getButtonName('next'), array('value' => ___('Next')));
        $this->form->addRule('callback', '--form error--', [$this, 'validate']);
        $this->setDefaultAction('next', 'data:image/gif;base64,R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs='); // that was "empty.gif"
    }

    public function getCode()
    {
        $code = $this->getController()->getSessionContainer()->getOpaque('EmailCode');
        if ($code == '')
            throw new Am_Exception_InternalError("email verification code lost in session");
        return $code;
    }
}