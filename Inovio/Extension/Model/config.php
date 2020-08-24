<?php
namespace Inovio\Extension\Model;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use Inovio\Extension\Model\Extension;

/**
 * Config file to retrieve data from
 * magento payment configuration table for inovio_extension
 *
 * @category    payment
 * @package     Inovio_Extension
 * @author      Chetu India Team
 */
class Config
{

    /**
     * @var string
     */
    protected $methodCode = Extension::CODE;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve information from payment configuration table
     *
     * @param string $field
     *
     * @return string
     */
    public function getPaymentConfigData($field)
    {
        $code = $this->methodCode;

        $path = 'payment/' . $code . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
