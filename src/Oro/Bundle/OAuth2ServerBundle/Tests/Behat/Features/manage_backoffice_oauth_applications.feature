@regression
@fixture-OroOAuth2ServerBundle:manage_notifications_for_oauth_applications.yml

Feature: Manage Backoffice OAuth Applications
  In order to use OAuth authorization
  As an Administrator
  I need to be able to manage backoffice OAuth applications with different grant types

  Scenario: Feature Background
    Given I enable API
    And I login as administrator

  Scenario: Applications grid
    When I go to System/User Management/OAuth Applications
    Then I should see following records in grid:
      | test_app  |

  Scenario: Create Client Credentials grant OAuth application
    When I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Client App         |
      | Grant Type       | Client Credentials |
      | User             | John Doe           |
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And I should see "Client ID"
    And I should see "Client Secret"

  Scenario: View OAuth application
    When I click "View"
    Then I should see OAuth Application with:
      | Application Name | Client App         |
      | Grant Type       | Client Credentials |
      | User             | John Doe           |
    And I should not see "Redirect URLs"

  Scenario: Delete OAuth application from view page
    When I click "Delete"
    Then I should see "Delete Confirmation"
    And I should see "Are you sure you want to delete this OAuth Application?"
    When I click "Yes, Delete"
    Then I should see "OAuth Application deleted" flash message
    And I should see "Test OAuth Application" in grid with following data:
      | Application Name | Test OAuth Application |
      | Grant Type       | Client Credentials |
      | Active           | Yes               |

  Scenario: New OAuth application name validation
    When I go to System/User Management/OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Grant Type       | Client Credentials |
      | User             | John Doe           |
    And click "Save"
    Then I should see validation errors:
      | Application Name | This value should not be blank. |
    Then I click "Cancel"

  Scenario: New OAuth application user validation for Client Credentials grant
    When I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Test App           |
      | Grant Type       | Client Credentials |
    And click "Save"
    Then I should see validation errors:
      | User | This value should not be blank. |
    Then I click "Cancel"

  Scenario: Create Password grant OAuth application
    When I go to System/User Management/OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Client App |
      | Grant Type       | Password   |
    And I should not see "User*"
    And I should not see "Redirect URLs"
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And I should see "Client ID"
    And I should see "Client Secret"

  Scenario: Edit Password grant OAuth application
    When I click "Edit"
    Then I should not see "User*"
    And I should not see "Redirect URLs"
    And I should see "Client ID"
    When I fill form with:
      | Application Name | Client App edited |
    And click "Save and Close"
    Then I should see "OAuth application has been updated." flash message
    And I should see OAuth Application with:
      | Application Name | Client App edited |
      | Grant Type       | Password          |

  Scenario: Deactivate OAuth application
    When I go to System/User Management/OAuth Applications
    And I click "Deactivate" on row "Client App edited" in grid
    Then I should see "Are you sure you want to deactivate the application?"
    When I click "Yes, do it"
    Then I should see "Deactivated successfully" flash message
    And I should see "Client App edited" in grid with following data:
      | Active | No |

  Scenario: Activate OAuth application
    When I click "Activate" on row "Client App edited" in grid
    Then I should see "Activated successfully" flash message
    And I should see "Client App edited" in grid with following data:
      | Active | Yes |

  Scenario: Delete OAuth application
    When I click "Delete" on row "Client App edited" in grid
    Then I should see "Are you sure you want to delete the application?"
    When I click "Yes"
    Then I should see "Deleted successfully" flash message
    And I should see following grid:
      | Application Name       | Active |
      | Test OAuth Application | Yes    |

  Scenario: New Auth Code grant OAuth application Redirect URLs validation
    When I go to System/User Management/OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Auth Code App      |
      | Grant Type       | Authorization Code |
    And click "Save"
    Then I should see "At least one redirect URL should be specified."
    Then I click "Cancel"

  Scenario: Create Auth Code grant OAuth application
    When I go to System/User Management/OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Auth Code App                           |
      | Grant Type       | Authorization Code                      |
      | Redirect URLs    | [https://test.com, https://another.com] |
    And I should not see "User*"
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And I should see "Client ID"
    And I should see "Client Secret"

  Scenario: Edit Auth Code grant OAuth application
    When I click "Edit"
    Then I should not see "User*"
    And I should see "Client ID"
    When I fill form with:
      | Application Name | Auth Code App edited |
    And click "Save and Close"
    Then I should see "OAuth application has been updated." flash message
    And I should see OAuth Application with:
      | Application Name | Auth Code App edited |
      | Grant Type       | Authorization Code   |
    And I should see "https://test.com"
    And I should see "https://another.com"
