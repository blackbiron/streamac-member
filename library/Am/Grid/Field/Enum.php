<?php

class Am_Grid_Field_Enum extends Am_Grid_Field
{
    protected $translation = [];

    public function __construct($field, $title, $sortable = false, $align = null, $renderFunc = null, $width = null)
    {
        parent::__construct($field, $title, $sortable, $align, $renderFunc, $width);
    }

    public function render($obj, $controller)
    {
        $v = isset($obj->{$this->field}) ? $obj->{$this->field} : null;
        if (array_key_exists($v, $this->translation))
        {
            $v = $this->translation[$v];
        } else {
            $v = htmlentities($v, null, 'UTF-8');
        }
        $ret = "<td>$v</td>";
        $this->applyDecorators('render', [& $ret, $obj, $controller]);
        return $ret;
    }

    public function translate($k, $v)
    {
        $this->translation[$k] = $v;
        return $this;
    }

    public function setTranslations(array $translations)
    {
        $this->translation = $translations;
        return $this;
    }
}