Feature: Search
  In order to find older articles
  As a website user
  I need to be able to search for a word

  Scenario: Searching for a post
    Given I am on the homepage
    When I search for "Welcome"
    Then I should see "Hello world!"
     And I should see "Welcome to WordPress. This is your first post."
