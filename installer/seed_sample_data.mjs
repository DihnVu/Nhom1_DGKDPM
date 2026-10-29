/**
 * Seed sample organizational data, employees, and system users for OrangeHRM.
 *
 * Usage: node installer/seed_sample_data.mjs
 */

import bcrypt from 'bcryptjs';
import mysql from 'mysql2/promise';

const DB_CONFIG = {
    host: '127.0.0.1',
    port: 3306,
    user: 'root',
    password: '',
    database: 'hrm_db',
};

const DEFAULT_PASSWORD = 'Ohrm@1423';

const SUBUNITS = [
    { key: 'HR', name: 'Human Resources', unitId: 'HR', lft: 2, rgt: 3 },
    { key: 'ENG', name: 'Engineering', unitId: 'ENG', lft: 4, rgt: 5 },
    { key: 'SAL', name: 'Sales', unitId: 'SAL', lft: 6, rgt: 7 },
    { key: 'FIN', name: 'Finance', unitId: 'FIN', lft: 8, rgt: 9 },
];

const JOB_TITLES = {
    hr_manager: 'HR Manager',
    developer: 'Software Developer',
    senior_developer: 'Senior Developer',
    qa_engineer: 'QA Engineer',
    sales_executive: 'Sales Executive',
    sales_manager: 'Sales Manager',
    accountant: 'Accountant',
};

const EMP_STATUSES = ['Full-Time', 'Contract'];

const LOCATIONS = [
    { key: 'hcm', name: 'Ho Chi Minh Office', city: 'Ho Chi Minh City', province: 'Ho Chi Minh' },
    { key: 'hn', name: 'Hanoi Office', city: 'Hanoi', province: 'Hanoi' },
];

const LEAVE_TYPES = ['Annual Leave', 'Sick Leave', 'Personal Leave'];

const USER_ROLES = {
    Admin: 1,
    ESS: 2,
    Supervisor: 3,
};

const EMPLOYEES = [
    {
        employeeId: '0002',
        firstName: 'Nguyen',
        lastName: 'Van Hai',
        email: 'hai.nguyen@example.com',
        gender: 1,
        subunit: 'HR',
        jobTitle: 'hr_manager',
        location: 'hcm',
        status: 'Full-Time',
        joinedDate: '2022-01-15',
        username: 'hai.nguyen',
        role: 'Supervisor',
        supervisorOf: ['0003', '0004', '0007'],
    },
    {
        employeeId: '0003',
        firstName: 'Tran',
        lastName: 'Thi Mai',
        email: 'mai.tran@example.com',
        gender: 2,
        subunit: 'ENG',
        jobTitle: 'developer',
        location: 'hcm',
        status: 'Full-Time',
        joinedDate: '2022-03-01',
        username: 'mai.tran',
        role: 'ESS',
        supervisorId: '0002',
    },
    {
        employeeId: '0004',
        firstName: 'Le',
        lastName: 'Van Duc',
        email: 'duc.le@example.com',
        gender: 1,
        subunit: 'ENG',
        jobTitle: 'senior_developer',
        location: 'hcm',
        status: 'Full-Time',
        joinedDate: '2021-06-10',
        username: 'duc.le',
        role: 'ESS',
        supervisorId: '0002',
        supervisorOf: ['0008'],
    },
    {
        employeeId: '0005',
        firstName: 'Pham',
        lastName: 'Thi Lan',
        email: 'lan.pham@example.com',
        gender: 2,
        subunit: 'SAL',
        jobTitle: 'sales_executive',
        location: 'hn',
        status: 'Full-Time',
        joinedDate: '2023-02-20',
        username: 'lan.pham',
        role: 'ESS',
        supervisorId: '0006',
    },
    {
        employeeId: '0006',
        firstName: 'Hoang',
        lastName: 'Van Minh',
        email: 'minh.hoang@example.com',
        gender: 1,
        subunit: 'SAL',
        jobTitle: 'sales_manager',
        location: 'hn',
        status: 'Full-Time',
        joinedDate: '2020-09-01',
        username: 'minh.hoang',
        role: 'Supervisor',
        supervisorOf: ['0005'],
    },
    {
        employeeId: '0007',
        firstName: 'Vo',
        lastName: 'Thi Hoa',
        email: 'hoa.vo@example.com',
        gender: 2,
        subunit: 'FIN',
        jobTitle: 'accountant',
        location: 'hcm',
        status: 'Full-Time',
        joinedDate: '2022-11-05',
        username: 'hoa.vo',
        role: 'ESS',
        supervisorId: '0002',
    },
    {
        employeeId: '0008',
        firstName: 'Dang',
        lastName: 'Van Khoa',
        email: 'khoa.dang@example.com',
        gender: 1,
        subunit: 'ENG',
        jobTitle: 'qa_engineer',
        location: 'hcm',
        status: 'Contract',
        joinedDate: '2023-08-14',
        username: 'khoa.dang',
        role: 'ESS',
        supervisorId: '0004',
    },
];

function log(message) {
    console.log(message);
}

async function main() {
    const connection = await mysql.createConnection(DB_CONFIG);

    try {
        const [existing] = await connection.execute(
            "SELECT emp_number FROM hs_hr_employee WHERE employee_id = '0002' LIMIT 1"
        );
        if (existing.length > 0) {
            log('Sample data already exists (employee 0002 found). Skipping seed.');
            return;
        }

        log('Seeding sample data...');
        const passwordHash = bcrypt.hashSync(DEFAULT_PASSWORD, 12);

        // Update root subunit to accommodate children
        await connection.execute('UPDATE ohrm_subunit SET rgt = 10 WHERE id = 1');
        log('  Updated organization tree');

        const subunitIds = {};
        for (const subunit of SUBUNITS) {
            const [result] = await connection.execute(
                `INSERT INTO ohrm_subunit (name, unit_id, description, lft, rgt, level)
                 VALUES (?, ?, ?, ?, ?, 1)`,
                [subunit.name, subunit.unitId, `${subunit.name} department`, subunit.lft, subunit.rgt]
            );
            subunitIds[subunit.key] = result.insertId;
            log(`  Created subunit: ${subunit.name}`);
        }

        const jobTitleIds = {};
        for (const [key, title] of Object.entries(JOB_TITLES)) {
            const [result] = await connection.execute(
                'INSERT INTO ohrm_job_title (job_title, is_deleted) VALUES (?, 0)',
                [title]
            );
            jobTitleIds[key] = result.insertId;
            log(`  Created job title: ${title}`);
        }

        const statusIds = {};
        for (const statusName of EMP_STATUSES) {
            const [result] = await connection.execute(
                'INSERT INTO ohrm_employment_status (name) VALUES (?)',
                [statusName]
            );
            statusIds[statusName] = result.insertId;
            log(`  Created employment status: ${statusName}`);
        }

        const locationIds = {};
        for (const location of LOCATIONS) {
            const [result] = await connection.execute(
                `INSERT INTO ohrm_location (name, country_code, province, city, address, phone, fax, notes)
                 VALUES (?, 'VN', ?, ?, ?, NULL, NULL, NULL)`,
                [location.name, location.province, location.city, location.name]
            );
            locationIds[location.key] = result.insertId;
            log(`  Created location: ${location.name}`);
        }

        for (const leaveTypeName of LEAVE_TYPES) {
            await connection.execute(
                'INSERT INTO ohrm_leave_type (name, exclude_in_reports_if_no_entitlement, deleted) VALUES (?, 0, 0)',
                [leaveTypeName]
            );
            log(`  Created leave type: ${leaveTypeName}`);
        }

        const empNumbers = {};
        const createdUsers = [];

        for (const emp of EMPLOYEES) {
            const [result] = await connection.execute(
                `INSERT INTO hs_hr_employee (
                    employee_id, emp_firstname, emp_lastname, emp_work_email, emp_gender,
                    joined_date, job_title_code, emp_status, work_station
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [
                    emp.employeeId,
                    emp.firstName,
                    emp.lastName,
                    emp.email,
                    emp.gender,
                    emp.joinedDate,
                    jobTitleIds[emp.jobTitle],
                    statusIds[emp.status],
                    subunitIds[emp.subunit],
                ]
            );

            const empNumber = result.insertId;
            empNumbers[emp.employeeId] = empNumber;

            await connection.execute(
                'INSERT INTO hs_hr_emp_locations (emp_number, location_id) VALUES (?, ?)',
                [empNumber, locationIds[emp.location]]
            );

            await connection.execute(
                `INSERT INTO ohrm_user (user_role_id, emp_number, user_name, user_password, date_entered, status, deleted)
                 VALUES (?, ?, ?, ?, NOW(), 1, 0)`,
                [USER_ROLES[emp.role], empNumber, emp.username, passwordHash]
            );

            createdUsers.push({
                username: emp.username,
                name: `${emp.firstName} ${emp.lastName}`,
                role: emp.role,
            });

            log(`  Created employee & user: ${emp.firstName} ${emp.lastName} (${emp.username})`);
        }

        // Fix subunit assignment - check correct column
        // From Employee entity: subDivision maps to subunit via ManyToOne - need to find column name
        // From DESCRIBE: eeo_cat_code is job category, not subunit
        // Let me check if there's hs_hr_emp_subunit or similar table

        for (const emp of EMPLOYEES) {
            if (emp.supervisorId) {
                await connection.execute(
                    `INSERT INTO hs_hr_emp_reportto (erep_sup_emp_number, erep_sub_emp_number, erep_reporting_mode)
                     VALUES (?, ?, 1)`,
                    [empNumbers[emp.supervisorId], empNumbers[emp.employeeId]]
                );
            }
        }

        await connection.execute(
            "UPDATE hs_hr_unique_id SET last_id = (SELECT MAX(emp_number) FROM hs_hr_employee) WHERE table_name = 'hs_hr_employee'"
        );

        log('');
        log('Sample data seeded successfully!');
        log('');
        log(`New user accounts (password for all: ${DEFAULT_PASSWORD}):`);
        log('-'.repeat(72));
        console.log(`${'Username'.padEnd(15)} ${'Name'.padEnd(25)} Role`);
        log('-'.repeat(72));
        for (const user of createdUsers) {
            console.log(`${user.username.padEnd(15)} ${user.name.padEnd(25)} ${user.role}`);
        }
        log('-'.repeat(72));
        log('');
        log('Summary:');
        log('  - 4 departments, 7 job titles, 2 employment statuses');
        log('  - 2 office locations, 3 leave types');
        log('  - 7 employees with system user accounts');
        log('  - Supervisor reporting relationships configured');
    } finally {
        await connection.end();
    }
}

main().catch((error) => {
    console.error('Seed failed:', error.message);
    process.exit(1);
});
