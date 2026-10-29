<?php

/**
 * Seed sample organizational data, employees, and system users for OrangeHRM.
 *
 * Usage: php installer/seed_sample_data.php
 */

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$pathToAutoload = realpath(__DIR__ . '/../src/vendor/autoload.php');
if (!$pathToAutoload) {
    echo "Composer dependencies not found. Run: composer install -d src\n";
    exit(1);
}

require_once $pathToAutoload;

use DateTime;
use OrangeHRM\Admin\Service\CompanyStructureService;
use OrangeHRM\Admin\Service\EmploymentStatusService;
use OrangeHRM\Admin\Service\JobTitleService;
use OrangeHRM\Admin\Service\LocationService;
use OrangeHRM\Admin\Service\UserService;
use OrangeHRM\Config\Config;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\EmploymentStatus;
use OrangeHRM\Entity\JobTitle;
use OrangeHRM\Entity\LeaveType;
use OrangeHRM\Entity\Location;
use OrangeHRM\Entity\ReportTo;
use OrangeHRM\Entity\Subunit;
use OrangeHRM\Entity\User;
use OrangeHRM\Framework\Framework;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\PluginConfigurationInterface;
use OrangeHRM\Leave\Dao\LeaveTypeDao;
use OrangeHRM\Pim\Dao\EmployeeReportingMethodDao;
use OrangeHRM\Pim\Service\EmployeeService;

const DEFAULT_PASSWORD = 'Ohrm@1423';

set_time_limit(0);

$env = 'prod';
new Framework($env, false);

$request = new Request();
$pluginConfigs = Config::get('ohrm_plugin_configs');
foreach (array_values($pluginConfigs) as $pluginConfig) {
    require_once $pluginConfig['filepath'];
    /** @var PluginConfigurationInterface $configClass */
    $configClass = new $pluginConfig['classname']();
    $configClass->initialize($request);
}

$employeeService = new EmployeeService();
$userService = new UserService();
$companyStructureService = new CompanyStructureService();
$jobTitleService = new JobTitleService();
$employmentStatusService = new EmploymentStatusService();
$locationService = new LocationService();
$leaveTypeDao = new LeaveTypeDao();
$reportingMethodDao = new EmployeeReportingMethodDao();

function println(string $message): void
{
    echo $message . PHP_EOL;
}

function findEmployeeByEmployeeId(EmployeeService $employeeService, string $employeeId): ?Employee
{
    $employees = $employeeService->getEmployeeDao()->getEmployeesByEmployeeIdList([$employeeId]);
    return $employees[0] ?? null;
}

$existing = findEmployeeByEmployeeId($employeeService, '0002');
if ($existing !== null) {
    println('Sample data already exists (employee 0002 found). Skipping seed.');
    exit(0);
}

println('Seeding sample data...');

// --- Organization structure ---
$rootSubunit = $companyStructureService->getSubunitById(1);
if ($rootSubunit === null) {
    println('ERROR: Root subunit not found.');
    exit(1);
}

$subunitDefinitions = [
    'HR' => ['name' => 'Human Resources', 'unitId' => 'HR'],
    'ENG' => ['name' => 'Engineering', 'unitId' => 'ENG'],
    'SAL' => ['name' => 'Sales', 'unitId' => 'SAL'],
    'FIN' => ['name' => 'Finance', 'unitId' => 'FIN'],
];

$subunits = [];
foreach ($subunitDefinitions as $key => $definition) {
    $subunit = new Subunit();
    $subunit->setName($definition['name']);
    $subunit->setUnitId($definition['unitId']);
    $subunit->setDescription($definition['name'] . ' department');
    $companyStructureService->addSubunit($rootSubunit, $subunit);
    $subunits[$key] = $subunit;
    println("  Created subunit: {$definition['name']}");
}

// --- Job titles ---
$jobTitleDefinitions = [
    'hr_manager' => 'HR Manager',
    'developer' => 'Software Developer',
    'senior_developer' => 'Senior Developer',
    'qa_engineer' => 'QA Engineer',
    'sales_executive' => 'Sales Executive',
    'sales_manager' => 'Sales Manager',
    'accountant' => 'Accountant',
];

$jobTitles = [];
foreach ($jobTitleDefinitions as $key => $title) {
    $jobTitle = new JobTitle();
    $jobTitle->setJobTitleName($title);
    $jobTitle->setIsDeleted(false);
    $jobTitles[$key] = $jobTitleService->saveJobTitle($jobTitle);
    println("  Created job title: {$title}");
}

// --- Employment statuses ---
$statusDefinitions = ['Full-Time', 'Contract'];
$empStatuses = [];
foreach ($statusDefinitions as $statusName) {
    $status = new EmploymentStatus();
    $status->setName($statusName);
    $empStatuses[$statusName] = $employmentStatusService->saveEmploymentStatus($status);
    println("  Created employment status: {$statusName}");
}

// --- Locations ---
$locationDefinitions = [
    'hcm' => ['name' => 'Ho Chi Minh Office', 'city' => 'Ho Chi Minh City', 'province' => 'Ho Chi Minh'],
    'hn' => ['name' => 'Hanoi Office', 'city' => 'Hanoi', 'province' => 'Hanoi'],
];

$locations = [];
foreach ($locationDefinitions as $key => $definition) {
    $location = new Location();
    $location->setName($definition['name']);
    $location->setCity($definition['city']);
    $location->setProvince($definition['province']);
    $location->setAddress($definition['name']);
    $location->getDecorator()->setCountryByCountryCode('VN');
    $locations[$key] = $locationService->saveLocation($location);
    println("  Created location: {$definition['name']}");
}

// --- Leave types ---
$leaveTypeDefinitions = ['Annual Leave', 'Sick Leave', 'Personal Leave'];
$leaveTypes = [];
foreach ($leaveTypeDefinitions as $leaveTypeName) {
    $leaveType = new LeaveType();
    $leaveType->setName($leaveTypeName);
    $leaveType->setDeleted(false);
    $leaveType->setSituational(false);
    $leaveTypes[] = $leaveTypeDao->saveLeaveType($leaveType);
    println("  Created leave type: {$leaveTypeName}");
}

// --- Employees ---
$employeeDefinitions = [
    [
        'employeeId' => '0002',
        'firstName' => 'Nguyen',
        'lastName' => 'Van Hai',
        'email' => 'hai.nguyen@example.com',
        'gender' => Employee::GENDER_MALE,
        'subunit' => 'HR',
        'jobTitle' => 'hr_manager',
        'location' => 'hcm',
        'status' => 'Full-Time',
        'joinedDate' => '2022-01-15',
        'username' => 'hai.nguyen',
        'role' => 'Supervisor',
        'supervisorOf' => ['0003', '0004', '0007'],
    ],
    [
        'employeeId' => '0003',
        'firstName' => 'Tran',
        'lastName' => 'Thi Mai',
        'email' => 'mai.tran@example.com',
        'gender' => Employee::GENDER_FEMALE,
        'subunit' => 'ENG',
        'jobTitle' => 'developer',
        'location' => 'hcm',
        'status' => 'Full-Time',
        'joinedDate' => '2022-03-01',
        'username' => 'mai.tran',
        'role' => 'ESS',
        'supervisorId' => '0002',
    ],
    [
        'employeeId' => '0004',
        'firstName' => 'Le',
        'lastName' => 'Van Duc',
        'email' => 'duc.le@example.com',
        'gender' => Employee::GENDER_MALE,
        'subunit' => 'ENG',
        'jobTitle' => 'senior_developer',
        'location' => 'hcm',
        'status' => 'Full-Time',
        'joinedDate' => '2021-06-10',
        'username' => 'duc.le',
        'role' => 'ESS',
        'supervisorId' => '0002',
        'supervisorOf' => ['0008'],
    ],
    [
        'employeeId' => '0005',
        'firstName' => 'Pham',
        'lastName' => 'Thi Lan',
        'email' => 'lan.pham@example.com',
        'gender' => Employee::GENDER_FEMALE,
        'subunit' => 'SAL',
        'jobTitle' => 'sales_executive',
        'location' => 'hn',
        'status' => 'Full-Time',
        'joinedDate' => '2023-02-20',
        'username' => 'lan.pham',
        'role' => 'ESS',
        'supervisorId' => '0006',
    ],
    [
        'employeeId' => '0006',
        'firstName' => 'Hoang',
        'lastName' => 'Van Minh',
        'email' => 'minh.hoang@example.com',
        'gender' => Employee::GENDER_MALE,
        'subunit' => 'SAL',
        'jobTitle' => 'sales_manager',
        'location' => 'hn',
        'status' => 'Full-Time',
        'joinedDate' => '2020-09-01',
        'username' => 'minh.hoang',
        'role' => 'Supervisor',
        'supervisorOf' => ['0005'],
    ],
    [
        'employeeId' => '0007',
        'firstName' => 'Vo',
        'lastName' => 'Thi Hoa',
        'email' => 'hoa.vo@example.com',
        'gender' => Employee::GENDER_FEMALE,
        'subunit' => 'FIN',
        'jobTitle' => 'accountant',
        'location' => 'hcm',
        'status' => 'Full-Time',
        'joinedDate' => '2022-11-05',
        'username' => 'hoa.vo',
        'role' => 'ESS',
        'supervisorId' => '0002',
    ],
    [
        'employeeId' => '0008',
        'firstName' => 'Dang',
        'lastName' => 'Van Khoa',
        'email' => 'khoa.dang@example.com',
        'gender' => Employee::GENDER_MALE,
        'subunit' => 'ENG',
        'jobTitle' => 'qa_engineer',
        'location' => 'hcm',
        'status' => 'Contract',
        'joinedDate' => '2023-08-14',
        'username' => 'khoa.dang',
        'role' => 'ESS',
        'supervisorId' => '0004',
    ],
];

$employeesByEmployeeId = [];
$createdUsers = [];

foreach ($employeeDefinitions as $definition) {
    $employee = new Employee();
    $employee->setFirstName($definition['firstName']);
    $employee->setLastName($definition['lastName']);
    $employee->setEmployeeId($definition['employeeId']);
    $employee->setWorkEmail($definition['email']);
    $employee->setGender($definition['gender']);
    $employee->setJoinedDate(new DateTime($definition['joinedDate']));

    $employeeService->saveNewEmployee($employee);

    $employee->getDecorator()->setSubunitById($subunits[$definition['subunit']]->getId());
    $employee->getDecorator()->setJobTitleById($jobTitles[$definition['jobTitle']]->getId());
    $employee->getDecorator()->setLocationById($locations[$definition['location']]->getId());
    $employee->getDecorator()->setEmpStatusById($empStatuses[$definition['status']]->getId());
    $employeeService->updateEmployeeJobDetails($employee);

    $employeeService->dispatchAddEmployeeEvent($employee);
    $employeesByEmployeeId[$definition['employeeId']] = $employee;

    $userRole = $userService->getUserRole($definition['role']);
    if ($userRole === null) {
        println("ERROR: User role '{$definition['role']}' not found.");
        exit(1);
    }

    $user = new User();
    $user->setUserName($definition['username']);
    $user->setStatus(true);
    $user->setDateEntered(new DateTime());
    $user->setCreatedBy(1);
    $user->getDecorator()->setUserRoleById($userRole->getId());
    $user->getDecorator()->setEmployeeByEmpNumber($employee->getEmpNumber());
    $user->getDecorator()->setNonHashedPassword(DEFAULT_PASSWORD);
    $userService->saveSystemUser($user);

    $createdUsers[] = [
        'username' => $definition['username'],
        'password' => DEFAULT_PASSWORD,
        'role' => $definition['role'],
        'name' => $definition['firstName'] . ' ' . $definition['lastName'],
    ];

    println("  Created employee & user: {$definition['firstName']} {$definition['lastName']} ({$definition['username']})");
}

// --- Supervisor relationships ---
foreach ($employeeDefinitions as $definition) {
    if (!empty($definition['supervisorId'])) {
        $reportTo = new ReportTo();
        $reportTo->getDecorator()->setReportingMethodByReportingMethodId(1);
        $reportTo->getDecorator()->setSubordinateEmployeeByEmpNumber(
            $employeesByEmployeeId[$definition['employeeId']]->getEmpNumber()
        );
        $reportTo->getDecorator()->setSupervisorEmployeeByEmpNumber(
            $employeesByEmployeeId[$definition['supervisorId']]->getEmpNumber()
        );
        $reportingMethodDao->saveEmployeeReportTo($reportTo);
    }
}

println('');
println('Sample data seeded successfully!');
println('');
println('New user accounts (password for all: ' . DEFAULT_PASSWORD . '):');
println(str_repeat('-', 72));
printf("%-15s %-25s %-12s\n", 'Username', 'Name', 'Role');
println(str_repeat('-', 72));
foreach ($createdUsers as $userInfo) {
    printf("%-15s %-25s %-12s\n", $userInfo['username'], $userInfo['name'], $userInfo['role']);
}
println(str_repeat('-', 72));
println('');
println('Summary:');
println('  - 4 departments, 7 job titles, 2 employment statuses');
println('  - 2 office locations, 3 leave types');
println('  - 7 employees with system user accounts');
println('  - Supervisor reporting relationships configured');
