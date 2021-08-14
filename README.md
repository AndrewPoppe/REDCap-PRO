![REDCapPRO](./images/REDCapPROLOGO_white.png)

# REDCapPRO - Patient Reported Outcomes

## Table of Contents <!-- omit in toc -->
- [REDCapPRO - Patient Reported Outcomes](#redcappro---patient-reported-outcomes)
  - [Overview](#overview)
  - [Installation](#installation)
  - [Settings](#settings)
    - [System Settings](#system-settings)

## Overview
This external module is designed to bring an ePRO interface to REDCap. Its primary purpose is to allow the identification of a survey taker and to log that information in a REDCap project's audit trail. The reason this is needed is that there is no built-in method of providing authentication and identification of survey respondents that provides all of the following:
1. **Identification**: It identifies the survey respondent (via a participant ID) in the logs of the project itself. 
2. **Authentication**: It provides a means of proving that the participant is genuinely who they claim to be. There is no way for another person to pretend to be the participant, either in real time or after the fact by modifying logs. Obvious exceptions:

    1. Malicious attacks outside of REDCapPRO (*e.g.*, intercepting a participant's password reset email by hacking their email account) 
    2. REDCap admin or other IT professional modifying the database

3. **Authorization**: It provides a means of preventing unauthorized users from viewing identifying information and taking other unauthorized actions.
4. **Convenience**: It must be easy to use for REDCap admins, REDCap users, and study participants.


To achieve this, project users must first register a participant with REDCapPRO and then enroll that participant in the REDCap project. Step-by-step instructions are provided below.



## Installation
* This External Module should be installed via the REDCapREPO
* It may also be installed by unpacking the code into the modules directory on your REDCap web server.

## Settings

### System Settings

| Setting              | Type    | Description                                                                | Default Value |
| :------------------- | :------ | :------------------------------------------------------------------------- | ------------- |
| **Warning Time**     | Number  | Number of minutes to wait before warning participant of inactivity timeout | 1 minute      |
| **Timeout Time**     | Number  | Number of minutes to wait before logging participant out due to inactivity | 5 minutes     |
| **Password Length**  | Integer | Minimum length of participant's password in characters                     | 8 characters  |
| **Login Attempts**   | Integer | Number of failed login attempts before being locked out                    | 3 attempts    |
| **Lockout Duration** | Integer | Length of a lockout due to failed login attempts, in seconds               | 300 seconds   |