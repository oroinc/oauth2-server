@regression
@fixture-OroCustomerBundle:CustomerUserFixture.yml

Feature: Manage Storefront OAuth Applications
  In order to use OAuth authorization
  As an Administrator
  I need to be able to manage storefront OAuth applications with different grant types

  Scenario: Feature Background
    Given I enable storefront API
    And I login as administrator

  Scenario: Create Client Credentials grant OAuth application
    When I go to System/Storefront OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Client App         |
      | Grants           | Client Credentials |
      | Customer Users   | Amanda Cole        |
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And I should see "Client ID"
    And I should see "Client Secret"

  Scenario: View OAuth application
    When I click "View"
    Then I should see OAuth Application with:
      | Application Name | Client App         |
      | Grants           | Client Credentials |
      | Customer Users   | Amanda Cole        |

  Scenario: Delete OAuth application from view page
    When I click "Delete"
    Then I should see "Delete Confirmation"
    And I should see "Are you sure you want to delete this OAuth Application?"
    When I click "Yes, Delete"
    Then I should see "OAuth Application deleted" flash message
    And I should see "There are no applications"

  Scenario: New OAuth application name validation
    System/Storefront OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Grants           | Client Credentials |
      | Customer Users   | Amanda Cole        |
    And click "Save"
    Then I should see validation errors:
      | Application Name | This value should not be blank. |
    Then I click "Cancel"

  Scenario: New OAuth application users validation for Client Credentials grant
    When I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Test App           |
      | Grants           | Client Credentials |
    And click "Save"
    Then I should see validation errors:
      | Customer Users | This value should not be blank. |
    Then I click "Cancel"

  Scenario: Create Password grant OAuth application
    When I go to System/Storefront OAuth Applications
    And I click "Create OAuth Application"
    And I fill form with:
      | Application Name | Client App |
      | Grants           | Password   |
    And I should not see "Customer Users"
    And I click "Save and Close"
    Then I should see "OAuth application has been created." flash message
    And I should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And I should see "Client ID"
    And I should see "Client Secret"

  Scenario: Edit Password grant OAuth application
    When I click "Edit"
    Then I should not see "Customer Users"
    And I should see "Client ID"
    When I fill form with:
      | Application Name | Client App edited |
    And click "Save and Close"
    Then I should see "OAuth application has been updated." flash message
    And I should see OAuth Application with:
      | Application Name | Client App edited |
      | Grants           | Password          |

  Scenario: Deactivate OAuth application
    When I go to System/Storefront OAuth Applications
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
    And I should see "There are no applications"
