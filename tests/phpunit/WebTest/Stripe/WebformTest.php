<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

require_once 'tests/phpunit/CiviTest/CiviSeleniumTestCase.php';

/**
 * Class WebTest_Event_AddEventTest
 */
class WebTest_Stripe_WebformTest extends CiviSeleniumTestCase {

  protected function setUp() {
    parent::setUp();
  }

  public function testWebformPayment() {
    $paymentData = $this->getPaymentTestData();

    $this->open($this->sboxPath . '/page/payment-test');
    $this->type('edit-submitted-civicrm-1-contact-1-fieldset-fieldset-civicrm-1-contact-1-email-email', $paymentData['email']);
    $this->click("op");
    $this->waitForElementPresent("credit_card_number");
    $this->type('credit_card_number', $paymentData['credit_card_number']);
    $this->type("cvv2", $paymentData['cvv2']);
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
    $this->click("xpath=//form[@id='webform-client-form-9783']/div/div[@class='form-actions']/input[@value='Submit']");

    $this->waitForElementPresent("xpath=//div[@class='webform-confirmation']");
    $this->assertElementContainsText("xpath=//div[@class='webform-confirmation']", "Thank you, your submission has been received.");

    $this->screenshot('tests/printscreen/webform-payment-complete.png');
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

    /*$this->click("xpath=//li[@id='tab_contribute']/a");
    //sleep(25);
    //$this->waitForElementPresent("xpath=//form[@class='CRM_Contribute_Form_Search']");
    $this->waitForElementPresent("xpath=//form[@id='Search']");
    $this->assertElementContainsText("xpath=//form[@id='Search']/div[@class='view-content']/div[@class='action-link']/a[2]/span", "Submit Credit Card Transaction");
    $this->click("xpath=//form[@id='Search']/div[@class='view-content']/div[@class='action-link']/a[2]");

    sleep(20);*/
    
    $this->open($this->sboxPath . "/civicrm/contact/view/contribution?reset=1&action=add&cid={$contactId}&context=contribution&mode=live");

    $this->waitForElementPresent("xpath=//form[@id='Contribution']");
    $this->select('payment_processor_id', 'value=' . $stripeProcessorId);
    $this->select('financial_type_id', 'value=' . $financialTypeId);
    $this->type('total_amount', '1');
    $this->screenshot('tests/printscreen/civicrm-payment4.png');
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

  private function screenshot($filename) {
    file_put_contents($filename, base64_decode($this->captureEntirePageScreenshotToString()));
  }
}
