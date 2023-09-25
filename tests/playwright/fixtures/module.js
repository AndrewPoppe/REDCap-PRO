// @ts-check
export class Module {
    /**
     * @param {import('@playwright/test').Page} page
     * @param {Object} settings - The settings to be used for the module
     * @param {string} settings.redcapVersion - The version of REDCap in the URL of the server, e.g. 'redcap_v13.1.27'
     * @param {string} settings.baseUrl - The base URL of the server, e.g. 'http://localhost:13740'
     * @param {string} settings.username - The username to log in with
     * @param {string} settings.password - The password to log in with
     * @param {Object} settings.module - The module settings
     * @param {string} settings.module.name - The name of the module
     * @param {string} settings.module.version - The version of the module
     */
    constructor(page, settings) {
        this.page = page;
        this.settings = settings;
        this.url = `${this.settings.baseUrl}/${this.settings.redcapVersion}`;
    }

    async logIn() {
        await this.page.goto(`${this.url}`);
        await this.page.screenshot({ path: 'test-results/login.png' });
        await this.page.locator('input#username').fill(this.settings.username);
        await this.page.locator('input#password').fill(this.settings.password);
        await this.page.locator('button#login_btn').click();
    }

    async visitControlCenter() {
        await this.page.goto(`${this.url}/ControlCenter/index.php`, { waitUntil: 'domcontentloaded' });
        await this.page.waitForURL('**/ControlCenter/index.php', { waitUntil: 'domcontentloaded' });
    }

    async visitExternalModuleConfigurationPage() {
        await this.page.goto(`${this.url}/ExternalModules/manager/control_center.php`, { waitUntil: 'domcontentloaded' });

    }

    async enableModuleSystemWide() {
        await this.visitExternalModuleConfigurationPage();
        const enabledModuleRow = this.page.locator(`table#external-modules-enabled tr[data-module="${this.settings.module.name}"]`);
        if (await enabledModuleRow.isVisible()) {
            return;
        }

        await this.page.locator('button#external-modules-enable-modules-button').click();
        await this.page.waitForTimeout(3000);
        //await this.page.locator('div.modal-header', { hasText: 'Available Modules' }).waitFor({ state: 'visible' });
        const enableButton = this.page.locator(`table#external-modules-disabled-table tr[data-module="${this.settings.module.name}"] button.enable-button`);
        await enableButton.waitFor({ state: 'visible' });
        await enableButton.click();

        const popupEnableButton = this.page.locator('div#external-modules-enable-modal div.modal-footer button.enable-button');
        await popupEnableButton.waitFor({ state: 'visible' });
        await popupEnableButton.click();
        await this.page.waitForTimeout(3000);
    }

    async openModuleSystemConfiguration() {
        if (this.page.url() !== `${this.url}/ExternalModules/manager/control_center.php`) {
            await this.visitExternalModuleConfigurationPage();
        }
        await this.page.locator(`tr[data-module="${this.settings.module.name}"] button.external-modules-configure-button`).click();
        await this.page.locator('div#external-modules-configure-modal table tr[field="enabled"]').waitFor({ state: 'visible' });
    }

    async setLanguageToEnglish() {
        await this.openModuleSystemConfiguration();

        // Select Language
        await this.page.locator('select[name="reserved-language-system"]').waitFor({ state: 'visible' });
        await this.page.locator('select[name="reserved-language-system"]').selectOption('');

        // We had some issues with the templates being erased, so wait for a bit for them to load.
        await this.page.waitForTimeout(5000);

        // Save and settle
        await this.page.locator('div#external-modules-configure-modal div.modal-footer button.save').click();
        await this.page.waitForURL('**/ExternalModules/manager/control_center.php', { waitUntil: 'domcontentloaded' });
        await this.page.reload({ waitUntil: 'domcontentloaded' });
    }

    async visitMyProjectsPage() {
        await this.page.goto(`${this.url}`, { waitUntil: 'domcontentloaded' });
        await this.page.locator('a.nav-link', { hasText: 'My Projects' }).click();
    }

    async deleteProjects(projectName) {
        await this.visitMyProjectsPage();
        let projects = this.page.locator('table#table-proj_table a', { hasText: projectName });
        let projects_count = await projects.count();
        if (projects_count === 0) {
            return;
        }
        while (projects_count > 0) {
            await projects.first().click();
            await this.page.locator('div.project_setup_tabs a', { hasText: 'Other Functionality' }).click();
            await this.page.waitForURL('**/ProjectSetup/other_functionality.php?pid=*', { waitUntil: 'domcontentloaded' });
            await this.page.locator('tr#row_delete_project button', { hasText: 'Delete the project' }).click();
            await this.page.locator('input#delete_project_confirm').fill('DELETE');
            await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Delete the project" }).click();
            await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Yes, delete the project" }).click();
            await this.page.locator('div.ui-dialog-titlebar', { hasText: "Project successfully deleted!" }).waitFor({ state: 'visible' });
            await this.visitMyProjectsPage();
            projects = this.page.locator('table#table-proj_table a', { hasText: projectName });
            projects_count = await projects.count();
        }
    }

    /**
     * 
     * @param {String} projectName 
     * @param {String} xmlFile path to the XML file to use for the project
     * @returns {Promise<number>} A promise resolving to the project ID of the newly created project
     */
    async createProject(projectName, xmlFile) {
        await this.visitMyProjectsPage();
        await this.page.locator('div#redcap-home-navbar-collapse a', { hasText: 'My Projects' }).click();
        await this.page.waitForURL('**/index.php?action=myprojects', { waitUntil: 'domcontentloaded' });
        const projLink = this.page.locator('table#table-proj_table a', { hasText: projectName });

        if (await projLink.count() > 0) {
            await this.deleteProjects(projectName);
        }

        await this.page.locator('div#redcap-home-navbar-collapse a', { hasText: 'New Project' }).click();
        await this.page.locator('input#app_title').fill(projectName);
        await this.page.locator('select#purpose').selectOption("0");
        await this.page.locator('input#project_template_radio2').click();
        await this.page.locator('div#odm_file_upload input[name="odm"]').setInputFiles(xmlFile);
        await this.page.locator('button', { hasText: "Create Project" }).click();
        await this.page.waitForTimeout(1000);

        return await this.page.evaluate(() => {
            return pid;
        });
    }

    async visitProjectUserRightsPage(pid) {
        if (this.page.url() !== `${this.url}/UserRights/index.php?pid=${pid}`) {
            await this.visitMyProjectsPage();
            await this.page.locator('table#table-proj_table tr', { has: this.page.locator('td:nth-child(2)', { hasText: pid }) })
                .locator('td:first-child a').click();
            await this.page.locator('div#app_panel a', { hasText: 'User Rights' }).click();
        } else {
            await this.page.reload({ waitUntil: 'domcontentloaded' });
        }
        //await this.page.goto(`${this.url}/UserRights/index.php?pid=${pid}`, { waitUntil: 'domcontentloaded' });
    }

    async visitDAGPage(pid) {
        if (this.page.url() !== `${this.url}/index.php?route=DataAccessGroupsController:index&pid=${pid}`) {
            await this.visitMyProjectsPage();
            await this.page.locator('table#table-proj_table tr', { has: this.page.locator('td:nth-child(2)', { hasText: pid }) })
                .locator('td:first-child a').click();
            await this.page.locator('div#app_panel a', { hasText: 'DAGs' }).click();
        } else {
            await this.page.reload({ waitUntil: 'domcontentloaded' });
        }
    }

    /**
     * 
     * @param {Number} pid 
     * @param {Array<string>} usernames
     * @param {string} rights What rights should the user have in the project? One of "default", "none", "all" 
     */
    async addUsersToProject(pid, usernames, rights = "default") {
        for (let username of usernames) {
            await this.visitProjectUserRightsPage(pid);
            await this.page.locator('input#new_username').fill(username);
            await this.page.locator('button#addUserBtn').click();
            await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
            if (rights === "all") {
                const checkboxes = this.page.locator('div#editUserPopup input[type="checkbox"]:not([name="mobile_app"])');
                const checkboxes_count = await checkboxes.count();
                for (let checkbox_index = 0; checkbox_index < checkboxes_count; checkbox_index++) {
                    await checkboxes.nth(checkbox_index).check();
                }
                await this.page.locator('div#editUserPopup input[name="mobile_app"]').check();
                await this.page
                    .locator('div.ui-dialog', { has: this.page.locator('div#mobileAppEnableConfirm') })
                    .locator('button', { hasText: "Yes, I understand" })
                    .click();

                await this.page.locator('div#editUserPopup input[name="lock_record"][value="2"]').check();
                await this.page.locator('div.ui-dialog-titlebar', { hasText: 'NOTICE' }).locator('button.ui-dialog-titlebar-close').click();

                await this.page.locator('div#editUserPopup input[name="form-form_1"][value="1"]').check();
                await this.page.locator('div#editUserPopup input[name="export-form-form_1"][value="1"]').check();
            } else if (rights === "none") {
                const checkboxes = this.page.locator('div#editUserPopup input[type="checkbox"]');
                const checkboxes_count = await checkboxes.count();
                for (let checkbox_index = 0; checkbox_index < checkboxes_count; checkbox_index++) {
                    await checkboxes.nth(checkbox_index).uncheck();
                }
                await this.page.locator('div#editUserPopup input[name="lock_record"][value="0"]').check();
                await this.page.locator('div#editUserPopup input[name="form-form_1"][value="0"]').check();
                await this.page.locator('div#editUserPopup input[name="export-form-form_1"][value="0"]').check();
            }
            await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Add user" }).click();
        }
    }

    async addUsersToProjectInRole(pid, usernames, roleName) {
        for (let username of usernames) {
            await this.visitProjectUserRightsPage(pid);
            await this.page.locator('input#new_username_assign').fill(username);
            await this.page.locator('button#assignUserBtn').click();
            await this.page.locator('select#user_role').waitFor({ state: 'visible' });
            await this.page.locator('select#user_role').selectOption({ label: roleName });
            await this.page.waitForTimeout(1000);
            await this.page.locator('select#user_role').selectOption({ label: roleName }, { force: true });
            await this.page.locator('button#assignDagRoleBtn').click();
        }
    }

    async removeUserFromProject(pid, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.userNameLinkDiv a[userid="${username}"]`).click();
        const removeFromRoleButton = this.page.locator('div#tooltipBtnRemoveRole button');
        if (await removeFromRoleButton.isVisible()) {
            await removeFromRoleButton.click();
            await this.page.waitForTimeout(1000);
            await this.removeUserFromProject(pid, username);
            return;
        }
        await this.page.locator('div#tooltipBtnSetCustom button').click();
        await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Remove user" }).click();
        await this.page.locator('div[role="dialog"]', { has: this.page.locator('div.ui-dialog-titlebar', { hasText: "Remove user?" }) }).locator('button', { hasText: "Remove user" }).click();
    }

    async grantAllRightsToUser(pid, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.userNameLinkDiv a[userid="${username}"]`).click();
        await this.page.locator('div#tooltipBtnSetCustom button').click();
        await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
        const checkboxes = this.page.locator('div#editUserPopup input[type="checkbox"]:not([name="mobile_app"])');
        const checkboxes_count = await checkboxes.count();
        for (let checkbox_index = 0; checkbox_index < checkboxes_count; checkbox_index++) {
            await checkboxes.nth(checkbox_index).check();
        }
        await this.page.locator('div#editUserPopup input[name="mobile_app"]').check();
        await this.page
            .locator('div.ui-dialog', { has: this.page.locator('div#mobileAppEnableConfirm') })
            .locator('button', { hasText: "Yes, I understand" })
            .click();

        await this.page.locator('div#editUserPopup input[name="lock_record"][value="2"]').check();
        await this.page.locator('div.ui-dialog-titlebar', { hasText: 'NOTICE' }).locator('button.ui-dialog-titlebar-close').click();

        await this.page.locator('div#editUserPopup input[name="form-form_1"][value="1"]').check();
        await this.page.locator('div#editUserPopup input[name="export-form-form_1"][value="1"]').check();

        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Save Changes" }).click();
    }

    async grantNoRightsToUser(pid, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.userNameLinkDiv a[userid="${username}"]`).click();
        await this.page.locator('div#tooltipBtnSetCustom button').click();
        await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
        const checkboxes = this.page.locator('div#editUserPopup input[type="checkbox"]');
        const checkboxes_count = await checkboxes.count();
        for (let checkbox_index = 0; checkbox_index < checkboxes_count; checkbox_index++) {
            await checkboxes.nth(checkbox_index).uncheck();
        }
        await this.page.locator('div#editUserPopup input[name="lock_record"][value="0"]').check();
        await this.page.locator('div#editUserPopup input[name="form-form_1"][value="0"]').check();
        await this.page.locator('div#editUserPopup input[name="export-form-form_1"][value="0"]').check();

        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Save Changes" }).click();
    }

    async enableModule(pid) {
        await this.page.goto(`${this.url}/ExternalModules/manager/project.php?pid=${pid}`, { waitUntil: 'domcontentloaded' });
        await this.page.locator('button#external-modules-enable-modules-button').click();
        await this.page.locator(`tr[data-module="${this.settings.module.name}"] button.enable-button`).click();
        await this.page.locator(`table#external-modules-enabled tr[data-module="${this.settings.module.name}"]`).waitFor({ state: 'visible' });
    }

    async disableModule(pid) {
        await this.page.goto(`${this.url}/ExternalModules/manager/project.php?pid=${pid}`, { waitUntil: 'domcontentloaded' });
        await this.page.locator(`tr[data-module="${this.settings.module.name}"] button.external-modules-disable-button`).click();
        await this.page.locator('div#external-modules-disable-confirm-modal button#external-modules-disable-button-confirmed').click();
        await this.page.reload({ waitUntil: 'domcontentloaded' });
    }

    async expireUser(pid, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.expireLinkDiv a[userid="${username}"]`).click();
        await this.page.locator('input#tooltipExpiration').click();
        await this.page.waitForTimeout(500);
        await this.page.keyboard.press('Enter');
        await this.page.locator('input#tooltipExpiration').fill('01/01/1970');
        await this.page.locator('button#tooltipExpirationBtn').click();
        await this.page.locator(`a.userRightsExpired[userid="${username}"]`).waitFor({ state: 'visible' });
    }

    async unexpireUser(pid, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.expireLinkDiv a[userid="${username}"]`).click();
        await this.page.locator('input#tooltipExpiration').click();
        await this.page.waitForTimeout(500);
        await this.page.keyboard.press('Enter');
        await this.page.locator('input#tooltipExpiration').clear();
        await this.page.locator('button#tooltipExpirationBtn').click();
    }

    async createRole(pid, name) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator('input#new_rolename').fill(name);
        await this.page.locator('button#createRoleBtn').click();
        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Create role" }).click();

    }

    async addUserToRole(pid, roleName, username) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`div.userNameLinkDiv a[userid="${username}"]`).click();
        await this.page.locator('button#assignUserBtn2').click();
        await this.page.locator('#roles_option').waitFor({ state: 'visible' });
        await this.page.waitForTimeout(1000);
        await this.page.locator('select#user_role').selectOption({ label: roleName });
        await this.page.locator('select#user_role').selectOption({ label: roleName }, { force: true });
        await this.page.waitForTimeout(1000);
        await this.page.locator('button#assignDagRoleBtn').click();
    }

    async grantNoRightsToRole(pid, roleName) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`table#table-user_rights_roles_table td:first-child a`, { hasText: roleName }).click();
        await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
        const checkboxes = this.page.locator('div#editUserPopup input[type="checkbox"]');
        const checkboxes_count = await checkboxes.count();
        for (let checkbox_index = 0; checkbox_index < checkboxes_count; checkbox_index++) {
            await checkboxes.nth(checkbox_index).uncheck();
        }
        await this.page.locator('div#editUserPopup input[name="lock_record"][value="0"]').check();
        await this.page.locator('div#editUserPopup input[name="form-form_1"][value="0"]').check();
        await this.page.locator('div#editUserPopup input[name="export-form-form_1"][value="0"]').check();

        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Save Changes" }).click();
    }

    async grantUserRightsToRole(pid, roleName, rightName) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator(`table#table-user_rights_roles_table td:first-child a`, { hasText: roleName }).click();
        await this.page.locator('div#editUserPopup').waitFor({ state: 'visible' });
        await this.page.locator(`div#editUserPopup input[name="${rightName}"]`).check();
        await this.page.locator('div.ui-dialog-buttonset button', { hasText: "Save Changes" }).click();
    }

    async visitLoggingPage(pid) {
        if (this.page.url() !== `${this.url}/Logging/index.php?pid=${pid}`) {
            await this.visitMyProjectsPage();
            await this.page.locator('table#table-proj_table tr', { has: this.page.locator('td:nth-child(2)', { hasText: pid }) })
                .locator('td:first-child a').click();
            await this.page.locator('div#app_panel a', { hasText: /^Logging$/ }).click();
        } else {
            await this.page.reload({ waitUntil: 'domcontentloaded' });
        }
    }

    async importUserCSV(pid, csv) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator('button', { hasText: 'Upload or download users, roles, and assignments' }).click();
        await this.page.locator('ul#downloadUploadUsersDropdown a', { hasText: 'Upload users (CSV)' }).click();
        await this.page.locator('form#importUserForm').waitFor({ state: 'visible' });
        await this.page.locator('form#importUserForm input[type="file"]').setInputFiles(csv);
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUsersDialog') }).locator('button', { hasText: 'Upload' }).click();
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUsersDialog2[title="Upload users (CSV) - Confirm"]') }).locator('button', { hasText: 'Upload' }).click();
    }

    async importRoleCSV(pid, csv) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator('button', { hasText: 'Upload or download users, roles, and assignments' }).click();
        await this.page.locator('ul#downloadUploadUsersDropdown a', { hasText: 'Upload user roles (CSV)' }).click();
        await this.page.locator('form#importRoleForm').waitFor({ state: 'visible' });
        await this.page.locator('form#importRoleForm input[type="file"]').setInputFiles(csv);
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importRolesDialog') }).locator('button', { hasText: 'Upload' }).click();
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importRolesDialog2[title="Upload user roles (CSV) - Confirm"]') }).locator('button', { hasText: 'Upload' }).click();
    }

    async getUniqueRoleName(pid, roleLabel) {
        await this.visitProjectUserRightsPage(pid);
        const tr = this.page.locator('table#table-user_rights_roles_table tr', { has: this.page.locator('td:first-child a', { hasText: roleLabel }) });
        const uniqueRoleName = await tr.locator('td:last-child').textContent();
        return uniqueRoleName?.trim();
    }

    async importRoleAssignmentsCSV(pid, csv) {
        await this.visitProjectUserRightsPage(pid);
        await this.page.locator('button', { hasText: 'Upload or download users, roles, and assignments' }).click();
        await this.page.locator('ul#downloadUploadUsersDropdown a', { hasText: 'Upload user role assignments (CSV)' }).click();
        await this.page.locator('form#importUserRoleForm').waitFor({ state: 'visible' });
        await this.page.locator('form#importUserRoleForm input[type="file"]').setInputFiles(csv);
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUserRoleDialog') }).locator('button', { hasText: 'Upload' }).click();
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUserRoleDialog2[title="Upload user role assignments (CSV) - Confirm"]') }).locator('button', { hasText: 'Upload' }).click();
    }

    async importDAGAssignmentsCSV(pid, csv) {
        await this.visitDAGPage(pid);
        await this.page.locator('button', { hasText: 'Upload or download DAGs/User-DAG assignments' }).click();
        await this.page.locator('a', { hasText: 'Upload User-DAG assignments (CSV)' }).click();
        await this.page.locator('form#importUserDAGForm input[name="file"]').setInputFiles(csv);
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUserDAGDialog') }).locator('button', { hasText: 'Upload' }).click();
        await this.page.locator('div.ui-dialog', { has: this.page.locator('div#importUserDAGsDialog2') }).locator('button', { hasText: 'Upload' }).click();
    }

    async setDAGSwitcher(pid, users, dags) {
        await this.visitDAGPage(pid);
        for (let user of users) {
            for (let dag of dags) {
                await this.page.locator(`input[data-user="${user}"][data-dag="${dag}"]`).click();
            }
        }
    }

    async visitApiPage(pid) {
        await this.visitMyProjectsPage();
        await this.page.locator('table#table-proj_table tr', { has: this.page.locator('td:nth-child(2)', { hasText: pid }) })
            .locator('td:first-child a').click();
        await this.page.locator('div#app_panel a', { hasText: /^API$/ }).click();
    }

    async getApiToken(pid) {
        await this.visitApiPage(pid);
        const apiInput = this.page.locator('input#apiTokenId');
        if (await apiInput.isVisible()) {
            return await apiInput.inputValue();
        }
        await this.page.locator('div#apiReqBoxId button', { hasText: 'Create API token now' }).click();
        await this.page.locator('input#apiTokenId').waitFor({ state: 'visible' });
        return await this.page.locator('input#apiTokenId').inputValue();
    }

    async visitProjectStatusPage(pid) {
        await this.page.goto(`${this.url}/ExternalModules/?prefix=${this.settings.module.name}&page=project-status&pid=${pid}`, { waitUntil: 'domcontentloaded' });
    }

    async visitRegisterPage(pid) {
        await this.page.goto(`${this.url}/ExternalModules/?prefix=${this.settings.module.name}&page=src%2Fregister&pid=${pid}`, { waitUntil: 'domcontentloaded' });
    }

    async changeEmailForExistingParticipant(email) {
        await this.visitSystemParticipantsPage();
        await this.page.locator('tr', { hasText: email }).locator('a[title="Change Email Address"]').click();
        await this.page.locator('div.swal2-popup input.swal2-input').fill('fakeemail_' + Math.random().toString(16).substring(2) + '@example.com');
        await this.page.locator('div.swal2-popup button.swal2-confirm').click();
    }

    async visitSystemParticipantsPage() {
        await this.page.goto(`${this.url}/ExternalModules/?prefix=${this.settings.module.name}&page=src%2Fcc_participants`, { waitUntil: 'domcontentloaded' });
    }

}

