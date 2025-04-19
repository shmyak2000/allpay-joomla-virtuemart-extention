<?php
/**
 * @package    Allpay Payment Plugin for VirtueMart
 * @author     Allpay
 * @copyright  (C) 2025 Allpay.co.il. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://allpay.co.il
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
}

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class plgVmpaymentAllpay extends vmPSPlugin
{
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->_table = '#__virtuemart_payment_plg_allpay';

        $varsToPush = $this->getVarsToPush();
        $this->addVarsToPushCore($varsToPush, 1);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVarsToPush()
    {
        return array(
            'login' => array('', 'char'),
            'api_key' => array('', 'char'),
            'vat' => array('', 'int'),
            'installment_n' => array('', 'int'),
            'installment_min_order' => array('', 'int')
        );
    }

    public function plgVmConfirmedOrder($cart, $order)
    {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $order_id = $order['details']['BT']->virtuemart_order_id;
        $amount = number_format($order['details']['BT']->order_total, 2, '.', '');

        $items = [];
        foreach ($order['items'] as $item) {
            $items[] = [
                'name' => $item->order_item_name,
                'qty' => $item->product_quantity,
                'price' => number_format($item->product_final_price, 2, '.', ''),
                'vat' => (int)$method->vat
            ];
        }

        $vendorModel = VmModel::getModel('vendor');
        $vendor = $vendorModel->getVendor(1);
        $currencyCode = shopFunctions::getCurrencyByID($vendor->vendor_currency, 'currency_code_3');

        $langCode = strtoupper(substr($order['details']['BT']->order_language ?? '', 0, 2));

        $bt = $order['details']['BT'];

        $params = [
            'login' => $method->login,
            'order_id' => $order_id,
            'items' => $items,
            'currency' => $currencyCode,
            'lang' => $langCode,
            'client_name' => trim($bt->first_name . ' ' . $bt->last_name),
            'client_email' => $bt->email,
            'client_phone' => $bt->phone_1 ?? '',
            'notifications_url' => Uri::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&method=allpay',
            'success_url' => Uri::root() . 'index.php?option=com_virtuemart&view=cart&layout=orderdone',
            'backlink_url' => Uri::root()
        ];

        if($method->installment_n > 0 && ((int)$method->installment_min_order == 0 || $method->installment_min_order <= $order['details']['BT']->order_total)) {
			$params ['inst'] = (int)$method->installment_n;
		}

        $params['sign'] = $this->generateSignature($params, $method->api_key);

        $ch = curl_init('https://allpay.to/app/?show=getpayment&mode=api7');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            vmError('Allpay cURL error: ' . curl_error($ch));
            return null;
        }
        curl_close($ch);

        $response_data = json_decode($response, true);
        if (isset($response_data['payment_url'])) {
            $this->savePaymentData($order, $method);
            $cart->emptyCart();
            Factory::getApplication()->redirect($response_data['payment_url']);
            exit();
        } else {
            vmError('Allpay response error: ' . $response);
            return null;
        }
    }

    public function plgVmOnPaymentNotification()
    {
        $input = Factory::getApplication()->input;

        if ($input->getMethod() === 'POST') {
            $data = $input->post->getArray();
        } else {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
        }

        if (!$data || empty($data['sign'])) {
            return;
        }

        $order_id = (int)$data['order_id'];
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($order_id);

        $payment_method_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $method = $this->getVmPluginMethod($payment_method_id);

        if (!$method) {
            return;
        }

        $expectedSign = $this->generateSignature($data, $method->api_key);
        if ($expectedSign !== $data['sign']) {
            vmError('Allpay: неверная подпись уведомления');
            return;
        }

        if ((int)$data['status'] !== 1) {
            vmInfo('Allpay: статус не равен 1, оплата не подтверждена');
            return;
        }

        $order_id = $data['order_id'];
        $amount = $data['amount'];

        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($order_id);

        if (empty($order)) {
            vmError('Allpay: заказ не найден по номеру ' . $order_id);
            return;
        }

        $orderData = [
            'order_status' => 'C', // Confirmed
            'customer_notified' => 1,
            'comments' => 'Payment authorized via Allpay'
        ];

        $orderModel->updateStatusForOneOrder($order_id, $orderData, true);
        echo 'OK';
        exit();
    }
    

    private function generateSignature($params, $api_key)
    {
        ksort($params);
        $chunks = [];
        foreach($params as $k => $v) { 
            if(is_array($v)) {
                foreach ($v as $item) {
                    if (is_array($item)) {
                        ksort($item);
                        foreach($item as $name => $val) {
                            if (trim($val) !== '') {
                                $chunks[] = $val; 
                            }	 
                        }
                    }
                }
            } else {
                if (trim($v) !== '' && $k != 'sign') {
                    $chunks[] = $v; 
                }	                
            }
        }
        $signature = implode(':', $chunks) . ':' . $api_key;
        return hash('sha256', $signature);
    }

    protected function savePaymentData($order, $method)
    {
        $data = [
            'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
            'order_id' => $order['details']['BT']->order_number,
            'payment_id' => '',
            'amount' => $order['details']['BT']->order_total,
            'currency' => $order['details']['BT']->order_currency,
            'payment_name' => $this->renderPluginName($method)
        ];
        $this->storePSPluginInternalData($data);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        return $this->getOrderBEFields($virtuemart_order_id, $payment_method_id);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Allpay Payment Table');
    }

    public function getTableSQLFields()
    {
        return [
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'order_id' => 'varchar(64)',
            'payment_id' => 'varchar(64)',
            'amount' => 'decimal(15,5)',
            'currency' => 'char(3)',
            'payment_name' => 'varchar(255)'
        ];
    }

    public function getPluginMethodsType()
    {
        return 'payment';
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function plgVmOnStoreInstallPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }
}  
