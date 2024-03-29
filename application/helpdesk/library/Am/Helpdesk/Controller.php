<?php

class Am_Helpdesk_Controller extends Am_Mvc_Controller
{
    /** @var Am_Helpdesk_Strategy */
    protected $strategy;

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Bootstrap_Helpdesk::ADMIN_PERM_ID);
    }

    public function init()
    {
        $this->strategy = $this->getDi()->helpdeskStrategy;
        $type = defined('AM_ADMIN') ? 'admin' : 'user';
        $this->getView()->headLink()->appendStylesheet($this->getView()->_scriptCss('helpdesk-' . $type . '.css'));
        parent::init();
    }

    protected function isGridRequest($gridId)
    {
        foreach ($this->getRequest()->getParams() as $key => $val)
            if (substr($key, 0, strlen($gridId)) == $gridId)
                return true;

        return false;
    }

    public function newAction()
    {
        if (!$this->getModule()->getConfig('live')) return;

        $this->getDi()->session->writeClose();
        set_time_limit(0);
        while (@ob_end_clean());

        $message = $this->getDi()->helpdeskMessageTable->load($this->getDi()->security->reveal($this->getParam('id')));
        if (!$this->strategy->canViewMessage($message)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }
        $ticket = $message->getTicket();
        if (!$this->strategy->canViewTicket($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        while (!($messages = $this->getDi()->helpdeskMessageTable->selectObjects("SELECT * " .
            "FROM ?_helpdesk_message WHERE ticket_id=? AND message_id>? ORDER BY message_id DESC",
                $ticket->pk(), $message->pk()
            ))) {
            sleep(2);
        }

        $this->strategy->onViewTicket($ticket);
        $out = '';
        $message_id = null;
        $this->view->strategy = $this->strategy;
        $this->view->ticket = $ticket;
        foreach ($messages as $message) {
            $message_id = $message_id ?: $message->pk();
            $this->view->message = $message;
            $out .= $this->view->render($this->strategy->getTemplatePath() . '/_message.phtml');
        }
        $url = json_encode($this->strategy->newUrl(). '?' . http_build_query([
            'id' => $this->getDi()->security->obfuscate($message_id)
            ]));
        $html = json_encode($out);
        echo <<<CUT
jQuery(function($){
  \$html = jQuery($html);
  jQuery('.am-helpdesk-ticket').after(\$html);
  \$html.fadeTo('slow', 0.1).fadeTo('slow', 1.0);
  amHelpdeskUpdate($url);
});
CUT;
        exit;
    }

    public function fileAction()
    {
        $message = $this->getDi()->helpdeskMessageTable->load($this->getDi()->security->reveal($this->getParam('message_id')));
        if (!$this->strategy->canViewMessage($message)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $upload = $this->getDi()->uploadTable->load($this->getDi()->security->reveal($this->getParam('id')));

        if (!in_array($upload->pk(), $message->getAttachments())) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        if (!in_array($upload->prefix, [
                Bootstrap_Helpdesk::ATTACHMENT_UPLOAD_PREFIX,
                Bootstrap_Helpdesk::ADMIN_ATTACHMENT_UPLOAD_PREFIX
        ])) {

            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $this->_helper->sendFile($upload->getFullPath(), $upload->mime, [
            'filename' => $upload->getName()
        ]);
    }

    public function surrenderAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canEditOwner($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        if ($ticket->owner_id == $this->strategy->getIdentity()) {
            $ticket->owner_id = null;
            $ticket->save();
        }

        $this->redirectTicket($ticket);
    }

    public function takeAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canEditOwner($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $id = $this->getParam('id');
        $id = $id ? $this->getDi()->security->reveal($id) : $this->strategy->getIdentity();

        $ticket->owner_id = $id;
        $ticket->save();

        if (($this->strategy->getIdentity() != $id) &&
            $this->getModule()->getConfig('notify_assign')) {

            $admin = $this->getDi()->adminTable->load($id);

            $customFields = $this->getDi()->helpdeskTicketTable->customFields()->getAll();
            $fields = [];
            foreach ($customFields as $fn => $field) {
                if ($out = $this->getDi()->view->getTicketField($ticket, $fn)) {
                    $fields[] = [
                        'title' => $field->title,
                        'value' => $out
                    ];
                }
            }

            $fields_text = array_reduce($fields, function($carry, $fn) {
                return $carry . sprintf("%s: %s\n", $fn['title'], $fn['value']);
            }, '');

            $fields_html = array_reduce($fields, function($carry, $fn) {
                return $carry . sprintf("%s: %s<br />", $fn['title'], $fn['value']);
            }, '');

            $et = Am_Mail_Template::load('helpdesk.notify_assign');
            $et->setFields($fields);
            $et->setFields_text($fields_text);
            $et->setFields_html($fields_html);
            $et->setUser($ticket->getUser());
            $et->setTicket($ticket);
            $et->setAdmin($admin);
            $et->setUrl($this->getDi()->surl("helpdesk/admin/ticket/$ticket->ticket_mask", false));
            $et->send($admin->email);
        }

        $this->redirectTicket($ticket);
    }

    public function editcategoryAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canEditCategory($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $ticket->category_id = $this->getDi()->security->reveal($this->getParam('id'));
        $ticket->save();

        $this->redirectTicket($ticket);
    }

    public function lockAction()
    {
        if (defined('AM_ADMIN') && AM_ADMIN) {
            $ticketIdentity = $this->getParam('ticket');
            /* @var $ticket HelpdeskTicket */
            $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);
            $ticket->lock($this->getDi()->authAdmin->getUser());
        }
    }

    public function checklockAction()
    {
        if (defined('AM_ADMIN') && AM_ADMIN) {
            $d = $this->getDi()->db->select("SELECT ticket_mask, lock_admin FROM ?_helpdesk_ticket WHERE lock_until IS NOT NULL AND lock_until > ?", sqlTime('now'));
            $this->getResponse()->ajaxResponse($d);
        }
    }

    public function viewAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canViewTicket($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }
        $this->strategy->onViewTicket($ticket);

        $grid = new Am_Helpdesk_Grid_Admin($this->getRequest(), $this->getView());
        $grid->getDataSource()->getDataSourceQuery()->addWhere('m.user_id=?d', $ticket->user_id);
        $grid->actionsClear();
        $grid->removeField('m_login');
        $grid->removeField('avatar');
        $grid->removeField('gravatar');

        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, function(& $ret, $record) use ($ticket) {
            if ($record->pk() == $ticket->pk())
                $ret['class'] = isset($ret['class']) ? $ret['class'] . ' emphase' : 'emphase';
        });
        if ($this->getDi()->helpdeskTicketTable->countByUserId($ticket->user_id) < 10) {
            $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_CONTENT, function(&$out, $g){
               $out = preg_replace('/<!-- start am-filter-wrap -->.*?<!-- end am-filter-wrap -->/is', '', $out);
            });
            $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TITLE, function(&$out, $g){
               $out = '';
            });
        }
        $grid->isAjax($this->isAjax() && $this->isGridRequest('_admin'));

        if ($grid->isAjax()) {
            echo $grid->run();
            return;
        }

        $category = $ticket->getCategory();

        $totalPaid = $this->getDi()->db->selectCell(<<<CUT
            SELECT ROUND(SUM(amount/base_currency_multi),2)
                FROM ?_invoice_payment
                WHERE user_id=?
CUT
            , $ticket->user_id);
        $totalRefund = $this->getDi()->db->selectCell(<<<CUT
            SELECT ROUND(SUM(amount/base_currency_multi),2)
                FROM ?_invoice_refund
                WHERE user_id=?
CUT
            , $ticket->user_id);

        $t = $this->getView();
        $t->assign('totalPaid', $totalPaid);
        $t->assign('totalRefund', $totalRefund);
        $t->assign('ticket', $ticket);
        $t->assign('category', $category);
        $t->assign('user', $ticket->getUser());
        $t->assign('strategy', $this->strategy);
        $t->assign('historyGrid', $grid->run()->getBody());
        $t->assign('userTotalTickets', $this->getDi()->helpdeskTicketTable->countByUserId($ticket->user_id));
        $t->assign('customFields', $this->getDi()->helpdeskTicketTable->customFields()->getAll());
        $content = $t->render($this->strategy->getTemplatePath() . '/ticket.phtml');

        if ($this->_request->isXmlHttpRequest()) {
            header('Content-type: text/html; charset=UTF-8');
            echo $content;
        } else {
            $this->view->assign('content', $content);
            $this->view->display($this->strategy->getTemplatePath() . '/index.phtml');
        }
    }

    public function replyAction()
    {
        $ticket = $this->getDi()->helpdeskTicketTable->load($this->getParam('ticket'));

        if (!$this->strategy->canEditTicket($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $message = null;
        $type = $this->getParam('type', 'message');
        if ($message_id = $this->getDi()->security->reveal($this->getParam('message_id'))) {
            $message = $this->getDi()->helpdeskMessageTable->load($message_id);

            switch ($type) {
                case 'message' :
                    if (!$this->strategy->canViewMessage($message)) {
                        throw new Am_Exception_AccessDenied(___('Access Denied'));
                    }
                    break;
                case 'comment' :
                    if (!$this->strategy->canEditMessage($message)) {
                        throw new Am_Exception_AccessDenied(___('Access Denied'));
                    }
                    break;
                default :
                    throw new Am_Exception_InputError('Unknown message type : ' . $type);
            }
        }

        /* @var $replyForm Am_Form */
        $replyForm = $this->getReplyForm(
                $this->getParam('ticket'),
                $message,
                $type
        );

        if ($this->isPost()) {
            $replyForm->setDataSources([$this->getRequest()]);
            $values = $replyForm->getValue();
            $message_id = $this->getParam('message_id', null);
            $message_id = $message_id ? $this->getDi()->security->reveal($message_id) : $message_id;
            $this->reply($ticket, $message_id, $values);
            $this->getRequest()->set('ticket', $ticket->ticket_mask);
            if ($this->_request->isXmlHttpRequest()) {
                return;
            } else {
                return $this->redirectTicket($ticket);
            }
        }

        $content = (string) $replyForm;

        if ($this->_request->isXmlHttpRequest()) {
            header('Content-type: text/html; charset=UTF-8');
            echo $content;
        } else {
            $this->view->assign('content', $content);
            $this->view->display($this->strategy->getTemplatePath() . '/index.phtml');
        }
    }

    public function changestatusAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canEditTicket($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        if (
            $this->getParam('status') == HelpdeskTicket::STATUS_CLOSED
            && !$this->strategy->canCloseTicket($ticket)
        ) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $ticket->status = $this->getParam('status');
        $ticket->save();
        return $this->redirectTicket($ticket);
    }

    public function displaysnippetsAction()
    {
        if (!$this->strategy->canUseSnippets()) {
            throw new Am_Exception_AccessDenied();
        }

        $ticket = $this->getDi()->helpdeskTicketTable->load($this->getParam('ticket'), false);
        $tpl = null;
        if ($ticket) {
            $tpl = new Am_SimpleTemplate;
            $tpl->assign('user', $ticket->getUser());
        }

        $ds = new Am_Query($this->getDi()->helpdeskSnippetTable);
        $ds->setOrder('sort_order');
        $grid = new Am_Grid_Editable('_snippet', ___('Snippets'), $ds, $this->getRequest(), $this->view, $this->getDi());
        if ($this->getRequest()->getParam('f') == 'menu') {
            $grid->addField('title', ___('Title'));
        } else {
            $grid->addField('title', ___('Title'))->setRenderFunction(
                function ($record, $fieldName, $grid) use ($tpl) {
                    $c = $record->content;
                    if ($tpl) {
                        $c = $tpl->render($c);
                    }
                    return sprintf('<td><a href="javascript:;" class="local am-helpdesk-insert-snippet" data-snippet-content="%s">%s</a></td>',
                        Am_Html::escape($c),
                        Am_Html::escape($record->title));
                });
        }
        $grid->addField('category', ___('Category'));
        $grid->setForm([$this, 'createForm']);
        $grid->actionGet('insert')->setTarget(null);
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_ID);
        $grid->actionAdd(new Am_Grid_Action_Sort_HelpdeskSnippet);

        if ($cOptions = $this->getDi()->helpdeskSnippetTable->getCategories()) {
            $grid->setFilter(new Am_Grid_Filter_HelpdeskSnippet($cOptions));
        }
        $grid->setFormValueCallback('category', null, function($val) {return $val ?: null;});

        $grid->isAjax($this->isAjax() && $this->isGridRequest('_snippet'));
        echo $grid->run();
    }

    public function displayfaqAction()
    {
        if (!$this->strategy->canUseFaq()) {
            throw new Am_Exception_AccessDenied();
        }

        $ds = new Am_Query($this->getDi()->helpdeskFaqTable);
        $grid = new Am_Grid_ReadOnly('_helpdesk_faq', ___('FAQ'), $ds, $this->getRequest(), $this->view, $this->getDi());
        $grid->addField('title', ___('Title'))->setRenderFunction([$this, 'renderFaqTitle']);
        $grid->addField('category', ___('Category'));
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_ID);

        $grid->isAjax($this->isAjax() && $this->isGridRequest('_helpdesk_faq'));
        echo $grid->run();
    }

    public function displaymarkdownAction()
    {
        if (!$this->strategy->canUseMarkdown()) {
            throw new Am_Exception_AccessDenied();
        }

        echo <<<CUT
<p>Subset of Markdown is supported</p>

<h2>Headers</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre class="am-code" style="width:50%;">
<strong style="background: grey"># </strong>Header 1
<strong style="background: grey">## </strong>Header 2
<strong style="background: grey">### </strong>Header 3
<strong style="background: grey">#### </strong>Header 4
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
<h1>Header 1</h1>
<h2>Header 2</h2>
<h3>Header 3</h3>
<h4>Header 4</h4>
</div>
</div>
<h2>List</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre class="am-code" style="width:50%;">
<strong style="background: grey">* </strong>Item 1
<strong style="background: grey">* </strong>Item 2
<strong style="background: grey">* </strong>Item 3
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
<ul>
 <li>Item 1</li>
 <li>Item 2</li>
 <li>Item 3</li>
</ul>
</div>
</div>
<h2>Horizontal Rule</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre class="am-code" style="width:50%;">
<strong style="background: grey">---</strong>
or
<strong style="background: grey">***</strong>
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
<hr />
or
<hr />
</div>
</div>
<h2>Bold Text</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre  class="am-code" style="width:50%;">
Almost <strong style="background: grey">**</strong>before we knew it<strong style="background: grey">**</strong>, we had left the ground.
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
Almost <strong>before we knew it</strong>, we had left the ground.
</div>
</div>
<h2>Italic Text</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre  class="am-code" style="width:50%;">
Almost <strong style="background: grey">*</strong>before we knew it<strong style="background: grey">*</strong>, we had left the ground.
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
Almost <em>before we knew it</em>, we had left the ground.
</div>
</div>
<h2>Hyperlinks</h2>
<div style="display: flex; margin-bottom: 1em;">
<pre  class="am-code" style="width:50%;">
https://example.com/example
</pre>
<div style="box-sizing: border-box; width:50%; padding: 1em; background: whitesmoke">
<a href="https://example.com/example">https://example.com/example</a>
</div>
</div>
CUT;

    }

    public function displayassignAction()
    {
        if (!$this->strategy->canEditOwner(null)) {
            throw new Am_Exception_AccessDenied();
        }

        $ds = new Am_Query($this->getDi()->adminTable);
        $grid = new Am_Grid_ReadOnly('_helpdesk_assign', ___('Admins'), $ds, $this->getRequest(), $this->view, $this->getDi());
        $grid->addField('login', ___('Name'))->setRenderFunction([$this, 'renderAssignTitle']);
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_ID);

        $grid->isAjax($this->isAjax() && $this->isGridRequest('_helpdesk_assign'));
        echo $grid->run();
    }

    public function displayeditcategoryAction()
    {
        if (!$this->strategy->canEditCategory(null)) {
            throw new Am_Exception_AccessDenied();
        }

        $ds = new Am_Query($this->getDi()->helpdeskCategoryTable);
        $grid = new Am_Grid_ReadOnly('_helpdesk_category', ___('Categories'), $ds, $this->getRequest(), $this->view, $this->getDi());
        $grid->addField('title', ___('Title'))->setRenderFunction([$this, 'renderEditCategoryTitle']);
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_ID);

        $grid->isAjax($this->isAjax() && $this->isGridRequest('_helpdesk_category'));
        echo $grid->run();
    }

    public function editwatcherAction()
    {
        $ticketIdentity = $this->getParam('ticket');
        $ticket = $this->getDi()->helpdeskTicketTable->load($ticketIdentity);

        if (!$this->strategy->canEditWatcher($ticket)) {
            throw new Am_Exception_AccessDenied();
        }

        $options = [];
        foreach (Am_Di::getInstance()->adminTable->findBy() as $admin) {
            $options[$admin->pk()] = sprintf('%s (%s %s)', $admin->login, $admin->name_f, $admin->name_l);
        }

        $form = new Am_Form_Admin;
        $form->addMagicSelect('watcher_ids')
            ->setLabel(___("Watchers\n" .
                'notify the following admins ' .
                'about new messages in this ticket'))
            ->loadOptions($options);

        $form->addHidden('ticket')
            ->setValue($ticketIdentity);
        $form->addSaveButton();

        if (!$form->isSubmitted()) {
            $form->setDataSources([
                new HTML_QuickForm2_DataSource_Array([
                'watcher_ids' => explode(',', $ticket->watcher_ids)
                ])
            ]);
        }

        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            $ticket->watcher_ids = implode(",", $vars['watcher_ids']);
            $ticket->save();
            if (!$this->_request->isXmlHttpRequest()) {
                return $this->redirectTicket($ticket);
            }
        }
        echo $form;
    }

    public function renderFaqTitle($record, $fieldName, $grid)
    {
        return sprintf('<td><a href="javascript:;" class="local am-helpdesk-insert-faq" data-faq-content="%s">%s</a></td>',
            $this->getDi()->rurl('helpdesk/faq/i/'.urlencode($record->title)),
            Am_Html::escape($record->title));
    }

    public function renderAssignTitle($record, $fieldName, $grid)
    {
        return sprintf('<td><a href="javascript:;" class="link am-helpdesk-assign" data-admin_id="%s">%s</a></td>',
            $this->getDi()->app->obfuscate($record->pk()),
            Am_Html::escape(sprintf('%s (%s %s)', $record->login, $record->name_f, $record->name_l)));
    }

    public function renderEditCategoryTitle($record, $fieldName, $grid)
    {
        return sprintf('<td><a href="javascript:;" class="link am-helpdesk-edit-category" data-category_id="%s">%s</a></td>',
            $this->getDi()->app->obfuscate($record->pk()),
            Am_Html::escape($record->title));
    }

    public function createForm()
    {
        $form = new Am_Form_Admin();
        $form->addText('title', ['class' => 'am-el-wide'])
            ->setLabel(___('Title'))
            ->addRule('required');

        $form->addTextarea('content', ['class' => 'am-el-wide', 'rows' => 10])
            ->setLabel(___('Content'))
            ->addRule('required');

        $catoptions = array_filter($this->getDi()->helpdeskSnippetTable->getCategories());
        $catoptions = array_merge(['' => ___('-- Without A Category --')], $catoptions);

        $form->addSelect('category', [], ['intrinsic_validation' => false, 'options' => $catoptions])
            ->setLabel('Display Category');

        $label_add_category = ___('add category');
        $label_title_error = ___('Enter title for your new category');
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function($){
    jQuery("select[name='category']").prop("id", "category").after(jQuery("<span> <a href='javascript:;' id='add-category' class='local'>$label_add_category</a></span>"));

    jQuery("select[name='category']").change(function(){
        jQuery(this).toggle(jQuery(this).find('option').length > 1);
    }).change();

    jQuery("a#add-category").click(function(){
        var ret = prompt("$label_title_error", "");
        if (!ret) return;
        var \$sel = jQuery("select#category").append(
            jQuery("<option></option>").val(ret).html(ret));
        \$sel.val(ret).change();
    });
})
CUT
            );

        return $form;
    }

    protected function redirectTicket($ticket)
    {
        $url = $this->strategy->ticketUrl($ticket);
        $this->_response->redirectLocation($url);
    }

    protected function editMessage($message_id, $value)
    {
        $message = $this->getDi()->helpdeskMessageTable->load($message_id);
        if (!$this->strategy->canEditMessage($message)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }
        $message->content = $value['content'];
        $message->save();
    }

    protected function addMessage($ticket, $value)
    {
        $message = $this->getDi()->helpdeskMessageRecord;
        $message->content = $value['content'];
        $message->ticket_id = $ticket->ticket_id;
        $message->type = $value['type'];
        $message->setAttachments($value['attachments']);
        $message = $this->strategy->fillUpMessageIdentity($message);
        $message->save();

        $this->strategy->onAfterInsertMessage($message, $ticket);

        $ticket->status = $this->strategy->getTicketStatusAfterReply($message);
        $ticket->updated = $this->getDi()->sqlDateTime;
        $ticket->save();
        if (isset($value['_close']) && $value['_close'] && $this->strategy->canEditTicket($ticket)) {
            $ticket->status = HelpdeskTicket::STATUS_CLOSED;
            $ticket->save();
        }
    }

    protected function reply($ticket, $message_id, $values)
    {
        if ($message_id) {
            $this->editMessage($message_id, $values);
        } else {
            $this->addMessage($ticket, $values);
        }
    }

    protected function getReplyForm($ticket, $message = null, $type = 'message')
    {
        $content = '';
        $form = $this->strategy->createForm();

        if (!is_null($message) && $type == 'message') {
            if (!$this->getModule()->getConfig('does_not_quote_in_reply')) {
                $content = explode("\n", $message->content);
                $content = array_map(function($v) { return '>'.$v; }, $content);
                $content = "\n\n" . implode("\n", $content);
            }
        } elseif (!is_null($message) && $type == 'comment') {
            $content = $message->content;
            $form->addHidden('message_id')
                ->setValue($this->getDi()->app->obfuscate($message->message_id));
        }

        if ($type == 'message' &&
                defined('AM_ADMIN') &&
                $this->getModule()->getConfig('add_signature')) {
            $content = "\n\n" . $this->expandPlaceholders($this->getModule()->getConfig('signature')) . $content;
        }

        $form->addHidden('type')
            ->setValue($type);

        $row_num = min(15, count(explode("\n", $content))+1);
        $form->addTextarea('content', [
            'rows' => $row_num,
            'class' => 'am-no-label am-el-wide',
            'placeholder' => $type == 'comment' ? ___('Write your comment...') : ___('Write your reply...')
        ])
            ->setValue($content)
            ->addRule('required');

        $form->setAction($this->strategy->assembleUrl([
                'page_id' => 'view',
                'action' => 'reply',
                'ticket' => $ticket,
                'type' => $type
        ], 'inside-pages', false));

        if ($type != 'comment') {
            $this->strategy->addUpload($form);
        }

        $ticketObj = $ticket instanceof HelpdeskTicket ? $ticket : $this->getDi()->helpdeskTicketTable->findFirstByTicketMask($ticket);
        if (defined('AM_ADMIN')
            && $this->strategy->canCloseTicket($ticketObj)
            && $type != 'comment') {
            $form->addAdvCheckbox('_close', null, ['content' => ___('Close This Ticket After Response')]);
        }

        $btns = $form->addGroup();
        $btns->setSeparator(' ');

        $btns->addSubmit('submit', ['value' => $type == 'comment' ? ___('Save Comment') : ___('Send Message')]);
        $btns->addInputButton('discard', ['value' => ___('Discard')]);

        return $form;
    }

    protected function expandPlaceholders($text)
    {
        $admin = $this->getDi()->authAdmin->getUser();

        return str_replace(
            ['%name_f%', '%name_l%'],
            [$admin->name_f, $admin->name_l],
            $text
        );
    }
}