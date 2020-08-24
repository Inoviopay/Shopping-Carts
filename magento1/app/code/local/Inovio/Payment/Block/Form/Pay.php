<?php

class Inovio_Payment_Block_Form_Pay extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('inovio/payment/form/pay.phtml');
        $inoviocard_logo = $this->getSkinUrl('images/inovio_cards.png');
        $this->setMethodLabelAfterHtml("<img src='$inoviocard_logo' />");
    }
}
