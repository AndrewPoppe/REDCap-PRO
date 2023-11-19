![REDCapPRO](./images/REDCapPROLOGO_white.png)

# REDCapPRO - Patient Reported Outcomes

[![CodeQL](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/codeql-analysis.yml/badge.svg)](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/codeql-analysis.yml) [![Psalm Security Scan](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/psalm-security.yml/badge.svg)](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/psalm-security.yml) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=alert_status)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO) [![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=security_rating)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO) [![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO)

## Table of Contents <!-- omit in toc -->
- [REDCapPRO - Patient Reported Outcomes](#redcappro---patient-reported-outcomes)
  - [Overview](#overview)
    - [What is REDCapPRO?](#what-is-redcappro)
    - [How does it work?](#how-does-it-work)
    - [What is the process like for participants?](#what-is-the-process-like-for-participants)
  - [Installation](#installation)
  - [EM Settings](#em-settings)
    - [System Settings](#system-settings)
    - [Project Settings](#project-settings)
    - [Translation](#translation)
  - [REDCapPRO Project Menu](#redcappro-project-menu)
    - [Home Tab](#home-tab)
    - [Manage Participants](#manage-participants)
    - [Enroll](#enroll)
    - [Register](#register)
    - [Study Staff](#study-staff)
    - [Settings](#settings)
    - [Logs](#logs)
  - [Self-Registration](#self-registration)
  - [REDCapPRO Control Center Menu](#redcappro-control-center-menu)
    - [Projects](#projects)
    - [Participants](#participants)
    - [Staff](#staff)
    - [Logs](#logs-1)
  - [API](#api)
    - [Register Participants](#register-participants)
    - [Enroll Participants](#enroll-participants)
  - [Action Tags](#action-tags)
  - [Changelog](#changelog)

## Overview

![login](images/screenshots/login.png)

### What is REDCapPRO?
**REDCapPRO** is an external module for [REDCap](https://projectredcap.org) that allows participants/patients to directly report study data (*i.e.*, ePRO). Its primary purpose is to allow the identification of a survey participant and to log that information in a REDCap project's audit trail in a manner compliant with regulatory stipulations (primarily FDA's 21 CFR Part 11). The reason this is needed is that there is no built-in REDCap feature that provides all of the following:
1. **Identification**: It identifies the survey respondent (via a participant ID) in the logs of the project itself. 
2. **Authentication**: It provides a means of proving that the participant is genuinely who they claim to be. There is no way for another person to pretend to be the participant, either in real time or after the fact by modifying logs. Obvious exceptions:

    1. Malicious attacks outside of **REDCapPRO** (*e.g.*, intercepting a participant's password reset email by hacking their email account) 
    2. REDCap admin or other IT professional directly modifying the database

3. **Authorization**: It provides a means of preventing unauthorized users from viewing identifying information and taking other unauthorized actions.
4. **Convenience**: It must be easy to use for REDCap admins, REDCap users, and study participants.


To achieve this, project users must first register a participant with **REDCapPRO** and then enroll that participant in the REDCap project. Step-by-step instructions are provided below.

**REDCapPRO** is not meant to replace any of the following features/modules/external modules:
- Survey Login
- Participant Identifier
- REDCap Mobile App
- [MyCap](https://projectmycap.org/)
- REDCap Survey Auth

### How does it work?
**REDCapPRO** provides a means for study participants to log in to REDCap surveys using a username created by **REDCapPRO** and a password that they choose for themselves. This password is not known to any other person (including study staff and REDCap admins). 

<u>These are the steps required to collect data using **REDCapPRO**:</u>

1. Enable the module in the REDCap system
2. Enable the module in a REDCap project
3. **Register:** A staff member of the study registers a participant with **REDCapPRO**
   * The participant is registered system-wide. They use the same username and password for all REDCapPRO surveys in this REDCap system regardless of which REDCap project the survey belongs to.
   * *Note: as of REDCapPRO 2.1.0, the option exists for participants to register themselves. See the [Self-Registration](#self-registration) section below for more information.*
4. **Enroll:** The study staff member then enrolls the participant in this particular REDCap project
   * *Note: as of REDCapPRO 2.1.0, the option exists for participants to enroll themselves. See the [Self-Registration](#self-registration) section below for more information.*
5. Survey invitations can then be sent to the participant using REDCap survey distribution tools like normal. Participants will be required to **log in** with their **REDCapPRO** username (or email address) and password to access the survey. Because the login credentials are not tied to a specific record, public surveys are compatible with **REDCapPRO**.

### What is the process like for participants?

![create-password](images/screenshots/create-password.png)

* Upon registration with **REDCapPRO**, the participant will receive an email with a
link to set their password.
* When the participant clicks a link to start a survey in a **REDCapPRO** project, they will see a login screen and will need to supply their username and password.
* They have the option to be sent a password reset email, a username reminder email, and/or an email address reminder email from the login screen.

![forgot-password](images/screenshots/forgot-password.png)

![forgot-username](images/screenshots/forgot-username.png)

* If multifactor authentication is enabled in the project, they will also be required to provide a security token after successfully logging in with their username and password. This token can either be sent to their email address or generated by an authenticator app like Google Authenticator or Microsoft Authenticator.

*If authenticator apps are enabled for MFA, users will have a choice of MFA method*
![mfa-choice](images/screenshots/mfa-choice.png)

*If email is chosen as the MFA method, users will receive an email with a security code*
![mfa-email](images/screenshots/mfa-email.png)

*If an authenticator app is chosen for the MFA method, users will be prompted to enter a code from their authenticator app*
![mfa-authenticator-app](images/screenshots/mfa-authenticator-app.png)



## Installation
* This External Module should be installed via the REDCapREPO
* It may also be installed by unpacking the code into the modules directory on your REDCap web server.

## EM Settings
These are settings/configuration options accessible in the normal External Module settings interface.

### System Settings

| Setting                                                                  |   Type   | Description                                                                                                                                                                                                                                            |     Default Value     |
| :----------------------------------------------------------------------- | :------: | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :-------------------: |
| **Language File**                                                        | Dropdown | The language that participant-facing text in the module will appear in. This system setting can be overridden by the corresponding project setting. See the [Translation](#translation) section below for more information.                            |        English        |
| **Email From Address**                                                   |  Email   | This will be the From email address for all system emails. This can help if emails are being sent to spam or are not delivered.                                                                                                                        | noreply@REDCapPRO.com |
| **Prevent Email Login**                                                  | Checkbox | Should participants be prevented from using their email address to log in to the system. Checking this will require that they login using their participant username only.<br>*Note: if checked, this overrides the associated project-level setting.* |       Unchecked       |
| **Warning Time**                                                         |  Number  | Number of minutes to wait before warning participant of inactivity timeout                                                                                                                                                                             |       1 minute        |
| **Timeout Time**                                                         |  Number  | Number of minutes to wait before logging participant out due to inactivity                                                                                                                                                                             |       5 minutes       |
| **Password Length**                                                      | Integer  | Minimum length of participant's password in characters                                                                                                                                                                                                 |     8 characters      |
| **Login Attempts**                                                       | Integer  | Number of consecutive failed login attempts before being locked out                                                                                                                                                                                    |      3 attempts       |
| **Lockout Duration**                                                     | Integer  | Length of a lockout due to failed login attempts, in seconds                                                                                                                                                                                           |      300 seconds      |
| **Multi-Factor Authentication**                                          | Checkbox | Require participants to use multi-factor authentication when logging in. This requires participants to enter a code sent to their email address in addition to their password.                                                                         |       Unchecked       |
| **Multi-Factor Authentication with Authenticator App** | Checkbox | Allow participants to use an authenticator app (like Google Authenticator or Microsoft Authenticator) to generate a security code for multi-factor authentication. If unchecked, participants will only be able to receive a security code via email. |       Unchecked       |
| **Restrict Multi-Factor Authentication project settings to REDCap administrators** | Checkbox | If checked, only REDCap administrators will be able to access the multi-factor authentication settings in the project. If unchecked, any REDCapPRO manager will be able to access the multi-factor authentication settings. |       Unchecked       |
| **Allow Self-Registration**                                              | Checkbox | Allow participants to [register themselves](#self-registration) with **REDCapPRO**. If checked, a link will appear on the login page that will take the participant to a registration page.                                                            |       Unchecked       |
| **Restrict Self-Registration project settings to REDCap administrators** | Checkbox | If checked, only REDCap administrators will be able to access the self-registration settings in the project. If unchecked, any REDCapPRO manager will be able to access the self-registration settings.                                                |       Unchecked       |
| **Allow Auto-Enroll Upon Self-Registration**                             | Checkbox | Allow participants to enroll themselves in a project when they register. If checked, the participant will be automatically enrolled in the REDCapPRO project when they self-register                                                                   |       Unchecked       |
| **reCaptcha Site Key**                                                   |   Text   | The site key for the reCaptcha v2 service. This is used to prevent bots from registering. You can use the same site key used for REDCap for this purpose as well, if you wish.                                                                         |        (blank)        |
| **reCaptcha Secret Key**                                                 |   Text   | The secret key for the reCaptcha v2 service. This is used to prevent bots from registering. You can use the same secret key used for REDCap for this purpose as well, if you wish.                                                                     |        (blank)        |
| **Enable the API**                                                       | Checkbox | Enable the API for this system. This allows you to register and enroll participants using the [API](#api).                                                                                                                                             |       Unchecked       |
| **Restrict API project settings to REDCap administrators**               | Checkbox | If checked, only REDCap administrators will be able to access the API settings in the project. If unchecked, any REDCapPRO manager will be able to access the API settings.                                                                            |       Unchecked       |

### Project Settings

Project configuration is done within the project's **REDCapPRO** menu. 

### Translation

This external module makes use of the built-in text translation functions in REDCap's External Module framework. These functions use `.ini` files (located in the [lang](lang) directory of this module's source code) to replace placeholder text with the equivalent translated text. 

If the language you desire does not appear in the `Language File` dropdown in the the external module settings, then no `.ini` file has been created for that language. Feel free to supply a translation file in whichever language you need. See the current language files located in the [lang](lang) directory as guides. Feel free to open a pull request or send the file directly to `andrew dot poppe at yale dot edu`.

***Note:** Currently the module only translates participant-facing pages. Work is underway to translate all module pages.*

## REDCapPRO Project Menu

The **REDCapPRO** Project Menu is accessible via a link in the Applications section of the REDCap project side menu. Access to the different sections of the menu is restricted based on the `role` of the user (see the Study Staff tab description for details). The link to the menu itself is only visible to users or role `Monitor` or above, although the Home tab is accessible by anyone. 

### Home Tab

![home](images/screenshots/home.png)

This is an informational page.
This tab is accessible by any role (including no access).

### Manage Participants

![manage](images/screenshots/manage.png)

This tab allows a user to view enrolled participants in this study project and to 
take various actions on study participants. The actions available
and the information that is visible depends on the `role` of the user. The tab
itself is available to Monitors and above.

| Label             |  Type  | Description                                                                                                                                                                                                | Minimum Role to view/use |
| ----------------- | :----: | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------: |
| Username          | Column | Contains the **REDCapPRO** username assigned to this participant                                                                                                                                           |        *Monitor*         |
| First Name        | Column | Contains the first name of this participant                                                                                                                                                                |    <u>Normal User</u>    |
| Last Name         | Column | Contains the last name of this participant                                                                                                                                                                 |    <u>Normal User</u>    |
| Email             | Column | Contains the email address of this participant                                                                                                                                                             |    <u>Normal User</u>    |
| Data Access Group | Column | Contains the DAG the participant is currently in *Note that this is independent of which DAG a REDCap record that corresponds with this participant might be assigned to*                                  |        *Monitor*         |
| Data Access Group | Action | Ability to reassign DAG. The user must not be assigned to a DAG in order to reassign DAGs, much like changing a record's DAG in REDCap. (Prior to version 1.0.0, DAGs in **REDCapPRO** worked differently) |    <u>Normal User</u>    |
| Reset Password    | Action | Sends an email to the participant which contains a password reset link                                                                                                                                     |        *Monitor*         |
| Change Name       | Action | Updates the name in the **REDCapPRO** database for this participant                                                                                                                                        |    <u>**Manager**</u>    |
| Change Email      | Action | Updates the email address in the **REDCapPRO** database for this participant                                                                                                                               |    <u>**Manager**</u>    |
| Disenroll         | Action | Removes the participant from this study project                                                                                                                                                            |    <u>Normal User</u>    |

### Enroll

![enroll](images/screenshots/enroll.png)

This tab allows a user to search for a registered participant in order to enroll them into this study project. Users can
set the participant's Data Access Group at this time as well, if applicable. This tab is available to Normal Users and 
above.

You can enroll many participants at once by importing a CSV file. The file must be formatted with the following columns. *Note: the participants must already be registered with **REDCapPRO** before they can be enrolled in a project.*

| Column name  | Description                                      | Possible values                                            | Required | Notes                                                                                                                                                                                                                                                                                                                                      |
| ------------ | ------------------------------------------------ | ---------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **username** | REDCapPRO username of the participant            | Any text                                                   | Required | Either this column or the `email` column must be present in the import file. **NOT BOTH**                                                                                                                                                                                                                                                  |
| **email**    | Email address of the participant                 | Valid email                                                | Required | Either this column or the `username` column must be present in the import file. **NOT BOTH**                                                                                                                                                                                                                                               |
| **dag**      | Data Access Group to enroll the participant into | Integer value representing the Data Access Group ID number | Optional | This value can be found on the DAGs page in the project. <br>The usual DAG rules apply, so you can only assign a participant to a DAG if that DAG exists in the project. If you are assigned to a DAG yourself, you can only assign participants to that DAG. If you are not assigned to a DAG, you can assign the participant to any DAG. |

You can also register (and optionally enroll) many participants at once by importing a CSV file on the [Register](#Register) tab.

### Register

![register](images/screenshots/register.png)

This tab allows a user to register a participant with **REDCapPRO**. Users also have the option to enroll at the same 
time as registering. They can set the participant's Data Access Group at this time as well, if applicable.  This tab is 
available to Normal Users and above.

You can also register (and optionally enroll) many participants at once by importing a CSV file. The file must be formatted with the following columns.


| Column name | Description                                                                       | Possible values                                            | Required | Notes                                                                                                                                                                                                                                                                                                                                                                                                                   |
| ----------- | --------------------------------------------------------------------------------- | ---------------------------------------------------------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **fname**   | First name of the participant                                                     | Any text                                                   | Required |                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **lname**   | Last name of the participant                                                      | Any text                                                   | Required |                                                                                                                                                                                                                                                                                                                                                                                                                         |
| **email**   | Email address of the participant                                                  | Valid email                                                | Required | The email address must not match the email address of a registered participant. If so, you will receive an error message and the import will be cancelled.                                                                                                                                                                                                                                                              |
| **enroll**  | Whether or not to enroll the participant into this study once they are registered | `Y` to enroll  <br>`<Blank>` not to enroll                 | Optional | You can omit the column entirely if you do not want to enroll any of the newly registered participants.                                                                                                                                                                                                                                                                                                                 |
| **dag**     | Data Access Group to enroll the participant into                                  | Integer value representing the Data Access Group ID number | Optional | This value can be found on the DAGs page in the project. If enroll is not "Y" for a row, then the DAG value is ignored for that row.  <br>The usual DAG rules apply, so you can only assign a participant to a DAG if that DAG exists in the project. If you are assigned to a DAG yourself, you can only assign participants to that DAG. If you are not assigned to a DAG, you can assign the participant to any DAG. |

*Note: The column names are case-sensitive. The order of the columns does not matter.*

*Note: If you are using Excel to create the CSV file, you will need to save the file as a CSV file in order for REDCap to recognize it as such.*


### Study Staff

![staff](images/screenshots/manage-users.png)

This tab allows Managers to set the `role` of users in the study project. All
REDCap users are shown in this table. Set the role of the user according to this
guide:
| Role        | Description                                                                                                                               |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| No Access   | No access to **REDCapPRO** is given to this user.                                                                                         |
| Monitor     | Basic access. Can only view usernames and dags and can only initiate password resets.                                                     |
| Normal User | Able to view participant identifying information and take several participant management actions (see Manage Participants section above). |
| Manager     | Highest permissions. Has the ability to grant/revoke staff access and change a participant's email address                                |

*<u>Note</u>: REDCap administrators have full Manager permissions in **REDCapPRO** no matter what `role` they have in the project (or if they appear in the staff list at all)*

### Settings

![settings](images/screenshots/settings.png)

This tab contains some project-level configuration options. This tab is only 
accessible by managers.
| Setting                                                             |   Type   | Description                                                                                                                                                                                                                                                                | Default Value |
| :------------------------------------------------------------------ | :------: | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :-----------: |
| **Language**                                                        | Dropdown | The language that participant-facing text in the module will appear in. This overrides the default system setting. See the [Translation](#translation) section for more information.                                                                                       |    English    |
| **Prevent Email Login**                                             | Checkbox | If checked, this prevents participants from using their email address to log in to surveys. Instead, they must use their REDCapPRO username to log in. <br>*If email logins are prevented at the system level, this setting will not appear in the project setttings tab.* |   Unchecked   |
| **Multifactor Authentication**                                      | Checkbox | Require participants to use multi-factor authentication when logging in. This requires participants to enter a code sent to their email address in addition to their password.                                                                                             |   Unchecked   |
| **Allow MFA Authenticator App** | Checkbox | Allow participants to use an authenticator app (like Google Authenticator or Microsoft Authenticator) to generate a security code for multi-factor authentication. If unchecked, participants will only be able to receive a security code via email. |   Unchecked   |
| **API**                                                             | Checkbox | Enable the API for this project. This allows you to register and enroll participants using the [API](#api).                                                                                                                                                                |   Unchecked   |
| **Allow Self-Registration**                                         | Checkbox | Allow participants to [register themselves](#self-registration) with **REDCapPRO**. If checked, a link will appear on the login page that will take the participant to a registration page.                                                                                |   Unchecked   |
| **Auto-Enroll Upon Self-Registration**                              | Checkbox | Participants will be automatically enrolled in this project when they self-register.                                                                                                                                                                                       |   Unchecked   |
| **Email address to notify when new participants are auto-enrolled** |  Email   | If auto-enroll is enabled, this email address will be notified when a participant self-registers and is auto-enrolled.                                                                                                                                                     |    (blank)    |
| **Study Contact Name**                                              |   Text   | The name of the study staff member that study participants should contact with questions/problems. This will appear in emails sent to the participant                                                                                                                      |      N/A      |
| **Study Contact Email**                                             |  Email   | Email address that participants should contact.                                                                                                                                                                                                                            |      N/A      |
| **Study Contact Phone**                                             |  Phone   | Phone number that participants should contact.                                                                                                                                                                                                                             |      N/A      |
### Logs
This tab allows Managers to view and export the logs of **REDCapPRO** relevant to this study
project. It only contains information about actions taken in this project, on
surveys tied to this project, or certain actions taken regarding participants enrolled in this project. This tab is only accessible by Managers.

## Self-Registration

![self-registration](images/screenshots/create-account.png)

As of REDCapPRO v2.1.0, the option exists to allow participants to register themselves with **REDCapPRO**. This is done by enabling the `Allow Self-Registration` option in both the [system configuration](#system-settings) and in the [project settings](#settings). When this option is enabled, a **"Don't have an account? Create one"** link will appear on the login page that will take the participant to a registration page.

## REDCapPRO Control Center Menu

This menu is accessible via a link in the External Modules section of the
Control Center. Being in the Control Center, it is only accessible to REDCap
administrators. It has the following sections:

### Projects

![cc-projects](images/screenshots/cc_projects.png)

This table shows all of the REDCap projects that currently have **REDCapPRO**
enabled and some basic information about them.

### Participants

![cc-participants](images/screenshots/cc_participants.png)

This table lists all registered participants in the system. It lists every study
project that each participant is enrolled in. It allows the following actions to
be taken on a participant:

| Action                        | Description                                                                                                                                                                    |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Reset Password                | Sends an email to the participant which contains a password reset link                                                                                                         |
| Update Name                   | Updates the name in the **REDCapPRO** database for this participant                                                                                                            |
| Change Email                  | Updates the email address in the **REDCapPRO** database for this participant                                                                                                   |
| Reactivate/Deactivate Account | A deactivated participant is unable to be enrolled in additional projects. However, they are still able to interact normally with projects to which they are already enrolled. |

### Staff

![cc-staff](images/screenshots/cc_staff.png)

This table lists all REDCap users that have a `role` above No Access in any 
**REDCapPRO** study project. It lists all projects that each user has access to with
a `role` of Monitor or above.

### Logs

![cc-logs](images/screenshots/cc_logs.png)

Similar to the project's Logs tab, this lists all logs made by **REDCapPRO** across
the system.

## API

This module provides an API for interacting with **REDCapPRO**. Using this API is similar to using the REDCap API. You must make a POST request to the API URL (The API URL is found on the project settings page.) The data must contain these three keys:

1. **token**: The REDCap API token
2. **action**: The action you want to perform (either `register` or `enroll`, see below)
3. **data**: A JSON encoded array of participants. Details about the format of the data are described below. 
   
### Register Participants
This method allows you to register participants with **REDCapPRO**. 

* **token**: The REDCap API token
* **action**: `register`
* **data**: A JSON string which represents an array of participant objects. The `data` array can contain any number of participants. Each participant object must have the following:
  
| Field name | Description                                                                       | Possible values                                            | Required | Notes                                                                                                                                                                                                                                                                                                                                                                                                              |
| ---------- | --------------------------------------------------------------------------------- | ---------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **fname**  | First name of the participant                                                     | Any text                                                   | Required |                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **lname**  | Last name of the participant                                                      | Any text                                                   | Required |                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **email**  | Email address of the participant                                                  | Valid email                                                | Required | The email address must not match the email address of a registered participant. If so, you will receive an error message and the import will be cancelled.                                                                                                                                                                                                                                                         |
| **enroll** | Whether or not to enroll the participant into this study once they are registered | `Y` to enroll  <br>Omit to not enroll                      | Optional |                                                                                                                                                                                                                                                                                                                                                                                                                    |
| **dag**    | Data Access Group to enroll the participant into                                  | Integer value representing the Data Access Group ID number | Optional | This value can be found on the DAGs page in the project. If enroll is not "Y" for a participant, then the DAG value is ignored.  <br>The usual DAG rules apply, so you can only assign a participant to a DAG if that DAG exists in the project. If you are assigned to a DAG yourself, you can only assign participants to that DAG. If you are not assigned to a DAG, you can assign the participant to any DAG. |

*Note: The field names are case-sensitive. The order of the fields does not matter.*

### Enroll Participants

This method allows you to enroll already-registered participants into a **REDCapPRO** project. 

* **token**: The REDCap API token
* **action**: `enroll`
* **data**: A JSON string which represents an array of participant objects. The `data` array can contain any number of participants. Each participant object must have the following:
  

| Field name   | Description                                      | Possible values                                            | Required | Notes                                                                                                                                                                                                                                                                                                                                       |
| ------------ | ------------------------------------------------ | ---------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **username** | REDCapPRO username of the participant            | Any text                                                   | Required | Either this field or the `email` field must be present in the import file. **NOT BOTH**                                                                                                                                                                                                                                                     |
| **email**    | Email address of the participant                 | Valid email                                                | Required | Either this field or the `username` field must be present in the import file. **NOT BOTH**                                                                                                                                                                                                                                                  |
| **dag**      | Data Access Group to enroll the participant into | Integer value representing the Data Access Group ID number | Optional | This value can be found on the DAGs page in the project.  <br>The usual DAG rules apply, so you can only assign a participant to a DAG if that DAG exists in the project. If you are assigned to a DAG yourself, you can only assign participants to that DAG. If you are not assigned to a DAG, you can assign the participant to any DAG. |

## Action Tags

This module provides several action tags for populating REDCap fields with 
information about **REDCapPRO** participants. These are described below:
*Note: All action tags must exist on the same data collection instrument (not survey).*

| Action Tag      | Use with field type | Validation on field type | Description                                                                                                                                                          |
| --------------- | :-----------------: | :----------------------: | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| @RCPRO-USERNAME |        text         |           none           | This transforms the field into a dropdown selector. The user can select a participant and populate the field with that participant's username                        |
| @RCPRO-EMAIL    |        text         |          email           | If @RCPRO-USERNAME is present on the instrument, then when it is selected the field with the @RCPRO-EMAIL tag will be populated with the participant's email address |
| @RCPRO-FNAME    |        text         |           none           | Like @RCPRO-EMAIL, but the field will be populated with the participant's first name                                                                                 |
| @RCPRO-LNAME    |        text         |           none           | Likewise, with last name                                                                                                                                             |

## Changelog

| Version | Release Date | Description                                                                                                   |
| ------- | ------------ | ------------------------------------------------------------------------------------------------------------- |
| 2.2.1   | 2023-11-20   | Minor Change and Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.2.1)      |
| 2.2.0   | 2023-11-19   | Feature release - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.2.0)               |
| 2.1.3   | 2023-11-10   | Minor Change to UI Text - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.1.3)       |
| 2.1.2   | 2023-10-30   | Major Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.1.2)                 |
| 2.1.1   | 2023-10-27   | Minor Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.1.1)                 |
| 2.1.0   | 2023-10-10   | Feature release - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.1.0)               |
| 2.0.1   | 2023-10-09   | Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.0.1)                       |
| 2.0.0   | 2023-10-09   | Major release - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/2.0.0)                 |
| 1.0.1   | 2022-04-01   | Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/1.0.1)                       |
| 1.0.0   | 2022-03-31   | Change and bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/1.0.0)            |
| 0.5.0   | 2022-03-30   | Bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.5.0)                       |
| 0.4.9   | 2022-03-24   | Minor bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.9)                 |
| 0.4.8   | 2022-02-23   | Minor bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.8)                 |
| 0.4.7   | 2022-01-04   | Minor bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.7)                 |
| 0.4.6   | 2021-12-14   | Minor bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.6)                 |
| 0.4.5   | 2021-12-12   | Medium security fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.5)           |
| 0.4.4   | 2021-11-15   | Improvement - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.4)                   |
| 0.4.3   | 2021-11-12   | Improvement and minor bug fix - [Release Notes](https://github.com/AndrewPoppe/REDCap-PRO/releases/tag/0.4.3) |
| 0.4.2   | 2021-11-04   | Initial release                                                                                               |
