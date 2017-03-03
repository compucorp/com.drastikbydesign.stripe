<?php

// Bootstrap Drupal.
$settings = StripePaymentTestSettings::getInstance();
$drupalRoot = $settings->get('site', 'drupal_path');
if (empty($drupalRoot)) {
  $currentPath = getcwd();
  $drupalRoot = explode('/sites/all/', $currentPath)[0];
}
if (empty($drupalRoot)) {
  throw new Exception('Cannot determine drupal root path.');
}

set_include_path($drupalRoot . PATH_SEPARATOR . get_include_path());
define('DRUPAL_ROOT', $drupalRoot);
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

class StripePaymentTest extends PHPUnit_Extensions_SeleniumTestCase {
  private $settings = NULL;

  public function setUp()
  {
    $this->settings = StripePaymentTestSettings::getInstance();
    $this->setBrowser("*chrome");
    $this->setBrowserUrl($this->settings->get('site', 'url'));
  }

  public function testWebformPayment() {
    civicrm_initialize();

    $this->installPaymentWebform();
    $webformNode = $this->getWebformNode();
    $this->assertNotNull($webformNode->nid);

    $bltId = $this->getBillingTypeId();

    $this->open($this->sboxPath . '/page/payment-test');
    $this->type('edit-submitted-civicrm-1-contact-1-fieldset-fieldset-civicrm-1-contact-1-email-email', $this->settings->get('payment', 'email'));
    $this->click('op');
    $this->waitForElementPresent('credit_card_number');
    $this->type('credit_card_number', $this->settings->get('payment', 'credit_card_number'));
    $this->type('cvv2', $this->settings->get('payment', 'cvv2'));
    $this->select('credit_card_exp_date[M]', 'value=' . $this->settings->get('payment', 'credit_card_exp_date_month'));
    $this->select('credit_card_exp_date[Y]', 'value=' . $this->settings->get('payment', 'credit_card_exp_date_year'));
    $this->type('billing_first_name', $this->settings->get('payment', 'billing_first_name'));
    $this->type('billing_last_name', $this->settings->get('payment', 'billing_last_name'));
    $this->type('billing_street_address-' . $bltId, $this->settings->get('payment', 'billing_street_address'));
    $this->type('billing_city-' . $bltId, $this->settings->get('payment', 'billing_city'));
    $this->select('billing_country_id-' . $bltId, 'value=' . $this->settings->get('payment', 'billing_country_id'));
    $this->select('billing_state_province_id-' . $bltId, 'value=' . $this->settings->get('payment', 'billing_state_province_id'));
    sleep(5);
    $this->type('billing_postal_code-' . $bltId, $this->settings->get('payment', 'billing_postal_code'));
    $this->click("xpath=//form[@id='webform-client-form-{$webformNode->nid}']/div/div[@class='form-actions']/input[@value='Submit']");

    $this->waitForElementPresent("xpath=//div[@class='webform-confirmation']");
    $this->assertElementContainsText("xpath=//div[@class='webform-confirmation']", "Thank you, your submission has been received.");

    node_delete($webformNode->nid);
  }

  public function testCiviCRMContributionPayment() {
    civicrm_initialize();

    $contact = $this->createContact();
    $bltId = $this->getBillingTypeId();
    $stripeProcessorId = $this->getStripeProcessorId();
    $financialTypeId = $this->getFinancialTypeId();

    $this->open('/user/login');
    $this->type('id=edit-name', $this->settings->get('site', 'drupal_admin_name'));
    $this->type('id=edit-pass', $this->settings->get('site', 'drupal_admin_password'));
    $this->clickAndWait("xpath=//form[@id='user-login']/div/div[@id='edit-actions']/input[@id='edit-submit']");
    
    $this->open("/civicrm/contact/view/contribution?reset=1&action=add&cid={$contact['id']}&context=contribution&mode=live");

    $this->waitForElementPresent("xpath=//form[@id='Contribution']");
    $this->select('payment_processor_id', 'value=' . $stripeProcessorId);
    $this->select('financial_type_id', 'value=' . $financialTypeId);
    $this->type('total_amount', '1');
    $this->select('credit_card_type', 'value=Visa');
    $this->type('credit_card_number', $this->settings->get('payment', 'credit_card_number'));
    $this->type('cvv2', $this->settings->get('payment', 'cvv2'));
    $this->select('credit_card_exp_date[M]', 'value=' . $this->settings->get('payment', 'credit_card_exp_date_month'));
    $this->select('credit_card_exp_date[Y]', 'value=' . $this->settings->get('payment', 'credit_card_exp_date_year'));
    $this->type('billing_street_address-' . $bltId, $this->settings->get('payment', 'billing_street_address'));
    $this->type('billing_city-' . $bltId, $this->settings->get('payment', 'billing_city'));
    $this->select('billing_country_id-' . $bltId, 'value=' . $this->settings->get('payment', 'billing_country_id'));
    sleep(5);
    $this->select('billing_state_province_id-' . $bltId, 'value=' . $this->settings->get('payment', 'billing_state_province_id'));
    $this->type('billing_postal_code-' . $bltId, $this->settings->get('payment', 'billing_postal_code'));
    $this->clickAndWait('_qf_Contribution_upload');

    $this->waitForElementPresent("xpath=//div[@id='crm-notification-container']/div/div[@class='notify-content']");
    $this->assertElementContainsText("xpath=//div[@id='crm-notification-container']/div/div[@class='notify-content']", "The transaction record has been processed.");

    $this->deleteContact($contact['id']);
  }

  private function createContact() {
    $random = substr(sha1(rand()), 0, 7);
    $contact = civicrm_api3('Contact', 'create', array(
      'sequential' => 1,
      'contact_type' => 'Individual',
      'first_name' => 'Test First Name' . $random,
      'last_name' => 'Test Last Name' . $random,
    ));
    if (empty($contact['values'][0]['id'])) {
      return NULL;
    }
    civicrm_api3('Email', 'create', array(
      'contact_id' => $contact['values'][0]['id'],
      'email' => 'testemail' . $random . '@testdomain' . $random . '.com',
    ));
    return $contact['values'][0];
  }

  private function deleteContact($contactId) {
    civicrm_api3('Contact', 'delete', array(
      'id' => $contactId,
    ));
  }

  private function getBillingTypeId() {
    $locationTypes = CRM_Core_PseudoConstant::get('CRM_Core_DAO_Address', 'location_type_id', array(), 'validate');
    return array_search('Billing', $locationTypes);
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
    exec('drush node-export-import --uid=1 < fixtures/webform/payment_webform.node');
  }

  private function getWebformNode() {
    $alias = 'page/payment-test';
    $path = drupal_lookup_path('source', $alias); 
    $node = menu_get_object('node', 1, $path);
    return $node;
  }
}

class StripePaymentTestSettings {
  private static $instance = NULL;
  private $settings = NULL;

  private function __construct() {
    $this->settings = $this->getSettings();
  }

  public static function getInstance() {
    if (!isset(static::$instance)) {
      static::$instance = new static;
    }
    if (!static::$instance->get('site', 'url')) {
      throw new Exception('Please edit StripePaymentTestSettings.xml with proper values.');
    }
    return static::$instance;
  }

  private function getSettings() {
    if (file_exists('StripePaymentTestSettings.xml')) {
      return simplexml_load_file('StripePaymentTestSettings.xml');
    }
    throw new Exception('StripePaymentTestSettings.xml file not found.');
  }

  public function get($section, $key) {
    if (!empty($this->settings->$section->$key)) {
      return $this->settings->$section->$key->__toString();
    }
    return NULL;
  }
}
