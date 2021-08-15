![REDCapPRO](https://i.imgur.com/5Xq2Vqt.png)

## Overview

### What is REDCapPRO?
**REDCapPRO** is an external module for [REDCap](https://projectredcap.org) that allows participants/patients to directly report study data (*i.e.*, ePRO). Its primary purpose is to allow the identification of a survey participant and to log that information in a REDCap project's audit trail in a manner compliant with regulatory stipulations (primarily FDA's 21 CFR Part 11). To achieve this, project users must first register a participant with REDCapPRO and then enroll that participant in the REDCap project. Step-by-step instructions are provided below.

### How does it work?
REDCapPRO provides a means for study participants to log in to REDCap surveys using a username created by REDCapPRO and a password that they choose for themselves. This password is not known to any other person (including study staff and REDCap admins). 

<u>These are the steps required to collect data using REDCapPRO:</u>

1. **Register:** A staff member of the study registers a participant with REDCapPRO
   * The participant is registered system-wide. They use the same username and password for all REDCapPRO surveys in this REDCap system regardless of which REDCap project the survey belongs to.
2. **Enroll:** The study staff member then enrolls the participant in this particular REDCap project
3. Survey invitations can then be sent to the participant using REDCap survey distribution tools like normal. Participants will need to log in with their REDCapPRO username and password to access the survey. Because the login credentials are not tied to a specific record, public surveys are compatible with REDCapPRO.

## EM Settings
These are settings/configuration options accessible in the normal External Module settings interface.

### Project Settings

| Setting                 | Type  | Description                                                                                                                                           | Default Value |
| :---------------------- | :---- | :---------------------------------------------------------------------------------------------------------------------------------------------------- | :------------ |
| **Study Contact Name**  | Text  | The name of the study staff member that study participants should contact with questions/problems. This will appear in emails sent to the participant | N/A           |
| **Study Contact Email** | Email | Email address that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                | N/A           |
| **Study Contact Phone** | Phone | Phone number that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                 | N/A           |


## REDCapPRO Project Menu

The REDCapPRO Project Menu is accessible via a link in the Applications section of the REDCap project side menu. Access to the different sections of the menu is restricted based on the `role` of the user (see the Study Staff tab description for details). The link to the menu itself is only visible to users or role `Monitor` or above, although the Home tab is accessible by anyone. 

### Home Tab
This is this page.

### Manage Participants
This tab allows a user to view enrolled participants in this study project and to 
take various actions on study participants. The actions available
and the information that is visible depends on the `role` of the user. The tab
itself is available to `Monitors` and above.

| Label             | Type   | Description                                                                                                                                                               | Minimum Role to view/use |
| ----------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| Username          | Column | Contains the REDCapPRO username assigned to this participant                                                                                                              | *Monitor*                |
| First Name        | Column | Contains the first name of this participant                                                                                                                               | <u>Normal User</u>       |
| Last Name         | Column | Contains the last name of this participant                                                                                                                                | <u>Normal User</u>       |
| Email             | Column | Contains the email address of this participant                                                                                                                            | <u>Normal User</u>       |
| Data Access Group | Column | Contains the DAG the participant is currently in *Note that this is independent of which DAG a REDCap record that corresponds with this participant might be assigned to* | *Monitor*                |
| Data Access Group | Action | Ability to reassign DAG. Only DAGs available to the REDCap User are available as options to switch to                                                                     | <u>Normal User</u>       |
| Reset Password    | Action | Sends an email to the participant which contains a password reset link                                                                                                    | *Monitor*                |
| Change Email      | Action | Updates the email address in the REDCapPRO database for this participant                                                                                                  | <u>**Manager**</u>       |
| Disenroll         | Action | Removes the participant from this study project                                                                                                                           | <u>Normal User</u>       |

### Enroll
This tab allows a user to search for a registered participant in order to enroll
them into this study project. This tab is available to `Normal Users` and above.

### Register
This tab allows a user to register a participant with REDCapPRO. It is available
to `Normal Users` and above.

### Study Staff
This tab allows `Managers` to set the `role` of users in the study project. All
REDCap users are shown in this table. Set the role of the user according to this
guide:
| Role        | Description                                                                                                                               |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| No Access   | No access to REDCapPRO is given to this user.                                                                                             |
| Monitor     | Basic access. Can only view usernames and dags and can only initiate password resets.                                                     |
| Normal User | Able to view participant identifying information and take several participant management actions (see Manage Participants section above). |
| Manager     | Highest permissions. Has the ability to grant/revoke staff access and change a participant's email address                                |

*<u>Note</u>: REDCap administrators have full `Manager` permissions in REDCapPRO no matter what `role` they have in the project (or if they appear in the staff list at all)*

### Logs
This tab allows `Managers` to view the logs of REDCapPRO relevant to this study
project. It only contains information about actions taken in this project or on
surveys tied to this project. 



## Action Tags

This module provides several action tags for populating REDCap fields with 
information about REDCapPRO participants. These are described below:
*Note: All action tags must exist on the same data collection instrument (not survey).*

| Action Tag      | Use with field type | Validation on field type | Description                                                                                                                                                          |
| --------------- | ------------------- | ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| @RCPRO-USERNAME | text                | none                     | This transforms the field into a dropdown selector. The user can select a participant and populate the field with that participant's username                        |
| @RCPRO-EMAIL    | text                | email                    | If @RCPRO-USERNAME is present on the instrument, then when it is selected the field with the @RCPRO-EMAIL tag will be populated with the participant's email address |
| @RCPRO-FNAME    | text                | none                     | Like @RCPRO-EMAIL, but the field will be populated with the participant's first name                                                                                 |
| @RCPRO-LNAME    | text                | none                     | Likewise, with last name                                                                                                                                             |
