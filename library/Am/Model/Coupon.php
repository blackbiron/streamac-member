<?php
/**
 * Class represents records from table coupon
 * {autogenerated}
 * @property int $coupon_id
 * @property int $batch_id
 * @property int $user_id
 * @property string $code
 * @property int $used_count
 * @see Am_Table
 */

class Coupon extends Am_Record_WithData
{
    const DISCOUNT_NUMBER  = 'number';
    const DISCOUNT_PERCENT = 'percent';

    /**
     * @return CouponBatch
     */
    function getBatch()
    {
        if (!$b = $this->getDi()->couponBatchTable->load($this->batch_id, false))
            throw new Am_Exception_InternalError("Database problem with coupon#{$this->coupon_id}: batch#{$this->batch_id} not found, orphaned record!");
        return $b;
    }

    /**
     * Validate if given coupon is applicable to a customer
     * @param int (optional)$user_id
     * @return string|null error message or null if all OK
     */
    function validate($user_id = null)
    {
        $batch = $this->getBatch();
        if ($batch->is_disabled)
            return ___('Coupon code disabled');
        if ($batch->use_count && ($batch->use_count <= $this->used_count))
            return ___('Coupon usage limit exceeded');
        if ($batch->user_id && $user_id && $batch->user_id != $user_id)
            return ___('This coupon belongs to another customer');
        if ($this->user_id && $this->user_id != $user_id)
            return ___('This coupon belongs to another customer');
        $tm = $this->getDi()->time;
        if ($batch->begin_date && strtotime($batch->begin_date) > $tm)
            return ___('Coupon is not yet active');
        if ($batch->expire_date && strtotime($batch->expire_date . ' 23:59:59') < $tm)
            return ___('Coupon code expired');
        if ($batch->user_use_count && $user_id)
        {
            $member_used_count = $this->getDi()->invoiceTable->findPaidCountByCouponId($this->coupon_id, $user_id);
            if ($batch->user_use_count <= $member_used_count)
                return ___('Coupon usage limit exceeded');
        }

        $event = new Am_Event(Am_Event::VALIDATE_COUPON, [
            'couponBatch' => $batch,
            'coupon' => $this,
            'user' => $user_id ? $this->getDi()->userTable->load($user_id, false) : null
        ]);

        $this->getDi()->hook->call($event);

        if ($r = $event->getReturn()) {
            return $r[0];
        }

        return null;
    }

    /**
     * Mark coupon as used by payment (if payment with saved coupon was finished)
     * and saves the coupon record
     * @param Payment
     */
    function setUsed()
    {
        $this->used_count++;
        $this->updateSelectedFields('used_count');
    }

    /**
     * Return true if discount applies to current $item and
     * if that is first payment or coupon is recurring
     * @param InvocieItem $item
     * @param bool $isFirstPayment
     * @return bool
     */
    function isApplicable($item, $isFirstPayment=true)
    {
        return $this->getDi()->hook->filter(
            $this->_isApplicable($item, $isFirstPayment),
            Am_Event::COUPON_IS_APPLICABLE,
            ['coupon' => $this, 'item' => $item, 'isFirstPayment' => $isFirstPayment]
        );
    }

    protected function _isApplicable($item, $isFirstPayment=true)
    {
        $batch = $this->getBatch();
        if (!$isFirstPayment && !$batch->is_recurring)
            return false;

        $product_ids = $batch->getApplicableProductIds();
        $bp_id = $batch->getApplicableBpIds();
        if (!is_null($product_ids) || $bp_id) {
            if ($item->item_type != 'product') {
                return false;
            }
            if (!in_array($item->item_id, (array)$product_ids) &&
                !in_array($item->billing_plan_id, $bp_id)) {

                return false;
            }
        }

        $product_ids = $batch->getNotApplicableProductIds();
        $bp_id = $batch->getNotApplicableBpIds();
        if (!is_null($product_ids) || $bp_id) {
            if (in_array($item->item_id, (array)$product_ids)
                || in_array($item->billing_plan_id, $bp_id)) {

                return false;
            }
        }

        return true;
    }

    /**
     * Check if require_product prevent_if_product coupon batch settings are statisfied
     * for current purchase. We does not take into account products from current purchase,
     * only user does matter.
     *
     * @param array $products Product objects that are purchasing now
     * @param array $haveActiveIds int product# user has active subscriptions to
     * @param array $haveExpiredIds int product# user has expired subscriptions to
     * @return array empty array of OK, or an array full of error messages
     */
    function checkRequirements(array $products, array $haveActiveIds = [], array $haveExpiredIds = [])
    {
        $batch = $this->getBatch();

        $error = [];
        $have = array_unique(array_merge(
                array_map(function($id) {return "ACTIVE-$id";}, $haveActiveIds),
                array_map(function($id) {return "EXPIRED-$id";}, $haveExpiredIds)
        ));
        $will_have = array_unique(array_merge(
                $have,
                array_map(function(Product $p) {return "ACTIVE-".$p->product_id;}, $products)
        ));

        if ($rp = $batch->getRequireProduct()){
            if ($rp && !array_intersect($rp, $have)) {
                $ids = [];
                foreach ($rp as $s)
                    if (preg_match('/^ACTIVE-(\d+)$/', $s, $args)) $ids[] = $args[1];
                if ($ids){
                    $error[] = '[' . $this->code . '] - ' . sprintf(___('Coupon can be used only if you have active subscription for these products: %s'), implode(', ', $this->getDi()->productTable->getProductTitles($ids)));
                }
                $ids = [];
                foreach ($rp as $s)
                    if (preg_match('/^EXPIRED-(\d+)$/', $s, $args)) $ids[] = $args[1];
                if ($ids){
                    $error[] = '[' . $this->code . '] - ' . sprintf(___('Coupon can be used only if you have expired subscription(s) for these products: %s'), implode(', ', $this->getDi()->productTable->getProductTitles($ids)));
                }
            }
        }
        if ($rp = $batch->getPreventIfProduct()){
            if ($rp && array_intersect($rp, $have)) {
                $ids = [];
                foreach ($rp as $s)
                    if (preg_match('/^ACTIVE-(\d+)$/', $s, $args)) $ids[] = $args[1];

                $ids = array_intersect($ids, $haveActiveIds);
                if ($ids)
                {
                    $error[] = '[' . $this->code . '] - ' . sprintf(___('Coupon cannot be used because you have active subscription(s) to: %s'), implode(', ', $this->getDi()->productTable->getProductTitles($ids)));
                }
                $ids = [];
                foreach ($rp as $s)
                    if (preg_match('/^EXPIRED-(\d+)$/', $s, $args)) $ids[] = $args[1];

                $ids = array_intersect($ids, $haveExpiredIds);
                if ($ids)
                {
                    $error[] = '[' . $this->code . '] - ' . sprintf(___('Coupon cannot be used because you have expired subscription(s) to: %s'), implode(', ',$this->getDi()->productTable->getProductTitles($ids)));
                }
            }
        }
        return $error;
    }

    public function update()
    {
        $hm = $this->getDi()->hook;
        if ($hm->have(Am_Event::COUPON_BEFORE_UPDATE))
        {
            $old = $this->getTable()->load($this->pk());
            $old->toggleFrozen(true);
            $hm->call(Am_Event::COUPON_BEFORE_UPDATE, ['coupon' => $this, 'old' => $old]);
        }
        parent::update();
        return $this;
    }
}

class CouponTable extends Am_Table_WithData
{
    protected $_key = 'coupon_id';
    protected $_table = '?_coupon';
    protected $_recordClass = 'Coupon';

    function generateCouponCode($length, &$new_length, $prefix = null)
    {
       $attempt = 0;
       do {
            $code = $prefix . strtoupper($this->getDi()->security->randomString($length, 'WERTYUPLKJHGFDSAZXCVBNM23456789'));
            //increase length of coupon
            //if can not generate unique
            //code for long time
            $attempt++;
            if ($attempt>2) {
                $attempt = 0;
                $length++;
            }
        } while ($this->findByCode($code));

        $new_length = $length;
        return $code;
    }
}