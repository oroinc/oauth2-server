@regression
@ticket-BAP-16477
@ticket-BAP-18430
@ticket-BAP-19159
Feature: Manage OAuth Applications
  In order to use OAuth authorization
  As an Administrator
  I need to be able to manage OAuth applications for a user

  Scenario: Feature Background
    Given I enable API
    And I login as administrator
    And go to System/User Management/Users
    And click view John in grid

  Scenario: Create OAuth application
    When I click "Add OAuth Application"
    And type "First App" in "Application Name"
    And click "Save"
    Then I should see "Saved successfully" in the "UiDialog" element
    And should see "OAuth application has been created."
    And should see "Please copy Client Secret and save it somewhere safe. For security reasons, we cannot show it to you again."
    And should see "Client ID"
    And should see "Client Secret"
    And An email containing the following was sent:
      | Subject | OAuth application added to your account |
      | To      | admin@example.com                       |
    And Email should contains the following text:
      """
      Hello, John Doe.

      A new OAuth application "First App" was recently added to your account.
      This application is authorized to use Web API.
      Please contact the administrator if you are not aware of this change to your account.
      """
    When I click "Close"
    Then I should see "First App" in "OAuth Applications Grid" with following data:
      | Application Name | First App |
      | Active           | Yes       |

  Scenario: New OAuth application name validation
    When I click "Add OAuth Application"
    And click "Save"
    Then I should see validation errors:
      | Application Name | This value should not be blank. |
    When I type "First App" in "Application Name"
    And click "Save"
    Then I should see validation errors:
      | Application Name | The application with the given name already exists. |
    Then I click "Cancel"

  Scenario: Create one more OAuth application
    When I click "Add OAuth Application"
    And type "Second App" in "Application Name"
    And click "Save"
    And click "Close"
    Then I should see following "OAuth Applications Grid" grid:
      | Application Name | Active |
      | First App        | Yes    |
      | Second App       | Yes    |

  Scenario: Existing OAuth application name validation
    When I click "Edit" on row "First App" in "OAuth Applications Grid"
    And fill form with:
      | Application Name | |
    And click "Save"
    Then I should see validation errors:
      | Application Name | This value should not be blank. |
    When type "Second App" in "Application Name"
    And click "Save"
    Then I should see validation errors:
      | Application Name | The application with the given name already exists. |
    When type "First App" in "Application Name"
    And click "Save"
    Then I should see "Saved successfully" flash message

  Scenario: Deactivate OAuth application
    When I click "Deactivate" on row "First App" in "OAuth Applications Grid"
    Then I should see "Are you sure you want to deactivate the application?"
    When I click "Yes, do it"
    Then I should see "Deactivated successfully" flash message
    And I should see "First App" in "OAuth Applications Grid" with following data:
      | Active | No |

  Scenario: Activate OAuth application
    When I click "Activate" on row "First App" in "OAuth Applications Grid"
    Then I should see "Activated successfully" flash message
    And I should see "First App" in "OAuth Applications Grid" with following data:
      | Active | Yes |

  Scenario: Delete OAuth application
    When I click "Delete" on row "First App" in "OAuth Applications Grid"
    Then I should see "Are you sure you want to delete the application?"
    When I click "Yes"
    Then I should see "Deleted successfully" flash message
    And I should see following "OAuth Applications Grid" grid:
      | Application Name | Active |
      | Second App       | Yes    |

  Scenario: User should be able to add new application after validation message
    When I click "Add OAuth Application"
    And I type "Second App" in "Application Name"
    And click "Save"
    Then I should see validation errors:
      | Application Name | The application with the given name already exists. |
    When I type "Another One App" in "Application Name"
    And click "Save"
    Then I should see "Saved successfully" in the "UiDialog" element
    And should see "OAuth application has been created."
