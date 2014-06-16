Feature: Use Shortcodes
  In order to use my Plugin
  As a website author
  I need to write posts with shortcodes

  Background:
    Given I am logged in as "admin" with "vagrant"

  Scenario: Without the plugin
    Given the plugin "freifunkmeta" is "inactive"
    When I write a post with title "test" and content "[ff_contact]"
    #Then print current URL
    Then I should see "ff_contact"

  Scenario: With the plugin
    Given the plugin "freifunkmeta" is "active"
    When I write a post with title "test" and content "[ff_contact]"
    #Then print current URL
    Then I should see "Twitter" in the ".ff_contact" element
    And I should not see "ff_contact"

