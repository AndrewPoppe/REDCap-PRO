![REDCapPRO](./images/REDCapPROLOGO_white.png)

# REDCapPRO - Patient Reported Outcomes

## Table of Contents <!-- omit in toc -->
- [REDCapPRO - Patient Reported Outcomes](#redcappro---patient-reported-outcomes)
  - [Overview](#overview)
    - [What is REDCapPRO?](#what-is-redcappro)
    - [How does it work?](#how-does-it-work)
  - [Installation](#installation)
  - [EM Settings](#em-settings)
    - [System Settings](#system-settings)
    - [Project Settings](#project-settings)

## Overview

### What is REDCapPRO?
**REDCapPRO** is an external module for [REDCap](https://projectredcap.org) that allows participants/patients to directly report study data (*i.e.*, ePRO). Its primary purpose is to allow the identification of a survey participant and to log that information in a REDCap project's audit trail in a manner compliant with regulatory stipulations (primarily FDA's 21 CFR Part 11). The reason this is needed is that there is no built-in REDCap feature that provides all of the following:
1. **Identification**: It identifies the survey respondent (via a participant ID) in the logs of the project itself. 
2. **Authentication**: It provides a means of proving that the participant is genuinely who they claim to be. There is no way for another person to pretend to be the participant, either in real time or after the fact by modifying logs. Obvious exceptions:

    1. Malicious attacks outside of REDCapPRO (*e.g.*, intercepting a participant's password reset email by hacking their email account) 
    2. REDCap admin or other IT professional directly modifying the database

3. **Authorization**: It provides a means of preventing unauthorized users from viewing identifying information and taking other unauthorized actions.
4. **Convenience**: It must be easy to use for REDCap admins, REDCap users, and study participants.


To achieve this, project users must first register a participant with REDCapPRO and then enroll that participant in the REDCap project. Step-by-step instructions are provided below.

REDCapPRO is not meant to replace any of the following features/modules/external modules:
- Survey Login
- Participant Identifier
- REDCap Mobile App
- [MyCap](https://projectmycap.org/)
- REDCap Survey Auth

### How does it work?
REDCapPRO provides a means for study participants to log in to REDCap surveys using a username created by REDCapPRO and a password that they choose for themselves. This password is not known to any other person (including study staff and REDCap admins). 

These are the steps required to collect data using REDCapPRO:

1. Enable the module in the REDCap system
2. Enable the module in a REDCap project
3. **Register:** A staff member of the study registers a participant with REDCapPRO
   * The participant is registered system-wide. They use the same username and password for all REDCapPRO surveys in this REDCap system regardless of which REDCap project the survey belongs to.
4. **Enroll:** The study staff member then enrolls the participant in this particular REDCap project
5. Survey invitations are sent to the participant using normal REDCap survey distribution tools. Public surveys are also compatible with REDCapPRO.

## Installation
* This External Module should be installed via the REDCapREPO
* It may also be installed by unpacking the code into the modules directory on your REDCap web server.

## EM Settings
These are settings/configuration options accessible in the normal External Module settings interface.

### System Settings

| Setting              | Type    | Description                                                                | Default Value |
| :------------------- | :------ | :------------------------------------------------------------------------- | ------------- |
| **Warning Time**     | Number  | Number of minutes to wait before warning participant of inactivity timeout | 1 minute      |
| **Timeout Time**     | Number  | Number of minutes to wait before logging participant out due to inactivity | 5 minutes     |
| **Password Length**  | Integer | Minimum length of participant's password in characters                     | 8 characters  |
| **Login Attempts**   | Integer | Number of consecutive failed login attempts before being locked out                    | 3 attempts    |
| **Lockout Duration** | Integer | Length of a lockout due to failed login attempts, in seconds               | 300 seconds   |

### Project Settings

| Setting                 | Type  | Description                                                                                                                                           | Default Value |
| :---------------------- | :---- | :---------------------------------------------------------------------------------------------------------------------------------------------------- | :------------ |
| **Study Contact Name**  | Text  | The name of the study staff member that study participants should contact with questions/problems. This will appear in emails sent to the participant | N/A           |
| **Study Contact Email** | Email | Email address that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                | N/A           |
| **Study Contact Phone** | Phone | Phone number that participants should contact (currently Study Contact Name must be defined for this to be presented to participants)                 | N/A           |