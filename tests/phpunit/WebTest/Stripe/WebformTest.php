<?php

require_once 'tests/phpunit/CiviTest/CiviSeleniumTestCase.php';

// Bootstrap Drupal.
$currentPath = getcwd();
$drupalRoot = explode('/sites/all/', $currentPath)[0];
set_include_path($drupalRoot . PATH_SEPARATOR . get_include_path());
define('DRUPAL_ROOT', $drupalRoot);
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

/**
 * Class WebTest_Event_AddEventTest
 */
class WebTest_Stripe_WebformTest extends CiviSeleniumTestCase {

  protected function setUp() {
    parent::setUp();
  }

  public function testWebformPayment() {
    $this->installPaymentWebform();
    $webformNode = $this->getWebformNode();
    $this->assertNotNull($webformNode->nid);

    $paymentData = $this->getPaymentTestData();

    $this->open($this->sboxPath . '/page/payment-test');
    $this->type('edit-submitted-civicrm-1-contact-1-fieldset-fieldset-civicrm-1-contact-1-email-email', $paymentData['email']);
    $this->click('op');
    $this->waitForElementPresent('credit_card_number');
    $this->type('credit_card_number', $paymentData['credit_card_number']);
    $this->type('cvv2', $paymentData['cvv2']);
    $this->select('credit_card_exp_date[M]', 'value=' . $paymentData['credit_card_exp_date_month']);
    $this->select('credit_card_exp_date[Y]', 'value=' . $paymentData['credit_card_exp_date_year']);
    $this->type('billing_first_name', $paymentData['billing_first_name']);
    $this->type('billing_last_name', $paymentData['billing_last_name']);
    $this->type('billing_street_address-5', $paymentData['billing_street_address']);
    $this->type('billing_city-5', $paymentData['billing_city']);
    $this->select('billing_country_id-5', 'value=' . $paymentData['billing_country_id']);
    $this->select('billing_state_province_id-5', 'value=' . $paymentData['billing_state_province_id']);
    sleep(5);
    $this->type('billing_postal_code-5', $paymentData['billing_postal_code']);
    $this->click("xpath=//form[@id='webform-client-form-{$webformNode->nid}']/div/div[@class='form-actions']/input[@value='Submit']");

    $this->waitForElementPresent("xpath=//div[@class='webform-confirmation']");
    $this->assertElementContainsText("xpath=//div[@class='webform-confirmation']", "Thank you, your submission has been received.");

    $this->screenshot('tests/printscreen/webform-payment-complete.png');
    node_delete($webformNode->nid);
  }

  public function testCiviCRMContributionPayment() {
    $paymentData = $this->getPaymentTestData();

    $financialTypeId = $this->getFinancialTypeId();
    $this->assertNotNull($financialTypeId);
    
    $this->webtestLogin();
    $stripeProcessorId = $this->getStripeProcessorId();
    $this->assertNotNull($stripeProcessorId);

    $random = substr(sha1(rand()), 0, 7);
    $this->webtestAddContact($random, 'Jameson', $random . '@jameson.name');

    $contact = civicrm_api3('Contact', 'get', [
      'sequential' => 1,
      'email' => $random . '@jameson.name',
    ]);
    $contactId = $contact['values'][0]['contact_id'];
    
    $this->open($this->sboxPath . "/civicrm/contact/view/contribution?reset=1&action=add&cid={$contactId}&context=contribution&mode=live");

    $this->waitForElementPresent("xpath=//form[@id='Contribution']");
    $this->select('payment_processor_id', 'value=' . $stripeProcessorId);
    $this->select('financial_type_id', 'value=' . $financialTypeId);
    $this->type('total_amount', '1');
    $this->select('credit_card_type', 'value=Visa');
    $this->type('credit_card_number', $paymentData['credit_card_number']);
    $this->type('cvv2', $paymentData['cvv2']);
    $this->select('credit_card_exp_date[M]', 'value=' . $paymentData['credit_card_exp_date_month']);
    $this->select('credit_card_exp_date[Y]', 'value=' . $paymentData['credit_card_exp_date_year']);
    $this->type('billing_street_address-5', $paymentData['billing_street_address']);
    $this->type('billing_city-5', $paymentData['billing_city']);
    $this->select('billing_country_id-5', 'value=' . $paymentData['billing_country_id']);
    sleep(5);
    $this->select('billing_state_province_id-5', 'value=' . $paymentData['billing_state_province_id']);
    $this->type('billing_postal_code-5', $paymentData['billing_postal_code']);
    $this->click('_qf_Contribution_upload');

    $this->waitForElementPresent("xpath=//div[@id='crm-notification-container']/div/div[@class='notify-content']");
    $this->assertElementContainsText("xpath=//div[@id='crm-notification-container']/div/div[@class='notify-content']", "The transaction record has been processed.");

    $this->screenshot('tests/printscreen/civicrm-payment-complete.png');
  }

  private function getPaymentTestData() {
    return [
      'email' => 'test@localhost.net',
      'credit_card_number' => '4242424242424242',
      'cvv2' => 111,
      'credit_card_exp_date_month' => 12,
      'credit_card_exp_date_year' => 2027,
      'billing_first_name' => 'Test First Name',
      'billing_last_name' => 'Test Last Name',
      'billing_street_address' => 'Test Street Address',
      'billing_city' => 'Test City',
      'billing_country_id' => 1226, // UK
      'billing_state_province_id' => 9999, // London
      'billing_postal_code' => 'ABC',
    ];
  }

  private function getStripeProcessorId() {
    $stripeProcessorType = civicrm_api3('PaymentProcessorType', 'get', [
      'sequential' => 1,
      'name' => 'Stripe',
    ]);
    if (empty($stripeProcessorType['values'])) {
      return NULL;
    }
    $stripeProcessor = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'payment_processor_type_id' => $stripeProcessorType['values'][0]['id'],
      'is_test' => 0,
    ]);
    if (empty($stripeProcessor['values'])) {
      return NULL;
    }
    return $stripeProcessor['values'][0]['id'];
  }

  private function getFinancialTypeId() {
    $financialTypes = civicrm_api3('FinancialType', 'get', array(
      'sequential' => 1,
      'is_active' => 1,
    ));
    if (empty($financialTypes['values'])) {
      return NULL;
    }
    return $financialTypes['values'][0]['id'];
  }

  private function installPaymentWebform() {
    exec('drush node-export-import --uid=1 < tests/webform/payment_webform.node');
  }

  private function getWebformNode() {
    $alias = 'page/payment-test';
    $path = drupal_lookup_path('source', $alias); 
    $node = menu_get_object('node', 1, $path);
    return $node;
  }

  private function screenshot($filename) {
    file_put_contents($filename, base64_decode($this->captureEntirePageScreenshotToString()));
  }
}
