<?php

class CiviSeleniumSettings {

  var $publicSandbox  = false;

  var $browser = '*firefox';

  /**
   * @var string SeleniumRC host name
   */
  var $rcHost = 'localhost';

  /**
   * @var int SeleniumRC port number
   */
  var $rcPort = 4444;

  var $sandboxURL = 'http://cew2017.localhost';

  var $sandboxPATH = '';

  var $username = 'compucorp_admin'; // demo

  var $password = 'admin'; // demo

  var $adminUsername = 'compucorp_admin';

  var $adminPassword = 'admin';

  var $adminApiKey = NULL; // civicrm_contact.api_key for admin

  var $UFemail = 'noreply@civicrm.org';

  /**
   * @var string site API key
   */
  var $siteKey = NULL;

  /**
   * @var int seconds
   */
  var $timeout = 30;

  /**
   * @var array
   * @see CiviSeleniumTestCase::setCookies
   */
  var $cookies = array();

  /**
   * @var int|NULL seconds to wait for SeleniumRC to become available
   *
   * If you have custom scripts which launch Selenium and PHPUnit in tandem, then
   * Selenium may initialize somewhat slowly. Set $serverStartupTimeOut to wait
   * for Selenium to startup. If you launch Selenium independently or otherwise
   * prefer to fail immediately, then leave the default value NULL.
   */
  var $serverStartupTimeOut = NULL;

  function __construct() {
    $this->fullSandboxPath = $this->sandboxURL . $this->sandboxPATH;
    // $this->cookies[] = array(
    //   'name' => 'mycookie',
    //   'value' => 'myvalue',
    //   'path' => '/',
    //   'max_age' => 24*60*60,
    // );;
  }

}
?>
