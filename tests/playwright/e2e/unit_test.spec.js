
const fs = require('fs');
const { parse } = require('csv-parse/sync');
const { stringify } = require('csv-stringify/sync');
const { transform } = require('stream-transform/sync');
const { test, expect } = require('../fixtures/initModule');
const { config } = require('../fixtures/config');

// Annotate entire file as serial.
test.describe.configure({ mode: 'serial' });


// Start tests
test.describe('Setup', () => {
    test('Setup system and projects', async ({ modulePage }, testInfo) => {
        test.setTimeout(300000);
        const outDir = `test-results/${testInfo.project.name}`;
        modulePage.page.video().saveAs(`${outDir}/S0_Setup.mp4`);
        await test.step('Log in and enable module', async () => {
            await modulePage.enableModuleSystemWide();
            await modulePage.setLanguageToEnglish();
        });

        await test.step('Check module system configuration', async () => {
            await modulePage.openModuleSystemConfiguration();
            const select = modulePage.page.locator('select[name="reserved-language-system"]');
            for (let language of config.system_em_framework_config.languages) {
                await expect(await select.locator('option', { hasText: language }).count()).toEqual(1);
            }
            await modulePage.page.locator('select[name="reserved-language-system"]').click();
            const settingTable = modulePage.page.locator('div#external-modules-configure-modal table');
            for (let defaultSetting of config.system_em_framework_config.default_options) {
                await expect(await settingTable.locator('label', { hasText: new RegExp(`^${defaultSetting}$`) }).count()).toEqual(1);
            }
            for (let customSetting of config.system_em_framework_config.custom_options) {
                await expect(await settingTable.locator('label', { hasText: customSetting }).count()).toBeGreaterThan(0);
            }
        });

        await test.step('Setup project', async () => {
            config.projects.Project.pid = await modulePage.createProject(config.projects.Project.projectName, config.projects.Project.xml);

            // Create User Import CSV File
            const userImportData = fs.readFileSync('data_files/templates/UserImport_Template.csv');
            const userImportRecords = parse(userImportData, { columns: true, trim: true });
            const editedRecords = transform(userImportRecords, (record) => {
                record.username = config.users.NormalUser1.username.trim();
                return record;
            });
            const editedUserImportData = stringify(editedRecords, { header: true });
            fs.writeFileSync('data_files/UserImport.csv', editedUserImportData);

            // Create DAG Import CSV File
            const dagImportData = fs.readFileSync('data_files/templates/DAGImport_Template.csv');
            const dagImportRecords = parse(dagImportData, { columns: true, trim: true });
            const editedDagRecords = transform(dagImportRecords, (record) => {
                record.username = config.users.NormalUser1.username.trim();
                return record;
            });
            const editedDagImportData = stringify(editedDagRecords, { header: true });
            fs.writeFileSync('data_files/DAGImport.csv', editedDagImportData);

            // Import Users
            await modulePage.importUserCSV(config.projects.Project.pid, 'data_files/UserImport.csv');

            // Import DAGs
            await modulePage.importDAGAssignmentsCSV(config.projects.Project.pid, 'data_files/DAGImport.csv');

            // Set DAG Switcher
            config.dag1 = (await modulePage.page.locator('tr', { has: modulePage.page.locator('td', { hasText: 'dag_1' }) }).locator('td').nth(4).innerText()).valueOf();
            console.log(config.dag1);
            await modulePage.setDAGSwitcher(config.projects.Project.pid, [config.users.NormalUser1.username], [0, config.dag1]);

            // Enable EM
            await modulePage.enableModule(config.projects.Project.pid);
            await modulePage.page.reload({ waitUntil: 'domcontentloaded' });
            await expect(modulePage.page.locator('div#app_panel a > span#RCPro-Link')).toBeVisible();

            // Check menu tabs
            await modulePage.page.locator('div#app_panel a > span#RCPro-Link').click();
            for (let tab of config.project_em_framework_config.project_menu_tabs) {
                await expect(modulePage.page.locator('div#center nav li a', { hasText: tab })).toBeVisible();
            }

            // Disable EM
            await modulePage.disableModule(config.projects.Project.pid);

            // Re-Enable EM
            await modulePage.enableModule(config.projects.Project.pid);

        });
    });
});

test.describe('Registering a Participant', () => {
    test('Register', async ({ modulePage, emailPage }, testInfo) => {
        //test.setTimeout(300000);
        const outDir = `test-results/${testInfo.project.name}`;
        modulePage.page.video().saveAs(`${outDir}/S1_Registering_a_participant_REDCap.mp4`);
        emailPage.page.video().saveAs(`${outDir}/S1_Registering_a_participant_Email.mp4`);
        await test.step('initiate registration', async () => {

            await modulePage.changeEmailForExistingParticipant(config.participants.Participant1.email);

            await modulePage.visitRegisterPage(config.projects.Project.pid);
            await expect(modulePage.page.locator('div.form-group', { hasText: 'First Name' })).toBeVisible();
            await expect(modulePage.page.locator('div.form-group', { hasText: 'Last Name' })).toBeVisible();
            await expect(modulePage.page.locator('div.form-group', { hasText: 'Email' })).toBeVisible();

            await modulePage.page.locator('form.rcpro-form.register-form button[type="submit"]').click();
            await expect(modulePage.page.locator('span', { hasText: 'Please enter a first name for this participant.' })).toBeVisible();
            await expect(modulePage.page.locator('span', { hasText: 'Please enter a last name for this participant.' })).toBeVisible();
            await expect(modulePage.page.locator('span', { hasText: 'Please enter a valid email address.' })).toBeVisible();

            await modulePage.page.locator('input[name="REDCapPRO_FName"]').fill(config.participants.Participant1.firstName);
            await modulePage.page.locator('input[name="REDCapPRO_LName"]').fill(config.participants.Participant1.lastName);
            const email = await modulePage.page.locator('input[name="REDCapPRO_Email"]');
            await email.fill('bad.email');
            await modulePage.page.locator('form.rcpro-form.register-form button[type="submit"]').click();

            const emailValidationMessage = await email.evaluate((element) => {
                return element.validationMessage;
            });
            await expect(emailValidationMessage).toContain("Please include an '@' in the email address. 'bad.email' is missing an '@'.");

            await emailPage.deleteAllMessages();
            await email.fill(config.participants.Participant1.email);
            await modulePage.page.locator('form.rcpro-form.register-form button[type="submit"]').click();
            await expect(modulePage.page.locator('div.swal2-popup', { hasText: 'Participant Registered' })).toBeVisible();

            await emailPage.openInbox();
            const registrationEmail = await emailPage.findEmailBySubject('REDCapPRO - Account Created');
            await registrationEmail.click();
            await expect(await emailPage.page.locator('tr', {
                has: emailPage.page.locator('th', { hasText: 'From' })
            }).locator('td').nth(0).innerText()).toEqual('noreply@REDCapPRO.com');
        });
    });
});













//         modulePage.page.video().saveAs(`${outDir}/S1_UI_FRS-VL-SAGEM-001-User_Rights.mp4`);
//         await test.step('Add users to project and enable module', async () => {
//             console.log(config);
//             await modulePage.addUsersToProject(config.projects.UI_Project.pid, [config.users.NothingUser.username, config.users.EverythingUser.username]);
//             await modulePage.enableModule(config.projects.UI_Project.pid);
//             await expect(modulePage.page.locator('table#external-modules-enabled tr[data-module="security_access_groups"]', { timeout: 30000 })).toBeVisible();
//             await expect(modulePage.page.locator('div#external_modules_panel a[data-link-key="security_access_groups-project-status"]')).toBeVisible();
//             await modulePage.page.locator('div#external_modules_panel a[data-link-key="security_access_groups-project-status"]').click();
//             await modulePage.page.waitForLoadState('domcontentloaded');
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-26-project_status_page.png`, fullPage: false });
//         });
//         await test.step('Test Project Status page', async () => {
//             await modulePage.visitProjectStatusPage(config.projects.UI_Project.pid);
//             const numRows = await modulePage.page.locator('table#discrepancy-table>tbody>tr').count();
//             await expect(numRows).toEqual(3);
//             const displayUsersButton = modulePage.page.locator('button#displayUsersButton');
//             await expect(displayUsersButton).toBeVisible();
//             const emailUsersButton = modulePage.page.locator('div#containerCard button', { hasText: "Email User(s)" });
//             await expect(emailUsersButton).toBeVisible();
//             const emailUserRightsHoldersButton = modulePage.page.locator('div#containerCard button', { hasText: "Email User Rights Holders" });
//             await expect(emailUserRightsHoldersButton).toBeVisible();
//             const expireUsersButton = modulePage.page.locator('div#containerCard button', { hasText: "Expire User(s)" });
//             await expect(expireUsersButton).toBeVisible();
//             const searchUsersInput = modulePage.page.locator('div.dataTables_filter input[type="search"]');
//             await expect(searchUsersInput).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-27-project_status_page.png`, fullPage: false });
//         });
//         await test.step('Attempt to give noncompliant rights to user', async () => {
//             await modulePage.grantAllRightsToUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot grant those user rights to user "${config.users.NothingUser.username}"` });
//             await expect(errorPopup).toBeVisible();
//             const rights = modulePage.page.locator('div.swal2-content table tbody tr');
//             await expect(rights).toHaveCount(24);
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-03-attempt_to_give_noncompliant_rights_to_user.png`, fullPage: false });
//         });
//         await test.step('Attempt to give compliant rights to user', async () => {
//             await modulePage.grantAllRightsToUser(config.projects.UI_Project.pid, config.users.EverythingUser.username);
//             const successPopup = modulePage.page.locator('div.userSaveMsg', { hasText: `User "${config.users.EverythingUser.username}" was successfully edited` });
//             await expect(successPopup).toBeVisible();
//         });
//         await test.step('Attempt to unexpire an expired user with noncompliant rights', async () => {
//             await modulePage.expireUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             await expect(modulePage.page.locator(`a.userRightsExpired[userid="${config.users.NothingUser.username}"]`)).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-17-expire_user_with_noncompliant_rights.png`, fullPage: false });
//             await modulePage.grantAllRightsToUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             const successPopup = modulePage.page.locator('div.userSaveMsg', { hasText: `User "${config.users.NothingUser.username}" was successfully edited` });
//             await expect(successPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-18-grant_any_rights_to_expired_user.png`, fullPage: false });
//             await modulePage.unexpireUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot grant those user rights to user "${config.users.NothingUser.username}"` });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-09-attempt_to_unexpire_user_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Remove rights from user and unexpire', async () => {
//             await modulePage.grantNoRightsToUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             await modulePage.unexpireUser(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             await expect(modulePage.page.locator(`a.userRightsExpireN[userid="${config.users.NothingUser.username}"]`)).toBeVisible();
//         });
//         await test.step('Create role with default rights and attempt to add both users to it', async () => {
//             await modulePage.createRole(config.projects.UI_Project.pid, config.roles.Test.name);
//             await modulePage.addUserToRole(config.projects.UI_Project.pid, config.roles.Test.name, config.users.EverythingUser.username);
//             const successPopup = modulePage.page.locator('div.userSaveMsg', { hasText: `User "${config.users.EverythingUser.username}" has been successfully ASSIGNED to the user role "${config.roles.Test.name}".` });
//             await expect(successPopup).toBeVisible();
//             await modulePage.addUserToRole(config.projects.UI_Project.pid, config.roles.Test.name, config.users.NothingUser.username);
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot assign user "${config.users.NothingUser.username}" to user role "${config.roles.Test.name}"` });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-04-attempt_to_add_user_to_role_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Remove all rights from role and add user to it', async () => {
//             await modulePage.grantNoRightsToRole(config.projects.UI_Project.pid, config.roles.Test.name);
//             await modulePage.addUserToRole(config.projects.UI_Project.pid, config.roles.Test.name, config.users.NothingUser.username);
//             const successPopup = modulePage.page.locator('div.userSaveMsg', { hasText: `User "${config.users.NothingUser.username}" has been successfully ASSIGNED to the user role "${config.roles.Test.name}".` });
//             await expect(successPopup).toBeVisible();
//         });
//         await test.step('Attempt to grant User Rights right to role', async () => {
//             await modulePage.grantUserRightsToRole(config.projects.UI_Project.pid, config.roles.Test.name, 'user_rights');
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot grant those rights to the role` });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-05-attempt_to_add_noncompliant_rights_to_a_role.png`, fullPage: false });
//         });
//         await test.step('Attempt to add user to project with custom noncompliant rights', async () => {
//             await modulePage.removeUserFromProject(config.projects.UI_Project.pid, config.users.NothingUser.username);
//             await modulePage.addUsersToProject(config.projects.UI_Project.pid, [config.users.NothingUser.username]);
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot grant those user rights to user "${config.users.NothingUser.username}"` });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-01-attempt_to_add_user_to_project_with_custom_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Attempt to add user to project with no rights', async () => {
//             await modulePage.addUsersToProject(config.projects.UI_Project.pid, [config.users.NothingUser.username], 'none');
//             const successPopup = modulePage.page.locator('div.userSaveMsg', { hasText: `User "${config.users.NothingUser.username}" was successfully added` });
//             await expect(successPopup).toBeVisible();
//             await modulePage.removeUserFromProject(config.projects.UI_Project.pid, config.users.NothingUser.username);
//         });
//         await test.step('Attempt to add user to project in a role with noncompliant rights', async () => {
//             await modulePage.grantUserRightsToRole(config.projects.UI_Project.pid, config.roles.Test.name, 'user_rights');
//             await modulePage.addUsersToProjectInRole(config.projects.UI_Project.pid, [config.users.NothingUser.username], config.roles.Test.name);
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: `You cannot assign user "${config.users.NothingUser.username}" to user role "${config.roles.Test.name}"` });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-02-attempt_to_add_user_to_project_in_role_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Check logging page', async () => {
//             await modulePage.visitLoggingPage(config.projects.UI_Project.pid);
//             const table = modulePage.page.locator('table[logeventtable]');

//             const addUserRow = table.locator('tr', { hasText: /Add user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(addUserRow).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-14-logging.png`, fullPage: true });
//             await addUserRow.scrollIntoViewIfNeeded();
//             await addUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editUserRow = table.locator('tr', { hasText: /Update user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editUserRow).toBeVisible();
//             await editUserRow.scrollIntoViewIfNeeded();
//             await editUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const expireUserRow = table.locator('tr', { hasText: /Updated User Expiration/, has: modulePage.page.locator('td', { hasText: 'expiration date = ' }) }).first();
//             await expect(expireUserRow).toBeVisible();
//             await expireUserRow.scrollIntoViewIfNeeded();
//             await expireUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const createRoleRow = table.locator('tr', { hasText: /Create user role/, has: modulePage.page.locator('td', { hasText: 'rights = ' }) }).first();
//             await expect(createRoleRow).toBeVisible();
//             await createRoleRow.scrollIntoViewIfNeeded();
//             await createRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editRoleRow = table.locator('tr', { hasText: /Edit user role/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editRoleRow).toBeVisible();
//             await editRoleRow.scrollIntoViewIfNeeded();
//             await editRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             await modulePage.page.waitForTimeout(5000);
//         });
//     });

//     test('Via the User Rights page: CSV', async ({ modulePage }, testInfo) => {
//         test.setTimeout(300000);
//         const outDir = `test-results/${testInfo.project.name}/S2_IMPORT`;
//         modulePage.page.video().saveAs(`${outDir}/S2_IMPORT_FRS-VL-SAGEM-001-User_Rights.mp4`);
//         await test.step('Enable module', async () => {
//             console.log(config);
//             await modulePage.enableModule(config.projects.CSV_Project.pid);
//             await expect(modulePage.page.locator('table#external-modules-enabled tr[data-module="security_access_groups"]', { timeout: 30000 })).toBeVisible();
//         });

//         // Users
//         await test.step('Import user via CSV - successfully', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserImport_1_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.username = config.users.EverythingUser.username.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserImport_1.csv', editedCsvData);

//             await modulePage.importUserCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserImport_1.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Import user via CSV - unsuccessfully', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserImport_2_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.username = config.users.NothingUser.username.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserImport_2.csv', editedCsvData);

//             await modulePage.importUserCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserImport_2.csv');
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: 'You cannot import those users' });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-06-Attempt_to_import_user_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Edit user via CSV - successfully', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserImport_4_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.username = config.users.EverythingUser.username.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserImport_4.csv', editedCsvData);

//             await modulePage.importUserCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserImport_4.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Import user via CSV - successfully (part 2)', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserImport_3_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.username = config.users.NothingUser.username.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserImport_3.csv', editedCsvData);

//             await modulePage.importUserCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserImport_3.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Edit user via CSV - unsuccessfully', async () => {
//             await modulePage.importUserCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserImport_2.csv');
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: 'You cannot import those users' });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-06-Attempt_to_edit_user_with_noncompliant_rights.png`, fullPage: false });
//         });

//         // Roles
//         await test.step('Import role via CSV - successfully', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserRoles_1_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.role_label = config.roles.Test.name.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserRoles_1.csv', editedCsvData);


//             await modulePage.importRoleCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoles_1.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//             config.roles.Test.uniqueRoleName = await modulePage.getUniqueRoleName(config.projects.CSV_Project.pid, 'Test');
//         });
//         await test.step('Edit role via CSV - successfully', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserRoles_2_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.unique_role_name = config.roles.Test.uniqueRoleName.trim();
//                 record.role_label = config.roles.Test.name.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserRoles_2.csv', editedCsvData);

//             await modulePage.importRoleCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoles_2.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Fail to assign user to role', async () => {
//             const csvData = fs.readFileSync('data_files/templates/S2_IMPORT_UserRoleAssignments_1_Template.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.unique_role_name = config.roles.Test.uniqueRoleName.trim();
//                 record.username = config.users.NothingUser.username.trim();
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserRoleAssignments_1.csv', editedCsvData);

//             await modulePage.importRoleAssignmentsCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoleAssignments_1.csv');
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: 'You cannot assign those users to those roles' });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-08-Attempt_to_assign_user_to_role_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Assign user to role', async () => {
//             const csvData = fs.readFileSync('data_files/S2_IMPORT_UserRoles_2.csv');
//             const records = parse(csvData, { columns: true, trim: true });
//             const editedRecords = transform(records, (record) => {
//                 record.user_rights = 0;
//                 return record;
//             });
//             const editedCsvData = stringify(editedRecords, { header: true });
//             fs.writeFileSync('data_files/S2_IMPORT_UserRoles_3.csv', editedCsvData);
//             await modulePage.importRoleCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoles_3.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();

//             await modulePage.importRoleAssignmentsCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoleAssignments_1.csv');
//             await expect(modulePage.page.locator('div.ui-dialog div.ui-dialog-titlebar', { hasText: 'SUCCESS!' }, { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Fail to edit role', async () => {
//             await modulePage.importRoleCSV(config.projects.CSV_Project.pid, 'data_files/S2_IMPORT_UserRoles_2.csv');
//             const errorPopup = modulePage.page.locator('h2#swal2-title', { hasText: 'You cannot import those roles' });
//             await expect(errorPopup).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-07-Attempt_to_edit_role_with_noncompliant_rights.png`, fullPage: false });
//         });

//         // Logging
//         await test.step('Check logging page', async () => {
//             await modulePage.visitLoggingPage(config.projects.CSV_Project.pid);
//             const table = modulePage.page.locator('table[logeventtable]');

//             const importUserRow = table.locator('tr', { hasText: /Add user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(importUserRow).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-14-logging.png`, fullPage: true });
//             await importUserRow.scrollIntoViewIfNeeded();
//             await importUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editUserRow = table.locator('tr', { hasText: /Update user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editUserRow).toBeVisible();
//             await editUserRow.scrollIntoViewIfNeeded();
//             await editUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const importRoleRow = table.locator('tr', { hasText: /Create user role/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(importRoleRow).toBeVisible();
//             await importRoleRow.scrollIntoViewIfNeeded();
//             await importRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editRoleRow = table.locator('tr', { hasText: /Edit user role/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editRoleRow).toBeVisible();
//             await editRoleRow.scrollIntoViewIfNeeded();
//             await editRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const assignUserRow = table.locator('tr', { hasText: /User assigned to role/, has: modulePage.page.locator('td', { hasText: 'unique_role_name = ' }) }).first();
//             await expect(assignUserRow).toBeVisible();
//             await assignUserRow.scrollIntoViewIfNeeded();
//             await assignUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             await modulePage.page.waitForTimeout(5000);
//         });
//     });

//     test('Via the API', async ({ modulePage }, testInfo) => {
//         test.setTimeout(300000);
//         const outDir = `test-results/${testInfo.project.name}/S3_API`;
//         modulePage.page.video().saveAs(`${outDir}/S3_API_FRS-VL-SAGEM-001-User_Rights.mp4`);
//         await test.step('Enable module', async () => {
//             await modulePage.enableModule(config.projects.API_Project.pid);
//             await expect(modulePage.page.locator('table#external-modules-enabled tr[data-module="security_access_groups"]', { timeout: 30000 })).toBeVisible();
//         });
//         await test.step('Enable API and get token', async () => {
//             config.api_token = await modulePage.getApiToken(config.projects.API_Project.pid);
//         });
//         await test.step('Fail to add user with noncompliant rights', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'user',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         user_rights: '1'
//                     }])
//                 }
//             });
//             await expect(response.status()).toBe(401);
//             console.log(await response.json());
//             const expected = {};
//             expected[config.users.NothingUser.username] = ['User Rights'];
//             await expect(await response.json()).toEqual(expected);
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-10-attempt_to_add_user_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Add user with noncompliant rights and expire them', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'user',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         user_rights: '1',
//                         expiration: '1970-01-01'
//                     }])
//                 }
//             });
//             await expect(response.ok()).toBeTruthy();
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/Add_user_with_noncompliant_rights_and_expire.png`, fullPage: false });
//         });
//         await test.step('Add role via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'userRole',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         role_label: config.roles.Test.name,
//                         user_rights: '1'
//                     }])
//                 }
//             });
//             await expect(response.ok()).toBeTruthy();
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/Add_role_via_API.png`, fullPage: false });
//             config.roles.Test.uniqueRoleName = await modulePage.getUniqueRoleName(config.projects.API_Project.pid, config.roles.Test.name);
//         });
//         await test.step('Edit user via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'user',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         user_rights: '0',
//                         expiration: ''
//                     }])
//                 }
//             });
//             await expect(response.ok()).toBeTruthy();
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/Edit_user_via_API.png`, fullPage: false });
//         });
//         await test.step('Fail to grant user noncompliant rights via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'user',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         user_rights: '1'
//                     }])
//                 }
//             });
//             await expect(response.status()).toBe(401);
//             console.log(await response.json());
//             const expected = {};
//             expected[config.users.NothingUser.username] = ['User Rights'];
//             await expect(await response.json()).toEqual(expected);
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-11-attempt_to_edit_user_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Fail to add user to role with noncompliant rights via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'userRoleMapping',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         unique_role_name: config.roles.Test.uniqueRoleName
//                     }])
//                 }
//             });
//             await expect(response.status()).toBe(401);
//             console.log(await response.json());
//             const expected = {};
//             expected[config.roles.Test.name] = ['User Rights'];
//             await expect(await response.json()).toEqual(expected);
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-12-attempt_to_add_user_to_role_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Edit role via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'userRole',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         role_label: config.roles.Test.name,
//                         unique_role_name: config.roles.Test.uniqueRoleName,
//                         user_rights: '0'
//                     }])
//                 }
//             });
//             await expect(response.ok()).toBeTruthy();
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/Edit_role_via_API.png`, fullPage: false });
//         });
//         await test.step('Add user to role via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'userRoleMapping',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         username: config.users.NothingUser.username,
//                         unique_role_name: config.roles.Test.uniqueRoleName
//                     }])
//                 }
//             });
//             await expect(response.ok()).toBeTruthy();
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/Add_user_to_role_via_API.png`, fullPage: false });
//         });
//         await test.step('Fail to grant role noncompliant rights via API', async () => {
//             const response = await apiContext.post('', {
//                 form: {
//                     token: config.api_token,
//                     content: 'userRole',
//                     format: 'json',
//                     returnFormat: 'json',
//                     data: JSON.stringify([{
//                         unique_role_name: config.roles.Test.uniqueRoleName,
//                         user_rights: '1'
//                     }])
//                 }
//             });
//             await expect(response.status()).toBe(401);
//             await modulePage.visitProjectUserRightsPage(config.projects.API_Project.pid);
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-13-attempt_to_edit_role_with_noncompliant_rights.png`, fullPage: false });
//         });
//         await test.step('Check logging page', async () => {
//             await modulePage.visitLoggingPage(config.projects.API_Project.pid);
//             const table = modulePage.page.locator('table[logeventtable]');

//             const importUserRow = table.locator('tr', { hasText: /Add user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(importUserRow).toBeVisible();
//             await modulePage.page.screenshot({ path: `${outDir}/FRS-VL-SAGEM-001-14-logging.png`, fullPage: true });
//             await importUserRow.scrollIntoViewIfNeeded();
//             await importUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editUserRow = table.locator('tr', { hasText: /Update user/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editUserRow).toBeVisible();
//             await editUserRow.scrollIntoViewIfNeeded();
//             await editUserRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const importRoleRow = table.locator('tr', { hasText: /Create user role/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(importRoleRow).toBeVisible();
//             await importRoleRow.scrollIntoViewIfNeeded();
//             await importRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             const editRoleRow = table.locator('tr', { hasText: /Edit user role/, has: modulePage.page.locator('td', { hasText: 'changes = ' }) }).first();
//             await expect(editRoleRow).toBeVisible();
//             await editRoleRow.scrollIntoViewIfNeeded();
//             await editRoleRow.highlight();
//             await modulePage.page.waitForTimeout(1000);

//             await modulePage.page.waitForTimeout(5000);
//         });
//     });
// });