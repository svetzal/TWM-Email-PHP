<?php

/**
 *
 * Generic Email Framework for PHP Scripts
 * Copyright (C) 2004-2005, Three Wise Men Software Development and Consulting
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * General Usage Information
 * ~~~~~~~~~~~~~~~~~~~~~~~~~
 *
 * This class provides a simplified interface to sending plain-text
 * or HTML emails.
 *
 * Here is the primary usage scenario:
 *
 * <code>
 * $cfg = new TWM_Email_Config();
 * $cfg->smtp_host = "mail.threewisemen.ca";
 * $cfg->template_dir = "/var/www/html/templates/";
 *
 * $email = new TWM_Email($cfg);
 * $email->setFormat(TWM_MAILFORMAT_HTML);
 * $email->loadTemplate("sample_email_template.html");  //body template
 * $email->loadTemplate("sample_subject_template.subject", false); //subject template
 * $email->addTo("Steven Vetzal <steve@threewisemen.ca>");
 * $email->addTo("TWM Sales <sales@threewisemen.ca>");
 * $email->send(array( "firstname" => "Steven", "lastname" => "Vetzal", "message" => "Hello!" ));
 * </code>
 *
 * @version 2.0.1
 * @copyright 2004-2005, Three Wise Men Software Development and Consulting
 *
 */

/**
 * Constants to designate format of email
 */
define("TWM_MAILFORMAT_TEXT", 0);
define("TWM_MAILFORMAT_HTML", 1);

/**
 * Default settings
 */
define("TWM_MAIL_DEFAULTLOGNAME", "mail");
define("TWM_MAIL_DEFAULTSENDER", "System Administrator <admin@somedomain.com>");
define("TWM_MAIL_DEFAULTSUBJECT", "Simple Text Subject Line");

/**
 * Email configuration class
 */
class TWM_Email_Config {

  var $template_dir;
  var $logname;
  var $smtp_host;
  var $template;
  var $lineterm;
  var $subject_template;
  var $subject;

  function TWM_Email_Config($smtphost = "localhost") {
    $this->logname = TWM_MAIL_DEFAULTLOGNAME;
    $this->smtp_host = "localhost";
    $this->lineterm = "\r\n";
  }
}

/**
 * Email class
 */
class TWM_Email {

  var $config;

  // Email attributes
  var $headers;
  var $sender;
  var $to;
  var $cc;
  var $subject;
  var $body;

  var $format;

  function TWM_Email($config) {
    $this->config = $config;
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "creating email");
    $this->headers = array();
    $this->to = array();
    $this->cc = array();
    $this->format = TWM_MAILFORMAT_TEXT;
    $this->subject = $config->subject;
    $this->sender = $config->sender;
    $this->body = "";
  }

  function destroy() {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "destroying email");
    unset($this->headers);
    unset($this->sender);
    unset($this->to);
    unset($this->cc);
    unset($this->subject);
    unset($this->replyto);
    unset($this->body);
    unset($this->format);
  }

  function addHeader($header) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "adding header to email - $header");
    array_push($this->headers, $header);
  }

  function addTo($recipient) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "adding recipient for email - $recipient");
    array_push($this->to, $recipient);
  }

  function addCC($recipient) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "adding CC recipient for email - $recipient");
    array_push($this->cc, $recipient);
  }

  function setFormat($fmt) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "attempting to set email format to $fmt");
    if ($fmt == TWM_MAILFORMAT_TEXT || $fmt == TWM_MAILFORMAT_HTML) {
      $this->format = $fmt;
    } else {
      $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "invalid email format specified ($fmt)");
    }
  }

  // WARNING: This routine is dependent on a system "nslookup" command! This is
  // getting more and more rare, despite Microsoft adding it into 2000/XP many
  // linux distros are dropping it or providing a very thin wrapper around more
  // modern tools (ie. dig) *that may or may not have the same output!*
  function verifyDomain($domain, $rectype = 'MX') {
    if(!empty($domain)) {
      $lines = array();
      exec("nslookup -type=$rectype $domain", $lines);
      foreach ($lines as $line) {
        if(eregi("^$domain", $line) && eregi("MX", $line)) return true;
      }
    }
    return false;
  }

  function stripEmail($email) {
    global $LOGGER;
    $parts = array();
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "looking to strip email from $email");
    preg_match("/<([-^!#$%&'*+\/=?`{|}~.\w]+@[-a-zA-Z0-9\.]+)>/", $email, $parts);
    if ($parts[1]) {
      return $parts[1];
    } else {
      return $email;
    }
  }

  function loadTemplate($filename, $body=TRUE) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "looking to load email template $filename from ".$this->config->template_dir);
    $path = $this->config->template_dir.$filename;
    if (file_exists($path)) {
      $fh = fopen($path, 'r');
      if($body) {
          $this->config->template = fread($fh, filesize($path));
          $subject_name = substr_replace($filename, ".subject", strrpos($filename, "."), -1);
          $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "Subject template: " .$subject_name);
/*	  if(file_exists($subject_name)) {
        loadTemplate($subject_name, false);
      }
*/      } else {
          $this->config->subject_template = fread($fh, filesize($path));
      }
      fclose($fh);
    } else {
      $LOGGER->log($this->config->logname, TWM_LOGLEVEL_ERROR, "email template does not exist");
    }
  }

  function loadBodyTemplate($filename) {
    $this->config->template = loadTemplate($filename);
  }

  function loadSubjectTemplate($filename) {
    $this->config->subject_template = loadTemplate($filename, false);
  }

  function setTemplate($data) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "setting template");
    $this->config->template = $data;
  }

  function send($fields = array()) {
    global $LOGGER;
    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "preparing to send email");

    if ($this->format == TWM_MAILFORMAT_HTML) {
      $this->addHeader("MIME-Version: 1.0");
      $this->addHeader("Content-Type: text/html; charset=iso-8859-1");
    }

    // verify we have a sender and a recipient
    if (count($this->to) == 0 || !$this->sender) {
      $LOGGER->log($this->config->logname, TWM_LOGLEVEL_ERROR, "email must have a sender and at least one recipient");
      return false;
    }

    // verify sender email address
    if (!TWM_isValidEmail($this->sender)) {
      $LOGGER->log($this->config->logname, TWM_LOGLEVEL_ERROR, "sender must be a valid email address ('$this->sender' not valid)");
      return false;
    }
    $this->addHeader("From: $this->sender");
    $this->addHeader("Return-Path: $this->sender");
    $this->addHeader("Return-Receipt-To: $this->sender");

    // verify recipient email address(es)
    foreach ($this->to as $recipient) {
      if (!TWM_isValidEmail($recipient)) {
        $LOGGER->log($this->config->logname, TWM_LOGLEVEL_ERROR, "recipient must be a valid email address ('$this->sender' not valid)");
        return false;
      }
//      $this->addHeader("To: $recipient");
    }

    if(isset($this->config->subject_template)) {
        $this->subject = $this->config->subject_template;
    foreach (array_keys($fields) as $key) {
            $this->subject = str_replace("%%$key%%", $fields[$key], $this->subject);
        }
    }
    $this->addHeader("Subject: $this->subject");

    $this->body = $this->config->template;
    foreach (array_keys($fields) as $key) {
      $this->body = str_replace("%%$key%%", $fields[$key], $this->body);
    }

    $headerval = "";
    foreach ($this->headers as $header) {
      $headerval .= $header.$this->config->lineterm;
    }
    $headerval .= $this->config->lineterm; // extra header to avoid SMTP header injection abuse

    $LOGGER->log($this->config->logname, TWM_LOGLEVEL_INFO, "setting smtp host to ".$this->config->smtp_host);
    ini_set("sendmail_from", $this->sender);
    ini_set("SMTP", $this->config->smtp_host);

    foreach ($this->to as $recipient) {
      $email = $this->stripEmail($recipient);
      $LOGGER->log($this->config->logname, TWM_LOGLEVEL_INFO, "sending email to $email ($this->subject)");
      if (mail($email,$this->subject,$this->body,$headerval,"-f $this->sender")) {
        $LOGGER->log($this->config->logname, TWM_LOGLEVEL_INFO, "email sent successfully");
      } else {
        $LOGGER->log($this->config->logname, TWM_LOGLEVEL_ERROR, "unable to send email");
      }
    }
    return true;
  }
}

function TWM_isValidEmail($email) {
  global $LOGGER;
  // Check for full-form email "Steven Vetzal <steve@threewisemen.ca>"
  $parts = array();
  preg_match("/([^<]*)<([-^!#$%&'*+\/=?`{|}~.\w]+@[-a-zA-Z0-9\.]+)>/", $email, $parts);
  if ($parts[2]) {
    if ($LOGGER) $LOGGER->log('email', TWM_LOGLEVEL_TRACE, "valid long-format email address $email");
    return true;
  } else {
    // Check for short-form email "steve@threewisemen.ca"
    preg_match("/([-^!#$%&'*+\/=?`{|}~.\w]+@[-a-zA-Z0-9\.]+)/", $email, $parts);
    if ($parts[0]) {
      if ($LOGGER) $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "valid short-format email address $email");
      return true;
    } else {
      if ($LOGGER) $LOGGER->log($this->config->logname, TWM_LOGLEVEL_TRACE, "not valid email address $email");
      return false;
    }
  }
}

function TWM_stripEmail($email) {
  global $LOGGER;
  $parts = array();
  if ($LOGGER) $LOGGER->log('email', TWM_LOGLEVEL_TRACE, "looking to strip email from $email");
  preg_match("/<([-^!#$%&'*+\/=?`{|}~.\w]+@[-a-zA-Z0-9\.]+)>/", $email, $parts);
  if ($parts[1]) {
    return $parts[1];
  } else {
    return $email;
  }
}

?>
