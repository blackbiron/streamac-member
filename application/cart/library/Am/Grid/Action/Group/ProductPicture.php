<?php

class Am_Grid_Action_Group_ProductPicture extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;

    public function __construct()
    {
        parent::__construct('product_picture', ___('Set Product Picture'));
        $this->setTarget('_top');
    }

    public function handleRecord($id, $product)
    {
        $data = $this->getForm()->getValue();

        if (empty($data['img']))
        {
            $product->img = null;
            $product->img_path = null;
            $product->img_cart_path = null;
            $product->img_detail_path = null;
            $product->img_orig_path = null;
            $product->update();
            return;
        }

        $product->img = $data['img'];
        $this->getDi()->modules->loadget('cart')->resize($product);
    }

    public function getForm()
    {
        if (!$this->form) {
            $this->form = new Am_Form_Admin;

            $this->form->addUpload('img', null, ['prefix' => Bootstrap_Cart::UPLOAD_PREFIX])
                ->setLabel(___("Product Picture\n" .
                    'for shopping cart pages. Only jpg, png and gif formats allowed'))
                ->setAllowedMimeTypes([
                    'image/png', 'image/jpeg', 'image/gif', 'image/webp'
                ]);
            $this->form->addSaveButton();
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
            $this->form->addHidden($k)->setvalue($v);

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
            return parent::run();
        }
    }

    function getDi()
    {
        return Am_DI::getInstance();
    }
}