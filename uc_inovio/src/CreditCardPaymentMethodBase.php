<?php
namespace Drupal\uc_inovio;
/**
 * @file
 * Processes payments using Inovio.
 */
use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;
use Drupal\uc_inovio\InovioCore\InovioProcessor
as InovioProcessors;
use Drupal\uc_inovio\InovioCore\InovioConnection
as InovioConnections;
use Drupal\uc_inovio\InovioCore\InovioServiceConfig
as InovioServices;

/**
 * Defines a base credit card payment method plugin implementation.
 */
abstract class CreditCardPaymentMethodBase extends PaymentMethodPluginBase {

  /**
   * Returns the set of fields which are used by this payment method.
   *
   * @return array
   *   An array with keys 'cvv', 'owner', 'start', 'issue', 'bank' and 'type'.
   */
  public function getEnabledFields() {
    return [
      'cvv' => TRUE,
      'owner' => FALSE,
      'start' => FALSE,
      'issue' => FALSE,
      'bank' => FALSE,
      'type' => FALSE,
    ];
  }

  /**
   * Returns the set of card types which are used by this payment method.
   *
   * @return array
   *   An array with keys as needed by the chargeCard() method and values
   *   that can be displayed to the customer.
   */
  public function getEnabledTypes() {
    return [
      'visa' => $this->t('Visa'),
      'mastercard' => $this->t('MasterCard'),
      'discover' => $this->t('Discover'),
      'amex' => $this->t('American Express'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label = "") {
    $build['label'] = array(
      '#plain_text' => $label,
    );
    $cc_types = $this->getEnabledTypes();
    foreach ($cc_types as $type => $description) {
      $build['image'][$type] = array(
        '#theme' => 'image',
        '#uri' => drupal_get_path('module', 'uc_inovio') . '/images/' . $type . '.gif',
        '#alt' => $description,
        '#attributes' => array('class' => array('uc-credit-cctype', 'uc-credit-cctype-' . $type)),
      );
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function cartDetails(OrderInterface $order, array $form, FormStateInterface $form_state) {

    $form = array('#type' => 'container', '#attributes' => array('class' => 'uc-inovio-form'));
    $form['cc_policy'] = $this->ccPolicy();
    $form['#attached']['library'][] = 'uc_inovio/uc_inovio.direct';
    $form['#attached']['library'][] = 'uc_inovio/uc_inovio.styles';
    $order->payment_details = array();

    // Encrypted data in the session is
    // from the user returning from the review page.
    $session = \Drupal::service('session');
    if ($session->has('sescrd')) {
      $order->payment_details = $this->ucInovioCache($session->get('sescrd'));
      $form['payment_details_data'] = array(
        '#type' => 'hidden',
        '#value' => base64_encode($session->get('sescrd')),
      );
      $session->remove('sescrd');
    }
    elseif (isset($_POST['panes']['payment']['details']['payment_details_data'])) {
      // Copy any encrypted data that was POSTed in.
      $form['payment_details_data'] = array(
        '#type' => 'hidden',
        '#value' => $_POST['panes']['payment']['details']['payment_details_data'],
      );
    }

    $fields = $this->getEnabledFields();
    if (!empty($fields['type'])) {
      $form['cc_type'] = $this->ccType();
    }
    if (!empty($fields['owner'])) {
      $form['cc_owner'] = $this->ccOwner($order);
    }
    // Set up the default CC number on the credit card form.
    if (!isset($order->payment_details['cc_number'])) {
      $default_num = NULL;
    }
    elseif (!$this->validateCardNumber($order->payment_details['cc_number'])) {
      // Display the number as is if it
      // does not validate so it can be corrected.
      $default_num = $order->payment_details['cc_number'];
    }
    else {
      // Otherwise default to the last 4 digits.
      $default_num = $this->t('(Last 4) ') . substr($order->payment_details['cc_number'], -4);
    }
    $form['cc_number'] = $this->ccNumber($default_num = "");

    if (!empty($fields['start'])) {
      $form['#attached']['library'][] = 'uc_inovio/uc_inovio.direct';
      $form['#attached']['library'][] = 'uc_inovio/uc_inovio.styles';
      $form['cc_start_month'] = $this->startedMonth($order);
      $form['cc_start_year'] = $this->startedYear($order);
    }
    $form['cc_exp_month'] = $this->ccExpMonth($order);

    $form['cc_exp_year'] = $this->ccExpYear($order);

    if (!empty($fields['issue'])) {
      // Set up the default Issue Number on the credit card form.

      if (empty($order->payment_details['cc_issue'])) {
        $default_card_issue = NULL;
      }
      elseif (!$this->validateIssueNumber($order->payment_details['cc_issue'])) {
        // Display the Issue Number as is if it does not validate so it can be
        // corrected.
        $default_card_issue = $order->payment_details['cc_issue'];
      }
      else {
        // Otherwise mask it with dashes.
        $default_card_issue = str_repeat('-', strlen($order->payment_details['cc_issue']));
      }

      $form['cc_issue'] = $this->cartIssue($default_card_issue);
    }

    if (!empty($fields['cvv'])) {
      // Set up the default CVV on the credit card form.
      if (empty($order->payment_details['cc_cvv'])) {
        $default_cvv = NULL;
      }
      elseif (!$this->validateCvv($order->payment_details['cc_cvv'])) {
        // Display the CVV as is if it does not validate so it can be corrected.
        $default_cvv = $order->payment_details['cc_cvv'];
      }
      else {
        // Otherwise mask it with dashes.
        $default_cvv = str_repeat('-', strlen($order->payment_details['cc_cvv']));
      }
      $form['cc_cvv'] = $this->cartCVV($order, $default_cvv);
    }

    if (!empty($fields['bank'])) {
      $form['cc_bank'] = $this->ccBank($order);
    }

    return $form;
  }

  /**
   * Bank name for credit card number on checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array 
   *   form element
   */
  public function ccBank(OrderInterface $order) {
    return array(
      '#type' => 'textfield',
      '#title' => $this->t('Issuing bank'),
      '#default_value' => isset($order->payment_details['cc_bank']) ? $order->payment_details['cc_bank'] : '',
      '#attributes' => array('autocomplete' => 'off'),
      '#size' => 32,
      '#maxlength' => 64,
    );
  }

  /**
   * Credit card start month on checkout page.
   *  
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function startedMonth(OrderInterface $order) {
    $month = isset($order->payment_details['cc_start_month']) ? $order->payment_details['cc_start_month'] : NULL;
    $year = isset($order->payment_details['cc_start_year']) ? $order->payment_details['cc_start_year'] : NULL;
    $year_range = range(date('Y') - 10, date('Y'));
    return array(
      '#type' => 'number',
      '#title' => $this->t('Start date'),
      '#options' => array(
                  1 => $this->t('01 - January'),
                  2 => $this->t('02 - February'),
                  3 => $this->t('03 - March'),
                  4 => $this->t('04 - April'),
                  5 => $this->t('05 - May'),
                  6 => $this->t('06 - June'),
                  7 => $this->t('07 - July'),
                  8 => $this->t('08 - August'),
                  9 => $this->t('09 - September'),
                  10 => $this->t('10 - October'),
                  11 => $this->t('11 - November'),
                  12 => $this->t('12 - December'),
                ),
      '#default_value' => $month,
      '#required' => TRUE,
    );
  }

  /**
   * Credit card start year on checkout page.
   *  
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function startedYear(OrderInterface $order) {
    $year = isset($order->payment_details['cc_start_year']) ? $order->payment_details['cc_start_year'] : NULL;
    $year_range = range(date('Y') - 10, date('Y'));
    return array(
      '#type' => 'select',
      '#title' => $this->t('Start year'),
      '#title_display' => 'invisible',
      '#options' => array_combine($year_range, $year_range),
      '#default_value' => $year,
      '#field_suffix' => $this->t('(if present)'),
      '#required' => TRUE,
    );
  }

  /**
   * Credit card owner for cart review.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function ccOwner(OrderInterface $order) {
    return array(
      '#type' => 'textfield',
      '#title' => $this->t('Card owner'),
      '#default_value' => isset($order->payment_details['cc_owner']) ? $order->payment_details['cc_owner'] : '',
      '#attributes' => array('autocomplete' => 'off'),
      '#size' => 32,
      '#maxlength' => 64,
    );
  }

  /**
   * Credit card number on checkout page.
   * 
   * @param string $default_num
   *   Default credit card number
   * 
   * @return array
   *   form element
   */
  public function ccNumber($default_num = "") {
    return array(
      '#type' => 'textfield',
      '#title' => $this->t('Card number'),
      '#default_value' => $default_num,
      '#attributes' => array('autocomplete' => 'off', 'class' => array('inovio-card-number')),
      '#size' => 20,
      '#maxlength' => 19,
      '#required' => TRUE,
    );
  }

  /**
   * Credit card type on checkout page.
   * 
   * @return arary
   *   form element
   */
  public function ccType() {
    return array(
      '#type' => 'select',
      '#title' => $this->t('Card type'),
      '#options' => $this->getEnabledTypes(),
      '#default_value' => isset($order->payment_details['cc_type']) ? $order->payment_details['cc_type'] : NULL,
    );
  }

  /**
   * Cart policy text on checkout page.
   * 
   * @return array
   *   form element
   */
  public function ccPolicy() {
    
   $description = !empty($this->configuration['description']) ? $this->configuration['description'] :"";
    return array(
      '#prefix' => '<p>',
      '#markup' => $this->t($description),
      '#suffix' => '</p>',
    );
  }

  /**
   * Credit card expiry months on checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function ccExpMonth(OrderInterface $order) {
    $month = isset($order->payment_details['cc_start_month']) ? $order->payment_details['cc_start_month'] : NULL;
    return array(
      '#type' => 'select',
      '#title' => $this->t('Expiration date'),
      '#options' => array(
        1 => $this->t('01 - January'), 2 => $this->t('02 - February'),
        3 => $this->t('03 - March'), 4 => $this->t('04 - April'),
        5 => $this->t('05 - May'), 6 => $this->t('06 - June'),
        7 => $this->t('07 - July'), 8 => $this->t('08 - August'),
        9 => $this->t('09 - September'), 10 => $this->t('10 - October'),
        11 => $this->t('11 - November'), 12 => $this->t('12 - December'),
      ),
      '#default_value' => $month,
      '#required' => TRUE,
    );
  }

  /**
   * Credit card expiry on checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function ccExpYear(OrderInterface $order) {
    $year = isset($order->payment_details['cc_start_year']) ? $order->payment_details['cc_start_year'] : NULL;
    $year_range = range(date('Y') + 10, date('Y'));

    return array(
      '#type' => 'select',
      '#title' => $this->t('Expiration year'),
      '#title_display' => 'invisible',
      '#options' => array_combine($year_range, $year_range),
      '#default_value' => $year,
      '#field_suffix' => $this->t('(if present)'),
      '#required' => TRUE,
    );
  }

  /**
   * Check issue found on cart page.
   * 
   * @return array
   *   form element
   */
  public function cartIssue($default_card_issue = "") {

    return array(
      '#type' => 'textfield',
      '#title' => $this->t('Issue number'),
      '#default_value' => $default_card_issue,
      '#attributes' => array('autocomplete' => 'off'),
      '#size' => 2,
      '#maxlength' => 2,
      '#field_suffix' => $this->t('(if present)'),
    );
  }

  /**
   * On checkout page cvv number.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function cartCvv(OrderInterface $order, $default_cvv = "") {

    return array(
      '#type' => 'password',
      '#title' => $this->t('CVV'),
      '#default_value' => $default_cvv,
      '#attributes' => array('autocomplete' => 'off'),
      '#size' => 4,
      '#maxlength' => 4,
      '#required' => TRUE,
      '#attributes' => array('autocomplete' => 'off', 'class' => array('inovio-cvv-number')),
      '#field_suffix' => array(
        '#theme' => 'uc_inovio_cvv_help',
        '#method' => $order->getPaymentMethodId(),
      ),
    );
  }

  /**
   * It contains cart review for checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   form element
   */
  public function cartReview(OrderInterface $order) {

    $fields = $this->getEnabledFields();

    if (!empty($fields['type'])) {
      $review[] = array('title' => $this->t('Card type'), 'data' => $order->payment_details['cc_type']);
    }
    if (!empty($fields['owner'])) {
      $review[] = array('title' => $this->t('Card owner'), 'data' => $order->payment_details['cc_owner']);
    }
    $review[] = array('title' => $this->t('Card number'), 'data' => $this->displayCardNumber($order->payment_details['cc_number']));
    if (!empty($fields['start'])) {
      $start = $order->payment_details['cc_start_month'] . '/' . $order->payment_details['cc_start_year'];
      $review[] = array('title' => $this->t('Start date'), 'data' => strlen($start) > 1 ? $start : '');
    }
    $review[] = array('title' => $this->t('Expiration'), 'data' => $order->payment_details['cc_exp_month'] . '/' . $order->payment_details['cc_exp_year']);
    if (!empty($fields['issue'])) {
      $review[] = array('title' => $this->t('Issue number'), 'data' => $order->payment_details['cc_issue']);
    }
    if (!empty($fields['bank'])) {
      $review[] = array('title' => $this->t('Issuing bank'), 'data' => $order->payment_details['cc_bank']);
    }

    return $review;
  }

  /**
   * Use to order review page for checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return string 
   *   $build form element
   */
  public function orderView(OrderInterface $order) {
    $build = array();
    // Add the hidden span for the CC details if possible.
    $account = \Drupal::currentUser();
    if ($account->hasPermission('view cc details')) {
      $rows = array();

      if (!empty($order->payment_details['cc_type'])) {
        $rows[] = $this->t('Card type') . ': ' . $order->payment_details['cc_type'];
      }

      if (!empty($order->payment_details['cc_owner'])) {
        $rows[] = $this->t('Card owner') . ': ' . $order->payment_details['cc_owner'];
      }

      if (!empty($order->payment_details['cc_number'])) {
        $rows[] = $this->t('Card number') . ': ' . $this->displayCardNumber($order->payment_details['cc_number']);
      }

      if (!empty($order->payment_details['cc_start_month']) && !empty($order->payment_details['cc_start_year'])) {
        $rows[] = $this->t('Start date') . ': ' . $order->payment_details['cc_start_month'] . '/' . $order->payment_details['cc_start_year'];
      }

      if (!empty($order->payment_details['cc_exp_month']) && !empty($order->payment_details['cc_exp_year'])) {
        $rows[] = $this->t('Expiration') . ': ' . $order->payment_details['cc_exp_month'] . '/' . $order->payment_details['cc_exp_year'];
      }

      if (!empty($order->payment_details['cc_issue'])) {
        $rows[] = $this->t('Issue number') . ': ' . $order->payment_details['cc_issue'];
      }

      if (!empty($order->payment_details['cc_bank'])) {
        $rows[] = $this->t('Issuing bank') . ': ' . $order->payment_details['cc_bank'];
      }

      $build['cc_info'] = array(
        '#markup' => implode('<br />', $rows) . '<br />',
      );
    }

    return $build;
  }

  /**
   * Use to see customer view for checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return string 
   *   $build
   */
  public function customerView(OrderInterface $order) {
    $build = array();

    if (!empty($order->payment_details['cc_number'])) {
      $build['#markup'] = $this->t('Card number') . ':<br />' . $this->displayCardNumber($order->payment_details['cc_number']);
    }

    return $build;
  }

  /**
   * Use to cart process for checkout page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @param array $form
   *   Form element
   * 
   * @param FormStateInterface $form_state
   *   Form state for checkout data
   * 
   * @return null
   *   null
   */
  public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state) {
    if (!$form_state->hasValue(['panes', 'payment', 'details', 'cc_number'])) {
      return;
    }

    $fields = $this->getEnabledFields();

    // Fetch the CC details from the $_POST directly.
    $cc_data = $form_state->getValue(['panes', 'payment', 'details']);
    $cc_data['cc_number'] = str_replace(' ', '', $cc_data['cc_number']);

    // Recover cached CC data in form state, if it exists.
    if (isset($cc_data['payment_details_data'])) {
      $cache = $this->ucInovioCache(base64_decode($cc_data['payment_details_data']));
      unset($cc_data['payment_details_data']);
    }

    // Account for partial CC numbers when masked by the system.
    if (substr($cc_data['cc_number'], 0, strlen(t('(Last4)'))) == $this->t('(Last4)')) {
      // Recover the number from the encrypted data in the form if truncated.
      if (isset($cache['cc_number'])) {
        $cc_data['cc_number'] = $cache['cc_number'];
      }
      else {
        $cc_data['cc_number'] = '';
      }
    }

    // Account for masked CVV numbers.
    if (!empty($cc_data['cc_cvv']) && $cc_data['cc_cvv'] == str_repeat('-', strlen($cc_data['cc_cvv']))) {
      // Recover the number from the encrypted data in $_POST if truncated.
      if (isset($cache['cc_cvv'])) {
        $cc_data['cc_cvv'] = $cache['cc_cvv'];
      }
      else {
        $cc_data['cc_cvv'] = '';
      }
    }

    // Go ahead and put the CC data in the payment details array.
    $order->payment_details = $cc_data;

    // Default our value for validation.
    $return = TRUE;

    // Make sure an owner value was entered.
    if (!empty($fields['owner']) && empty($cc_data['cc_owner'])) {
      $form_state->setErrorByName('panes][payment][details][cc_owner');
      $return = FALSE;
    }

    // Validate the credit card number.
    if (!$this->validateCardNumber($cc_data['cc_number'])) {
      $form_state->setErrorByName('panes][payment][details][cc_number');
      $return = FALSE;
    }

    // Validate the start date (if entered).
    if (!empty($fields['start']) && !$this->validateStartDate($cc_data['cc_start_month'], $cc_data['cc_start_year'])) {
      $form_state->setErrorByName('panes][payment][details][cc_start_month');
      $form_state->setErrorByName('panes][payment][details][cc_start_year');
      $return = FALSE;
    }

    // Validate the card expiration date.
    if (!$this->validateExpirationDate($cc_data['cc_exp_month'], $cc_data['cc_exp_year'])) {
      $form_state->setErrorByName('panes][payment][details][cc_exp_month');
      $form_state->setErrorByName('panes][payment][details][cc_exp_year');
      $return = FALSE;
    }

    // Validate the issue number (if entered). With issue numbers, '01' is
    // different from '1', but is_numeric() is still appropriate.
    if (!empty($fields['issue']) && !$this->validateIssueNumber($cc_data['cc_issue'])) {
      $form_state->setErrorByName('panes][payment][details][cc_issue', $this->t('The issue number you entered is invalid.'));
      $return = FALSE;
    }

    // Validate the CVV number if enabled.
    if (!empty($fields['cvv']) && !$this->validateCvv($cc_data['cc_cvv'])) {
      $form_state->setErrorByName('panes][payment][details][cc_cvv', $this->t('You have entered an invalid CVV number.'));
      $return = FALSE;
    }

    // Validate the bank name if enabled.
    if (!empty($fields['bank']) && empty($cc_data['cc_bank'])) {
      $form_state->setErrorByName('panes][payment][details][cc_bank', $this->t('You must enter the issuing bank for that card.'));
      $return = FALSE;
    }
    $crypt = \Drupal::service('uc_store.encryption');

    // Store the encrypted details in the session for the next pageload.
    // We are using base64_encode() because the encrypt function works with a
    // limited set of characters, not supporting the full Unicode character
    // set or even extended ASCII characters that may be present.
    // base64_encode() converts everything to a subset of ASCII, ensuring that
    // the encryption algorithm does not mangle names.
    $session = \Drupal::service('session');
    $session->set('sescrd', $crypt->encrypt("", base64_encode(serialize($order->payment_details))));

    // Log any errors to the watchdog.
    uc_store_encryption_errors($crypt, 'uc_inovio');

    return $return;
  }

  /**
   * Use to load order for review page.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   */
  public function orderLoad(OrderInterface $order) {
    // Load the CC details from the credit cache if available.
    $order->payment_details = $this->ucInovioCache();

    // Otherwise load any details that might be stored in the data array.
    if (empty($order->payment_details) && isset($order->data->cc_data)) {
      $order->payment_details = $this->ucInovioCache($order->data->cc_data);
    }
  }

  /**
   * Use to save order so that after load data not goes out.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   */
  public function orderSave(OrderInterface $order) {
    // Check on update and status with canceled.
    $option = filter_input(INPUT_POST, 'op');
    $status = filter_input(INPUT_POST, 'status');

    if ($option == "Update" && $status == "canceled") {
      $query = "SELECT data FROM uc_orders WHERE order_id=" . $order->id();
      $datas = db_query($query)->fetchField();
      $data = unserialize($datas);
      $this->inovioRefund($order, $data['transaction_id'], $order->getPaymentMethodId());
    }
    // Save only some limited, PCI compliant data.
    $cc_data = $order->payment_details;
    // Stuff the serialized and encrypted CC details into the array.
    $crypt = \Drupal::service('uc_store.encryption');
    $order->data->cc_data = $crypt->encrypt("", base64_encode(serialize($cc_data)));
    uc_store_encryption_errors($crypt, 'uc_inovio');
  }

  /**
   * Use to submit order and process for payment.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return string
   *   string
   */
  public function orderSubmit(OrderInterface $order) {
    // Attempt to process the credit card payment.
    if (!$this->processPayment($order, $order->getTotal())) {
      return $this->t('We were unable to process your credit card payment. Please verify your details and try again.');
    }
  }

  /**
   * Refund the amount through inovio gateway.
   * 
   * @param inot $transaction_id
   *   Transaction Id from API
   * 
   * @param string $paymentmethod
   *   Set payment method for core SDK
   * 
   * @param object $order
   *   Order data as object to get order id
   * 
   * @return TRUE|FALSE
   *   TRUE|FALSE
   */
  public function inovioRefund(OrderInterface $order, $transaction_id = 0, $paymentmethod = "") {

    try {
      if ($transaction_id <= 0) {
        drupal_set_message('Invalid transaction id.', 'error');
        return FALSE;
      }
      if ($paymentmethod != 'inovio_payment_gateway') {
        drupal_set_message('Invalid Payment method.', 'error');
        return FALSE;
      }
      $params = array_merge($this->setGatewayInformation(), array('request_ref_po_id' => $transaction_id, 'credit_on_fail' => 1));
      $parseresult = $this->setApiMethodAndgetResponse('ccreverse', $params);

      if (isset($parseresult->TRANS_STATUS_NAME) && $parseresult->TRANS_STATUS_NAME == "APPROVED") {
        drupal_set_message("Order has been successfully cancelled/refunded through Inovio Payment Gateway", "success");
        uc_order_comment_save($order->id(), 0, $this->t('Order have been refunded through Inovio Payment Gateway.'));
        // Log message if debug is on.
        if ($this->configuration['debug'] == 1) {
          \Drupal::logger('uc_inovio')->info('Successfully refund parameters @info', ['@info' => print_r($parseresult, TRUE)]);
        }
      }
      else {
        drupal_set_message("Order Already reversed or canceled", "error");
        // Log message if debug is on.
        if ($this->configuration['debug'] == 1) {
          \Drupal::logger('uc_inovio')->info('Failed paramters for refund @info', ['@info' =>
            print_r($parseresult, TRUE)]);
        }
        return FALSE;
      }
    }
    catch (Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  /**
   * Process a payment through the credit card gateway.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @param float $amount
   *   Order amount data
   * 
   * @param string $reference
   *   Reference
   * 
   * @return TRUE|FALSE
   *   true or false
   */
  public function processPayment(OrderInterface $order, $amount = NULL, $reference = NULL) {

    $this->orderLoad($order);
    try {

      if ($this->restrictQuantity($order) == false) {
        drupal_set_message("For any single product's quantity should not be greater than "
          . $this->configuration['inovio_product_quantity_restriction']
          ." Please go to cart page and update product's quantity.", 'error');
        return FALSE;
      }

      if ($this->merchantAuthenticate() == FALSE) {
        drupal_set_message('Something went wrong, Please contact to your service provider.', 'error');
        return FALSE;
      }
      else {
        $authandcaptureparams = array_merge(
                $this->setGatewayInformation(), $this->cartParams($order), $this->cartItems($order)) + $this->getAdvaceparam();
        $parseresult = $this->setApiMethodAndgetResponse('authAndCapture', $authandcaptureparams);

        // Check if status approved.
        if (isset($parseresult->TRANS_STATUS_NAME) && 'APPROVED' == $parseresult->TRANS_STATUS_NAME) {

          // Build a message for display and comments in the payments table.
          $message = $this->t('Type: @type ID: @id', ['@type' => 'Auth and Capture', '@id' => $parseresult->TRANS_ID]);
          $result = array(
            'success' => TRUE,
            'comment' => $message,
            'message' => $message,
            'data' => array(
              'module' => 'uc_inovio', 'txn_type' => 'auth and capture',
              'txn_id' => $parseresult->TRANS_ID, 'txn_authcode' => $parseresult->PO_ID,
            ),
            'uid' => $order->getOwnerId(),
          );
          // Save order related data bases of transaction ID.

          $this->ucInovioLogPriorAuthCapture($order->id(), $parseresult->PO_ID);
          // Check debug enable the log.
          if ($this->configuration['debug'] == 1) {
            // Log if status approved.
            \Drupal::logger('uc_inovio')->info('success parameters for Auth and Capture@info', ['@info' => print_r($parseresult, TRUE)]);
          }
        }
  //check API Advice, Service Advice and Transaction status 
  else if (isset($parseresult->SERVICE_RESPONSE) && $parseresult->SERVICE_RESPONSE == 500 && !empty($parseresult->SERVICE_ADVICE))
  {
    // Check debug enable the log.
    if ($this->configuration['debug'] == 1)
    {
      // Log if status approved.
      \Drupal::logger('uc_inovio')->info('Credit card not support @info', ['@info' => print_r($parseresult, TRUE)]);
    }
    drupal_set_message('Credit card type does not support, Please contact to your service provider', 'error');
    return FALSE;
  }
        else {
          drupal_set_message('Something went wrong, Please contact to your service provider.', 'error');
          // Check debug enable the log.
          if ($this->configuration['debug'] == 1) {

            \Drupal::logger('uc_inovio')->info('Failed Parameters for Auth and Capture @info', ['@info' => print_r($parseresult, TRUE)]);
          }
          return FALSE;
        }
        // Build an admin order comment.
        $comment = $this->t('<b>@type</b><br /><b>@status:</b> @message<br />Amount: @amount<br />', [
          '@type' => 'PaymentMethod : Auth And Capture<br />',
          '@status' => "Status: " . $parseresult->TRANS_STATUS_NAME ? $this->t('APPROVED') : $this->t('REJECTED') . "<br />",
          '@message' => "Transaction ID:" . $parseresult->TRANS_ID . "<br />",
          '@amount' => $amount
        ]);

        // Save the comment to the order.
        $this->ucInovioOrderCommentSave($order->id(), $order->getOwnerId(), $comment, 'admin');
      }
    }
    catch (Exception $ex) {
      drupal_set_message($ex->getMessage(), 'error');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Logs the capture of a prior authorization to an order's data array.
   *
   * @param int $order_id
   *   Order Id on capture data
   * 
   *   The order associated with the credit card capture.
   * 
   * @param string $transaction_id
   *   Transaction id for auth and capture   
   *   The payment service's ID for the authorization that was captured.
   *
   * @return array
   *   The entire updated data array for the order or FALSE to indicate the
   *   specified authorization was not found.
   */
  public function ucInovioLogPriorAuthCapture($order_id = NULL, $transaction_id = NULL) {
    // Load the existing order data array.
    $query = "SELECT data FROM uc_orders WHERE order_id=" . $order_id;
    $data = db_query($query)->fetchField();
    $data = unserialize($data);
    $data['transaction_id'] = $transaction_id;
    // Otherwise log the capture timestamp to the authorization.
    $data['cc_txns']['inovio']['captured'] = REQUEST_TIME;
    // Save the updated data array to the database.
    db_update('uc_orders')
        ->fields(array('data' => serialize($data)))
        ->condition('order_id', $order_id)
        ->execute();

    return $data;
  }

  /**
   * Inserts a comment, $type being either 'order' or 'admin'.
   * 
   * @param int $order_id
   *   Order Id to save comment
   * 
   * @param int $uid
   *   User id
   * 
   * @param string $message
   *   Save message after order complete
   * 
   * @param string $type
   *   Type as admin
   * 
   * @param string $status
   *   Status as pending
   * 
   * @param TRUE|FALSE $notify
   *   Notify use as true or false
   */
  public function ucInovioOrderCommentSave($order_id = NULL, $uid = NULL, $message = "", $type = 'admin', $status = 'pending', $notify = FALSE) {
    if ($type == 'admin') {
      db_insert('uc_order_admin_comments')
          ->fields(array(
            'order_id' => $order_id,
            'uid' => $uid,
            'message' => $message,
            'created' => REQUEST_TIME,
          ))
          ->execute();
    }
    elseif ($type == 'order') {
      db_insert('uc_order_comments')
          ->fields(array(
            'order_id' => $order_id,
            'uid' => $uid,
            'message' => $message,
            'order_status' => $status,
            'notified' => $notify ? 1 : 0,
            'created' => REQUEST_TIME,
          ))
          ->execute();
    }
  }

  /**
   * Use to validate mercahnt information.
   * 
   * @return TRUE|FALSE
   *   TRUE|FALSE
   */
  public function merchantAuthenticate() {
    $parseresult = $this->setApiMethodAndgetResponse('authenticate', $this->setGatewayInformation());
    if ($parseresult->SERVICE_RESPONSE != 100) {
      \Drupal::logger('uc_inovio')->info('merchant Authentication failed @info', ['@info' => print_r($parseresult, TRUE)]);
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * For single product's qunantity should not be greater than 99.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   get form data
   */
  public function restrictQuantity(OrderInterface $order) {

     $returnstate = TRUE;
    // Get the cart Items.
  
      foreach ($order->products as $product) {

        if($product->qty->value > $this->configuration['inovio_product_quantity_restriction']) {
          $returnstate = FALSE;
        }

      }
    return $returnstate;
  }

  /**
   * Use to get advance fields from checkout page.
   * 
   * @return array
   *   get form data from checkout page
   */
  public function getAdvaceparam() {

    return
        array(
          $this->configuration['advancetab']['APIkey1'] => $this->configuration['advancetab']['APIvalue1'],
          $this->configuration['advancetab']['APIkey2'] => $this->configuration['advancetab']['APIvalue2'],
          $this->configuration['advancetab']['APIkey3'] => $this->configuration['advancetab']['APIvalue3'],
          $this->configuration['advancetab']['APIkey4'] => $this->configuration['advancetab']['APIvalue4'],
          $this->configuration['advancetab']['APIkey5'] => $this->configuration['advancetab']['APIvalue5'],
          $this->configuration['advancetab']['APIkey6'] => $this->configuration['advancetab']['APIvalue6'],
          $this->configuration['advancetab']['APIkey7'] => $this->configuration['advancetab']['APIvalue7'],
          $this->configuration['advancetab']['APIkey8'] => $this->configuration['advancetab']['APIvalue8'],
          $this->configuration['advancetab']['APIkey9'] => $this->configuration['advancetab']['APIvalue9'],
          $this->configuration['advancetab']['APIkey10'] => $this->configuration['advancetab']['APIvalue10'],
        );
  }

  /**
   * Cart parameters to process payment.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   get form data
   */
  public function cartParams(OrderInterface $order) {
    $order->getDisplayLineItems();
    $billing_address = $order->getAddress('billing');
   
    $delivery_address = $order->getAddress('delivery');
  
    $user_address = [
      // We have changed.
      "bill_addr" => substr($billing_address->street1, 0, 60) .substr($billing_address->street2, 0, 60),
      "pmt_numb" => $order->payment_details['cc_number'],
      "pmt_key" => $order->payment_details['cc_cvv'],
      // "xtl_ip" => \Drupal::request()->getClientIp(),
      "xtl_ip" => '127.0.0.1',
      "cust_fname" => $billing_address->getFirstName(),
      "cust_lname" => $billing_address->getLastName(),
      "pmt_expiry" => strlen($order->payment_details['cc_exp_month']) < 2 ? (
          '0' . $order->payment_details['cc_exp_month'] . $order->payment_details['cc_exp_year']) : $order->payment_details['cc_exp_month'] . $order->payment_details['cc_exp_year'],
      "cust_email" => $order->getEmail(),
      "bill_addr_zip" => $billing_address->getPostalCode(),
      "bill_addr_city" => $billing_address->getCity(),
      "bill_addr_state" => $billing_address->getZone(),
      "request_currency" => $order->getCurrency(),
      "bill_addr_country" => $billing_address->getCountry(),
      "ship_addr_country" => $delivery_address->getCountry(),
      "ship_addr_city" => $delivery_address->getCity(),
      "ship_addr_state" => $delivery_address->getZone(),
      "ship_addr_zip" => $delivery_address->getPostalCode(),
      "ship_addr" =>substr($delivery_address->street1, 0, 60) . substr($delivery_address->street2, 0, 60),
    ];

    return $user_address;

  }

  /**
   * Cart items like product name, id and quantity.
   * 
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order that is being processed.
   * 
   * @return array
   *   get form data
   */
  public function cartItems(OrderInterface $order) {

 
    $prodid = 1;
    $qnty = 1;
    $pricecount = 1;
    $final_array = [];
    // Get the cart Items.
    foreach ($order->products as $product) {
      // Static because client has told that use only 41241 id.
      $final_array['li_prod_id_' . $prodid++] = $this->configuration['inovio_product_id'];
      $final_array['li_count_' . $qnty++] = $product->qty->value;
      $final_array['li_value_' . $pricecount++] = uc_currency_format($product->price->value, FALSE, FALSE, '.');
    }
    return $final_array;
  }

  /**
   * Set method for core SDK and get response.
   * 
   * @param string $methodname
   *   Methodname as string for core SDK
   * 
   * @param array $requestparams
   *   requestParams as parameters for core SDK
   * 
   * @return string
   *   get order data 
   */
  public function setApiMethodAndgetResponse($methodname = "", $requestparams = array()) {
    // Create connection for InovioService.
    $configservices = new InovioServices();
    // Create connection for Inovio Processor.
    $processors = new InovioProcessors();
    // Create connection for InovioConnections.
    $connections = new InovioConnections();
    $configservices->serviceConfig($requestparams, $connections);
    $processors->setServiceConfig($configservices);
    $response = $processors->setMethodName($methodname)->getResponse();
    return json_decode($response);
  }

  /**
   * Use to set Inovio initial requeired parameters.
   * 
   * @return array
   *   get form data
   */
  public function setGatewayInformation() {
  
        $requestParams = array(
          'end_point' => $this->configuration['api_endpoint'],
          'site_id' => $this->configuration['site_id'],
          'req_username' => $this->configuration['req_username'],
          'req_password' => $this->configuration['req_password'],
          'request_response_format' => 'json',
        );

        $finalRequestParams = [];
      
        foreach ($requestParams as $reqKey => $reqParamVal) {
          if (empty($requestParams[$reqKey])) {
              drupal_set_message('Somethig went wrong, please contact to your service provider.');
              exit;   
          }

          $finalRequestParams[$reqKey] = trim($reqParamVal);
      }
    
      return $finalRequestParams;
  }

  /**
   * Returns a credit card number with appropriate masking.
   *
   * @param string $number
   *   Credit card number as a string.
   *
   * @return string
   *   Masked credit card number - just the last four digits.
   */
  protected function displayCardNumber($number = NULL) {
    if (strlen($number) == 4) {
      return t('(Last 4)') . $number;
    }

    return str_repeat('-', 12) . substr($number, -4);
  }

  /**
   * Validates a credit card number during checkout.
   *
   * @param string $number
   *   Credit card number as a string.
   *
   * @return bool
   *   TRUE if card number is valid according to the Luhn algorithm.
   */
  protected function validateCardNumber($number = NULL) {
    $id = substr($number, 0, 1);
    $types = $this->getEnabledTypes();
    if (($id == 3 && empty($types['amex'])) || ($id == 4 && empty($types['visa'])) || ($id == 5 && empty($types['mastercard'])) ||
        ($id == 6 && empty($types['discover'])) || !ctype_digit($number)) {
      return FALSE;
    }
    $total = 0;
    for ($i = 0; $i < strlen($number); $i++) {
      $digit = substr($number, $i, 1);
      if ((strlen($number) - $i - 1) % 2) {
        $digit *= 2;
        if ($digit > 9) {
          $digit -= 9;
        }
      }
      $total += $digit;
    }

    if ($total % 10 != 0) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates a CVV number during checkout.
   *
   * @param string $cvv
   *   CVV number as a string.
   *
   * @return bool
   *   TRUE if CVV has the correct number of digits.
   */
  protected function validateCvv($cvv = NULL) {
    $digits = array();

    $types = $this->getEnabledTypes();
    if (!empty($types['visa']) ||
        !empty($types['mastercard']) ||
        !empty($types['discover'])) {
      $digits[] = 3;
    }
    if (!empty($types['amex'])) {
      $digits[] = 4;
    }

    // Fail validation if it's non-numeric or an incorrect length.
    if (!is_numeric($cvv) || (count($digits) > 0 && !in_array(strlen($cvv), $digits))) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Validates a start date on a card.
   *
   * @param int $month
   *   The 1 or 2-digit numeric representation of the month, i.e. 1, 6, 12.
   * @param int $year
   *   The 4-digit numeric representation of the year, i.e. 2008.
   *
   * @return bool
   *   TRUE for cards whose start date is blank (both month and year) or in the
   *   past, FALSE otherwise.
   */
  protected function validateStartDate($month = NULL, $year = NULL) {
    if (empty($month) && empty($year)) {
      return TRUE;
    }

    if (empty($month) || empty($year)) {
      return FALSE;
    }

    if ($year > date('Y')) {
      return FALSE;
    }
    elseif ($year == date('Y')) {
      if ($month > date('n')) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Validates an expiration date on a card.
   *
   * @param int $month
   *   The 1 or 2-digit numeric representation of the month, i.e. 1, 6, 12.
   * @param int $year
   *   The 4-digit numeric representation of the year, i.e. 2008.
   *
   * @return bool
   *   TRUE if expiration date is in the future, FALSE otherwise.
   */
  protected function validateExpirationDate($month = NULL, $year = NULL) {
    if ($year < date('Y')) {
      return FALSE;
    }
    elseif ($year == date('Y')) {
      if ($month < date('n')) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates an issue number on a card.
   *
   * @param string $issue
   *   The issue number.
   *
   * @return bool
   *   TRUE if the issue number if valid, FALSE otherwise.
   */
  protected function validateIssueNumber($issue = NULL) {
    if (empty($issue) || (is_numeric($issue) && $issue > 0)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Store data into cache.
   * 
   * @param string $data
   *   Data check cache
   * 
   * @param bool $encrypted
   *   TRUE|FALSE
   * 
   * @return json
   *   json
   */
  public function ucInovioCache($data = NULL, $encrypted = TRUE) {
    // The CC data will be stored in this static variable.
    $cache = &drupal_static(__FUNCTION__, array());

    if ($data) {
      if ($encrypted) {
        // Initialize the encryption key and class.
        $crypt = \Drupal::service('uc_store.encryption');

        // Save the unencrypted CC details for the duration of this request.
        $data = unserialize(base64_decode($crypt->decrypt("", $data)));
      }
      $cache = $data;
    }

    return $cache;
  }

}
