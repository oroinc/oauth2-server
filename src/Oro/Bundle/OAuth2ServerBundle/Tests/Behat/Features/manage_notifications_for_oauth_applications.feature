@regression
@ticket-BAP-18430
@fixture-OroOAuth2ServerBundle:manage_notifications_for_oauth_applications.yml
Feature: Manage Notifications for OAuth Applications
  In order to notify user about changes in OAuth applications
  As an Administrator
  I need to be able to create email templates and configure notification rules for a user OAuth applications

  Scenario: Feature Background
    Given I enable API
    And I login as administrator
    And go to System/ User Management/ Business Units
    And click edit Main in grid
    And I fill form with:
      | Email | main_bu@example.org |
    When I save and close form
    Then I should see "Business unit saved" flash message

  Scenario: Create email template for OAuth application
    Given go to System/ Emails/ Templates
    When click "Create Email Template"
    And fill form with:
      | Owner         | John Doe                                                                                      |
      | Template Name | OAuth App Changed                                                                             |
      | Type          | Plain Text                                                                                    |
      | Entity Name   | OAuth Application                                                                             |
      | Subject       | Changed "{{ entity.name }}" belongs to {{ entity.user.firstName }} {{ entity.user.lastName }} |
      | Content       | For {{ entity.user }}. Grant Type: {{ entity.grants }}. Scopes: {{ entity.scopes }}.              |
    When I save and close form
    Then I should see "Template saved" flash message

  Scenario: Create Email Notification Rule for OAuth Application entity
    Given go to System/ Emails/ Notification Rules
    And click "Create Notification Rule"
    And fill form with:
      | Entity Name             | OAuth Application |
      | Event Name              | Entity update     |
      | Template                | OAuth App Changed |
      | Additional Associations | User > Owner      |
    When I save and close form
    Then I should see "Notification Rule saved" flash message

  Scenario: Change OAuth application and check notification email
    Given go to System/User Management/Users
    And click view John in grid
    When I click "Edit" on row "Test OAuth Application" in "OAuth Applications Grid"
    And fill form with:
      | Application Name | Test OAuth Application (changed) |
    And click "Save"
    Then I should see "Saved successfully" flash message
    And An email containing the following was sent:
      | To      | main_bu@example.org                                            |
      | Subject | Changed "Test OAuth Application (changed)" belongs to John Doe |
      | Body    | For John Doe. Grant Type: Client Credentials. Scopes: all.         |
