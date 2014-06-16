<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Context\Step\When,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

require "WordPressContext.php";
use \WordPress\Mink\Context as WP_Context;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Features context.
 */
class FeatureContext extends WP_Context\WordPress_Context
{
    /**
     * @Given /^I am logged in as "([^"]*)" with "([^"]*)"$/
     */
    public function iAmLoggedInAsWith($username, $password) {
      // Works out of the box (with a base_url of course)
      // And makes sure the current user is logged out first!
      $this->login($username, $password);
    }

    /**
     * @When /^I write a post with title "([^"]*)" and content "([^"]*)"$/
     */
    public function iWriteAPostWithTitleAndContent($post_title, $content)
    {
          $this->fill_in_post('post', $post_title, 'publish', $content);
    }
    
    /**
     * @Given /^the plugin "([^"]*)" is "([^"]*)"$/
     */
    public function thePluginIs($plugin, $state)
    {
        if ($state == "active") {
            $action = "activate";
        } else {
            $action = "deactivate";
        }
        shell_exec(escapeshellcmd("wp plugin $action $plugin"));
    }

    /**
     * @When /^I search for "([^"]*)"$/
     */
    public function iSearchFor($term)
    {
        return array(
            new When("I fill in \"s\" with \"$term\""),
            new When("I press \"searchsubmit\""),
        );
    }

}
