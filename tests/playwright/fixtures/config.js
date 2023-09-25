exports.config = {
    module: {
        name: 'redcap_pro',
        version: '',
    },
    redcapVersion: 'redcap_v13.1.27',
    redcapUrl: 'http://localhost:13740',
    emailUrl: 'http://localhost:13745/mailhog',
    projects: {
        Project: {
            projectName: 'REDCapPRO EM - Test Project',
            pid: 286,
            xml: 'data_files/TestProject.xml'
        }
    },
    roles: {
        Test: {
            id: null,
            name: 'Test',
            uniqueRoleName: null
        }
    },
    users: {
        NormalUser1: {
            username: 'alice',
            password: 'password'
        },
        NormalUser2: {
            username: 'bob',
            password: 'password'
        },
        AdminUser: {
            username: 'admin',
            password: 'password'
        }
    },
    participants: {
        Participant1: {
            firstName: 'Test',
            lastName: 'User1',
            email: 'test@user1.com',
            password: 'Password1!'
        },
        Participant2: {
            firstName: 'Test',
            lastName: 'User2',
            email: 'test@user2.com'
        }
    },
    system_em_framework_config: {
        languages: [
            'English',
            'Chinese',
            'Deutsch',
            'Español',
            'Français'
        ],
        default_options: [
            "Language file",
            "Enable module on all projects by default",
            "Make module discoverable by users",
            "Allow non-admins to enable this module on projects",
            "Hide this module from non-admins in the list of enabled modules on each project"
        ],
        custom_options: [
            "Email From Address",
            "Prevent Email Login",
            "Warning Time",
            "Timeout Time",
            "Password Length",
            "Login Attempts",
            "Lockout Duration"
        ]
    },
    project_em_framework_config: {
        project_menu_tabs: [
            'Home',
            'Manage Participants',
            'Enroll',
            'Register',
            'Study Staff',
            'Settings',
            'Logs'
        ]
    }
}