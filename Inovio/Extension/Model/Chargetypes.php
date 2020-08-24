<?php
namespace Inovio\Extension\Model;

/**
 * Platform payment method model Chargetypes
 *
 * @category    Payment
 * @author      Chetu India Team
 */

class Chargetypes extends \Magento\Payment\Model\Method\AbstractMethod
{
    const AVS = 'avsonly';
    const CHARGE = 'sale';    /* For Sale or Direct Payment */
    const AUTHONLY = 'authonly';
    const AUTHCOMPLETE = 'authcomplete';
    const REFUND = 'refund';
    const VOID = 'void';
    const PAYMENT_AUTHORIZE = 'authorize';
    const PAYMENT_AUTHORIZE_CAPTURE = 'authorize_capture';
    const PARTIALREVERSAL = 'partialreversal';

    /**
     * Override toOptionArray to set value into zeeamster payment
     * configuration in Magento Admin
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::PAYMENT_AUTHORIZE_CAPTURE, 'label' => 'Charge Now'),
            array('value' => self::PAYMENT_AUTHORIZE, 'label' => 'Auth Only'),
        );
    }

    /**
     *
     *  Void Transaction Conditions
     *
     * @return array
     */
    public static function voidableTransactions()
    {
        return array(self::AVS, self::AUTHONLY, self::REFUND, self::AUTHCOMPLETE);
    }
}
