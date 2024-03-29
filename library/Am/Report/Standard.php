<?php

class_exists('Am_Report', true);

foreach (am_glob(__DIR__ . '/*.php') as $fn)
    include_once $fn;

abstract class Am_Report_Standard {} // trigger auto loading

class Am_Report_Income extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Income Report - payments minus refunds');
        $this->description = "";
    }

    // we have a VERY complex query here, so we will run it directly
    // without using Am_Query
    // Simulate FULL OUTER JOIN - not implemented in MYSQL
    // Usually it is better to avoid it!
    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprb = $this->quantity->getSqlExpr('r.dattm');
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT point, SUM(amt) as amt FROM (
            SELECT $expra AS point, ROUND(IFNULL(SUM(p.amount/p.base_currency_multi),0),2) AS amt
                FROM ?_invoice_payment p
                WHERE p.dattm BETWEEN ? AND ?
                GROUP BY $expra
            UNION ALL
            SELECT $exprb AS point, ROUND(SUM(-ABS(r.amount)/r.base_currency_multi),2) AS amt
                FROM ?_invoice_refund r
                WHERE
                r.dattm BETWEEN ? AND ?
                GROUP BY $exprb
            ) AS t GROUP BY point
        ", $this->start, $this->stop, $this->start, $this->stop
        );
    }

    function getLines()
    {
        return [new Am_Report_Line("amt", ___('Payments Amount') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])];
    }
}

class Am_Report_Tax extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Tax Report');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->addField('ROUND(SUM(tax/base_currency_multi), 2)', 'tax');

        return $q;
    }

    function getLines()
    {
       return [new Am_Report_Line('tax', ___('Tax Amount') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])];
    }
}

class Am_Report_TaxCountry extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Tax by Customer Country');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->leftJoin('?_user', 'u', 'p.user_id=u.user_id');
        foreach ($this->getCountries() as $country) {
            $q->addField("ROUND(SUM(IF(country='$country', tax/base_currency_multi, 0)), 2)", 'tax_' . $country);
        }

        return $q;
    }

    function getLines()
    {
        $ret = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->getCountries() as $country) {
            $ret[] = new Am_Report_Line('tax_' . $country, sprintf('%s (%s), %s', $country_name[$country], $country, Am_Currency::getDefault()), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }

    function getCountries()
    {
        return $this->getDi()->db->selectCol('SELECT DISTINCT country FROM ?_user WHERE country<>?', '');
    }
}

class Am_Report_RebillNum extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Number of Rebills');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->leftJoin('?_invoice', 'i', 'i.invoice_id=p.invoice_id');
        $q->addWhere('DATE(p.dattm) > DATE(i.tm_started)');
        $q->clearFields();
        $q->addField('COUNT(invoice_payment_id)', 'cnt');

        return $q;
    }

    function getLines()
    {
        return [new Am_Report_Line('cnt', ___('Number of Rebills'))];
    }
}

class Am_Report_RebillAmount extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Amount of Rebills');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->leftJoin('?_invoice', 'i', 'i.invoice_id=p.invoice_id');
        $q->addWhere('DATE(p.dattm) > DATE(i.tm_started)');
        $q->clearFields();
        $q->addField('ROUND(SUM(p.amount/p.base_currency_multi), 2)', 'amt');

        return $q;
    }

    function getLines()
    {
        return [new Am_Report_Line('amt', ___('Amount of Rebills') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])];
    }
}

class Am_Report_PaymentVsRefund extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments vs Refunds');
        $this->description = "";
    }

    // we have a VERY complex query here, so we will run it directly
    // without using Am_Query
    // Simulate FULL OUTER JOIN - not implemened in MYSQL
    // Usually it is better to avoid it!
    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprb = $this->quantity->getSqlExpr('r.dattm');
        $exprc = $this->quantity->getSqlExpr('c.dattm');
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT point, SUM(pmt) as pmt, SUM(rfd) as rfd, SUM(chrg) as chrg, SUM(pmt-rfd) AS amt FROM (
            SELECT $expra AS point, ROUND(IFNULL(SUM(p.amount/p.base_currency_multi),0),2) AS pmt, 0 AS rfd, 0 AS chrg
                FROM ?_invoice_payment p
                WHERE p.dattm BETWEEN ? AND ?
                GROUP BY $expra
            UNION ALL
            SELECT $exprb AS point, 0 AS pmt, ROUND(SUM(ABS(r.amount)/r.base_currency_multi),2) AS rfd, 0 AS chrg
                FROM ?_invoice_refund r
                WHERE
                r.dattm BETWEEN ? AND ? AND refund_type<>1
                GROUP BY $exprb
            UNION ALL
            SELECT $exprc AS point, 0 AS pmt, 0 AS rfd, ROUND(SUM(ABS(c.amount)/c.base_currency_multi),2) AS chrg
                FROM ?_invoice_refund c
                WHERE
                c.dattm BETWEEN ? AND ? AND refund_type=1
                GROUP BY $exprc

            ) AS t GROUP BY point
        ", $this->start, $this->stop, $this->start, $this->stop, $this->start, $this->stop
        );
    }

    function getLines()
    {
        return [
            new Am_Report_Line("amt", ___('Income') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render']),
            new Am_Report_Line("pmt", ___('Payment') . ', ' . Am_Currency::getDefault(), '#488f37', ['Am_Currency', 'render']),
            new Am_Report_Line("rfd", ___('Refund') . ', ' . Am_Currency::getDefault(), '#BA2727', ['Am_Currency', 'render']),
            new Am_Report_Line("chrg", ___('Chargeback') . ', ' . Am_Currency::getDefault(), '#990000', ['Am_Currency', 'render']),
        ];
    }
}

class Am_Report_Paysystems extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by payment system breakdown');
        $this->description = "";
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();

        foreach ($this->getPaysystems() as $k => $ps) {
            $ps = $q->escape($ps);
            $q
                ->addField("ROUND(SUM(IF(p.paysys_id=$ps, p.amount/p.base_currency_multi, 0)),2)\n", 'amt_' . $k);
        }
        return $q;
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('paysys', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Payment Systems\nkeep empty to report all"))
            ->loadOptions($this->getDi()->paysystemList->getOptions());
    }

    function getPaysystems()
    {
        $vars = $this->form->getValue();

        static $cache;
        if (!$cache) {
            $cache = $this->getDi()->db->selectCol(
                "SELECT DISTINCT paysys_id FROM ?_invoice_payment WHERE 1 {AND paysys_id IN (?a)}",
                $vars['paysys'] ?: DBSIMPLE_SKIP
            );
        }
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getPaysystems() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_PaymentBySignupForm extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by signup form breakdown');
        $this->description = "";
    }

    public function getPointField()
    {
        return 'i.tm_started';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceTable, 'i');
        $q->clearFields();

        foreach ($this->getForms() as $k => $title) {
            $form = $q->escape($k);
            $q
                ->addField("ROUND(SUM(IF(i.saved_form_id=$form, i.first_total/i.base_currency_multi, 0)),2)\n", 'amt_' . $k);
        }
        return $q;
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('form', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Signup Forms\nkeep empty to report all"))
            ->loadOptions($this->getDi()->savedFormTable->getOptions(SavedForm::T_SIGNUP));
    }

    function getForms()
    {
        $vars = $this->form->getValue();

        static $cache;
        if (!$cache) {
            $t = $this->getDi()->db->escape(SavedForm::T_SIGNUP);
            $cache = $this->getDi()->db->selectCol(
                "SELECT title, saved_form_id AS ARRAY_KEY FROM ?_saved_form WHERE type = $t  {AND saved_form_id IN (?a)}",
                $vars['form'] ?: DBSIMPLE_SKIP
            );
        }
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getForms() as $k => $title) {
            $ret[] = new Am_Report_Line('amt_' . $k, $title, null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_PaysystemConversionRate extends Am_Report_Abstract
{
    protected $added_start, $added_stop;

    public function __construct()
    {
        $this->title = ___('Conversion rate by payment system breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);

        $gr = $form->addGroup()
            ->setLabel(___("Period\n" .
                'Take into Account only invoices in defined period, '.
                'keep empty to use all invoices'));

        $gr->setSeparator(' ');
        $gr->addDate('date_start', ['placeholder' => ___('Begin Date')]);
        $gr->addHtml()
            ->setHtml('&mdash;');
        $gr->addDate('date_end', ['placeholder' => ___('End Date')]);

    }

    protected function processConfigForm(array $values)
    {
        $this->added_start = sqlTime(!empty($values['date_start']) ? $values['date_start'] : '1927-01-01');
        $this->added_stop = sqlTime(!empty($values['date_end']) ? $values['date_end'] : 'now');

        $quant = new Am_Report_Quant_Enum($this->getPaysystems());
        $this->setQuantity($quant);
    }

    public function getPointFieldType()
    {
        return Am_Report_Abstract::POINT_VALUE;
    }

    public function getPointField()
    {
        return 'paysys_id';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceTable);
        $q->clearFields();
        $q->addWhere('tm_added BETWEEN ? AND ?', $this->added_start, $this->added_stop);
        $q->addWhere('paysys_id IN (?a)', array_keys($this->getPaysystems()));
        $q->addField("ROUND(100 * SUM(IF(tm_started IS NOT NULL, 1, 0))/COUNT(*), 2)", 'rate');
        return $q;
    }

    protected function getPaysystems()
    {
        static $cache;
        if (!$cache) {
            $cache = $this->getDi()->db->selectCol("SELECT DISTINCT paysys_id FROM ?_invoice_payment");
            $cache = array_combine($cache, $cache);
        }
        return $cache;
    }

    function getLines()
    {
        return [new Am_Report_Line('rate', ___('Conversion Rate'), null, function($v) {return sprintf('%0.2f%%', $v);})];
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result, false)
        ];
    }
}

class Am_Report_RefundRate extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Refunds Rate');
        $this->description = "";
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->addField("ROUND(100 * SUM(p.refund_amount/p.base_currency_multi)/SUM(p.amount/p.base_currency_multi),2)", 'rate');
        return $q;
    }

    function getLines()
    {
        return [new Am_Report_Line('rate', ___('Rate'), null, function($v) {return sprintf("%0.2f%%", $v);})];
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result, false)
        ];
    }
}

class Am_Report_RefundPaysystems extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Refunds by payment system breakdown');
        $this->description = "";
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceRefundTable, 'p');
        $q->clearFields();

        foreach ($this->getPaysystems() as $k => $ps) {
            $ps = $q->escape($ps);
            $q
                ->addField("ROUND(SUM(IF(p.paysys_id=$ps, p.amount/p.base_currency_multi, 0)),2)\n", 'amt_' . $k);
        }
        return $q;
    }

    function getPaysystems()
    {
        static $cache;
        if (!$cache)
            $cache = $this->getDi()->db->selectCol("SELECT DISTINCT paysys_id FROM ?_invoice_refund");
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getPaysystems() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_Products extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by products breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');

        $fields = [];
        foreach ($this->getProducts() as $k => $v) {
            $fields[] = "ROUND(
                            SUM(
                                    IFNULL((
                                        SELECT p.amount * LEAST(1, IF(p.is_first, ii.first_total/p.invoice_total, ii.second_total/p.invoice_total))
                                        FROM ?_invoice_item ii WHERE p.invoice_id=ii.invoice_id AND item_id=$k LIMIT 1
                                    ), 0)
                                ), 2) AS amt_$k";
        }

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_invoice_payment_report_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_invoice_payment_report_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            dattm DATETIME not null,
            invoice_id int not null,
            is_first smallint,
            amount decimal(12,2),
            invoice_total decimal(12,2),
            PRIMARY KEY (id)
        )
        ");
        $db->query("
            INSERT INTO ?_invoice_payment_report_tmp (dattm, invoice_id, is_first, amount, invoice_total)
            SELECT p.dattm, p.invoice_id
                ,i.first_total > 0 &&
                    NOT EXISTS (SELECT * FROM ?_invoice_payment pp
                        WHERE pp.invoice_id=p.invoice_id AND pp.invoice_payment_id < p.invoice_payment_id)
                    AS is_first
                ,p.amount / p.base_currency_multi
                ,(SELECT(IF(is_first, i.first_total, i.second_total))) AS invoice_total
            FROM ?_invoice_payment p
                LEFT JOIN ?_invoice i USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            HAVING invoice_total > 0
        ", $this->start, $this->stop);

        $fields = "\n," . implode("\n,", $fields);
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                $expra as point
                $fields
            FROM ?_invoice_payment_report_tmp p
            GROUP BY $expra
            ", $this->start, $this->stop);
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
                DISTINCT product_id as ARRAY_KEY, title
                FROM ?_product
                {WHERE product_id IN (?a)}
                ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_RefundProducts extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Refunds by products breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'r.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceRefundTable, 'r');
        $q->clearFields();
        $q->leftJoin('?_invoice_item', 'ii', 'r.invoice_id=ii.invoice_id')
            ->addWhere('ii.item_type=?', 'product');

        foreach ($this->getProducts() as $k => $v) {
            $q->addField("ROUND(SUM(IF(ii.item_id=$k, r.amount/r.base_currency_multi, 0)),2)", 'amt_' . $k);
        }
        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
                DISTINCT product_id as ARRAY_KEY, title
                FROM ?_product
                {WHERE product_id IN (?a)}
                ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_ProductCategories extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by product categories breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('categories', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Product categories\nkeep empty to report all categories"))
            ->loadOptions($this->getDi()->productCategoryTable->getAdminSelectOptions());
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');

        $fields = [];
        foreach ($this->getCetegoryProducts($this->getCategories()) as $k => $v) {
            array_push($v, -1);
            array_walk($v, 'intval');
            $v = implode(',', $v);
            $fields[] = "ROUND(
                            SUM(
                                    IFNULL((
                                        SELECT SUM(p.amount * LEAST(1, IF(p.is_first, ii.first_total/p.invoice_total, ii.second_total/p.invoice_total)))
                                        FROM ?_invoice_item ii WHERE p.invoice_id=ii.invoice_id AND item_id IN ($v)
                                    ), 0)
                                ), 2) AS amt_$k";
        }

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_invoice_payment_report_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_invoice_payment_report_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            dattm DATETIME not null,
            invoice_id int not null,
            is_first smallint,
            amount decimal(12,2),
            invoice_total decimal(12,2),
            PRIMARY KEY (id)
        )
        ");
        $db->query("
            INSERT INTO ?_invoice_payment_report_tmp (dattm, invoice_id, is_first, amount, invoice_total)
            SELECT p.dattm, p.invoice_id
                ,i.first_total > 0 &&
                    NOT EXISTS (SELECT * FROM ?_invoice_payment pp
                        WHERE pp.invoice_id=p.invoice_id AND pp.invoice_payment_id < p.invoice_payment_id)
                    AS is_first
                ,p.amount / p.base_currency_multi
                ,(SELECT(IF(is_first, i.first_total, i.second_total))) AS invoice_total
            FROM ?_invoice_payment p
                LEFT JOIN ?_invoice i USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            HAVING invoice_total > 0
        ", $this->start, $this->stop);

        $fields = count($fields) ? "\n," . implode("\n,", $fields) : '';
        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                $expra as point
                $fields
            FROM ?_invoice_payment_report_tmp p
            GROUP BY $expra
            ", $this->start, $this->stop);
    }

    function getCetegoryProducts($categories)
    {
        $res = $this->getDi()->productCategoryTable->getCategoryProducts();
        foreach ($res as $k => $v)
            if (!array_key_exists($k, $categories))
                unset($res[$k]);

        return $res;
    }

    function getCategories()
    {
        $res = [];
        $options = $this->getDi()->productCategoryTable->getAdminSelectOptions();
        $vars = $this->form->getValue();
        if (!empty($vars['categories'])) {
            foreach ($vars['categories'] as $cat_id)
                $res[$cat_id] = $options[$cat_id];
        } else {
            $res = $options;
        }
        return $res;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getCategories() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_RefundCategories extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Refunds by product categories breakdown');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('categories', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Product categories\nkeep empty to report all categories"))
            ->loadOptions($this->getDi()->productCategoryTable->getAdminSelectOptions());
    }

    public function getPointField()
    {
        return 'r.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceRefundTable, 'r');
        $q->clearFields();
        $q->leftJoin('?_invoice_item', 'ii', 'r.invoice_id=ii.invoice_id')
            ->addWhere('ii.item_type=?', 'product');

        foreach ($this->getCetegoryProducts($this->getCategories()) as $k => $v) {
            array_push($v, -1);
            array_walk($v, 'intval');
            $v = implode(',', $v);
            $q->addField("ROUND(SUM(IF(ii.item_id IN($v), r.amount/r.base_currency_multi, 0)),2)", 'amt_' . $k);
        }
        return $q;
    }

    function getCetegoryProducts($categories)
    {
        $res = $this->getDi()->productCategoryTable->getCategoryProducts();
        foreach ($res as $k => $v)
            if (!array_key_exists($k, $categories))
                unset($res[$k]);

        return $res;
    }

    function getCategories()
    {
        $res = [];
        $options = $this->getDi()->productCategoryTable->getAdminSelectOptions();
        $vars = $this->form->getValue();
        if (!empty($vars['categories'])) {
            foreach ($vars['categories'] as $cat_id)
                $res[$cat_id] = $options[$cat_id];
        } else {
            $res = $options;
        }
        return $res;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getCategories() as $k => $ps) {
            $ret[] = new Am_Report_Line('amt_' . $k, ucfirst($ps), null, ['Am_Currency', 'render']);
        }
        return $ret;
    }
}

class Am_Report_InvoiceBySignupForm extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Invoices By Signup Form');
        $this->description = "";
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('saved_form_ids')
            ->loadOptions($this->getDi()->savedFormTable->getOptions(SavedForm::T_SIGNUP))
            ->setLabel(___("Signup Form\nkeep empty to report all"));
        $form->addAdvRadio('status')
            ->setLabel(___("Invoice Status"))
            ->loadOptions([
                'all' => ___('All'),
                'pending' => ___('Pending'),
                'completed' => ___('Completed'),
            ])->setValue('all');
    }

    protected function processConfigForm(array $values)
    {
        parent::processConfigForm($values);
        $this->status = $values['status'] ?? 'all';
    }

    public function getPointField()
    {
        return 'i.tm_added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceTable, 'i');
        $q->clearFields();

        switch ($this->status) {
            case 'pending' :
                $q->addWhere('tm_started IS NULL');
                break;
            case 'completed' :
                $q->addWhere('tm_started IS NOT NULL');
                break;
            case 'all' :
                //nop
                break;
        }

        foreach ($this->getSavedForms() as $id => $title) {
            $q->addField("SUM(IF(i.saved_form_id=$id, 1, 0))\n", 'f_' . $id);
        }
        return $q;
    }

    function getSavedForms()
    {
        $vars = $this->form->getValue();
        return $this->getDi()->db->selectCol(<<<CUT
                SELECT DISTINCT saved_form_id as ARRAY_KEY, title
                    FROM ?_saved_form
                    WHERE type = ?
                    {AND saved_form_id IN (?a)}
                    ORDER BY title
CUT
            , SavedForm::T_SIGNUP, !empty($vars['saved_form_ids']) ? (array) $vars['saved_form_ids'] : DBSIMPLE_SKIP
);
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getSavedForms() as $id => $title) {
            $ret[] = new Am_Report_Line('f_' . $id, $title);
        }
        return $ret;
    }
}

class Am_Report_NewVsExisting extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by New vs Existing members');
        $this->description = "";
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('p.dattm');
        $exprpp = $this->quantity->getSqlExpr('dattm');

        $this->stmt = $this->getDi()->db->queryResultOnly(<<<CUT
            SELECT
                $expra as point,
                ROUND(SUM(p.amount / p.base_currency_multi),2) as total,
                ROUND(SUM(IF($expra = tm, p.amount / p.base_currency_multi, 0)),2) as new,
                ROUND(SUM(IF($expra > tm, p.amount / p.base_currency_multi, 0)),2) as existing
            FROM ?_invoice_payment p
                LEFT JOIN (SELECT user_id AS uid, MIN($exprpp) AS tm FROM ?_invoice_payment GROUP BY user_id) AS pp 
                ON p.user_id=uid
            WHERE dattm BETWEEN ? AND ? AND amount > 0
            GROUP BY $expra
CUT
            , $this->start, $this->stop);
    }

    function getLines()
    {
        return [
            new Am_Report_Line('total', ___('Payments total'), null, ['Am_Currency', 'render']),
            new Am_Report_Line('existing', ___('Payments from existing customers'), null, ['Am_Currency', 'render']),
            new Am_Report_Line('new', ___('Payments from new customers'), null, ['Am_Currency', 'render']) // who did not pay earlier in the point period
        ];
    }
}

class Am_Report_SignupsCount extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of user signups');
        $this->description = ___('including pending records');
    }

    public function getPointField()
    {
        return 'u.added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->userTable, 'u');
        $q->clearFields();
        $q->addField('COUNT(user_id)', 'cnt');

        return $q;
    }

    function getLines()
    {
        $ret = [];
        $ret[] = new Am_Report_Line('cnt', ___('Count of signups'));
        return $ret;
    }
}

class Am_Report_PaymentCountry extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Payments by Customer Country');
    }

    public function getPointField()
    {
        return 'p.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoicePaymentTable, 'p');
        $q->clearFields();
        $q->leftJoin('?_user', 'u', 'p.user_id=u.user_id');
        foreach ($this->getCountries() as $country) {
            $q->addField("ROUND(SUM(IF(IFNULL(country, '')='$country', amount/base_currency_multi, 0)), 2)", 'amt_' . $country);
        }

        return $q;
    }

    function getLines()
    {
        $ret = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->getCountries() as $country) {
            $title = $country ? sprintf('%s (%s)', $country_name[$country], $country) : ___('Unknown');
            $ret[] = new Am_Report_Line('amt_' . $country,
                sprintf('%s, %s', $title, Am_Currency::getDefault()),
                null, ['Am_Currency', 'render']);
        }
        return $ret;
    }

    function getCountries()
    {
        return $this->getDi()->db->selectCol(<<<CUT
            SELECT DISTINCT country
                FROM ?_user
                LEFT JOIN ?_invoice_payment USING (user_id)
                WHERE dattm BETWEEN ? AND ?
CUT
            , $this->getStart(), $this->getStop());
    }
}

class Am_Report_RefundCountry extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Refunds by Customer Country');
    }

    public function getPointField()
    {
        return 'r.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceRefundTable, 'r');
        $q->clearFields();
        $q->leftJoin('?_user', 'u', 'r.user_id=u.user_id');
        foreach ($this->getCountries() as $country) {
            $q->addField("ROUND(SUM(IF(IFNULL(country, '')='$country', amount/base_currency_multi, 0)), 2)", 'ramt_' . $country);
        }

        return $q;
    }

    function getLines()
    {
        $ret = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->getCountries() as $country) {
            $title = $country ? sprintf('%s (%s)', $country_name[$country], $country) : ___('Unknown');
            $ret[] = new Am_Report_Line('ramt_' . $country,
                sprintf('%s, %s', $title, Am_Currency::getDefault()),
                null, ['Am_Currency', 'render']);
        }
        return $ret;
    }

    function getCountries()
    {
        return $this->getDi()->db->selectCol(<<<CUT
            SELECT DISTINCT country
                FROM ?_user
                LEFT JOIN ?_invoice_refund USING (user_id)
                WHERE dattm BETWEEN ? AND ?
CUT
            , $this->getStart(), $this->getStop());
    }
}

class Am_Report_PaymentByCountry extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Payments Distribution by Customer Country');
    }

    protected function runQuery()
    {
        $point_fld = self::POINT_FLD;
        $this->stmt = $this->getDi()->db->queryResultOnly(<<<CUT
            SELECT country AS $point_fld,
                ROUND(SUM(amount/base_currency_multi), 2) as amt
            FROM ?_invoice_payment ip
            LEFT JOIN ?_user u USING (user_id)
            WHERE country IN (?a)
            GROUP BY $point_fld
CUT
            , $this->countries);
    }

    function getLines()
    {
        return [new Am_Report_Line('amt', ___('Amount'), null, ['Am_Currency', 'render'])];
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $opt = $this->getCountries();
        if (!$opt) {
            $text = Am_Html::escape(___('You have not any payments associated with country yet.'));
            $form->addHtml(null, ['class' => 'am-row-wide'])
                ->setHtml(<<<CUT
<span class="red" id="r-payment-by-country">$text</span>
<script type="text/javascript">
jQuery(function(){
    jQuery('#r-payment-by-country').closest('.am-row').nextAll('.am-row').remove();
});
</script>
CUT
                    );
        } else {
            $form->addMagicSelect('country', ['class' => 'am-combobox-fixed'])
                ->setLabel(___("Countries\nkeep empty to report all"))
                ->loadOptions($opt);
        }
    }

    function getCountries()
    {
        $_ = $this->getDi()->db->selectCol(<<<CUT
            SELECT DISTINCT country
                FROM ?_user
                LEFT JOIN ?_invoice_payment USING (user_id)
                WHERE invoice_payment_id IS NOT NULL
                    AND country IS NOT NULL
                    AND country <> ''
CUT
            );
        $res = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($_ as $c) {
            $res[$c] = isset($country_name[$c]) ? $country_name[$c] : $c;
        }
        asort($res, SORT_REGULAR);
        return $res;
    }

    protected function processConfigForm(array $values)
    {
        $vars = $this->form->getValue();
        $this->countries = $vars['country'] ?: array_keys($this->getCountries());
        $res = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->countries as $c) {
            $res[$c] = isset($country_name[$c]) ? $country_name[$c] : $c;
        }
        $this->setQuantity(new Am_Report_Quant_Enum($res));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_RefundByCountry extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Refunds Distribution by Customer Country');
    }

    protected function runQuery()
    {
        $point_fld = self::POINT_FLD;
        $this->stmt = $this->getDi()->db->queryResultOnly(<<<CUT
            SELECT country AS $point_fld,
                ROUND(SUM(amount/base_currency_multi), 2) as amt
            FROM ?_invoice_refund ip
            LEFT JOIN ?_user u USING (user_id)
            WHERE country IN (?a)
            GROUP BY $point_fld
CUT
            , $this->countries);
    }

    function getLines()
    {
        return [new Am_Report_Line('amt', ___('Amount'), null, ['Am_Currency', 'render'])];
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $opt = $this->getCountries();
        if (!$opt) {
            $text = Am_Html::escape(___('You have not any refunds associated with country yet.'));
            $form->addHtml(null, ['class' => 'am-row-wide'])
                ->setHtml(<<<CUT
<span class="red" id="r-refund-by-country">$text</span>
<script type="text/javascript">
jQuery(function(){
    jQuery('#r-refund-by-country').closest('.am-row').nextAll('.am-row').remove();
});
</script>
CUT
                    );
        } else {
            $form->addMagicSelect('country', ['class' => 'am-combobox-fixed'])
                ->setLabel(___("Countries\nkeep empty to report all"))
                ->loadOptions($opt);
        }
    }

    function getCountries()
    {
        $_ = $this->getDi()->db->selectCol(<<<CUT
            SELECT DISTINCT country
                FROM ?_user
                LEFT JOIN ?_invoice_refund USING (user_id)
                WHERE invoice_refund_id IS NOT NULL
                    AND country IS NOT NULL
                    AND country <> ''
CUT
            );
        $res = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($_ as $c) {
            $res[$c] = isset($country_name[$c]) ? $country_name : $c;
        }
        asort($res, SORT_REGULAR);
        return $res;
    }

    protected function processConfigForm(array $values)
    {
        $vars = $this->form->getValue();
        $this->countries = $vars['country'] ?: array_keys($this->getCountries());
        $res = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->countries as $c) {
            $res[$c] = isset($country_name[$c]) ? $country_name[$c] : $c;
        }
        $this->setQuantity(new Am_Report_Quant_Enum($res));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_SignupsCountCountry extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of Signups by Country');
    }

    public function getPointField()
    {
        return 'added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->userTable, 'u');
        $q->clearFields();
        foreach ($this->getCountries() as $country) {
            $q->addField("SUM(IF(IFNULL(country, '')='$country', 1, 0))", 'cnt_' . $country);
        }

        return $q;
    }

    function getLines()
    {
        $ret = [];
        $country_name = $this->getDi()->countryTable->getOptions();
        foreach ($this->getCountries() as $country) {
            $ret[] = new Am_Report_Line('cnt_' . $country,
                $country ? sprintf('%s (%s)', $country_name[$country], $country) : ___('Unknown'));
        }
        return $ret;
    }

    function getCountries()
    {
        return $this->getDi()->db->selectCol(<<<CUT
            SELECT DISTINCT country
                FROM ?_user
                WHERE added BETWEEN ? AND ?
CUT
            , $this->getStart(), $this->getStop());
    }
}

class Am_Report_PurchaseCount extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of product purchase');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'i.tm_added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceItemTable, 'ii');
        $q->clearFields()
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id=i.invoice_id')
            ->addWhere('i.status IN (?a)', [
                Invoice::PAID,
                Invoice::RECURRING_ACTIVE,
                Invoice::RECURRING_CANCELLED,
                Invoice::RECURRING_FINISHED
            ])
            ->addWhere('ii.item_type=?', 'product');


        $fields = [];
        $products = $this->getProducts();
        foreach ($products as $k => $v) {
            $q->addField("SUM(IF(ii.item_id = $k, ii.qty, 0))", 'cnt_' . $k);
        }
        $q->addField("SUM(IF(ii.item_id IN (".implode(', ', array_map(function($p){return $p;}, array_keys($products)))."), ii.qty, 0))", 'cnt_total');


        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $title) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
        }
        $ret[] = new Am_Report_Line('cnt_total', 'Total');
        return $ret;
    }
}

class Am_Report_PurchaseCountByPaysystems extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of product purchase by payment system breakdown');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'i.tm_started';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceItemTable, 'ii');
        $q->clearFields()
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id=i.invoice_id')
            ->addWhere('i.status IN (?a)', [
                Invoice::PAID,
                Invoice::RECURRING_ACTIVE,
                Invoice::RECURRING_CANCELLED,
                Invoice::RECURRING_FINISHED
            ])
            ->addWhere('ii.item_type=?', 'product')
            ->addWhere('ii.item_id IN (?a)', array_keys($this->getProducts()));

        $fields = [];
        foreach ($this->getPaysystems() as $ps) {
            $q->addField("SUM(IF(i.paysys_id = '$ps', ii.qty, 0))", 'cnt_' . $ps);
        }
        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getPaysystems()
    {
        static $cache;
        if (!$cache)
            $cache = $this->getDi()->db->selectCol("SELECT DISTINCT paysys_id FROM ?_invoice WHERE tm_started IS NOT NULL");
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getPaysystems() as $ps) {
            $ret[] = new Am_Report_Line('cnt_' . $ps, $ps);
        }
        return $ret;
    }
}

class Am_Report_PendingInvoiceCount extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of Pending Invoices');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'i.tm_added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceItemTable, 'ii');
        $q->clearFields()
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id=i.invoice_id')
            ->addWhere('i.status IN (?a)', [Invoice::PENDING])
            ->addWhere('ii.item_type=?', 'product');

        $fields = [];
        foreach ($this->getProducts() as $k => $v) {
            $q->addField("SUM(IF(ii.item_id = $k, ii.qty, 0))", 'cnt_' . $k);
        }
        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $title) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
        }
        return $ret;
    }
}

class Am_Report_CancelInvoiceCount extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Count of Cancellations');
        $this->description = ___('Number of Cancelled Recurring Subscriptions by Period');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'i.tm_cancelled';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->invoiceItemTable, 'ii');
        $q->clearFields()
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id=i.invoice_id')
            ->addWhere('i.status IN (?a)', [Invoice::RECURRING_CANCELLED])
            ->addWhere('ii.item_type=?', 'product');

        $fields = [];
        foreach ($this->getProducts() as $k => $v) {
            $q->addField("SUM(IF(ii.item_id = $k, ii.qty, 0))", 'cnt_' . $k);
        }
        return $q;
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $title) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
        }
        return $ret;
    }
}

class Am_Report_Downloads extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Downloads by files breakdown');
        $this->description = ___('only files downloaded by registered users is taken to account');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('files', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Files\nkeep empty to report all files"))
            ->loadOptions($this->getOptions());
    }

    protected function getOptions()
    {
        return $this->getDi()->db->selectCol("SELECT DISTINCT file_id as ARRAY_KEY,
            title FROM ?_file");
    }

    public function getPointField()
    {
        return 'fd.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $q = new Am_Query($this->getDi()->fileDownloadTable, 'fd');
        $q->clearFields();
        foreach ($this->getFiles() as $k => $v) {
            $q->addField(sprintf('SUM(IF(file_id=%d,1,0))', $k), 'cnt_' . $k);
        }
        return $q;
    }

    function getFiles()
    {
        $vars = $this->form->getValue();
        $files = $this->getDi()->db->selectCol("SELECT
                DISTINCT file_id as ARRAY_KEY, title
                FROM ?_file
                {WHERE file_id IN (?a)}", !empty($vars['files']) ? (array) $vars['files'] : DBSIMPLE_SKIP);
        return $files;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->getFiles() as $k => $ps) {
            $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($ps));
        }
        return $ret;
    }
}

class Am_Report_RetentionRate extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Retention Rate');
        $this->description = ___('Number of cancel on each billing cycle');
        $this->setQuantity(new Am_Report_Quant_Exact());
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
        $gr = $form->addGroup()
            ->setLabel(___("Report Period\n" .
                'Take into Account only subscriptions that began in defined period, '.
                'keep empty to use all subscriptions'));

        $gr->setSeparator(' ');
        $gr->addDate('date_start', ['placeholder' => ___('Begin Date')]);
        $gr->addHtml()
            ->setHtml('&mdash;');
        $gr->addDate('date_end', ['placeholder' => ___('End Date')]);

    }

    public function runQuery()
    {
        $fields = [];
        foreach ($this->getProducts() as $k => $product) {
            $fields[] = "SUM(IF(ii.item_id=$k AND ii.item_type='product', 1, 0)) AS cnt_" . $k;
        }
        $fields = implode(',', $fields);

        $vars = $this->form->getValue();
        $date_stmt_start = isset($vars['date_start']) ?
            sprintf(" AND i.tm_started >= %s",
                $this->getDi()->db->escape(date('Y-m-d 00:00:00', strtotime($vars['date_start'])))) :
            '';
        $date_stmt_end = isset($vars['date_end']) ?
            sprintf(" AND i.tm_started <= %s",
                $this->getDi()->db->escape(date('Y-m-d 23:59:59', strtotime($vars['date_end'])))) :
            '';

        $point_fld = self::POINT_FLD;
        $sql = "SELECT $fields,
        (SELECT COUNT(invoice_payment_id) + IF(i.first_total = 0, 1, 0)
            FROM ?_invoice_payment WHERE invoice_id=i.invoice_id) AS $point_fld
        FROM ?_invoice i LEFT JOIN ?_invoice_item ii USING(invoice_id)
        WHERE i.tm_cancelled IS NOT NULL $date_stmt_start $date_stmt_end GROUP BY $point_fld";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        $ret = [];
        foreach ($this->getProducts() as $k => $product) {
            $ret[] = new Am_Report_Line('cnt_' . $k, $product);
        }
        return $ret;
    }

    protected function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_AverageLifetimeValue extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Average Lifetime Value (Cohort Analysis)');
        $this->description = ___('report includes only user who did at least one payment');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addSelect('quant')->setLabel(___('Grouping'))
                ->loadOptions([
                    '7' => ___('Week'),
                    '30' => ___('Month (30 days)'),
                    '90' => ___('Quarter (90 days)'),
                    '365' => ___('Year')
                ]);

        $gr = $form->addGroup()
            ->setLabel(___("Users\n" .
                'Take into Account only user who sign up in defined period, '.
                'keep empty to use all user'));

        $gr->setSeparator(' ');
        $gr->addDate('date_start', ['placeholder' => ___('Begin Date')]);
        $gr->addHtml()
            ->setHtml('&mdash;');
        $gr->addDate('date_end', ['placeholder' => ___('End Date')]);

    }

    public function getPointFieldType()
    {
        return Am_Report_Abstract::POINT_VALUE;
    }

    protected function processConfigForm(array $values)
    {
        $this->setInterval(
            isset($values['date_start']) ? $values['date_start'] : '1927-01-01',
            isset($values['date_end']) ? $values['date_end'] : 'now');

        $this->d = (int)$values['quant'];
        $quant = new Am_Report_Quant_Exact();
        $this->setQuantity($quant);
    }

    public function setInterval($start, $stop)
    {
        $this->start = date('Y-m-d 00:00:00', strtotime($start));
        $this->stop = date('Y-m-d 23:59:59', strtotime($stop));
        return $this;
    }

    public function runQuery()
    {
        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_alv_report_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_alv_report_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            delay INT NOT NULL,
            amount DECIMAL(12,2),
            PRIMARY KEY (id)
        )");

        $d = $this->d;

        $db->query("INSERT INTO ?_alv_report_tmp (user_id, delay, amount)
            SELECT user_id, CEIL((DATEDIFF(ip.dattm, u.added)+1)/$d),
                (amount / base_currency_multi)
            FROM ?_invoice_payment ip
                LEFT JOIN ?_user u
                USING (user_id)
            WHERE u.added BETWEEN ? AND ?
                AND ip.dattm>u.added",
            $this->start, $this->stop);
        $db->query("INSERT INTO ?_alv_report_tmp (user_id, delay, amount)
            SELECT user_id, CEIL((DATEDIFF(ip.dattm, u.added)+1)/$d),
                (-1 * amount / base_currency_multi)
            FROM ?_invoice_refund ip
                LEFT JOIN ?_user u
                USING (user_id)
            WHERE u.added BETWEEN ? AND ?
                AND ip.dattm>u.added",
            $this->start, $this->stop);

        $cnt = $db->selectCell("SELECT COUNT(DISTINCT ip.user_id)
            FROM ?_invoice_payment ip
                LEFT JOIN ?_user u
                USING (user_id)
            WHERE u.added BETWEEN ? AND ?
                AND ip.dattm>u.added",
            $this->start, $this->stop);

        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT ROUND(SUM(amount)/$cnt,2) AS alv, delay AS point
            FROM ?_alv_report_tmp
            GROUP BY delay");
    }

    public function getLines()
    {
        return [new Am_Report_Line('alv', ___('Average Lifetime Value') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])];
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_Active extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Active Users by Products');
        $this->description = ___('number of active users per product');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    public function runQuery()
    {
        $now = $this->getDi()->sqlDate;
        $products = implode(',', array_keys($this->getProducts()));

        $point_fld = self::POINT_FLD;
        $sql = "SELECT COUNT(DISTINCT user_id) as active, product_id AS $point_fld
        FROM ?_access
        WHERE begin_date <= '$now'
        AND expire_date >= '$now'
        GROUP BY $point_fld
        HAVING product_id IN ($products)";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        return [new Am_Report_Line('active', ___('Active Users'))];
    }

    protected function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum(array_map('strip_tags', $this->getProducts())));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_ActiveByProductCategory extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Active Users by Product Categories');
        $this->description = ___('number of active users per product category');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('categories', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Categories\nkeep empty to report all categories"))
            ->loadOptions($this->getDi()->productCategoryTable->getOptions());
    }

    public function runQuery()
    {
        $now = $this->getDi()->sqlDate;
        $categories = implode(',', array_keys($this->getCategories()));

        $point_fld = self::POINT_FLD;
        $sql = <<<CUT
            SELECT COUNT(DISTINCT user_id) as active, product_category_id AS $point_fld
                FROM ?_access a
                LEFT JOIN ?_product_product_category ppc
                USING (product_id)
                WHERE begin_date <= '$now'
                    AND expire_date >= '$now'
                GROUP BY $point_fld
                HAVING product_category_id IN ($categories)
CUT;

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        return [new Am_Report_Line('active', ___('Active Users'))];
    }

    protected function getCategories()
    {
        $vars = $this->form->getValue();
        $c = $vars['categories'];

        $cats = $this->getDi()->productCategoryTable->getOptions();
        return array_filter($cats, function($k) use ($c) { return empty($c) || in_array($k, $c); }, ARRAY_FILTER_USE_KEY);
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum($this->getCategories()));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_AvgPaymentUserGroup extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___("Average Payments by User Group");
        $this->description = "";
        parent::__construct();
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('user_group_id', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("User Groups\nkeep empty to include all groups to report"))
            ->loadOptions($this->getDi()->userGroupTable->getOptions());
    }

    public function getPointField()
    {
        return 't.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $vars = $this->form->getValue();
        $user_group_ids = $vars['user_group_id'] ?: array_keys($this->getDi()->userGroupTable->getOptions());

        $cnts = $this->getDi()->db->selectCol(<<<CUT
            SELECT COUNT(user_id), user_group_id AS ARRAY_KEY
                FROM ?_user_user_group
                GROUP BY user_group_id;
CUT
            );

        $q = new Am_Query($this->getDi()->invoicePaymentTable);
        $q->leftJoin('?_user_user_group', 'uug', 't.user_id=uug.user_id')
            ->addWhere('user_group_id IN (?a)', $user_group_ids);

        $q->clearFields();
        foreach ($user_group_ids as $uid) {
            if(!empty($cnts[$uid]))
                $q->addField("ROUND(SUM(IF(user_group_id = {$uid}, t.amount/t.base_currency_multi, 0))/{$cnts[$uid]}, 2) AS amt_{$uid}");
            else
                $q->addField("0 AS amt_{$uid}");
        }
        return $q;
    }

    function getLines()
    {
        $vars = $this->form->getValue();
        $op = $this->getDi()->userGroupTable->getOptions();
        $user_group_ids = $vars['user_group_id'] ?: array_keys($op);
        $_ = [];
        foreach ($user_group_ids as $uid) {
            $_[] = new Am_Report_Line("amt_{$uid}", $op[$uid] . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render']);
        }
        return $_;
    }
}

class Am_Report_PaymentUserGroup extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___("Payments by User Group");
        $this->description = "";
        parent::__construct();
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('user_group_id', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("User Groups\nkeep empty to include all groups to report"))
            ->loadOptions($this->getDi()->userGroupTable->getOptions());
    }

    public function getPointField()
    {
        return 't.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $vars = $this->form->getValue();
        $user_group_ids = $vars['user_group_id'] ?: array_keys($this->getDi()->userGroupTable->getOptions());

        $q = new Am_Query($this->getDi()->invoicePaymentTable);
        $q->leftJoin('?_user_user_group', 'uug', 't.user_id=uug.user_id')
            ->addWhere('user_group_id IN (?a)', $user_group_ids);

        $q->clearFields();
        foreach ($user_group_ids as $uid) {
            $q->addField("ROUND(SUM(IF(user_group_id = {$uid}, t.amount/t.base_currency_multi, 0)), 2) AS amt_{$uid}");
        }
        return $q;
    }

    function getLines()
    {
        $vars = $this->form->getValue();
        $op = $this->getDi()->userGroupTable->getOptions();
        $user_group_ids = $vars['user_group_id'] ?: array_keys($op);
        $_ = [];
        foreach ($user_group_ids as $uid) {
            $_[] = new Am_Report_Line("amt_{$uid}", $op[$uid] . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render']);
        }
        return $_;
    }
}

class Am_Report_UserUserGroup extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('Users by User Groups');
        $this->description = ___('number of users per groups');
    }

    public function runQuery()
    {
        $point_fld = self::POINT_FLD;
        $sql = <<<CUT
            SELECT COUNT(user_id) AS cnt, user_group_id AS $point_fld
                FROM ?_user_user_group
                GROUP BY $point_fld
CUT;

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        return [new Am_Report_Line('cnt', ___('Number of Members'))];
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum($this->getDi()->userGroupTable->getOptions()));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result, false)
        ];
    }
}

class Am_Report_UserDemography extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('User Demographics');
        $this->description = ___('number of users per region');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addAdvRadio('type')
            ->setLabel(___('Group By'))
            ->loadOptions([
                'country' => ___('Country'),
                'state' => ___('State')
            ])->setValue('country');
    }

    public function runQuery()
    {
        $vars = $this->form->getValue();

        $field = $vars['type'];

        $point_fld = self::POINT_FLD;
        $sql = "SELECT COUNT(user_id) AS demography, $field AS $point_fld
        FROM ?_user
        GROUP BY $point_fld
        HAVING $point_fld<>''";

        $this->stmt = $this->getDi()->db->queryResultOnly($sql);
    }

    public function getLines()
    {
        return [new Am_Report_Line('demography', ___('User Demographics'))];
    }

    protected function getOptions()
    {
        $vars = $this->form->getValue();
        switch ($vars['type']) {
            case 'country' :
                return $this->getDi()->db->selectCol("SELECT
            country as ARRAY_KEY, title
            FROM ?_country
            WHERE country IN (SELECT DISTINCT country FROM ?_user)");
                break;
            case 'state' :
                return $this->getDi()->db->selectCol("SELECT
            state as ARRAY_KEY, title
            FROM ?_state
            WHERE state IN (SELECT DISTINCT state FROM ?_user)");
                break;
            default:
                throw new Am_Exception_InputError(sprintf('Unknown type [%s] in %s::%s', $vars['type'], __CLASS__, __METHOD__));
        }
    }

    protected function processConfigForm(array $values)
    {
        $this->setQuantity(new Am_Report_Quant_Enum($this->getOptions()));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_ChurnRate extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Churn Rate');
        $this->description = ___('Number of users who becomes inactive in given period');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Products\nkeep empty to report all products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expbegin = $this->quantity->getSqlExpr('begin');
        $expend = $this->quantity->getSqlExpr('expire');


        $vars = $this->form->getValue();
        if (empty($vars['products'])) {
            $this->stmt = $this->getDi()->db->queryResultOnly(
                "SELECT $expend as point, COUNT(t.user_id) as cnt
                 FROM (
                      SELECT user_id, MIN(begin_date) AS begin, MAX(expire_date) AS expire
                      FROM ?_access GROUP BY user_id
                ) AS t
                WHERE $expbegin < $expend  and expire > ? and expire < ?  group by point", sqlDate($this->start), sqlDate($this->stop));
        } else {
            $fields = [];
            foreach ($this->getProducts() as $k => $title) {
                $fields[] = "sum(if(product_id=$k, 1,0)) as cnt_$k";
            }
            $fields = implode(", ", $fields);
            $this->stmt = $this->getDi()->db->queryResultOnly(
                "SELECT $expend as point, $fields
                 FROM (
                      SELECT concat(user_id, '-',product_id) as user_product,  user_id, product_id, MIN(begin_date) AS begin, MAX(expire_date) AS expire
                      FROM ?_access GROUP BY user_product
                ) AS t
                WHERE $expbegin < $expend  and expire > ? and expire < ?  group by point", sqlDate($this->start), sqlDate($this->stop));
        }
    }

    function getProducts()
    {
        $vars = $this->form->getValue();
        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    function getLines()
    {
        $vars = $this->form->getValue();
        $ret = [];
        if (empty($vars['products'])) {
            $ret[] = new Am_Report_Line('cnt', ucfirst("Users who became inactive"));
        } else {
            foreach ($this->getProducts() as $k => $title) {
                $ret[] = new Am_Report_Line('cnt_' . $k, ucfirst($title));
            }
        }
        return $ret;
    }
}

class Am_Report_ActiveUsers extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Active Users by Period');
        $this->description = ___('Number of Active Users by Date');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Include only users who have active subscriptions to these products"))
            ->loadOptions($this->getDi()->productTable->getOptions());
    }

    protected function runQuery()
    {
        $expbegin = $this->quantity->getSqlExpr('begin_date');
        $expend = $this->quantity->getSqlExpr('expire_date');
        $this->getDi()->db->query("DROP TEMPORARY TABLE IF EXISTS ?_active_users_by_period_tmp");
        $this->getDi()->db->query(
            "CREATE TEMPORARY TABLE ?_active_users_by_period_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            point varchar(10) not null,
            cnt int(10) not null,
            PRIMARY KEY (id),
            UNIQUE KEY (point)
        )
        ");

        $this->getDi()->db->query(
            "INSERT IGNORE INTO ?_active_users_by_period_tmp (point) "
            . "SELECT DISTINCT($expbegin) FROM ?_access WHERE begin_date BETWEEN ? AND ?  "
            . "UNION SELECT DISTINCT($expend) FROM ?_access WHERE expire_date BETWEEN ? AND ?", $this->start, $this->stop, $this->start, $this->stop);

        $vars = $this->form->getValue();

        $this->getDi()->db->query("UPDATE ?_active_users_by_period_tmp p SET p.cnt = (SELECT COUNT(DISTINCT(user_id)) FROM ?_access a WHERE p.point BETWEEN $expbegin AND $expend {AND a.product_id in (?a)})", (!empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP));


        $this->stmt = $this->getDi()->db->queryResultOnly("SELECT point, cnt FROM ?_active_users_by_period_tmp");
    }

    function getProducts()
    {
        $vars = $this->form->getValue();

        $cache = $this->getDi()->db->selectCol("SELECT
            DISTINCT product_id as ARRAY_KEY, title
            FROM ?_product
            {WHERE product_id IN (?a)}
            ORDER BY sort_order, title", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP);
        return $cache;
    }

    public function getLines()
    {
        return [new Am_Report_Line('cnt', 'Active Users')];
    }
}

class Am_Report_RollingConversion extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Rolling Conversion');
        $this->description = ___('Percentage of users who convert from free to paid within given amount of days');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addMagicSelect('products', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Count conversion for these products only"))
            ->loadOptions($this->getDi()->productTable->getOptions());
        $form->addText('rolling_days', ['placeholder' => '30', 'size' => 3])
            ->setLabel(___("Rolling Days"));
    }

    protected function runQuery()
    {
        $pointexp = $this->quantity->getSqlExpr('t.added');
        $vars = $this->form->getValue();
        $rolling_days = intval($vars['rolling_days']) ? intval($vars['rolling_days']) : 30;

        $this->getDi()->db->query("DROP TABLE IF EXISTS ?_rolling_conversion_tmp");
        $this->getDi()->db->query(
            "CREATE TEMPORARY TABLE ?_rolling_conversion_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            point varchar(10) not null,
            added int(10) not null,
            paid int(10) not null,
            PRIMARY KEY (id),
            UNIQUE KEY(point)
        )
        ");
        // Add count of users who
        $this->getDi()->db->query(
            "INSERT INTO ?_rolling_conversion_tmp (point, added)
             SELECT $pointexp as point, count(t.user_id) AS added
             FROM ?_user t where t.added>? AND t.added < ?
             GROUP BY point
             ", $this->start, $this->stop
        );

        $this->getDi()->db->queryResultOnly(
            "INSERT INTO ?_rolling_conversion_tmp (point, paid) SELECT $pointexp as point, COUNT(t.user_id) as cnt
                 FROM (
                      SELECT u.user_id, added,  MIN(p.dattm)  AS  paid
                      FROM ?_user u LEFT JOIN ?_invoice_payment p ON u.user_id = p.user_id
                      {LEFT JOIN ?_access a  on p.invoice_payment_id = a.invoice_payment_id WHERE a.product_id IN (?a)} GROUP BY u.user_id
                ) AS t
                WHERE   to_days(paid) - to_days(added) <= ?  AND  t.added > ? AND  t.added<? GROUP BY point
                ON DUPLICATE KEY UPDATE paid=VALUES(paid)
                ", !empty($vars['products']) ? (array) $vars['products'] : DBSIMPLE_SKIP, $rolling_days, $this->start, $this->stop);


        $this->stmt = $this->getDi()->db->queryResultOnly("SELECT point, 100*paid/added as cnt FROM ?_rolling_conversion_tmp");
    }

    public function getLines()
    {
        return [new Am_Report_Line('cnt', ___('Conversion'), null, function($v){return sprintf("%.2f%%", $v);}, false)];
    }
}

class Am_Report_ProductConversion extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('Product Conversion');
        $this->description = ___('Percentage of Users who Purchase product B after Product A');
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $form->addSelect('producta', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Product A"))
            ->loadOptions($this->getDi()->productTable->getOptions());
        $form->addSelect('productb', ['class' => 'am-combobox-fixed'])
            ->setLabel(___("Product B"))
            ->loadOptions(['' => 'Any Product'] + $this->getDi()->productTable->getOptions());
    }

    public function getPointField()
    {
        return 'begin_date';
    }

    protected function runQuery()
    {
        $expra = $this->quantity->getSqlExpr('begin_date');

        $vars = $this->form->getValue();

        $db = $this->getDi()->db;
        $db->query("DROP TEMPORARY TABLE IF EXISTS ?_product_conversion_tmp");
        $db->query("CREATE TEMPORARY TABLE ?_product_conversion_tmp (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usera_id INT NOT NULL,
            userb_id INT NULL,
            begin_date DATE NOT NULL,
            revenue DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id)
        )");
        $db->query("INSERT INTO ?_product_conversion_tmp (usera_id, userb_id, begin_date)
            SELECT a.user_id, MAX(IF(a2.product_id IS NULL, NULL, a.user_id)), MIN(a.begin_date)
                FROM ?_access a
                    LEFT JOIN ?_access a2
                        ON a.user_id = a2.user_id
                            {AND a2.product_id = ?}
                            {AND a2.product_id <> ?}
                            AND a2.begin_date >= a.begin_date
                WHERE a.product_id = ?
                GROUP BY a.user_id", $vars['productb']?:DBSIMPLE_SKIP,$vars['productb']?DBSIMPLE_SKIP:$vars['producta'], $vars['producta']);

        $db->query("
        UPDATE ?_product_conversion_tmp t LEFT JOIN ?_invoice_payment p ON (p.user_id = t.userb_id)
        SET t.revenue = t.revenue + p.amount
        WHERE p.dattm > t.begin_date
        ");

        $this->stmt = $this->getDi()->db->queryResultOnly("
            SELECT
                (COUNT(DISTINCT userb_id)/COUNT(DISTINCT usera_id)) * 100 AS cnt,
                   sum(revenue) as revenue,
                $expra AS point
            FROM ?_product_conversion_tmp
            WHERE begin_date BETWEEN ? AND ?
            GROUP BY $expra", $this->start, $this->stop);
    }

    public function getLines()
    {
        return [
            new Am_Report_Line('cnt', ___('Conversion'), null, function($_){return sprintf('%.2f%%', $_);}, false),
        ];
    }

    public function getOutput(Am_Report_Result $result)
    {
        $tableResult = clone $result;
        $tableResult->addLine(new Am_Report_Line('revenue', ___('Revenue'), null, function($_){return Am_Currency::render($_);}, false));
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($tableResult)
        ];
    }
}

class Am_Report_CustomUserField extends Am_Report_Abstract
{
    public function __construct()
    {
        $this->title = ___('User Distribution by Custom Field');
    }

    protected function runQuery()
    {
        $f = $this->field;
        $point_fld = self::POINT_FLD;
        if ($f->sql) {
            $this->stmt = $this->getDi()->db->queryResultOnly("
                SELECT
                    ?# AS $point_fld, COUNT(user_id) as cnt
                FROM
                    ?_user
                WHERE
                    ?# IS NOT NULL AND ?# <> ''
                GROUP BY
                    ?#", $f->name, $f->name, $f->name, $f->name, $f->name);
        } else {
            $this->stmt = $this->getDi()->db->queryResultOnly("
                SELECT
                    value AS $point_fld, COUNT(value) as cnt
                FROM
                    ?_data
                WHERE
                    `key`= ? AND value IS NOT NULL AND value<>''
                GROUP BY
                    $point_fld", $f->name);
        }
    }

    function getLines()
    {
        $ret = [];
        $ret[] = new Am_Report_Line('cnt', ___($this->field->title));
        return $ret;
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $opt = $this->getFieldOptions();
        if (!$opt) {
            $text = Am_Html::escape(___('This report works only with radio and select fields. You have not such custom fields yet.'));
            $form->addHtml(null, ['class' => 'am-row-wide'])
                ->setHtml(<<<CUT
<span class="red" id="r-custom-user-field">$text</span>
<script type="text/javascript">
jQuery(function(){
    jQuery('#r-custom-user-field').closest('.am-row').nextAll('.am-row').remove();
});
</script>
CUT
                    );
        } else {
            $sel = $form->addSelect('field')
                ->setLabel(___("Field"))
                ->loadOptions($opt);
        }
    }

    protected function getFieldOptions()
    {
        $opt = [];
        foreach ($this->getDi()->userTable->customFields()->getAll() as $f) {
            if (in_array($f->type, ['radio', 'select'])) {
                $opt[$f->name] = $f->title;
            }
        }
        return $opt;
    }

    protected function processConfigForm(array $values)
    {
        $vars = $this->form->getValue();
        $this->field = $this->getDi()->userTable->customFields()->get($vars['field']);
        $this->setQuantity(new Am_Report_Quant_Enum($this->field->options));
    }

    public function getOutput(Am_Report_Result $result)
    {
        return [
            new Am_Report_Graph_Bar($result),
            new Am_Report_Table($result)
        ];
    }
}

class Am_Report_CustomUserFieldByDate extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___('User Distribution by Custom Field by Date');
    }

    public function getPointField()
    {
        return 'u.added';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $field = $this->field;

        $q = new Am_Query($this->getDi()->userTable, 'u');
        $q->clearFields();

        if (!empty($field->sql)) {
            foreach ($field->options as $k => $v) {
                $q->addField("SUM(IF(IFNULL({$field->name}, '')='$k', 1, 0))", 'cnt_' . sha1($k));
            }
        } else {
            $q->leftJoin('?_data', 'd', "d.`table`='user' AND d.`id`=u.user_id AND d.`key`='{$field->name}'");
            foreach ($field->options as $k => $v) {
                $q->addField("SUM(IF(IFNULL(d.`value`, '')='$k', 1, 0))", 'cnt_' . sha1($k));
            }
        }
        return $q;
    }

    function getLines()
    {
        $ret = [];
        foreach ($this->field->options as $k=>$v) {
            $ret[] = new Am_Report_Line('cnt_' . sha1($k), $v);
        }
        return $ret;
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $opt = $this->getFieldOptions();
        if (!$opt) {
            $text = Am_Html::escape(___('This report works only with radio and select fields. You have not such custom fields yet.'));
            $form->addHtml(null, ['class' => 'am-row-wide'])
                ->setHtml(<<<CUT
<span class="red" id="r-custom-user-field-by-date">$text</span>
<script type="text/javascript">
jQuery(function(){
    jQuery('#r-custom-user-field-by-date').closest('.am-row').nextAll('.am-row').remove();
});
</script>
CUT
                );
        } else {
            $form->addSelect('field')
                ->setLabel(___("Field"))
                ->loadOptions($opt);
        }
    }

    protected function processConfigForm(array $values)
    {
        $vars = $this->form->getValue();
        $this->field = $this->getDi()->userTable->customFields()->get($vars['field']);
        return parent::processConfigForm($values);
    }

    protected function getFieldOptions()
    {
        $opt = [];
        foreach ($this->getDi()->userTable->customFields()->getAll() as $f) {
            if (in_array($f->type, ['radio', 'select'])) {
                $opt[$f->name] = $f->title;
            }
        }
        return $opt;
    }
}

class Am_Report_PaymentCouponBatch extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___("Payments by Used Coupon");
        $this->description = "";
        parent::__construct();
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addSelect('batch_id', ['class' => 'am-combobox-fixed'])->setLabel(___("Coupon Batch"));
        $sel->loadOptions($this->getDi()->couponBatchTable->getOptions());
    }

    public function getPointField()
    {
        return 't.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $vars = $this->form->getValue();
        $batch_id = $vars['batch_id'];

        $q = new Am_Query($this->getDi()->invoicePaymentTable);
        $q->leftJoin('?_invoice', 'i', 't.invoice_id=i.invoice_id');
        $q->leftJoin('?_coupon', 'c', 'i.coupon_id=c.coupon_id')
            ->addWhere('i.coupon_id IS NOT NULL')
            ->addWhere('batch_id=?', $batch_id);

        $q->clearFields();
        $q->addField('ROUND(SUM(t.amount/t.base_currency_multi), 2) AS amt');
        return $q;
    }

    function getLines()
    {
       return [
            new Am_Report_Line("amt", ___('Payments Amount') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])
       ];
    }
}

class Am_Report_RefundCouponBatch extends Am_Report_Date
{
    public function __construct()
    {
        $this->title = ___("Refunds by Used Coupon");
        $this->description = "";
        parent::__construct();
    }

    public function _initConfigForm(Am_Form $form)
    {
        parent::_initConfigForm($form);
        $sel = $form->addSelect('batch_id', ['class' => 'am-combobox-fixed'])->setLabel(___("Coupon Batch"));
        $sel->loadOptions($this->getDi()->couponBatchTable->getOptions());
    }

    public function getPointField()
    {
        return 't.dattm';
    }

    /** @return Am_Query */
    public function getQuery()
    {
        $vars = $this->form->getValue();
        $batch_id = $vars['batch_id'];

        $q = new Am_Query($this->getDi()->invoiceRefundTable);
        $q->leftJoin('?_invoice', 'i', 'i.invoice_id=t.invoice_id')
            ->leftJoin('?_coupon', 'c', 'i.coupon_id=c.coupon_id')
            ->addWhere('i.coupon_id IS NOT NULL')
            ->addWhere('batch_id=?', $batch_id);

        $q->clearFields();
        $q->addField('ROUND(SUM(t.amount/t.base_currency_multi), 2) AS amt');
        return $q;
    }

    function getLines()
    {
       return [
            new Am_Report_Line("amt", ___('Refund Amount') . ', ' . Am_Currency::getDefault(), null, ['Am_Currency', 'render'])
       ];
    }
}