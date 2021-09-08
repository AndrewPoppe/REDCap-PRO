![REDCapPRO](./images/REDCapPROLOGO_white.png)

# REDCapPRO - Patient Reported Outcomes

[![Psalm Static analysis](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/psalm.yml/badge.svg)](https://github.com/AndrewPoppe/REDCap-PRO/actions/workflows/psalm.yml) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=alert_status)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO) [![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=security_rating)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO) [![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=AndrewPoppe_REDCap-PRO&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=AndrewPoppe_REDCap-PRO)

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
  - [REDCapPRO Project Menu](#redcappro-project-menu)
    - [Home Tab](#home-tab)
    - [Manage Participants](#manage-participants)
    - [Enroll](#enroll)
    - [Register](#register)
    - [Study Staff](#study-staff)
    - [Logs](#logs)
  - [REDCapPRO Control Center Menu](#redcappro-control-center-menu)
    - [Projects](#projects)
    - [Participants](#participants)
    - [Staff](#staff)
    - [Logs](#logs-1)
  - [Action Tags](#action-tags)

## Overview

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
4. **Enroll:** The study staff member then enrolls the participant in this particular REDCap project
5. Survey invitations can then be sent to the participant using REDCap survey distribution tools like normal. Participants will need to log in with their **REDCapPRO** username and password to access the survey. Because the login credentials are not tied to a specific record, public surveys are compatible with **REDCapPRO**.

### What is the process like for participants?
* Upon registration with **REDCapPRO**, the participant will receive an email with a
link to set their password.
* When the participant clicks a link to start a survey in a **REDCapPRO** project, they will see a login screen and will need to supply their username and password.
* They have the option to be sent a password reset email, a username reminder email, and/or an email address reminder email from the login screen.

## Installation
* This External Module should be installed via the REDCapREPO
* It may also be installed by unpacking the code into the modules directory on your REDCap web server.

## EM Settings
These are settings/configuration options accessible in the normal External Module settings interface.

### System Settings

| Setting              |  Type   | Description                                                                | Default Value |
| :------------------- | :-----: | :------------------------------------------------------------------------- | :-----------: |
| **Warning Time**     | Number  | Number of minutes to wait before warning participant of inactivity timeout |   1 minute    |
| **Timeout Time**     | Number  | Number of minutes to wait before logging participant out due to inactivity |   5 minutes   |
| **Password Length**  | Integer | Minimum length of participant's password in characters                     | 8 characters  |
| **Login Attempts**   | Integer | Number of consecutive failed login attempts before being locked out        |  3 attempts   |
| **Lockout Duration** | Integer | Length of a lockout due to failed login attempts, in seconds               |  300 seconds  |

### Project Settings

| Setting                 | Type  | Description                                                                                                                                           | Default Value |
| :---------------------- | :---: | :---------------------------------------------------------------------------------------------------------------------------------------------------- | :-----------: |
| **Study Contact Name**  | Text  | The name of the study staff member that study participants should contact with questions/problems. This will appear in emails sent to the participant |      N/A      |
| **Study Contact Email** | Email | Email address that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                |      N/A      |
| **Study Contact Phone** | Phone | Phone number that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                 |      N/A      |


## REDCapPRO Project Menu

The **REDCapPRO** Project Menu is accessible via a link in the Applications section of the REDCap project side menu. Access to the different sections of the menu is restricted based on the `role` of the user (see the Study Staff tab description for details). The link to the menu itself is only visible to users or role `Monitor` or above, although the Home tab is accessible by anyone. 

### Home Tab
This is an informational page.
This tab is accessible by any role (including no access).

### Manage Participants
This tab allows a user to view enrolled participants in this study project and to 
take various actions on study participants. The actions available
and the information that is visible depends on the `role` of the user. The tab
itself is available to Monitors and above.

| Label             |  Type  | Description                                                                                                                                                               | Minimum Role to view/use |
| ----------------- | :----: | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | :----------------------: |
| Username          | Column | Contains the **REDCapPRO** username assigned to this participant                                                                                                          |        *Monitor*         |
| First Name        | Column | Contains the first name of this participant                                                                                                                               |    <u>Normal User</u>    |
| Last Name         | Column | Contains the last name of this participant                                                                                                                                |    <u>Normal User</u>    |
| Email             | Column | Contains the email address of this participant                                                                                                                            |    <u>Normal User</u>    |
| Data Access Group | Column | Contains the DAG the participant is currently in *Note that this is independent of which DAG a REDCap record that corresponds with this participant might be assigned to* |        *Monitor*         |
| Data Access Group | Action | Ability to reassign DAG. Only DAGs available to the REDCap User are available as options to switch to                                                                     |    <u>Normal User</u>    |
| Reset Password    | Action | Sends an email to the participant which contains a password reset link                                                                                                    |        *Monitor*         |
| Change Email      | Action | Updates the email address in the **REDCapPRO** database for this participant                                                                                              |    <u>**Manager**</u>    |
| Disenroll         | Action | Removes the participant from this study project                                                                                                                           |    <u>Normal User</u>    |

### Enroll
This tab allows a user to search for a registered participant in order to enroll
them into this study project. This tab is available to Normal Users and above.

### Register
This tab allows a user to register a participant with **REDCapPRO**. It is available
to Normal Users and above.

### Study Staff
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

### Logs
This tab allows Managers to view the logs of **REDCapPRO** relevant to this study
project. It only contains information about actions taken in this project or on
surveys tied to this project. 

## REDCapPRO Control Center Menu

This menu is accessible via a link in the External Modules section of the
Control Center. Being in the Control Center, it is only accessible to REDCap
administrators. It has the following sections:

### Projects
This table shows all of the REDCap projects that currently have **REDCapPRO**
enabled and some basic information about them.

### Participants
This table lists all registered participants in the system. It lists every study
project that each participant is enrolled in. It allows the following actions to
be taken on a participant:

| Action         | Description                                                                  |
| -------------- | ---------------------------------------------------------------------------- |
| Reset Password | Sends an email to the participant which contains a password reset link       |
| Change Email   | Updates the email address in the **REDCapPRO** database for this participant |

### Staff
This table lists all REDCap users that have a `role` above No Access in any 
**REDCapPRO** study project. It lists all projects that each user has access to with
a `role` of Monitor or above.

### Logs
Similar to the project's Logs tab, this lists all logs made by **REDCapPRO** across
the system.

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
