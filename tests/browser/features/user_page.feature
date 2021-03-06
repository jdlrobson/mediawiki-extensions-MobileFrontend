@chrome @en.m.wikipedia.beta.wmflabs.org @firefox @login @test2.m.wikipedia.org @vagrant @integration
Feature:  User:<username>

  Background:
    Given I am logged into the mobile website
    And I visit my user page

  Scenario: Check components in user page
    Then I should be on my user page
    And there should be a link to my talk page
    And there should be a link to my contributions
    And there should be a link to my uploads

  Scenario: Check user page is editable
    And I should be on my user page
    And there should be a link to create my user page
    When I click the create my user page link
    Then I see the wikitext editor overlay
