<?php

class Am_Grid_Action_Group_ContentAssignTpl extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;

    public function __construct()
    {
        parent::__construct(null, ___('Assign Template'));
    }

    public function renderConfirmationForm($btn = "Yes, assign", $addHtml = null)
    {
        $select = sprintf('<select name="%s__tpl">
            %s
            </select><br /><br />' . PHP_EOL,
            $this->grid->getId(),
            Am_Html::renderOptions($this->grid->getTemplateOptions())
        );
        return parent::renderConfirmationForm(null, $select);
    }

    public function handleRecord($id, $record)
    {
        $record->updateQuick('tpl', $this->grid->getRequest()->getParam('_tpl'));
    }
}

class Am_Grid_Action_Group_ContentAssignCategory extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;
    protected $_vars;

    public function __construct($id = null, $title = null)
    {
        parent::__construct('content-assign-category', ___("Assign Category"));
    }

    public function handleRecord($id, $record)
    {
        $categories = $record->getCategories();
        $record->setCategories(array_merge($categories, $this->_vars['categories']));
    }

    public function getForm()
    {
        if (!$this->form) {
            $prefix = $this->grid->getId() . '_';
            $this->form = new Am_Form_Admin;

            $this->form->addMagicSelect("{$prefix}categories", ['class' => 'am-combobox'])
                ->setLabel(___("Assign Category"))
                ->loadOptions(Am_Di::getInstance()->resourceCategoryTable->getOptions())
                ->setJsOptions('{allowSelectAll: true}');

            $this->form->addSaveButton(___("Assign Category"));
        }
        return $this->form;
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $this->getForm();
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        foreach ($vars as $k => $v)
            if ($this->form->getElementsByName($k))
                unset($vars[$k]);
        foreach(Am_Html::getArrayOfInputHiddens($vars) as $k => $v)
            $this->form->addHidden($k)->setValue($v);

        $url_yes = $this->grid->makeUrl(null);
        $this->form->setAction($url_yes);
        echo $this->renderTitle();
        echo (string)$this->form;
    }

    public function run()
    {
        if (!$this->getForm()->validate()) {
            echo $this->renderConfirmationForm();
        } else {
            $prefix = $this->grid->getId().'_';
            foreach ($this->getForm()->getValue() as $k => $v) {
                if (strpos($k, $prefix)===0)
                    $this->_vars[substr($k, strlen($prefix))] = $v;
            }
            return parent::run();
        }
    }
}

class Am_Grid_Action_Group_ContentRemoveCategory extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;
    protected $_vars;

    public function __construct($id = null, $title = null)
    {
        parent::__construct('content-remove-category', ___("Remove Category"));
    }

    public function handleRecord($id, $record)
    {
        $categories = $record->getCategories();
        $record->setCategories(array_diff($categories, $this->_vars['categories']));
    }

    public function getForm()
    {
        if (!$this->form) {
            $prefix = $this->grid->getId() . '_';
            $this->form = new Am_Form_Admin;

            $this->form->addMagicSelect("{$prefix}categories", ['class' => 'am-combobox'])
                ->setLabel(___("Remove Category"))
                ->loadOptions(Am_Di::getInstance()->resourceCategoryTable->getOptions())
                ->setJsOptions('{allowSelectAll: true}');

            $this->form->addSaveButton(___("Remove Category"));
        }
        return $this->form;
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $this->getForm();
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        foreach ($vars as $k => $v)
            if ($this->form->getElementsByName($k))
                unset($vars[$k]);
        foreach(Am_Html::getArrayOfInputHiddens($vars) as $k => $v)
            $this->form->addHidden($k)->setValue($v);

        $url_yes = $this->grid->makeUrl(null);
        $this->form->setAction($url_yes);
        echo $this->renderTitle();
        echo (string)$this->form;
    }

    public function run()
    {
        if (!$this->getForm()->validate()) {
            echo $this->renderConfirmationForm();
        } else {
            $prefix = $this->grid->getId().'_';
            foreach ($this->getForm()->getValue() as $k => $v) {
                if (strpos($k, $prefix)===0)
                    $this->_vars[substr($k, strlen($prefix))] = $v;
            }
            return parent::run();
        }
    }
}

class Am_Grid_Action_Group_ContentHideFromDashboard extends Am_Grid_Action_Group_Abstract
{
    public function __construct()
    {
        parent::__construct(null, ___('Hide from Dashboard'));
    }

    public function handleRecord($id, $record)
    {
        $record->updateQuick('hide', 1);
    }
}

class Am_Grid_Action_Group_ContentShowOnDashboard extends Am_Grid_Action_Group_Abstract
{
    public function __construct()
    {
        parent::__construct(null, ___('Show on Dashboard'));
    }

    /**
     * @param int $id
     * @param User $record
     */
    public function handleRecord($id, $record)
    {
        $record->updateQuick('hide', 0);
    }
}

class Am_Grid_Action_Group_ContentSetAccessPermission extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;
    protected $_vars, $_products;

    public function __construct()
    {
        parent::__construct('set_p', ___('Set Access Permissions'));
        $this->setTarget('_top');
    }

    public function handleRecord($id, $record)
    {
        $record->setAccess($this->_vars['access']);
    }

    public function getForm()
    {
        if (!$this->form) {
            $id = $this->grid->getId();
            $this->form = new Am_Form_Admin;
            $this->form->addElement(new Am_Form_Element_ResourceAccess)->setName($id . '_access')->setLabel(___('Access Permissions'));
            $this->form->addSaveButton(___('Set Access Permissions'));
        }
        return $this->form;
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $this->getForm();
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        foreach ($vars as $k => $v)
            if ($this->form->getElementsByName($k))
                unset($vars[$k]);
        foreach(Am_Html::getArrayOfInputHiddens($vars) as $k => $v)
            $this->form->addHidden($k)->setValue($v);

        $url_yes = $this->grid->makeUrl(null);
        $this->form->setAction($url_yes);
        echo $this->renderTitle();
        echo (string)$this->form;
    }

    public function run()
    {
        if (!$this->getForm()->validate()) {
            echo $this->renderConfirmationForm();
        } else {
            $prefix = $this->grid->getId().'_';
            foreach ($this->getForm()->getValue() as $k => $v) {
                if (strpos($k, $prefix)===0)
                    $this->_vars[substr($k, strlen($prefix))] = $v;
            }
            return parent::run();
        }
    }
}

class Am_Grid_Action_Group_ContentAmendAccessPermission extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;
    protected $_vars, $_products;

    public function __construct()
    {
        parent::__construct('amend_p', ___('Amend Access Permissions'));
        $this->setTarget('_top');
    }

    public function handleRecord($resource_id, $record)
    {
        if (!empty($this->_vars['add'])) {
            Am_Di::getInstance()->resourceAccessTable
                ->deleteBy([
                    'fn' => [ResourceAccess::FN_FREE, ResourceAccess::FN_FREE_WITHOUT_LOGIN, ResourceAccess::FN_SPECIAL],
                    'resource_id' => $resource_id,
                    'resource_type' => $record->getAccessType(),
                ]);
            foreach($this->_vars['add'] as $fn => $access_list)
            {
                $existing_ids = Am_Di::getInstance()->db->selectCol(
                    "SELECT `id` FROM ?_resource_access WHERE fn=? AND resource_id=? AND resource_type=?",
                    $fn,
                    $resource_id,
                    $record->getAccessType()
                );
                $access_list = array_filter($access_list, function($k) use($existing_ids) {if(in_array($k, $existing_ids)) return false; else return true;}, ARRAY_FILTER_USE_KEY);
                if(!count($access_list)) continue;
                foreach ($access_list as $id => $params) {
                    if (!is_array($params))
                        $params = json_decode($params, true);
                    Am_Di::getInstance()->resourceAccessTable->addAccessListItem($resource_id, $record->getAccessType(), $id, $params['start'], $params['stop'], $fn);
                }
            }
        }
        if (!empty($this->_vars['remove'])) {
            $pids = array_reduce($this->_vars['remove'], function($_, $item) {
                if (preg_match('/^[0-9]+$/', $item)) {
                    $_[] = $item;
                }
                return $_;
            }, []);
            $cids = array_reduce($this->_vars['remove'], function($_, $item) {
                if (preg_match('/^c([0-9]+)$/', $item, $m)) {
                    $_[] = $m[1];
                }
                return $_;
            });
            if ($pids) {
                Am_Di::getInstance()->resourceAccessTable
                    ->deleteBy([
                        'fn' => ResourceAccess::FN_PRODUCT,
                        'resource_id' => $resource_id,
                        'resource_type' => $record->getAccessType(),
                        'id' => $pids
                    ]);
            }
            if ($cids) {
                Am_Di::getInstance()->resourceAccessTable
                    ->deleteBy([
                        'fn' => ResourceAccess::FN_CATEGORY,
                        'resource_id' => $resource_id,
                        'resource_type' => $record->getAccessType(),
                        'id' => $cids
                    ]);
            }
        }
    }

    public function getForm()
    {
        if (!$this->form) {
            $id = $this->grid->getId();
            $this->form = new Am_Form_Admin;
            $this->form->addElement(new Am_Form_Element_ResourceAccess)
                ->setName($id . '_add')
                ->setLabel(___('Add'))
                ->setAttribute('without_free_without_login', 'true')
                ->setAttribute('without_free', 'true');
            $this->form->addMagicSelect($id . '_remove')
                ->setLabel(___('Remove'))
                ->loadOptions(Am_Di::getInstance()->productTable->getProductOptions())
                ->setJsOptions('{allowSelectAll: true}');
            $this->form->addSaveButton(___('Amend Access Permissions'));
        }
        return $this->form;
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $this->getForm();
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        foreach ($vars as $k => $v)
            if ($this->form->getElementsByName($k))
                unset($vars[$k]);
        foreach(Am_Html::getArrayOfInputHiddens($vars) as $k => $v)
            $this->form->addHidden($k)->setValue($v);

        $url_yes = $this->grid->makeUrl(null);
        $this->form->setAction($url_yes);
        echo $this->renderTitle();
        echo (string)$this->form;
    }

    public function run()
    {
        if (!$this->getForm()->validate()) {
            echo $this->renderConfirmationForm();
        } else {
            $prefix = $this->grid->getId().'_';
            foreach ($this->getForm()->getValue() as $k => $v) {
                if (strpos($k, $prefix)===0)
                    $this->_vars[substr($k, strlen($prefix))] = $v;
            }
            return parent::run();
        }
    }
}

abstract class Am_Grid_Filter_Content extends Am_Grid_Filter_Abstract
{
    protected $varList = ['filter_q', 'filter_a', 'filter_c'];

    protected function applyFilter()
    {
        $query = $this->grid->getDataSource()->getDataSourceQuery();
        $datasources = array_merge([$query], $query->getUnions());

        foreach ($datasources as $query) {

            $r = $query->createRecord();
            $key_name = $r->getTable()->getKeyField();
            $resource_type = $r->getAccessType();

            if ($filter = $this->getParam('filter_q')) {
                $condition = null;
                foreach ($this->getSearchFields() as $f) {
                    $c = new Am_Query_Condition_Field($f, 'LIKE', '%' . $filter . '%');
                    $condition = $condition ? $condition->_or($c) : $c;
                }
                $c = new Am_Query_Condition_Field($query->getTable()->getKeyField(), '=', $filter);
                $condition->_or($c);
                $query->add($condition);
            }
            if ($filter = $this->getParam('filter_a')) {
                if (preg_match('/^c([0-9]+)$/', $filter, $m)) {
                    $ctp = Am_Di::getInstance()->productCategoryTable->getCategoryProducts();
                    $product = $ctp[$m[1]];
                    $category = [$m[1]];
                } else {
                    $p = Am_Di::getInstance()->productTable->load($filter);
                    $category = $p->getCategories();
                    $product = [$filter];
                }

                $product[] = -1;
                $category[] = -1;
                $query->leftJoin('?_resource_access', 'ra',
                    "t.$key_name=ra.resource_id AND ra.resource_type='$resource_type'")
                    ->addWhere('(ra.fn = ? AND ra.id IN (?a)) OR (ra.fn = ? AND ra.id IN (?a))',
                        'product_id', $product,
                        'product_category_id', $category);
            }
            if ($filter = $this->getParam('filter_c')) {
                $query->leftJoin('?_resource_resource_category', 'rrc',
                    "t.$key_name=rrc.resource_id AND rrc.resource_type='$resource_type'");
                $query->addWhere('resource_category_id=?', $filter);
            }
        }
    }

    function renderInputs()
    {
        $filter = '';
        if($this->getSearchFields() != null)
        {
            $filter .= $this->renderInputText([
                'name' => 'filter_q',
                'placeholder' => $this->getPlaceholder()
            ]);
            $filter .= ' ';
        }

        $options = Am_Di::getInstance()->productTable->getProductOptions();

        $filter .= $this->renderInputSelect('filter_a',
            ['' => ___('-- Filter by Product --')] + $options,
            ['style'=>'max-width:300px']);

        if ($options = $this->grid->getDi()->resourceCategoryTable->getOptions()) {
            $filter .= ' ' . $this->renderInputSelect('filter_c',
            ['' => ___('-- Filter by Content Category --')] + $options,
            ['style'=>'max-width:300px']);
        }

        return $filter;
    }

    function getTitle()
    {
        return;
    }

    abstract function getPlaceholder();
    abstract function getSearchFields();
}

class Am_Grid_Filter_Content_All extends Am_Grid_Filter_Content
{
    function getPlaceholder()
    {
        return '';
    }

    function getSearchFields()
    {
        return null;
    }
}

class Am_Grid_Filter_Content_Email extends Am_Grid_Filter_Content
{
    function getSearchFields()
    {
        return ['subject'];
    }

    function getPlaceholder()
    {
        return ___('Subject');
    }
}

class Am_Grid_Filter_Content_Folder extends Am_Grid_Filter_Content
{
    function getSearchFields()
    {
        return ['title', 'url', 'path'];
    }

    function getPlaceholder()
    {
        return ___('Title/URL/Path');
    }
}

class Am_Grid_Filter_Content_Page extends Am_Grid_Filter_Content
{
    function getSearchFields()
    {
        return ['title', 'path'];
    }

    function getPlaceholder()
    {
        return ___('Title/Path');
    }
}

class Am_Grid_Filter_Content_Common extends Am_Grid_Filter_Content
{
    function getSearchFields()
    {
        return ['title'];
    }

    function getPlaceholder()
    {
        return ___('Title');
    }
}

class Am_Grid_Filter_Content_Integration extends Am_Grid_Filter_Content
{
    function getSearchFields()
    {
        return ['plugin'];
    }

    function getPlaceholder()
    {
        return ___('Plugin Id');
    }
}

class Am_Grid_Action_EmailPreview extends Am_Grid_Action_Abstract
{
    public function run()
    {
        if ($this->grid->getRequest()->getParam('preview')) {
            $session = $this->grid->getDi()->session->ns('email_preview');
            echo $session->output;
            exit;
        }
        $f = $this->createForm();
        $f->setDataSources([$this->grid->getCompleteRequest()]);
        echo $this->renderTitle();
        if ($f->isSubmitted() && $f->validate() && $this->process($f))
            return;
        echo $f;
    }

    function process(Am_Form $f)
    {
        $vars = $f->getValue();
        $user = $this->grid->getDi()->userTable->findFirstByLogin($vars['user']);
        if (!$user) {
            [$el] = $f->getElementsByName('user');
            $el->setError(___('User %s not found', $vars['user']));
            return false;
        }

        $product = $this->grid->getDi()->productTable->load($vars['product_id']);
        $template = $this->grid->getRecord();
        $mail = Am_Mail_Template::createFromEmailTemplate($template);

        switch ($template->name) {
            case EmailTemplate::AUTORESPONDER:
                $mail->setLast_product_title($product->title);
                break;
            case EmailTemplate::EXPIRE:
                $invoice = Am_Di::getInstance()->invoiceRecord;
                $invoice->toggleFrozen(true);
                $invoice->invoice_id = 'ID';
                $invoice->public_id = 'PUBLIC ID';
                $invoice->setUser($user);
                $invoice->add($product);
                $invoice->calculate();

                $mail->setInvoice($invoice);
                $mail->setProduct_title($product->title);
                $mail->setExpires(amDate($vars['expires']));
                break;
            case EmailTemplate::PRODUCTWELCOME:
                $invoice = Am_Di::getInstance()->invoiceRecord;
                $invoice->toggleFrozen(true);
                $invoice->invoice_id = 'ID';
                $invoice->public_id = 'PUBLIC ID';
                $invoice->setUser($user);
                $invoice->add($product);
                $invoice->calculate();

                /* @var $payment InvoicePayment */
                $payment = Am_Di::getInstance()->invoicePaymentRecord;
                $payment->toggleFrozen(true);
                $payment->amount = $invoice->first_total;
                $payment->currency = $invoice->currency;
                $payment->receipt_id = 'RECEIPT_ID';
                $mail->setInvoice($invoice);
                $mail->setPayment($payment);
                $mail->setLast_product_title($product->title);
                break;
            case EmailTemplate::PAYMENT:
                $invoice = Am_Di::getInstance()->invoiceRecord;
                $invoice->toggleFrozen(true);
                $invoice->invoice_id = 'ID';
                $invoice->public_id = 'PUBLIC ID';
                $invoice->tm_started = sqlTime('now');
                $invoice->setUser($user);
                $invoice->add($product);
                $invoice->calculate();
                if ($invoice->second_total) {
                    $p = new Am_Period($invoice->first_period);
                    $invoice->rebill_date = $p->addTo(sqlDate('now'));
                }

                /* @var $payment InvoicePayment */
                $payment = Am_Di::getInstance()->invoicePaymentRecord;
                $payment->toggleFrozen(true);
                $payment->invoice_payment_id = -1;
                $payment->invoice_id = -1;
                $payment->dattm = sqlTime('now');
                $payment->amount = $invoice->first_total;
                $payment->currency = $invoice->currency;
                $payment->receipt_id = 'RECEIPT_ID';
                $payment->_setInvoice($invoice);
                $mail->setInvoice($invoice);
                $mail->setPayment($payment);
                $mail->setInvoice_text($invoice->render('', $payment));
                $mail->setInvoice_html($invoice->renderHtml($payment));
                $mail->setInvoice_items($invoice->getItems());
                $mail->setProduct($product);
                break;
            default:
                throw new Am_Exception_InternalError(sprintf('Unknown email template name [%s]', $template->name));
        }

        $mail->setUser($user);
        $mail->send($user, new Am_Mail_Transport_Null());
        if ($template->format == 'text') {
            printf('<div style="margin-bottom:0.5em;">%s: <strong>%s</strong></div><div style="border:1px solid #2E2E2E; width:100%%"><pre>%s</pre></div>',
                ___('Subject'),
                Am_Html::escape($this->getSubject($mail)),
                Am_Html::escape($mail->getMail()->getBodyText()->getRawContent()));
        } else {
            $session = $this->grid->getDi()->session->ns('email_preview');
            $session->output = $mail->getMail()->getBodyHtml()->getRawContent();
            printf('<div style="margin-bottom:0.5em;">%s: <strong>%s</strong></div><iframe  style="border:1px solid #2E2E2E; width:100%%; height:600px" src="%s"></iframe>',
                ___('Subject'),
                Am_Html::escape($this->getSubject($mail)),
                $this->grid->getDi()->url('default/admin-content/p/emails', [
                    '_emails_a'  => 'preview',
                    '_emails_id' => $this->grid->getRecordId(),
                    '_emails_preview' => 1,
                ]));
        }
        return true;
    }

    protected function getSubject($mail)
    {
        $subject = $mail->getMail()->getSubject();
        if (strpos($subject, '=?') === 0)
            $subject = mb_decode_mimeheader($subject);
        return $subject;
    }

    protected function createForm()
    {
        $f = new Am_Form_Admin;
        $f->addText('user')->setLabel(___('Enter username of existing user'))
            ->addRule('required');
        $f->addSelect('product_id')
            ->setLabel(___('Product'))
            ->loadOptions($this->grid->getDi()->productTable->getOptions());
        $f->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#user-0" ).autocomplete({
        minLength: 2,
        source: amUrl("/admin-users/autocomplete")
    });
});
CUT
        );

        $tmp = $this->grid->getRecord();

        if ($tmp->name == EmailTemplate::EXPIRE) {
            $f->addDate('expires')
                ->setLabel(___('Expiration Date'))
                ->addRule('required');
        }

        $f->addSaveButton(___('Preview'));
        foreach ($this->grid->getVariablesList() as $k) {
            $kk = $this->grid->getId() . '_' . $k;
            if ($v = @$_REQUEST[$kk])
                $f->addHidden($kk)->setValue($v);
        }
        return $f;
    }
}

class Am_Grid_Action_PagePreview extends Am_Grid_Action_Abstract
{
    protected $type = Am_Grid_Action_Abstract::SINGLE;

    public function run()
    {
        $f = $this->createForm();
        $f->setDataSources([$this->grid->getCompleteRequest()]);
        if ($f->isSubmitted() && $f->validate() && $this->process($f))
            return;
        echo $this->renderTitle();
        echo $f;
    }

    function process(Am_Form $f)
    {
        $vars = $f->getValue();
        $user = Am_Di::getInstance()->userTable->findFirstByLogin($vars['user']);
        if (!$user) {
            [$el] = $f->getElementsByName('user');
            $el->setError(___('User %s not found', $vars['user']));
            return false;
        }

        $page = $this->grid->getRecord();

        echo $page->render(Am_Di::getInstance()->view, $user);
        exit;
    }

    protected function createForm()
    {
        $f = new Am_Form_Admin;
        $f->addText('user')->setLabel(___('Enter username of existing user'))
            ->addRule('required');
        $f->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#user-0" ).autocomplete({
        minLength: 2,
        source: amUrl("/admin-users/autocomplete")
    });
});
CUT
        );
        $f->addSaveButton(___('Preview'));
        foreach ($this->grid->getVariablesList() as $k) {
            $kk = $this->grid->getId() . '_' . $k;
            if ($v = @$_REQUEST[$kk])
                $f->addHidden($kk)->setValue($v);
        }
        return $f;
    }
}

class Am_Form_Element_PlayerConfig extends HTML_QuickForm2_Element
{
    protected $value;
    /* @var HTML_QuickForm2_Element_InputHidden */
    protected $elHidden;
    /* @var HTML_QuickForm2_Element_Select */
    protected $elSelect;

    public function __construct($name = null, $attributes = null, array $data = [])
    {

        $this->elHidden = new HTML_QuickForm2_Element_InputHidden($name);
        $this->elHidden->setContainer($this->getContainer());

        $this->elSelect = new HTML_QuickForm2_Element_Select('__' . $name);

        $this->elSelect->loadOptions([
            '--global--' => ___('Use Global Settings'),
            '--custom--' => ___('Use Custom Settings')
            ]
        );

        $this->addPresets($this->elSelect);
        parent::__construct($name, $attributes, $data);
    }

    public function getType()
    {
        return 'player-config';
    }

    public function getRawValue()
    {
        return $this->elHidden->getRawValue();
    }

    public function updateValue()
    {
        $this->elHidden->setContainer($this->getContainer());
        $this->elHidden->updateValue();
        $this->setValue($this->elHidden->getRawValue());
    }

    public function setValue($value)
    {
        if (!$value) {
            $this->elSelect->setValue('--global--');
        } elseif (@unserialize($value)) {
            $this->elSelect->setValue('--custom--');
        } else {
            $this->elSelect->setValue($value);
        }
        $this->elHidden->setValue($value);
    }

    public function __toString()
    {
        return sprintf('<div class="player-config">%s%s <div class="player-config-edit"><a href="javascript:;" class="local">%s</div><div class="player-config-delete"><a href="javascript:;" class="local">%s</div><div class="player-config-save"><a href="javascript:;" class="local">%s</a></div></div>',
            $this->elHidden, $this->elSelect, ___('Edit'), ___('Delete Preset'), ___('Save As Preset')) .
        "<script type='text/javascript'>
             jQuery('.player-config').playerConfig();
         </script>";
    }

    protected function addPresets(HTML_QuickForm2_Element_Select $select)
    {
        $result = [];
        $presets = Am_Di::getInstance()->store->getBlob('flowplayer-presets');
        $presets = $presets ? unserialize($presets) : [];
        foreach ($presets as $id => $preset) {
            $select->addOption($preset['name'], $id, ['data-config' => serialize($preset['config'])]);
        }
    }
}

class Am_Form_Element_DownloadLimit extends HTML_QuickForm2_Element
{
    protected $value = [];
    /* @var HTML_QuickForm2_Element_InputText */
    protected $elText;
    /* @var HTML_QuickForm2_Element_Select */
    protected $elSelect;
    /* @var Am_Form_Element_AdvCheckbox */
    protected $elCheckbox;

    public function __construct($name = null, $attributes = null, array $data = [])
    {
        $this->elText = new HTML_QuickForm2_Element_InputText("__limit_" . $name, ['class' => 'download-limit-limit', 'size' => 4]);
        $this->elText->setValue(5); //Default

        $this->elSelect = new HTML_QuickForm2_Element_Select("__period_" . $name, ['class' => 'download-limit-period']);
        $this->elSelect->loadOptions([
            FileDownloadTable::PERIOD_HOUR => ___('Hour'),
            FileDownloadTable::PERIOD_DAY => ___('Day'),
            FileDownloadTable::PERIOD_WEEK => ___('Week'),
            FileDownloadTable::PERIOD_MONTH => ___('Month'),
            FileDownloadTable::PERIOD_YEAR => ___('Year'),
            FileDownloadTable::PERIOD_ALL => ___('All Subscription Period')
            ]
        )->setValue(FileDownloadTable::PERIOD_MONTH); //Default

        $this->elCheckbox = new Am_Form_Element_AdvCheckbox("__enable_" . $name, ['class' => 'download-limit-enable']);

        parent::__construct($name, $attributes, $data);
    }

    public function getType()
    {
        return 'download-limit';
    }

    public function updateValue()
    {
        $this->elText->setContainer($this->getContainer());
        $this->elText->updateValue();
        $this->elSelect->setContainer($this->getContainer());
        $this->elSelect->updateValue();
        $this->elCheckbox->setContainer($this->getContainer());
        $this->elCheckbox->updateValue();
        parent::updateValue();
    }

    public function getRawValue()
    {
        return $this->elCheckbox->getValue() ? sprintf('%d:%d', $this->elText->getValue(), $this->elSelect->getValue()) : '';
    }

    public function setValue($value)
    {
        if (!$value) {
            $this->elCheckbox->setValue(0);
        } else {
            $this->elCheckbox->setValue(1);
            [$limit, $period] = explode(':', $value);
            $this->elText->setValue($limit);
            $this->elSelect->setValue($period);
        }
    }

    public function __toString()
    {
        $name = Am_Html::escape($this->getName());

        $ret = "<div class='download-limit' id='downlod-limit-$name'>\n";
        $ret .= $this->elCheckbox;
        $ret .= ' <span>';
        $ret .= ___('allow max');
        $ret .= ' ' . (string) $this->elText . ' ';
        $ret .= ___('downloads within');
        $ret .= ' ' . (string) $this->elSelect . ' ';
        $ret .= ___('during subscription period');
        $ret .= "</span>\n";
        $ret .= "</div>";
        $ret .= "
        <script type='text/javascript'>
             jQuery('.download-limit').find('input[type=checkbox]').change(function(){
                jQuery(this).next().toggle(this.checked)
             }).change();
        </script>
        ";
        return $ret;
    }
}

class Am_Grid_Editable_Files extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->addCallback(self::CB_AFTER_DELETE, [$this, 'afterDelete']);
        $this->addCallback(self::CB_AFTER_SAVE, [$this, 'dropCache']);
        $this->addCallback(self::CB_AFTER_DELETE, [$this, 'dropCache']);
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
        $this->setFilter(new Am_Grid_Filter_Content_Common);
    }

    public function _valuesFromForm(& $values)
    {
        $path = $values['path'];
        $file = $this->getDi()->plugins_storage->getFile($path);

        $values['mime'] = $file->getMime();
        $values['filename'] = $file->getName();
    }

    public function _valuesToForm(array & $values, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $values['_category'] = $record->getCategories();
        }
        parent::_valuesToForm($values, $record);
    }

    public function afterInsert(array & $values, ResourceAbstract $record)
    {
        $record->setCategories(empty($values['_category']) ? [] : $values['_category']);
        parent::afterInsert($values, $record);
    }

    protected function dropCache()
    {
        $this->getDi()->cache->clean();
    }

    protected function afterDelete(File $record, $grid)
    {
        if (ctype_digit($record->path)
            && !$this->getDi()->fileTable->countBy(['path' => $record->path])) {
            $this->getDi()->uploadTable->load($record->path)->delete();
        }
    }

    public function initActions()
    {
        parent::initActions();
        if (!$this->getDi()->config->get('disable_resource_category'))
        {
            $this->actionAdd(new Am_Grid_Action_Group_ContentAssignCategory);
            $this->actionAdd(new Am_Grid_Action_Group_ContentRemoveCategory);
        }
        $this->actionAdd(new Am_Grid_Action_Group_ContentHideFromDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentShowOnDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentSetAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAmendAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderAccessTitle']);
        $this->addField('path', ___('Filename'))->setRenderFunction([$this, 'renderPath']);
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category WHERE resource_type=?", 'file')) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->fileTable);
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');

        $maxFileSize = Am_Storage_File::getSizeReadable(
            min(Am_Storage_File::getSizeBytes(ini_get('post_max_size')),
                Am_Storage_File::getSizeBytes(ini_get('upload_max_filesize'))));
        $el = $form->addUpload('path', [], ['prefix' => 'downloads'])
            ->setLabel(___("File\n(max filesize %s)", $maxFileSize))
            ->setId('form-path');

        $jsOptions = <<<CUT
{
    onFileAdd : function (info) {
        var txt = jQuery(this).closest("form").find("input[name='title']");
        if (txt.data('set-by-me') || !txt.val().trim()) {
            txt.data('set-by-me', true);
            txt.val(info.name);
        }
    }
}
CUT;
        $el->setJsOptions($jsOptions);
        $form->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("input[name='title']").change(function(){
        jQuery(this).data('set-by-me', false);
    });
});
CUT
        );

        $el->addRule('required', ___('File is required'));
        $form->addText('title', ['class' => 'am-el-wide translate'])->setLabel(___('Title'))->addRule('required', 'This field is required');
        $form->addText('desc', ['class' => 'am-el-wide translate'])->setLabel(___('Description'));
        $form->addAdvCheckbox('hide')->setLabel(___("Hide from Dashboard\n" . "do not display this item link in members dashboard\n This doesn't remove link from category."));
        $form->addElement(new Am_Form_Element_DownloadLimit('download_limit'))->setLabel(___('Limit Downloads Count'));
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        $form->addText('no_access_url', ['class' => 'am-el-wide'])
            ->setLabel(___("No Access URL\ncustomer without required access will be redirected to this url, leave empty if you want to redirect to default 'No access' page"));

        $this->addCategoryToForm($form);

        return $form;
    }
}

class Am_Grid_Editable_Pages extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
        $this->setFilter(new Am_Grid_Filter_Content_Page);
        $this->setFormValueCallback('meta_robots', ['RECORD', 'unserializeList'], ['RECORD', 'serializeList']);
    }

    public function _valuesToForm(array & $values, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $values['_category'] = $record->getCategories();
        }
        parent::_valuesToForm($values, $record);
    }

    public function afterInsert(array & $values, ResourceAbstract $record)
    {
        $record->setCategories(empty($values['_category']) ? [] : $values['_category']);
        parent::afterInsert($values, $record);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_PagePreview('preview', ___('Preview')));
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentRemoveCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignTpl);
        $this->actionAdd(new Am_Grid_Action_Group_ContentHideFromDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentShowOnDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentSetAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAmendAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderPageTitle']);
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category WHERE resource_type=?", 'page')) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->pageTable);
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $form->addText('title', ['class' => 'am-el-wide translate'])
            ->setLabel(___('Title'))
            ->addRule('required', 'This field is required');
        $form->addText('desc', ['class' => 'am-el-wide translate'])
            ->setLabel(___('Description'));
        $form->addText('path', ['class' => 'am-el-wide'])
            ->setId('page-path')
            ->setLabel(___("Path\n" .
                'will be used to construct user-friendly url, in case of you leave ' .
                'it empty aMember will use id of this page to do it'))
            ->addRule('callback2', null, [$this, 'checkPath']);

        $page_url = $this->getDi()->rurl('page/');

        $form->addStatic()
            ->setLabel(___('Permalink'))
            ->setContent(<<<CUT
<div data-page_url="$page_url" id="page-permalink"></div>
CUT
        );

        $form->addScript()
            ->setScript(<<<CUT
jQuery('#page-path').bind('keyup', function(){
    jQuery('#page-permalink').closest('.am-row').toggle(jQuery(this).val() != '');
    jQuery('#page-permalink').html(jQuery('#page-permalink').data('page_url') + encodeURIComponent(jQuery(this).val()).replace(/%20/g, '+'))
}).trigger('keyup')
CUT
        );

        $form->addAdvCheckbox('hide')->setLabel(___("Hide from Dashboard\n" . "do not display this item link in members area"));

        $placeholder_items =& $options['placeholder_items'];
        foreach ($this->getUserTagOptions() as $k => $v) {
            $placeholder_items[] = [$v, $k];
        }

        $form->addHtmlEditor('html')
            ->setMceOptions($options);

        $form->addAdvCheckbox('use_layout')
            ->setId('use-layout')
            ->setLabel(___("Display inside layout\nWhen displaying to customer, will the\nheader/footer from current theme be displayed?"));
        $form->addSelect('tpl')
            ->setId('use-layout-tpl')
            ->setLabel(___("Template\nalternative template for this page") .
                "\n" .
                ___("aMember will look for templates in [application/default/views/] folder\n" .
                    "and in theme's [/] folder\n" .
                    "and template filename must start with [layout]"))
            ->loadOptions($this->getTemplateOptions());
        $form->addScript()
            ->setScript(<<<CUT
jQuery('#use-layout').change(function(){
    jQuery('#use-layout-tpl').closest('.am-row').toggle(this.checked);
}).change()
CUT
        );

        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        $form->addText('no_access_url', ['class' => 'am-el-wide'])
            ->setLabel(___("No Access URL\ncustomer without required access will be redirected to this url, leave empty if you want to redirect to default 'No access' page"));

        $this->addCategoryToForm($form);

        $fs = $form->addAdvFieldset('meta', ['id'=>'meta'])
                ->setLabel(___('Meta Data'));

        $fs->addText('meta_title', ['class' => 'am-el-wide'])
            ->setLabel(___('Title'));

        $fs->addText('meta_keywords', ['class' => 'am-el-wide'])
            ->setLabel(___('Keywords'));

        $fs->addText('meta_description', ['class' => 'am-el-wide'])
            ->setLabel(___('Description'));

        $gr = $fs->addGroup()->setLabel(___("Robots\n" .
            "instructions for search engines"));
        $gr->setSeparator(' ');
        $gr->addCheckbox('meta_robots[]', ['value' => 'noindex'], ['content' => 'noindex']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'nofollow'], ['content' => 'nofollow']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'noarchive'], ['content' => 'noarchive']);
        $gr->addFilter('array_filter');

        return $form;
    }

    function renderPageTitle(ResourceAbstract $r)
    {
        $specials = [];
        if ($this->getDi()->config->get('404_page') == $r->pk()) {
            $specials[] = ___('Page Not Found');
        }
        if ($this->getDi()->config->get('403_page') == $r->pk()) {
            $specials[] = ___('Access Forbidden');
        }
        if ($this->getDi()->config->get('dashboard_page') == $r->pk()) {
            $specials[] = ___('Dashboard Page');
        }
        if ($this->getDi()->config->get('index_page') == $r->pk()) {
            $specials[] = ___('Index Page');
        }

        $title = $this->escape($r->title);
        if (!empty($r->hide)) {
            $title = "<span class='disabled-text'>$title</span>";
        }
        if ($specials) {
            $title .= sprintf(' &ndash; <span style="opacity:.7">%s</span>',
                implode(', ', $specials));
        }
        if (!empty($r->desc)) {
            $_ = html_entity_decode(strip_tags($r->desc));
            $desc = $this->escape(mb_substr($_, 0, 100));
            if (mb_strlen($_) > 100) {
                $desc .= '&hellip;';
            }
            $title .= "<div style='opacity: .7'>{$desc}</div>";
        }
        return $this->renderTd($title, false);
    }

    function checkPath($v, $e)
    {
        $r = $this->getRecord();
        $found = $this->getDi()->db->selectCell('SELECT COUNT(*) FROm ?_page WHERE path=?
            {AND page_id<>?}', $v, $r->isLoaded() ? $r->pk() : DBSIMPLE_SKIP);
        return $found ? ___('Path should be unique') : null;
    }

    function getUserTagOptions()
    {
        $tagOptions = [
                '%user.name_f%' => 'User First Name',
                '%user.name_l%' => 'User Last Name',
                '%user.login%' => 'Username',
                '%user.email%' => 'E-Mail',
                '%user.user_id%' => 'User Internal ID#',
                '%user.street%' => 'User Street',
                '%user.street2%' => 'User Street (Second Line)',
                '%user.city%' => 'User City',
                '%user.state%' => 'User State',
                '%user.zip%' => 'User ZIP',
                '%user.country%' => 'User Country',
                '%user.status%' => 'User Status (0-pending, 1-active, 2-expired)'
        ];

        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (@$field->sql && @$field->from_config) {
                $tagOptions['%user.' . $field->name . '%'] = 'User ' . $field->title;
            }
        }

        return $tagOptions;
    }

    function _valuesFromForm(& $vals, $record)
    {
        if (!$vals['path'])
            $vals['path'] = null;
        if (!$vals['tpl'])
            $vals['tpl'] = null;
    }
}

class Am_Grid_Editable_Links extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Content_Common);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentRemoveCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentHideFromDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentShowOnDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentSetAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAmendAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderAccessTitle']);
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category WHERE resource_type=?", 'link')) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->linkTable);
    }

    public function _valuesToForm(array & $values, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $values['_category'] = $record->getCategories();
        }
        parent::_valuesToForm($values, $record);
    }

    public function afterInsert(array & $values, ResourceAbstract $record)
    {
        $record->setCategories(empty($values['_category']) ? [] : $values['_category']);
        parent::afterInsert($values, $record);
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $form->addText('title', ['class' => 'am-el-wide translate'])->setLabel(___('Title'))->addRule('required');
        $form->addText('desc', ['class' => 'am-el-wide translate'])->setLabel(___('Description'));
        $form->addText('url', ['class' => 'am-el-wide'])->setLabel(___('URL'))->addRule('required');
        $form->addAdvCheckbox('hide')->setLabel(___("Hide from Dashboard\n" . "do not display this item link in members area"));
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_free_without_login', 'true');

        $this->addCategoryToForm($form);

        return $form;
    }

    public function renderContent()
    {
        return '<div class="info"><strong>' . ___("IMPORTANT NOTE: This will not protect content. If someone know link url, he will be able to open link without a problem. This just control what additional links user will see after login to member's area.") . '</strong></div>' . parent::renderContent();
    }
}

class Am_Grid_Editable_Integrations extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Content_Integration);
    }

    public function init()
    {
        parent::init();
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    public function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->integrationTable);
    }

    protected function initGridFields()
    {
        $this->addField('plugin', ___('Plugin'))->setRenderFunction([$this, 'renderPluginTitle']);
        $this->addField('resource', ___('Resource'), false)->setRenderFunction([$this, 'renderResourceTitle']);
        parent::initGridFields();
        $this->removeField('_link');
    }

    public function renderPluginTitle(Am_Record $r)
    {
        return $this->renderTd($r->plugin);
    }

    public function renderResourceTitle(Am_Record $r)
    {
        try {
            $pl = $this->getDi()->plugins_protect->get($r->plugin);
        } catch (Am_Exception_InternalError $e) {
            $pl = null;
        }
        $config = unserialize($r->vars);
        if($config === false)
        {
            $s = '';
        }
        else
        {
            $s = $pl ? $pl->getIntegrationSettingDescription($config) : Am_Protect_Abstract::static_getIntegrationDescription($config);
        }

        return $this->renderTd($s);
    }

    public function getGridPageTitle()
    {
        return ___("Integration plugins");
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $plugins = $form->addSelect('plugin')->setLabel(___('Plugin'));
        $plugins->addRule('required');
        $plugins->addOption('*** ' . ___('Select a plugin') . ' ***', '');
        foreach (Am_Di::getInstance()->plugins_protect->getAllEnabled() as $plugin) {
            if (!$plugin->isConfigured())
                continue;
            $group = $form->addFieldset($plugin->getId())->setId('headrow-' . $plugin->getId());
            $group->setLabel($plugin->getTitle());
            $plugin->getIntegrationFormElements($group);
            // add id[...] around the element name
            foreach ($group->getElements() as $el)
                $el->setName('_plugins[' . $plugin->getId() . '][' . $el->getName() . ']');
            if (!$group->count())
                $form->removeChild($group);
            else
                $plugins->addOption($plugin->getTitle(), $plugin->getId());
        }
        $group = $form->addFieldset('access')->setLabel(___('Access'));
        $group->addElement(new Am_Form_Element_ResourceAccess)
            ->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_period', 'true')
            ->setAttribute('without_free', 'true')
            ->setAttribute('without_free_without_login', 'true');

        $form->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("select[name='plugin']").change(function(){
        var selected = jQuery(this).val();
        jQuery("[id^='headrow-']").hide();
        if (selected) {
            jQuery("[id=headrow-"+selected+"-legend]").show();
            jQuery("[id=headrow-"+selected+"]").show();
        }
    }).change();
});
CUT
        );
        return $form;
    }

    public function _valuesFromForm(array & $vars)
    {
        if ($vars['plugin'] && !empty($vars['_plugins'][$vars['plugin']]))
            $vars['vars'] = serialize($vars['_plugins'][$vars['plugin']]);
    }

    public function _valuesToForm(array & $vars, Am_Record $record)
    {
        if (!empty($vars['vars'])) {
            foreach (unserialize($vars['vars']) as $k => $v)
                $vars['_plugins'][$vars['plugin']][$k] = $v;
        }
        parent::_valuesToForm($vars, $record);
    }
}

class Am_Grid_Editable_Folders extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Content_Folder());
        $this->setFormValueCallback('options',
            function($val) {return explode(' ', $val);},
            function($val) {return implode(' ', $val);});
    }

    public function _valuesToForm(array & $values, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $values['_category'] = $record->getCategories();
        }
        parent::_valuesToForm($values, $record);
    }

    public function init()
    {
        parent::init();
        $this->addCallback(self::CB_AFTER_UPDATE, [$this, 'afterUpdate']);
        $this->addCallback(self::CB_AFTER_DELETE, [$this, 'afterDelete']);
    }

    public function validatePath($path)
    {
        if (trim($path, '/') == trim(ROOT_DIR, '/'))
            return ___('You can not protect folder with aMember');
        if (!is_dir($path))
            return ___('Wrong path: not a folder: %s', htmlentities($path));
        if (!is_writeable($path))
            return ___('Specified folder is not writable - please chmod the folder to 777, so aMember can write .htaccess file for folder protection');
        if ((!$this->getRecord()->isLoaded() || $this->getRecord()->path != $path) &&
            $this->getDi()->folderTable->findFirstByPath($path))
            return ___('Specified folder is already protected. Please alter existing record or choose another folder.');
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $title = $form->addText('title', ['class' => 'am-el-wide translate',])->setLabel(___("Title\ndisplayed to customers"));
        $title->addRule('required');
        $form->addText('desc', ['class' => 'am-el-wide translate'])->setLabel(___('Description'));
        $form->addAdvCheckbox('hide')->setLabel(___("Hide from Dashboard\n" . "do not display this item link in members area"));

        $path = $form->addText('path')->setLabel(___('Path to Folder'))->setAttribute('size', 50)->addClass('dir-browser');
        $path->addRule('required');
        $path->addRule('callback2', '-- Wrong path --', [$this, 'validatePath']);

        $url = $form->addGroup()->setLabel(___('Folder URL'));
        $url->addRule('required');
        $url->addText('url')->setAttribute('size', 50)->setId('url');
        $url->addHtml()->setHtml(' <a href="javascript:;" id="test-url-link" class="link">' . ___('open in new window') . '</a>');

        $methods = [
            'new-rewrite' => ___('New Rewrite'),
            'htpasswd' => ___('Traditional .htpasswd'),
        ];
        foreach ($methods as $k => $v)
            if (!Am_Di::getInstance()->plugins_protect->isEnabled($k))
                unset($methods[$k]);


        $method = $form->addAdvRadio('method')->setLabel(___('Protection Method'));
        $method->addRule('required');
        $method->loadOptions($methods);
        if (count($methods) == 0) {
            throw new Am_Exception_InputError(___('No protection plugins enabled, please enable new-rewrite or htpasswd at aMember CP -> Setup -> Plugins'));
        } elseif (count($methods) == 1) {
            $method->setValue(key($methods))->toggleFrozen(true);
        }

        $form->addElement(new Am_Form_Element_ResourceAccess)
            ->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_free_without_login', 'true');
        $form->addScript('script')->setScript('
        jQuery(function(){
            jQuery(".dir-browser").dirBrowser({
                urlField : "#url",
                rootUrl  : ' . json_encode($this->getDi()->url('',null,false)) . ',
            });
            jQuery("#test-url-link").click(function() {
                var href = jQuery("input", jQuery(this).parent()).val();
                if (href)
                    window.open(href , "test-url", "");
            });
        });
        ');
        $form->addText('no_access_url', ['class' => 'am-el-wide'])
            ->setLabel(___("No Access URL\ncustomer without required access will be redirected to this url, leave empty if you want to redirect to default 'No access' page"));

        $gr = $form->addGroup('options')
            ->setLabel(___('Folder Options'));
        $gr->setSeparator("<br />");
        foreach ([
                '+Indexes' => ___('Directory Listings')
                 ] as $k => $v) {

            $gr->addAdvCheckbox(null, ['value' => $k])->setContent($v);
        }
        $gr->addHidden(null, ['value' => '']);
        $gr->addFilter('array_filter');

        $this->addCategoryToForm($form);

        return $form;
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentRemoveCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentHideFromDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentShowOnDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderAccessTitle']);
        $this->addField('path', ___('Path/URL'))->setRenderFunction([$this, 'renderPathUrl']);
        $this->addField('method', ___('Protection Method'));
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category WHERE resource_type=?", 'folder')) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }
        parent::initGridFields();
    }

    public function renderPathUrl(Folder $f)
    {
        $url = Am_Html::escape($f->url);
        return $this->renderTd(
            Am_Html::escape($f->path) .
            "<br />" .
            "<a href='$url' class='link' target='_blank' rel='noreferrer'>$url</a>", false);
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->folderTable);
    }

    public function getGridPageTitle()
    {
        return ___("Folders");
    }

    public function getHtaccessRewriteFile(Folder $folder)
    {
        if (AM_WIN) {
            $rd = str_replace("\\", '/', $this->getDi()->data_dir);
        } else {
            $rd = $this->getDi()->data_dir;
        }

        $rd = str_replace(' ', '\ ', $rd);

        $options = $folder->options;
        $no_access_url = $this->getDi()->url('no-access/folder/id/'.$folder->folder_id,null,false,2);
        $no_access_url .= strchr($no_access_url, '?') ? '&' : '?';
        $no_access_rule = "RewriteRule ^(.*)$ {$no_access_url}url=%{REQUEST_URI}?%{QUERY_STRING}&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R]";

        $new_rewrite_url = $this->getDi()->url('protect/new-rewrite',null,false,2);
        $new_rewrite_url .= strchr($new_rewrite_url, '?') ? '&' : '?';
        // B flag requires APACHE 2.2
        // older version causes 500 error code
        // define('AMEMBER_OLD_APACHE',true); can be added into config.php
        if(!defined('AMEMBER_OLD_APACHE'))
            $bflag = <<<BFLN
RewriteRule ^(.*)$ {$new_rewrite_url}f=$folder->folder_id&url=%{REQUEST_URI}?%1&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R,B]
BFLN;
        else
            $bflag = <<<BFLO
RewriteRule ^(.*)$ {$new_rewrite_url}f=$folder->folder_id&url=%{REQUEST_URI}?%{QUERY_STRING}&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R]
BFLO;
        return <<<CUT
########### AMEMBER START #####################
Options +FollowSymLinks $options
RewriteEngine On

# if cookie is set and file exists, stop rewriting and show page
RewriteCond %{HTTP_COOKIE} amember_nr=([a-zA-Z0-9]+)
RewriteCond $rd/new-rewrite/%1-{$folder->folder_id} -f
RewriteRule ^(.*)\$ - [S=3]

# if cookie is set but folder file does not exists, user has no access to given folder
RewriteCond %{HTTP_COOKIE} amember_nr=([a-zA-Z0-9]+)
RewriteCond $rd/new-rewrite/%1-{$folder->folder_id} !-f
$no_access_rule

## if user is not authorized, redirect to login page
# BrowserMatch "MSIE" force-no-vary
RewriteCond %{QUERY_STRING} (.+)
$bflag
RewriteRule ^(.*)$ {$new_rewrite_url}f=$folder->folder_id&url=%{REQUEST_URI}&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R]
########### AMEMBER FINISH ####################
CUT;
    }

    public function getHtaccessHtpasswdFile(Folder $folder)
    {
        $rd = $this->getDi()->data_dir;

        $options = $folder->options ? "Options $folder->options" : '';
        $require = '';
        if (!$folder->hasAnyProducts())
            $require = 'valid-user';
        else
            $require = 'group FOLDER_' . $folder->folder_id;

//        $redirect = ROOT_SURL . "/no-access?folder_id={$folder->folder_id}";
//        ErrorDocument 401 $redirect

        return <<<CUT
########### AMEMBER START #####################
AuthType Basic
AuthName "Members Only"
AuthUserFile $rd/.htpasswd
AuthGroupFile $rd/.htgroup
Require $require
$options
########### AMEMBER FINISH ####################

CUT;
    }

    public function protectFolder(Folder $folder)
    {
        switch ($folder->method) {
            case 'new-rewrite':
                $ht = $this->getHtaccessRewriteFile($folder);
                break;
            case 'htpasswd':
                $ht = $this->getHtaccessHtpasswdFile($folder);
                break;
            default: throw new Am_Exception_InternalError(___('Unknown protection method'));
        }
        $ht = $this->getDi()->hook->filter($ht, Am_Event::FOLDER_PROTECT_CODE, ['folder' => $folder]);

        $htaccess_path = $folder->path . '/' . '.htaccess';
        if (file_exists($htaccess_path)) {
            $content = file_get_contents($htaccess_path);
            $new_content = preg_replace('/#+\sAMEMBER START.+AMEMBER FINISH\s#+/ms', $ht, $content, 1, $found);
            if (!$found)
                $new_content = $ht . "\n\n" . $content;
        } else {
            $new_content = $ht . "\n\n";
        }
        if (!file_put_contents($htaccess_path, $new_content))
            throw new Am_Exception_InputError(___('Could not write file [%s] - check file permissions and make sure it is writeable', $htaccess_path));
    }

    public function unprotectFolder(Folder $folder)
    {
        $htaccess_path = $folder->path . '/.htaccess';
        if (!is_dir($folder->path)) {
            trigger_error(___('Could not open folder [%s] to remove .htaccess from it. Do it manually', $folder->path), E_USER_WARNING);
            return;
        }
        $content = file_get_contents($htaccess_path);
        $content = preg_replace('/^\s*\#+\sAMEMBER START.+AMEMBER FINISH\s#+\s*/s', '', $content);
        if (!trim($content)) {
            if (!unlink($folder->path . '/.htaccess'))
                trigger_error(___('File [%s] cannot be deleted - remove it manually to unprotect folder', $htaccess_path), E_USER_WARNING);
        } else {
            if(!file_put_contents($htaccess_path, $content))
                trigger_error(___('File [%s] cannot be deleted - remove it manually to unprotect folder', $htaccess_path), E_USER_WARNING);
        }
    }

    public function afterInsert(array &$values, ResourceAbstract $record)
    {
        $record->setCategories(empty($values['_category']) ? [] : $values['_category']);
        parent::afterInsert($values, $record);
        $this->protectFolder($record);
    }

    public function afterUpdate(array &$values, ResourceAbstract $record)
    {
        $this->protectFolder($record);
    }

    public function afterDelete($record)
    {
        $this->unprotectFolder($record);
    }

    public function renderContent()
    {
        return $this->getDi()->plugins_protect->isEnabled('htpasswd') ?
            '<div class="info">' . ___("After making any changes to htpasswd protected areas, please run %sUtiltites -> Rebuild Db -> Rebuild Htpasswd Database%s to refresh htpasswd file", '<a href="' . $this->getDi()->url('admin-rebuild') . '" class="link" target="_top">', '</a>') . '</div>' . parent::renderContent() :
            parent::renderContent();
    }
}

class Am_Grid_Editable_Emails extends Am_Grid_Editable_Content
{
    protected $comment = [];

    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Content_Email);
    }

    public function init()
    {
        $this->comment = [
            EmailTemplate::PAYMENT => ___('Payment email will be automatically sent after payment received.
Payment email will not be sent if:
<ul>
    <li>User has unsubscribed from e-mail messages</li>
</ul>'),
            EmailTemplate::PRODUCTWELCOME =>
            ___('Product welcome email will be automatically sent immediately after payment received.
Product welcome email will not be sent if:
<ul>
    <li>User has unsubscribed from e-mail messages</li>
</ul>'),
            EmailTemplate::AUTORESPONDER =>
            ___('Autoresponder message will be automatically sent by cron job
when configured conditions met. If you set message to be sent
after payment, it will be sent immediately after payment received.
Auto-responder message will not be sent if:
<ul>
    <li>User has unsubscribed from e-mail messages</li>
</ul>'),
            EmailTemplate::EXPIRE =>
            ___('Expiration message will be sent when configured conditions met.
Additional restrictions applies to do not sent unnecessary e-mails.
Expiration message will not be sent if:
<ul>
    <li>User has other active products with the same renewal group</li>
    <li>User has unsubscribed from e-mail messages</li>
</ul>')
        ];
        parent::init();
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionDelete('insert');
        $this->actionAdd($a0 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::AUTORESPONDER, ___('New Autoresponder')));
        $a0->addUrlParam('name', EmailTemplate::AUTORESPONDER);
        $this->actionAdd($a1 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::EXPIRE, ___('New Expiration E-Mail')));
        $a1->addUrlParam('name', EmailTemplate::EXPIRE);
        $this->actionAdd($a2 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::PRODUCTWELCOME, ___('New Product Welcome E-Mail')));
        $a2->addUrlParam('name', EmailTemplate::PRODUCTWELCOME);
        $this->actionAdd($a3 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::PAYMENT, ___('New Payment E-Mail')));
        $a3->addUrlParam('name', EmailTemplate::PAYMENT);
        $this->actionAdd(new Am_Grid_Action_EmailPreview('preview', ___('Preview')));
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
        $this->actionAdd(new Am_Grid_Action_CopyEmail());
    }

    protected function createAdapter()
    {
        $ds = new Am_Query(Am_Di::getInstance()->emailTemplateTable);
        $ds->addWhere('name IN (?a)', [
            EmailTemplate::AUTORESPONDER, EmailTemplate::EXPIRE,
            EmailTemplate::PRODUCTWELCOME, EmailTemplate::PAYMENT
        ]);
        return $ds;
    }

    protected function initGridFields()
    {
        $this->addField('name', ___('Name'));
        $this->addField('recipient_emails', ___('Recipients'), true, '', [$this, 'getRecipients']);
        $this->addField('day', ___('Send'))->setGetFunction([$this, 'getDay']);
        $this->addField(new Am_Grid_Field_Expandable('subject', ___('Subject'), true))
            ->setPlaceholder(Am_Grid_Field_Expandable::PLACEHOLDER_SELF_TRUNCATE_BEGIN)
            ->setMaxLength(30);
        parent::initGridFields();
        $this->removeField('_link');
    }

    public function getDay(EmailTemplate $record)
    {
        switch ($record->name) {
            case EmailTemplate::AUTORESPONDER:
                return ($record->day > 1) ? ___("%d-th subscription day", $record->day) : ___("immediately after subscription is started");
                break;
            case EmailTemplate::EXPIRE:
                switch (true) {
                    case $record->day > 0:
                        return ___("%d days after expiration", $record->day);
                    case $record->day < 0:
                        return ___("%d days before expiration", -$record->day);
                    case $record->day == 0:
                        return ___("on expiration day");
                }
                break;
            case EmailTemplate::PAYMENT:
                switch (true) {
                    case $record->day > 0:
                        return ___("%d days in advance of recurring payment", $record->day);
                    case $record->day < 0:
                        return ___("%d days after payment", -$record->day);
                    case $record->day == 0:
                        switch ($record->payment_type) {
                            case EmailTemplate::PAYMENT_TYPE_ANY:
                                return ___("immediately after any payment");
                            case EmailTemplate::PAYMENT_TYPE_FIRST:
                                return ___("immediately after first payment in subscription");
                            case EmailTemplate::PAYMENT_TYPE_SECOND:
                                return ___("immediately after subsequent payment in subscription");
                        }
                }
                break;
            case EmailTemplate::PRODUCTWELCOME:
                return ___("immediately after product is purchased");
                break;
        }
    }

    public function getRecipients(EmailTemplate $record)
    {
        $recipients = [];
        if ($record->recipient_user)
            $recipients[] = "<strong>User</strong>";

        if ($record->recipient_aff)
            $recipients[] = "<strong>Affiliate</strong>";

        if ($record->recipient_subusers_parent)
            $recipients[] = "<strong>Subusers Parent</strong>";

        if ($record->recipient_admin)
            $recipients[] = "<strong>Admin</strong>";

        if ($record->recipient_emails)
            $recipients[] = $record->recipient_emails;

        return sprintf('<td>%s</td>', implode(', ', $recipients));
    }

    public function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $name = empty($r->name) ?
            $this->getCompleteRequest()->getFiltered('name') :
            $r->name;

        $form->addHidden('name');

        $form->addStatic()->setContent(nl2br($this->comment[$name]))->setLabel(___('Description'));

        $form->addStatic()->setLabel(___('E-Mail Type'))->setContent($name);

        if ($options = $this->getDi()->emailTemplateLayoutTable->getOptions()) {
            $form->addSelect('email_template_layout_id')
                ->setLabel(___('Layout'))
                ->loadOptions([''=>___('No Layout')] + $options);
        }

        $form->addSelect('reply_to')
            ->loadOptions($this->getReplyToOptions())
            ->setLabel(___("Reply To\n" .
                "mailbox for replies to message"));

        $recipient = $form->addGroup(null)->setLabel(___('Recipients'));
        $recipient->setSeparator('<br />');
        $recipient->addAdvCheckbox('recipient_user')
            ->setContent(___('User Email'));
        if ($this->getDi()->modules->isEnabled('aff')) {
            $recipient->addAdvCheckbox('recipient_aff')
                ->setContent(___('Affiliate Email'));
        }
        if ($this->getDi()->modules->isEnabled('subusers')) {
            $recipient->addAdvCheckbox('recipient_subusers_parent')
                ->setContent(___('Subusers Parent Email'));
        }
        $recipient->addAdvCheckbox('recipient_admin')
            ->setContent(___('Admin Email'));
        $recipient->addAdvCheckbox('recipient_other', ['id' => 'checkbox-recipient-other'])
            ->setContent(___('Other'));

        $form->addText('recipient_emails', ['class' => 'am-el-wide', 'id' => 'input-recipient-emails', 'placeholder' => ___('Email Addresses Separated by Comma')])
            ->setLabel(___('Emails'))
            ->addRule('callback2', ___('Please enter valid e-mail addresses'), [$this, 'validateOtherEmails']);

        $form->addText('bcc', ['class' => 'am-el-wide', 'placeholder' => ___('Email Addresses Separated by Comma')])
            ->setLabel(___("BCC\n" .
                "blind carbon copy allows the sender of a message to conceal the person entered in the Bcc field from the other recipients"))
            ->addRule('callback', ___('Please enter valid e-mail addresses'), ['Am_Validate', 'emails']);

        $form->addScript()->setScript(<<<CUT
jQuery("#checkbox-recipient-other").change(function(){
    jQuery("#row-input-recipient-emails").toggle(this.checked);
}).change();
CUT
            );

        $form->addElement(new Am_Form_Element_MailEditor($name, ['upload-prefix' => 'email-messages']));
        switch ($name)
        {
            case EmailTemplate::AUTORESPONDER:
                $access_desc = ___('Send E-Mail if customer has subscription (required)');
                break;
            case EmailTemplate::EXPIRE:
                $access_desc = ___('Send E-Mail when subscription expires (required)');
                break;
            case EmailTemplate::PRODUCTWELCOME:
                $access_desc = ___('Send E-Mail when the next subscription is started (required)');
                break;
            case EmailTemplate::PAYMENT:
                $access_desc = ___('Send E-Mail if invoice has the following subscriptions (required)');
                break;
        }
        if ($name == EmailTemplate::EXPIRE) {
            $gr = $form->addGroup()
                ->setLabel($access_desc)
                ->setSeparator('<br />');
            $access_el = $gr->addElement(new Am_Form_Element_ResourceAccess('_access'))
                ->setAttribute('without_period', true)
                ->setAttribute('without_free', true)
                ->setAttribute('without_user_group_id', true);
            $gr->addAdvCheckbox('recurring', null, ['content' => ___('send this message even if customer has active recurring subscription for matched product')]);
            $gr->addAdvCheckbox('cancelled', null, ['content' => ___('send this message even if customer has cancelled recurring subscription for matched product')]);
        } else {
            $access_el = $form->addElement(new Am_Form_Element_ResourceAccess('_access'))
                ->setLabel($access_desc)
                ->setAttribute('without_period', true)
                ->setAttribute('without_free', true)
                ->setAttribute('without_user_group_id', true);
        }

        $group = $form->addGroup()
                ->setLabel(___('Send E-Mail only if customer has no subscription (optional)'));
        $group->setSeparator('<br />');

        $select = $group->addMagicSelect('_not_conditions', ['class'=>'am-combobox']);
        $this->addCategoriesProductsList($select);
        $group->addAdvCheckbox('not_conditions_expired')->setContent(___('check expired subscriptions too'));
        $group->addAdvCheckbox('not_conditions_future')->setContent(___('check future subscriptions too'));

        if ($name != EmailTemplate::PRODUCTWELCOME)
        {
            $group = $form->addGroup('day')->setLabel(___('Send E-Mail Message'))
                ->setSeparator(' ');
            switch ($name) {
                case EmailTemplate::AUTORESPONDER:
                    $options = ['' => ___('..th subscription day (starts from 2)'), '1' => ___('immediately after subscription is started')];
                    break;
                case EmailTemplate::EXPIRE:
                    $options = ['-' => ___('days before expiration'), '0' => ___('on expiration day'), '+' => ___('days after expiration')];
                    break;
                case EmailTemplate::PAYMENT:
                    $options = [
                        '+' => ___('days in advance of recurring payment'),
                        EmailTemplate::PAYMENT_TYPE_ANY => ___('immediately after any payment'),
                        EmailTemplate::PAYMENT_TYPE_FIRST => ___('immediately after first payment in subscription'),
                        EmailTemplate::PAYMENT_TYPE_SECOND => ___('immediately after subsequent payment in subscription'),
                        '-' => ___('days after payment')];
                    break;

            }
            $group->addInteger('count', ['size' => 3, 'id' => 'days-count']);
            $group->addSelect('type', ['id' => 'days-type'])->loadOptions($options);
            $group->addScript()->setScript(<<<CUT
jQuery("#days-type").change(function(){
    var sel = jQuery(this);
    if (jQuery("input[name='name']").val() == 'autoresponder')
        jQuery("#days-count").toggle( sel.val() != '1' );
    else if (jQuery("input[name='name']").val() == 'payment')
        jQuery("#days-count").toggle( sel.val() == '+' || sel.val() == '-');
    else
        jQuery("#days-count").toggle( sel.val() != '0' );
}).change();
CUT
            );
        }
        if ($name == EmailTemplate::PAYMENT) {
            $form->addMagicSelect('paysys_ids')
                ->setLabel(___("Payment Systems\nsend email only for these paymet systems, keep empty to send for any"))
                ->loadOptions($this->getDi()->paysystemList->getOptions());
        }
        if ($this->getDi()->config->get('send_pdf_invoice') && ($name == EmailTemplate::PAYMENT)) {
           $form->addAdvCheckbox('attach_pdf_invoice', [], [
               'content' => ___('Attach PDF Invoice')
           ]);
        }
        return $form;
    }

    protected function getReplyToOptions()
    {
        $op = [];
        $op[''] = Am_Html::escape(sprintf('%s <%s>',
            $this->getDi()->config->get('admin_email_name', $this->getDi()->config->get('site_title')),
            $this->getDi()->config->get('admin_email_from', $this->getDi()->config->get('admin_email'))));
        foreach (Am_Di::getInstance()->adminTable->findBy() as $admin) {
           $op[$admin->pk()] = Am_Html::escape(sprintf('%s <%s>', $admin->getName(), $admin->email));
        }
        return $op;
    }

    function validateOtherEmails($val, $el)
    {
        $vars = $el->getContainer()->getValue();
        if ($vars['recipient_other'] == 1) {
            if (!strlen($vars['recipient_emails']))
                return ___('Please enter one or more email');
            if (!Am_Validate::emails($val))
                return ___('Please enter valid e-mail addresses');
        }
    }

    function addCategoriesProductsList(HTML_QuickForm2_Element_Select $select)
    {
        $g = $select->addOptgroup(___('Product Categories'), ['class' => 'product_category_id', 'data-text' => ___("Category")]);
        $g->addOption(___('Any Product'), 'c-1', ['style' => 'font-weight: bold']);
        foreach ($this->getDi()->productCategoryTable->getAdminSelectOptions() as $k => $v) {
            $g->addOption($v, 'c' . $k);
        }
        $g = $select->addOptgroup(___('Products'), ['class' => 'product_id', 'data-text' => ___("Product")]);
        foreach ($this->getDi()->productTable->getOptions() as $k => $v) {
            $g->addOption($v, 'p' . $k);
        }
    }

    public function _valuesToForm(array &$values, Am_Record $record)
    {
        parent::_valuesToForm($values, $record);
        switch (get_first(@$values['name'], @$_GET['name'])) {
            case EmailTemplate::AUTORESPONDER :
                $values['day'] = (empty($values['day']) || ($values['day'] == 1)) ?
                    ['count' => 1, 'type' => '1'] :
                    ['count' => $values['day'], 'type' => ''];
                break;
            case EmailTemplate::EXPIRE :
                $day = empty($values['day']) ? null : (int)$values['day'];
                $values['day'] = ['count' => $day, 'type' => ''];
                if ($day > 0)
                    $values['day']['type'] = '+';
                elseif ($day < 0) {
                    $values['day']['type'] = '-';
                    $values['day']['count'] = -$day;
                } else {
                    $values['day']['type'] = '0';
                }
                break;
            case EmailTemplate::PAYMENT :
                $day = empty($values['day']) ? null : (int)$values['day'];
                $values['day'] = ['count' => $day, 'type' => ''];
                if ($day > 0)
                    $values['day']['type'] = '+';
                elseif ($day < 0) {
                    $values['day']['type'] = '-';
                    $values['day']['count'] = -$day;
                } else {
                    $values['day']['type'] = $values['payment_type'] ?? EmailTemplate::PAYMENT_TYPE_ANY;
                }
        }
        $values['attachments'] = explode(',', @$values['attachments']);
        $values['paysys_ids'] = explode(',', @$values['paysys_ids']);
        $values['_not_conditions'] = explode(',', @$values['not_conditions']);

        if (!empty($values['recipient_emails'])) {
            $values['recipient_other'] = 1;
        }

        if (!$record->isLoaded()) {
            $values['recipient_user'] = 1;
            $values['format'] = 'html';
        }
    }

    public function _valuesFromForm(array &$values)
    {
        if (!$values['reply_to']) {
            $values['reply_to'] = null;
        }
        if (!$values['email_template_layout_id']) {
            $values['email_template_layout_id'] = null;
        }

        switch ($values['day']['type']) {
            case '0': $values['day'] = 0;
                break;
            case '1': $values['day'] = 1;
                break;
            case '': case '+':
                $values['day'] = (int) $values['day']['count'];
                break;
            case '-':
                $values['day'] = - $values['day']['count'];
                break;
            case EmailTemplate::PAYMENT_TYPE_ANY:
            case EmailTemplate::PAYMENT_TYPE_FIRST:
            case EmailTemplate::PAYMENT_TYPE_SECOND:
                $values['payment_type'] = $values['day']['type'];
                $values['day'] = 0;
                break;
        }
        $values['attachments'] = implode(',', @$values['attachments']);
        ///////
        foreach (['free', 'free_without_login', 'product_category_id', 'product_id'] as $key) {
            if (!empty($values['_access'][$key]))
                foreach ($values['_access'][$key] as & $item) {
                    if (is_string($item))
                        $item = json_decode($item, true);
                    $item['start'] = $item['stop'] = $values['day'] . 'd';
                }
        }
        $values['_not_conditions'] = array_filter(array_map('filterId', $values['_not_conditions']));
        $values['not_conditions'] = implode(',', $values['_not_conditions']);

        if (!$values['recipient_other']) {
            $values['recipient_emails'] = null;
        }
        unset($values['recipient_other']);
        if (empty($values['paysys_ids'])) {
            $values['paysys_ids'] = null;
        } else {
            $values['paysys_ids'] = implode(',', $values['paysys_ids']);
        }
    }

    public function getProducts(ResourceAbstract $resource)
    {
        $s = "";
        foreach ($resource->getAccessList() as $access)
            $s .= sprintf("%s <b>%s</b> %s<br />\n", $access->getClass(), $access->getTitle(), "");
        return $s;
    }
}

class Am_Grid_Editable_Video extends Am_Grid_Editable_Content
{
    function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
        $this->setFilter(new Am_Grid_Filter_Content_Common);
        $this->setFormValueCallback('meta_robots', ['RECORD', 'unserializeList'], ['RECORD', 'serializeList']);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentRemoveCategory);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAssignTpl);
        $this->actionAdd(new Am_Grid_Action_Group_ContentHideFromDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentShowOnDashboard);
        $this->actionAdd(new Am_Grid_Action_Group_ContentSetAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_ContentAmendAccessPermission);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderAccessTitle']);
        $this->addField('path', ___('Filename'))->setRenderFunction([$this, 'renderPath']);
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category WHERE resource_type=?", 'video')) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }
        $this->addField(new Am_Grid_Field_Expandable('_code', ___('JavaScript Code')))
            ->setGetFunction([$this, 'renderJsCode']);
        parent::initGridFields();
    }

    protected function _valuesFromForm(& $values)
    {
        $path = $values['path'];

        $file = $this->getDi()->plugins_storage->getFile($path);

        $values['mime'] = $file->getMime();
        $values['filename'] = $file->getName();

        if (!$values['tpl'])
            $values['tpl'] = null;
    }

    public function _valuesToForm(array & $values, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $values['_category'] = $record->getCategories();
        }
        parent::_valuesToForm($values, $record);
    }

    public function afterInsert(array & $values, ResourceAbstract $record)
    {
        $record->setCategories(empty($values['_category']) ? [] : $values['_category']);
        parent::afterInsert($values, $record);
    }

    public function renderJsCode(Video $video)
    {
        $type = $video->mime == 'audio/mpeg' ? 'audio' : 'video';

        $width = 550;
        $height = $type == 'video' ? 330 : 30;

        $url = $this->getDi()->surl($type.'/js/id/'.$video->video_id, false);

        $cnt = <<<CUT
<!-- the following code you may insert into any HTML, PHP page of your website or into WP post -->
<!-- you may skip including Jquery library if that is already included on your page -->
<script type="text/javascript"
        src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
<!-- end of JQuery include -->
<!-- there is aMember video JS code starts -->
<!-- you can use GET variable width and height in src URL below
     to customize these params for specific entity
     eg. $url?width=$width&height=$height -->
<script type="text/javascript" async id="am-$type-{$video->video_id}"
    src="$url">
</script>
<!-- end of aMember video JS code -->
CUT;
        return "<pre>" . Am_Html::escape($cnt) . "</pre>";
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->videoTable);
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');

        $maxFileSize = min(ini_get('post_max_size'), ini_get('upload_max_filesize'));
        $el = $form->addElement(new Am_Form_Element_Upload('path', [], ['prefix' => 'video']))
                ->setLabel(___("Video/Audio File\n".
                            "(max upload size %s)\n".
                            "You can use this feature only for video and\naudio formats that supported by HTML5",
                            $maxFileSize))
                ->setId('form-path');

        $jsOptions = <<<CUT
{
    onFileAdd : function (info) {
        var txt = jQuery(this).closest("form").find("input[name='title']");
        if (txt.data('changed-value')) return;
        txt.val(info.name);
    }
}
CUT;
        $el->setJsOptions($jsOptions);
        $form->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("input[name='title']").change(function(){
        jQuery(this).data('changed-value', true);
    });
});
CUT
        );
        $el->addRule('required');

        $form->addUpload('poster_id', null, ['prefix' => 'video-poster'])
            ->setLabel(___("Poster Image\n" .
                "applicable only for video files"));
        /*
        $form->addUpload('cc_id', null, ['prefix' => 'video-cc'])
            ->setLabel(___("Closed Captions (SubRip file, .srt)\n" .
                "file must be in SRT format, used for Flash player, applicable only for video files"));
        */
        $form->addUpload('cc_vtt_id', null, ['prefix' => 'video-cc'])
            ->setLabel(___("Closed Captions (WebVTT file, .vtt)\n" .
                "file must be in VTT format, used for HTML5 player, applicable only for video files. " .
                "You can easily convert SRT to WebVTT using an online converter such as %ssrt2vtt%s.",
                '<a href="https://atelier.u-sub.net/srt2vtt/" class="link" rel="noreferrer" target="_top">', '</a>'));

        $form->addText('title', ['class' => 'am-el-wide translate'])->setLabel(___('Title'))->addRule('required', 'This field is required');
        $form->addText('desc', ['class' => 'am-el-wide translate'])->setLabel(___('Description'));
        $form->addAdvCheckbox('hide')->setLabel(___("Hide from Dashboard\n" . "do not display this item link in members area"));

        $form->addElement(new Am_Form_Element_PlayerConfig('config'))
            ->setLabel(___("Player Configuration\n" .
                'this option is applied only for video files'));

        $form->addSelect('tpl')
            ->setLabel(___("Template\nalternative template for this video\n" .
                    "aMember will look for templates in [application/default/views/] folder\n" .
                    "and in theme's [/] folder\n" .
                    "and template filename must start with [layout]"))
            ->loadOptions($this->getTemplateOptions());

        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___('Access Permissions'));
        $form->addText('no_access_url', ['class' => 'am-el-wide'])
            ->setLabel(___("No Access URL\n" .
                "customer without required access will see link to this url in " .
                "the player window\nleave empty if you want to redirect to " .
                "default 'No access' page"));

        $this->addCategoryToForm($form);

        $fs = $form->addAdvFieldset('meta', ['id'=>'meta'])
                ->setLabel(___('Meta Data'));

        $fs->addText('meta_title', ['class' => 'am-el-wide'])
            ->setLabel(___('Title'));

        $fs->addText('meta_keywords', ['class' => 'am-el-wide'])
            ->setLabel(___('Keywords'));

        $fs->addText('meta_description', ['class' => 'am-el-wide'])
            ->setLabel(___('Description'));

        $gr = $fs->addGroup()->setLabel(___("Robots\n" .
            "instructions for search engines"));
        $gr->setSeparator(' ');
        $gr->addCheckbox('meta_robots[]', ['value' => 'noindex'], ['content' => 'noindex']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'nofollow'], ['content' => 'nofollow']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'noarchive'], ['content' => 'noarchive']);
        $gr->addFilter('array_filter');

        $form->addEpilog('<div class="info">' . ___('In case of video do not start play before
full download and you use <a class="link" href="http://en.wikipedia.org/wiki/MPEG-4_Part_14">mp4 format</a>
more possible that metadata (moov atom) is located
at the end of file. There is special programs that allow to relocate
this metadata to the beginning of your file and allow play video before full
download (On Linux machine you can use <em>qt-faststart</em> utility to do it).
Also your video editor can has option to locate metadata at beginning of file
(something like <em>Fast Start</em>, <em>Progressive Download</em>,
<em>Use Streaming Mode</em> or <em>Web Optimized</em> option). You need to
relocate metadata for this file and re upload it to aMember. You can use such
utilities as <em>AtomicParsley</em> or similar to check your file structure.') . '</div>');

        return $form;
    }
}

class Am_Grid_Editable_ContentAll extends Am_Grid_Editable
{
    protected $_cat_options = null;

    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        $di = Am_Di::getInstance();

        $ds = null;
        $i = 0;
        $key = null;
        foreach ($di->resourceAccessTable->getAccessTables() as $k => $t) {
            $q = new Am_Query($t);
            $q->clearFields();
            if (empty($key))
                $key = $t->getKeyField();
            $q->addField($t->getKeyField(), $key);
            $type = $t->getAccessType();
            $q->addField("'$type'", 'resource_type');
            $q->addField($t->getTitleField(), 'title');
            $q->addField($q->escape($t->getAccessTitle()), 'type_title');
            $q->addField($q->escape($t->getPageId()), 'page_id');
            $q->addField("(SELECT GROUP_CONCAT(resource_category_id SEPARATOR ',') FROM ?_resource_resource_category rrc WHERE"
                . " resource_id=$key AND resource_type='$type' GROUP BY resource_id)", 'cat_id');

            if ($t instanceof EmailTemplateTable) {
                $q->addWhere('name IN (?a)', [EmailTemplate::AUTORESPONDER, EmailTemplate::EXPIRE]);
            }
            if ($t instanceof NewsletterListTable) {
                $q->addWhere('IFNULL(disabled,0)=0');
            }
            if (empty($ds)) {
                $ds = $q;
            } else {
                $ds->addUnion($q);
            }
        }
        // yes we need that subquery in subquery to mask field names
        // to get access of fields of main query (!)
        $ds->addOrderRaw("(SELECT _sort_order
             FROM ( SELECT sort_order as _sort_order,
                    resource_type as _resource_type,
                    resource_id as _resource_id
                  FROM ?_resource_access_sort ras) AS _ras
             WHERE _resource_id=$key AND _resource_type=resource_type LIMIT 1),
             $key, resource_type");

        parent::__construct('_all', ___('All Content'), $ds, $request, $view, $di);
        $this->addField('type_title', ___('Type'));
        $this->addField('title', ___('Title'));
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_resource_resource_category")) {
            $this->addField('rgroup', ___('Categories'), false)->setRenderFunction([$this, 'renderCategory']);
        }

        $this->actionDelete('insert');
        $this->actionDelete('edit');
        $this->actionDelete('delete');
        $this->setFilter(new Am_Grid_Filter_Content_All);
        $di = $this->getDi();
        $this->actionAdd(new Am_Grid_Action_ContentAllEdit('edit', ___('Edit'), ''))
            ->setIsAvailableCallback(function($r) use ($di) {
                return $di->authAdmin->getUser()->hasPermission('grid_' . $r->page_id, 'edit');
            });
        $this->actionAdd(new Am_Grid_Action_SortContent());
    }

    public function renderCategory(Am_Record $r, $fieldname, Am_Grid_ReadOnly $grid, $field)
    {
        $res = [];
        if (is_null($this->_cat_options)) {
            $this->_cat_options = $this->getDi()->resourceCategoryTable->getOptions();
        }
        foreach (explode(',', $r->cat_id) as $resc_id) {
            if ($resc_id)
                $res[] = $this->_cat_options[$resc_id];
        }
        return $this->renderTd(implode(', ', $res));
    }
}

class Am_Grid_Action_SortContent extends Am_Grid_Action_Sort_Abstract
{
    protected $privilege = 'edit';

    protected function getRecordParams($obj)
    {
        $id = $obj->pk();
        $type = $obj->get('resource_type');
        if (!$type)
            $type = $this->grid->getDataSource()->createRecord()->getAccessType();

        return [
            'id' => $id,
            'type' => $type
        ];
    }

    protected function setSortBetween($item, $after, $before)
    {
        $move_after = $after ? $after['id'] : null;
        $move_after_type = $after ? $after['type'] : null;
        $move_before = $before ? $before['id'] : null;
        $move_before_type = $before ? $before['type'] : null;

        $accessTables = Am_Di::getInstance()->resourceAccessTable->getAccessTables();
        $record = $accessTables[$item['type']]->load($item['id']);

        $record->setSortBetween($move_after, $move_before, $move_after_type,
            $move_before_type);
    }
}

class Am_Grid_Action_Group_ContentChangeOrder extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;

    public function __construct($id = null, $title = null)
    {
        parent::__construct($id, $title);
        $this->title = ___('Change Order');
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $_ = $this->grid->getDataSource()->createRecord()->getTable();
        $name = $_->getName();
        $key = $_->getKeyField();
        $field = $name == '?_email_template' ? 'subject' : 'title';
        $options = $this->grid->getDi()->db->selectCol(<<<CUT
            SELECT ?#, ?# AS ARRAY_KEY
                FROM $name
                {WHERE name IN (?a)};
CUT
            , $field, $key, $name == '?_email_template' ? ['autoresponder', 'expire', 'productwelcome', 'payment'] : DBSIMPLE_SKIP);

        $select = sprintf('%s <select name="%s__resource_id">
            %s
            </select><br /><br />'.PHP_EOL,
            ___('Put Chosen Resources After'),
            $this->grid->getId(),
            Am_Html::renderOptions($options)
        );
        return parent::renderConfirmationForm(___("Change Order"), $select);
    }

    public function handleRecord($id, $record)
    {
        $ids = $this->getIds();
        $after = null;
        foreach ($ids as $k => $v) {
            if ($v == $id) {
                $after = isset($ids[$k-1]) ? $ids[$k-1] : $this->grid->getRequest()->getInt('_resource_id');
                break;
            }
        }
        if (!$after) return;
        $record->setSortBetween($after, null);
    }
}

class Am_Grid_Action_ContentAllEdit extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $url;

    public function __construct($id, $title, $url)
    {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        parent::__construct();
        $this->setTarget('_top');
    }

    public function getUrl($record = null, $id = null)
    {
        $id = $record->pk();
        $page_id = $record->page_id;
        $back_url = Am_Html::escape($this->grid->getBackUrl());
        return Am_Di::getInstance()->url("default/admin-content/p/$page_id/index",
            ["_{$page_id}_a"=>'edit',"_{$page_id}_b"=>$back_url,"_{$page_id}_id"=>$id],false);
    }

    public function run()
    {
        //nop
    }
}

class AdminContentController extends Am_Mvc_Controller_Pages
{
    public function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    public function initPages()
    {
        if (empty($this->getSession()->admin_content_sort_checked)) {
            // dirty hack - we are checking that all content records have sort order
            $count = 0;
            foreach ($this->getDi()->resourceAccessTable->getAccessTables() as $k => $table)
                $count += $table->countBy();
            $countSort = $this->getDi()->db->selectCell("SELECT COUNT(*) FROM
                ?_resource_access_sort");
            if ($countSort != $count)
                $this->getDi()->resourceAccessTable->syncSortOrder();
            $this->getSession()->admin_content_sort_checked = 1;
        }
        //
        foreach ($this->getDi()->resourceAccessTable->getAccessTables() as $k => $table) {
            if (!$this->getDi()->authAdmin->getUser()->hasPermission('grid_' . $table->getPageId())) continue;
            /* @var $table ResourceAbstractTable */
            $page_id = $table->getPageId();
            $this->addPage('Am_Grid_Editable_' . ucfirst($page_id), $page_id, $table->getAccessTitle());
        }
        if ($this->getDi()->authAdmin->getUser()->hasPermission('grid_all')) {
            $this->addPage('Am_Grid_Editable_ContentAll', 'all', ___('All'));
        }
    }

    public function renderPage(Am_Mvc_Controller_Pages_Page $page)
    {
        $this->setActiveMenu($page->getId() == 'all' ? 'content' : 'content-' . $page->getId());
        return parent::renderPage($page);
    }
}

class Am_Grid_Action_CopyEmail extends Am_Grid_Action_Abstract
{
    protected $id = 'copy';
    protected $privilege = 'insert';

    public function run()
    {
        $record = $this->grid->getRecord();

        $vars = $record->toRow();
        unset($vars['email_template_id']);

        $back = @$_SERVER['HTTP_X_REQUESTED_WITH'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $controller = new AdminContentController(new Am_Mvc_Request(), new Am_Mvc_Response(),
            ['di' => Am_Di::getInstance()]);

        $this->grid->_valuesToForm($vars, $record);

        $request = new Am_Mvc_Request($vars + [
                '_emails_a' => 'insert-' . $vars['name'],
                '_emails_b' => $this->grid->getBackUrl()
            ], Am_Mvc_Request::METHOD_POST);

        $controller->setRequest($request);

        $request->setModuleName('default')
            ->setControllerName('admin-content')
            ->setParam('page_id', 'emails')
            ->setActionName('index')
            ->setDispatched(true);

        $controller->dispatch('indexAction');
        $response = $controller->getResponse();
        $response->sendResponse();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $back;
    }
}