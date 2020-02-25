@regression
@fixture-OroOrganizationProBundle:SecondOrganizationFixture.yml
# enterprise-edition-only

Feature: Manage OAuth Applications in second organization
  In order to use OAuth authorization
  As an Administrator
  I need to be able to manage OAuth applications for a user in second organization

  Scenario: Feature Background
    Given I enable API
    And I login as administrator
    And I am logged in under ORO Pro organization

  Scenario: Create Client Credentials grant OAuth application
    When I go to System/User Management/OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Client App         |
      | Grants           | Client Credentials |
      | Users            | John Doe           |
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."

  Scenario: View OAuth application
    When I click "View"
    Then I should see OAuth Application with:
      | Application Name | Client App         |
      | Grants           | Client Credentials |
      | Users            | John Doe           |

  Scenario: View OAuth application in grid
    When I go to System/User Management/OAuth Applications
    Then I should see following grid:
      | Application Name | Users    |
      | Client App       | John Doe |
