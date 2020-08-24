<?php
/**
 * Platform payment method Cctypes
 *
 * @category    Payment module
 * @package     payment
 * @author      Chetu India Team
 */
namespace Inovio\Extension\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC', 'AE', 'DI', 'JCB', 'OT');
    }
}
