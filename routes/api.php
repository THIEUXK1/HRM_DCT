<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\ExternalSyncController;
use App\Http\Controllers\Api\AttendanceDeviceController;
use App\Http\Controllers\Api\AttendanceSourceController;
use App\Http\Controllers\Api\ZkTecoSyncController;
use App\Http\Controllers\Api\AttendanceDevicePunchController;
use App\Http\Controllers\Api\AttendanceGeofenceZoneController;
use App\Http\Controllers\Api\AttendancePunchController;
use App\Http\Controllers\Api\AttendanceCorrectionReasonController;
use App\Http\Controllers\Api\AttendanceDisplayConfigController;
use App\Http\Controllers\Api\AttendanceCorrectionRequestController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\AttendanceSummaryController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BhxhController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompetencyController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\CandidateDocumentController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\RecruitmentMetaController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyPolicyController;
use App\Http\Controllers\Api\PolicyTemplateController;
use App\Http\Controllers\Api\ContractTypeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\WorkShiftController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\JobLevelController;
use App\Http\Controllers\Api\CompanyHolidayController;
use App\Http\Controllers\Api\EmployeePunchAccountController;
use App\Http\Controllers\Api\EmployeeDependentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Controllers\Api\EmployeeAwardDisciplineController;
use App\Http\Controllers\Api\EmployeeTransferController;
use App\Http\Controllers\Api\EmployeeTerminationController;
use App\Http\Controllers\Api\HrMetaController;
use App\Http\Controllers\Api\HrAlertController;
use App\Http\Controllers\Api\HrReportController;
use App\Http\Controllers\Api\EmploymentContractController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PayrollBonusTypeController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\PayrollFormulaRuleController;
use App\Http\Controllers\Api\PayrollFormulaVariableController;
use App\Http\Controllers\Api\EmployeePayrollAllowanceController;
use App\Http\Controllers\Api\PayrollCongLuongImportController;
use App\Http\Controllers\Api\PayrollCycleController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PayrollJournalMappingController;
use App\Http\Controllers\Api\PayrollJournalController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\PublicRecruitmentController;
use App\Http\Controllers\Api\RecruitmentRequestController;
use App\Http\Controllers\Api\BenefitPlanController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrgStructureController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\SelfServiceController;
use App\Http\Controllers\Api\TrainingClassController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\AuthenticateAttendanceDevice;
use App\Http\Middleware\SetCompanyContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('openapi.yaml', function () {
        $path = base_path('docs/openapi-v1.yaml');
        if (! file_exists($path)) {
            return response()->json(['error' => 'OpenAPI file not found'], 500);
        }

        $response = response()->file($path, ['Content-Type' => 'application/x-yaml']);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET');

        return $response;
    });

    Route::get('docs', fn () => response()->view('swagger'));

    Route::prefix('public')->group(function () {
        Route::get('job-posts', [PublicRecruitmentController::class, 'jobPosts']);
        Route::get('job-posts/{job_post}', [PublicRecruitmentController::class, 'show']);
        Route::post('job-posts/{job_post}/apply', [PublicRecruitmentController::class, 'apply']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware(AuthenticateApiToken::class);
        Route::get('me', [AuthController::class, 'me'])->middleware(AuthenticateApiToken::class);
        Route::post('rotate', [AuthController::class, 'rotate'])->middleware(AuthenticateApiToken::class);
        Route::post('change-password', [AuthController::class, 'changePassword'])->middleware(AuthenticateApiToken::class);
    });

    Route::post('attendance/device-punch', [AttendanceDevicePunchController::class, 'store'])
        ->middleware('attendance.device');

    Route::middleware([AuthenticateApiToken::class, SetCompanyContext::class])->group(function () {

        // ── Benefits ──────────────────────────────────────────────────────────
        Route::prefix('benefits')->group(function () {
            Route::get('/',                           [BenefitPlanController::class, 'index']);
            Route::post('/',                          [BenefitPlanController::class, 'store']);
            Route::get('/summary',                    [BenefitPlanController::class, 'summary']);
            Route::get('/enrollments',                [BenefitPlanController::class, 'enrollments']);
            Route::post('/enroll',                    [BenefitPlanController::class, 'enroll']);
            Route::put('/enrollments/{enrollment}',   [BenefitPlanController::class, 'updateEnrollment']);
            Route::get('/employee/{employeeId}',      [BenefitPlanController::class, 'employeeBenefits']);
            Route::get('/{benefitPlan}',              [BenefitPlanController::class, 'show']);
            Route::put('/{benefitPlan}',              [BenefitPlanController::class, 'update']);
            Route::delete('/{benefitPlan}',           [BenefitPlanController::class, 'destroy']);
        });

        // ── Notifications (all authenticated users) ───────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/',      [NotificationController::class, 'index']);
            Route::get('/count', [NotificationController::class, 'count']);
            Route::post('/read', [NotificationController::class, 'markRead']);
            Route::delete('/{notification}', [NotificationController::class, 'destroy']);
        });

        // ── Self-service (all authenticated employees) ────────────────────────
        Route::prefix('self-service')->group(function () {
            Route::get('profile', [SelfServiceController::class, 'profile']);
            Route::put('profile', [SelfServiceController::class, 'updateProfile']);
            Route::get('contracts', [SelfServiceController::class, 'contracts']);
            Route::get('payslips', [SelfServiceController::class, 'payslips']);
            Route::get('leave-requests', [SelfServiceController::class, 'leaveRequests']);
            Route::get('leave-balance', [SelfServiceController::class, 'leaveBalance']);
            Route::get('attendance-summary', [SelfServiceController::class, 'attendanceSummary']);
            Route::get('resignation-requests', [SelfServiceController::class, 'resignationRequests']);
            Route::post('resignation-requests', [SelfServiceController::class, 'storeResignationRequest']);
            Route::post('resignation-requests/{termination}/cancel', [SelfServiceController::class, 'cancelResignationRequest']);
            Route::get('my-kpi', [SelfServiceController::class, 'myKpi']);
            Route::get('attendance/punch/today', [AttendancePunchController::class, 'today']);
            Route::post('attendance/punch', [AttendancePunchController::class, 'punch']);
        });

        // ── Reference / lookup data (read-only, all HR staff) ─────────────────
        Route::get('hr-meta', [HrMetaController::class, 'index']);
        Route::get('hr-alerts', [HrAlertController::class, 'index']);
        Route::get('hr-alerts/summary', [HrAlertController::class, 'summary']);
        Route::get('leave-types', [LeaveTypeController::class, 'index']);
        Route::get('leave-types/meta', [LeaveTypeController::class, 'meta']);
        Route::get('payroll-bonus-types', [PayrollBonusTypeController::class, 'index']);
        Route::get('payroll-bonus-types/meta', [PayrollBonusTypeController::class, 'meta']);

        // ── Audit log (admin + auditor + hr_manager) ─────────────────────────
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);

        // ── Org structure (company admin / hr_manager) ────────────────────────
        Route::middleware('role_or_permission:admin|companies.view|companies.manage|company_policies.view|company_policies.manage')->group(function () {
            Route::get('policy-templates', [PolicyTemplateController::class, 'index']);
            Route::get('policy-templates/{code}', [PolicyTemplateController::class, 'show']);
            Route::post('companies/{company}/apply-policy-template', [PolicyTemplateController::class, 'apply']);
            Route::apiResource('companies', CompanyController::class);
            Route::post('branches/ensure-default', [BranchController::class, 'ensureDefault']);
            Route::apiResource('branches', BranchController::class);
            Route::apiResource('departments', DepartmentController::class);
            Route::apiResource('positions', PositionController::class);
            Route::apiResource('contract-types', ContractTypeController::class);
            Route::post('work-shifts/seed-presets', [WorkShiftController::class, 'seedPresets']);
            Route::apiResource('work-shifts', WorkShiftController::class);
            Route::post('job-levels/seed-standard', [JobLevelController::class, 'seedStandard']);
            Route::apiResource('job-levels', JobLevelController::class);
            Route::apiResource('company-holidays', CompanyHolidayController::class);
            Route::get('company-settings', [CompanySettingController::class, 'index']);
            Route::post('company-settings', [CompanySettingController::class, 'update']);
            Route::get('company-policies', [CompanyPolicyController::class, 'index']);
            Route::get('company-policies/group-comparison', [CompanyPolicyController::class, 'groupComparison']);
            Route::get('company-policies/export', [CompanyPolicyController::class, 'export']);
            Route::post('company-policies/import', [CompanyPolicyController::class, 'import']);
            Route::get('company-policies/versions', [CompanyPolicyController::class, 'versions']);
            Route::get('company-policies/domains/{domain}', [CompanyPolicyController::class, 'showDomain']);
            Route::put('company-policies/domains/{domain}', [CompanyPolicyController::class, 'updateDomain']);
            Route::post('company-policies/apply-to-employees', [CompanyPolicyController::class, 'applyToEmployees']);
            Route::get('company-policies/employee-overrides', [CompanyPolicyController::class, 'employeeOverrides']);
            Route::apiResource('attendance-correction-reasons', AttendanceCorrectionReasonController::class);
            Route::post('org-structure/import', [OrgStructureController::class, 'import']);
        });

        Route::middleware('role_or_permission:admin|leave.manage')->group(function () {
            Route::get('leave-entitlement-groups', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'indexGroups']);
            Route::post('leave-entitlement-groups', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'storeGroup']);
            Route::put('leave-entitlement-groups/{leave_entitlement_group}', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'updateGroup']);
            Route::delete('leave-entitlement-groups/{leave_entitlement_group}', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'destroyGroup']);
            Route::post('leave-entitlement-groups/{leave_entitlement_group}/assign-employees', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'assignEmployees']);
            Route::patch('departments/{department}/leave-entitlement', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'assignDepartment']);
            Route::post('leave-types/seed-standard', [LeaveTypeController::class, 'seedStandard']);
            Route::post('leave-types', [LeaveTypeController::class, 'store']);
            Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update']);
            Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy']);
        });

        Route::middleware('role_or_permission:admin|payroll.manage')->group(function () {
            Route::post('payroll-bonus-types/seed-standard', [PayrollBonusTypeController::class, 'seedStandard']);
            Route::post('payroll-bonus-types', [PayrollBonusTypeController::class, 'store']);
            Route::put('payroll-bonus-types/{payrollBonusType}', [PayrollBonusTypeController::class, 'update']);
            Route::delete('payroll-bonus-types/{payrollBonusType}', [PayrollBonusTypeController::class, 'destroy']);
        });

        // ── Employees (core HR data) ──────────────────────────────────────────
        Route::middleware('role_or_permission:admin|employees.view|employees.manage|employees.edit')->group(function () {
            Route::get('employees/actions/export', [EmployeeController::class, 'export']);
            Route::get('employees/actions/template', [EmployeeController::class, 'downloadTemplate']);
            Route::post('employees/actions/import', [EmployeeController::class, 'import']);
            Route::post('employees/actions/sync-api/prepare', [\App\Http\Controllers\Api\EmployeeSyncController::class, 'prepare']);
            Route::post('employees/actions/sync-api/execute', [\App\Http\Controllers\Api\EmployeeSyncController::class, 'execute']);
            Route::apiResource('employees', EmployeeController::class);
            Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo']);
            Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);
            Route::put('employees/{employee}/profile', [EmployeeController::class, 'updateProfile']);

            Route::get('employees/{employee}/dependents', [EmployeeDependentController::class, 'index']);
            Route::post('employees/{employee}/dependents', [EmployeeDependentController::class, 'store']);
            Route::put('employees/{employee}/dependents/{dependent}', [EmployeeDependentController::class, 'update']);
            Route::delete('employees/{employee}/dependents/{dependent}', [EmployeeDependentController::class, 'destroy']);

            Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index']);
            Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store']);
            Route::post('employees/{employee}/documents/metadata', [EmployeeDocumentController::class, 'storeMetadata']);
            Route::get('employees/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download']);
            Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy']);

            // HR actions
            Route::get('employees/{employee}/awards-discipline', [EmployeeAwardDisciplineController::class, 'index']);
            Route::post('employees/{employee}/awards-discipline', [EmployeeAwardDisciplineController::class, 'store']);
            Route::delete('employees/{employee}/awards-discipline/{awardsDiscipline}', [EmployeeAwardDisciplineController::class, 'destroy']);

            Route::get('employees/{employee}/transfers', [EmployeeTransferController::class, 'index']);
            Route::post('employees/{employee}/transfers', [EmployeeTransferController::class, 'store']);
            Route::post('employees/{employee}/transfers/{transfer}/approve', [EmployeeTransferController::class, 'approve']);

            Route::middleware('role_or_permission:admin|attendance.punch_accounts.manage|attendance.manage')->group(function () {
                Route::get('employees/{employee}/punch-account', [EmployeePunchAccountController::class, 'show']);
                Route::post('employees/{employee}/punch-account', [EmployeePunchAccountController::class, 'provision']);
                Route::delete('employees/{employee}/punch-account', [EmployeePunchAccountController::class, 'revoke']);
            });

            Route::get('employee-terminations', [EmployeeTerminationController::class, 'all']);
            Route::post('employee-terminations/{termination}/approve', [EmployeeTerminationController::class, 'approveById']);
            Route::post('employee-terminations/{termination}/reject', [EmployeeTerminationController::class, 'reject']);
            Route::put('employee-terminations/{termination}', [EmployeeTerminationController::class, 'update']);
            Route::get('employees/{employee}/terminations', [EmployeeTerminationController::class, 'index']);
            Route::post('employees/{employee}/terminations', [EmployeeTerminationController::class, 'store']);
            Route::post('employees/{employee}/terminations/{termination}/approve', [EmployeeTerminationController::class, 'approve']);

            Route::post('employment-contracts/bulk', [EmploymentContractController::class, 'storeBulk']);
            Route::apiResource('employment-contracts', EmploymentContractController::class);
            Route::post('employment-contracts/{employment_contract}/upload', [EmploymentContractController::class, 'upload']);
            Route::get('employment-contracts/{employment_contract}/download', [EmploymentContractController::class, 'download']);
        });

        // ── Onboarding ────────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|employees.view|employees.edit')->group(function () {
            Route::get('onboarding', [OnboardingController::class, 'index']);
            Route::get('employees/{employee}/onboarding', [OnboardingController::class, 'summary']);
            Route::get('employees/{employee}/onboarding-tasks', [OnboardingController::class, 'employeeTasks']);
            Route::put('employees/{employee}/onboarding-tasks/{employeeOnboardingTask}', [OnboardingController::class, 'updateTask']);
            Route::post('employees/{employee}/onboarding/complete', [OnboardingController::class, 'complete']);
        });

        // ── BHXH ─────────────────────────────────────────────────────────────
        Route::prefix('bhxh')->middleware('role_or_permission:admin|bhxh.export|bhxh.manage')->group(function () {
            Route::get('meta', [BhxhController::class, 'meta']);
            Route::get('dashboard', [BhxhController::class, 'dashboard']);
            Route::get('preview', [BhxhController::class, 'preview']);
            Route::post('export', [BhxhController::class, 'export']);
            Route::get('declarations', [BhxhController::class, 'declarations']);
            Route::get('declarations/{bhxhDeclaration}', [BhxhController::class, 'showDeclaration']);
            Route::get('declarations/{bhxhDeclaration}/download', [BhxhController::class, 'downloadDeclaration']);
            Route::post('calculate-contribution', [BhxhController::class, 'calculateContribution']);
        });

        // ── Recruitment / ATS ─────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|candidates.view|candidates.manage')->group(function () {
            Route::get('recruitment-meta', [RecruitmentMetaController::class, 'index']);
            Route::apiResource('recruitment-requests', RecruitmentRequestController::class)->only(['index', 'store', 'show', 'update']);
            Route::post('recruitment-requests/{recruitment_request}/submit', [RecruitmentRequestController::class, 'submit']);
            Route::get('job-posts', [JobPostController::class, 'index']);
            Route::post('job-posts', [JobPostController::class, 'store']);
            Route::get('job-posts/{job_post}', [JobPostController::class, 'show']);
            Route::put('job-posts/{job_post}', [JobPostController::class, 'update']);
            Route::post('job-posts/{job_post}/publish', [JobPostController::class, 'publish']);
            Route::apiResource('candidates', CandidateController::class)->only(['index', 'store', 'show', 'update']);
            Route::patch('candidates/{candidate}/stage', [CandidateController::class, 'updateStage']);
            Route::post('candidates/{candidate}/reject', [CandidateController::class, 'reject']);
            Route::post('candidates/{candidate}/talent-pool', [CandidateController::class, 'moveToTalentPool']);
            Route::post('candidates/{candidate}/hire', [CandidateController::class, 'hire']);
            Route::get('candidates/{candidate}/documents', [CandidateDocumentController::class, 'index']);
            Route::post('candidates/{candidate}/documents', [CandidateDocumentController::class, 'store']);
            Route::get('candidates/{candidate}/documents/{document}/download', [CandidateDocumentController::class, 'download']);
            Route::delete('candidates/{candidate}/documents/{document}', [CandidateDocumentController::class, 'destroy']);
            Route::post('candidates/{candidate}/interviews', [InterviewController::class, 'store']);
            Route::post('interviews/{interview}/feedback', [InterviewController::class, 'storeFeedback']);
            Route::post('candidates/{candidate}/offers', [OfferController::class, 'store']);
            Route::post('offers/{offer}/accept', [OfferController::class, 'accept']);
            Route::post('offers/{offer}/decline', [OfferController::class, 'decline']);
            Route::get('offers/{offer}/letter', [OfferController::class, 'letter']);
        });

        // ── External EHR sync (admin only) ───────────────────────────────────
        Route::middleware('role_or_permission:admin')->group(function () {
            Route::post('admin/sync-external-hr', [ExternalSyncController::class, 'syncHr']);
        });

        // ── User / Role management (admin only) ───────────────────────────────
        Route::middleware('role_or_permission:admin|users.manage')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::get('users/assignable-roles', [UserController::class, 'assignableRoles']);
            Route::put('users/{user}/access', [UserController::class, 'syncAccess']);
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
            Route::put('users/{user}/company-access', [UserController::class, 'syncCompanyAccess']);
            Route::get('users/{user}/company-access', [UserController::class, 'companyAccess']);
            Route::apiResource('roles', RoleController::class);
            Route::get('permissions', [PermissionController::class, 'index']);
        });

        // ── Leave & Overtime ──────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|leave.view|leave.manage|leave.approve')->group(function () {
            Route::get('leave-balances', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'balances']);
            Route::get('employees/{employee}/leave-balance', [\App\Http\Controllers\Api\LeaveEntitlementController::class, 'employeeBalance']);
            Route::get('leave-requests/calculate-days', [LeaveRequestController::class, 'calculateDays']);
            Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'store']);
            Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);

            Route::apiResource('overtime-requests', OvertimeRequestController::class)->only(['index', 'store']);
            Route::post('overtime-requests/{overtimeRequest}/approve', [OvertimeRequestController::class, 'approve']);
            Route::get('overtime-requests/cap-summary', [OvertimeRequestController::class, 'capSummary']);
        });

        // ── Attendance ────────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|attendance.view|attendance.manage')->group(function () {
            Route::apiResource('attendance-geofence-zones', AttendanceGeofenceZoneController::class);
            Route::post('attendance-geofence-zones/{attendance_geofence_zone}/issue-gate-token', [AttendanceGeofenceZoneController::class, 'issueGateToken']);
            Route::post('attendance-devices/push-employees-bulk', [AttendanceDeviceController::class, 'pushEmployeesBulk']);
            Route::apiResource('attendance-devices', AttendanceDeviceController::class)->only(['index', 'store', 'update']);
            Route::post('attendance-devices/{attendanceDevice}/issue-token', [AttendanceDeviceController::class, 'issueToken']);
            Route::post('attendance-devices/{attendanceDevice}/import', [AttendanceDeviceController::class, 'import']);
            Route::post('attendance-devices/{attendanceDevice}/sync', [AttendanceDeviceController::class, 'syncNow']);
            Route::post('attendance-devices/{attendanceDevice}/test-connection', [AttendanceDeviceController::class, 'testConnection']);
            Route::post('attendance-devices/{attendanceDevice}/fetch-info', [AttendanceDeviceController::class, 'fetchDeviceInfo']);
            Route::post('attendance-devices/{attendanceDevice}/push-employees', [AttendanceDeviceController::class, 'pushEmployees']);
            Route::post('attendance-devices/{attendanceDevice}/pull-biometrics', [AttendanceDeviceController::class, 'pullBiometrics']);

            // ZKTeco direct device synchronization
            Route::post('zkteco/sync/dry-run', [ZkTecoSyncController::class, 'dryRun']);
            Route::post('zkteco/sync/run', [ZkTecoSyncController::class, 'run']);
            Route::get('zkteco/sync/batches', [ZkTecoSyncController::class, 'listBatches']);
            Route::get('zkteco/sync/batches/{batch}', [ZkTecoSyncController::class, 'getBatch']);

            // ZKTime SQL Sources
            Route::post('attendance-sources/mappings', [AttendanceSourceController::class, 'saveMapping']);
            Route::apiResource('attendance-sources', AttendanceSourceController::class);
            Route::post('attendance-sources/{attendanceSource}/test-connection', [AttendanceSourceController::class, 'testConnection']);
            Route::post('attendance-sources/{attendanceSource}/sync', [AttendanceSourceController::class, 'syncNow']);
            Route::post('attendance-sources/{attendanceSource}/sync-badge-numbers', [AttendanceSourceController::class, 'syncBadgeNumbers']);
            Route::get('attendance-sources/{attendanceSource}/sync-logs', [AttendanceSourceController::class, 'syncLogs']);
            Route::get('attendance-sources/{attendanceSource}/unmapped-logs', [AttendanceSourceController::class, 'unmappedLogs']);

            Route::get('attendance-summaries/period-status', [AttendanceSummaryController::class, 'periodStatus']);
            Route::get('attendance-summaries', [AttendanceSummaryController::class, 'index']);
            Route::post('attendance-summaries/build', [AttendanceSummaryController::class, 'build']);
            Route::post('attendance-summaries/lock', [AttendanceSummaryController::class, 'lock']);
            Route::get('attendance-display-config', [AttendanceDisplayConfigController::class, 'show']);
            Route::get('attendance-reports/timesheet', [AttendanceReportController::class, 'dailyTimesheet']);
            Route::get('attendance-reports/phased-monthly', [AttendanceReportController::class, 'phasedMonthly']);
            Route::get('attendance-reports/monthly-grid', [AttendanceReportController::class, 'monthlyGrid']);
            Route::get('attendance-reports/overtime', [AttendanceReportController::class, 'overtime']);
            Route::get('attendance-reports/diligence', [AttendanceReportController::class, 'diligence']);
            Route::get('attendance-reports/leave', [AttendanceReportController::class, 'leave']);
            Route::get('attendance-reports/terminations', [AttendanceReportController::class, 'terminations']);
            Route::get('attendance-reports/employee-detail', [AttendanceReportController::class, 'employeeDetail']);
            Route::get('attendance-reports/cong-luong-sheet', [AttendanceReportController::class, 'congLuongSheet']);
            Route::get('attendance-reports/export-cong-luong', [AttendanceReportController::class, 'exportCongLuong']);
            Route::get('attendance-reports/export-employee-detail', [AttendanceReportController::class, 'exportEmployeeDetail']);
            Route::apiResource('attendance-correction-requests', AttendanceCorrectionRequestController::class)->only(['index', 'store']);
            Route::post('attendance-correction-requests/{attendanceCorrectionRequest}/approve', [AttendanceCorrectionRequestController::class, 'approve']);
            Route::post('attendance-correction-requests/{attendanceCorrectionRequest}/reject', [AttendanceCorrectionRequestController::class, 'reject']);

            Route::get('work-schedules/config', [WorkScheduleController::class, 'config']);
            Route::post('work-schedules/seed-defaults', [WorkScheduleController::class, 'seedDefaults']);
            Route::get('work-schedules/groups', [WorkScheduleController::class, 'indexGroups']);
            Route::post('work-schedules/groups', [WorkScheduleController::class, 'storeGroup']);
            Route::put('work-schedules/groups/{workScheduleGroup}', [WorkScheduleController::class, 'updateGroup']);
            Route::get('work-schedules/patterns', [WorkScheduleController::class, 'indexPatterns']);
            Route::post('work-schedules/patterns', [WorkScheduleController::class, 'storePattern']);
            Route::put('work-schedules/patterns/{workSchedulePattern}', [WorkScheduleController::class, 'updatePattern']);
            Route::get('work-schedules/assignments', [WorkScheduleController::class, 'indexAssignments']);
            Route::post('work-schedules/assignments', [WorkScheduleController::class, 'storeAssignment']);
            Route::put('work-schedules/assignments/{employeeWorkSchedule}', [WorkScheduleController::class, 'updateAssignment']);
            Route::get('work-schedules/compliance-alerts', [WorkScheduleController::class, 'complianceAlerts']);
            Route::get('work-schedules/overtime-excess', [WorkScheduleController::class, 'overtimeExcess']);
            Route::post('work-schedules/assignments/bulk', [WorkScheduleController::class, 'bulkAssign']);
            Route::get('work-schedules/week-overrides', [WorkScheduleController::class, 'indexWeekOverrides']);
            Route::post('work-schedules/week-overrides', [WorkScheduleController::class, 'storeWeekOverride']);
        });

        Route::middleware('role_or_permission:admin|attendance.manage')->group(function () {
            Route::put('attendance-display-config', [AttendanceDisplayConfigController::class, 'update']);
        });

        Route::middleware('role:admin')->group(function () {
            Route::post('attendance-summaries/unlock', [AttendanceSummaryController::class, 'unlock']);
            Route::post('payroll-cycles/{payrollCycle}/unlock', [PayrollCycleController::class, 'unlock']);
        });

        // ── Payroll ───────────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|payroll.view|payroll.manage')->group(function () {
            Route::get('payroll-cycles/period-status', [PayrollCycleController::class, 'periodStatus']);
            Route::apiResource('payroll-cycles', PayrollCycleController::class)->only(['index', 'store', 'show']);
            Route::post('payroll-cycles/{payrollCycle}/calculate', [PayrollCycleController::class, 'calculate']);
            Route::post('payroll-cycles/{payrollCycle}/lock', [PayrollCycleController::class, 'lock']);
            Route::get('payroll-cycles/{payrollCycle}/export', [PayrollCycleController::class, 'export']);
            Route::post('payroll-cycles/{payrollCycle}/publish-payslips', [PayslipController::class, 'publishCycle']);
            Route::get('payroll-results/{payrollResult}/payslip', [PayslipController::class, 'show']);
            Route::get('payroll-formula-rules', [PayrollFormulaRuleController::class, 'index']);
            Route::post('payroll-formula-rules', [PayrollFormulaRuleController::class, 'store']);
            Route::put('payroll-formula-rules/{payrollFormulaRule}', [PayrollFormulaRuleController::class, 'update']);
            Route::delete('payroll-formula-rules/{payrollFormulaRule}', [PayrollFormulaRuleController::class, 'destroy']);
            Route::post('payroll-formula-settings', [PayrollFormulaRuleController::class, 'updateSettings']);
            Route::get('payroll-formula-variables', [PayrollFormulaVariableController::class, 'index']);
            Route::put('payroll-formula-variables/parameters', [PayrollFormulaVariableController::class, 'updateParameters']);
            Route::post('payroll-formula-custom-variables', [PayrollFormulaVariableController::class, 'storeCustom']);
            Route::put('payroll-formula-custom-variables/{payrollFormulaCustomVariable}', [PayrollFormulaVariableController::class, 'updateCustom']);
            Route::delete('payroll-formula-custom-variables/{payrollFormulaCustomVariable}', [PayrollFormulaVariableController::class, 'destroyCustom']);
            Route::get('payroll-allowances/catalog', [EmployeePayrollAllowanceController::class, 'catalog']);
            Route::get('payroll-allowances', [EmployeePayrollAllowanceController::class, 'index']);
            Route::post('payroll-allowances', [EmployeePayrollAllowanceController::class, 'upsert']);
            Route::post('payroll-allowances/copy-previous', [EmployeePayrollAllowanceController::class, 'copyFromPrevious']);
            Route::post('payroll-import/cong-luong', [PayrollCongLuongImportController::class, 'import']);

            // Mappings & Journal entries hạch toán (FDI TT 99/2025)
            Route::apiResource('payroll-journal-mappings', PayrollJournalMappingController::class);
            Route::get('payroll-journal-entries', [PayrollJournalController::class, 'index']);
            Route::get('payroll-journal-entries/{payrollJournalEntry}', [PayrollJournalController::class, 'show']);
            Route::post('payroll-journal-entries/{payrollJournalEntry}/post', [PayrollJournalController::class, 'post']);
        });

        // ── Approval workflow ─────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|approvals.view|approvals.act')->group(function () {
            Route::get('approvals/inbox', [ApprovalController::class, 'inbox']);
            Route::post('approvals/{approvalInstance}/approve', [ApprovalController::class, 'approve']);
            Route::post('approvals/{approvalInstance}/reject', [ApprovalController::class, 'reject']);
        });

        // ── LMS / Training ────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|training.view|training.manage')->group(function () {
            Route::get('courses', [CourseController::class, 'index']);
            Route::post('courses', [CourseController::class, 'store']);
            Route::get('courses/{course}/competencies', [CourseController::class, 'competencies']);
            Route::put('courses/{course}/competencies', [CourseController::class, 'syncCompetencies']);
            Route::post('training-classes', [TrainingClassController::class, 'store']);
            Route::post('training-classes/{trainingClass}/enroll', [TrainingClassController::class, 'enroll']);
            Route::post('training-enrollments/{trainingEnrollment}/complete', [TrainingClassController::class, 'complete']);
        });

        // ── Competency ────────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|competency.view|competency.manage')->group(function () {
            Route::get('competency-meta', [CompetencyController::class, 'meta']);
            Route::get('competencies', [CompetencyController::class, 'index']);
            Route::post('competency-groups', [CompetencyController::class, 'storeGroup']);
            Route::post('competencies', [CompetencyController::class, 'storeCompetency']);
            Route::post('competency-assessments', [CompetencyController::class, 'assess']);
            Route::get('employees/{employee}/competency-matrix', [CompetencyController::class, 'employeeMatrix']);
            Route::get('positions/{position}/competency-requirements', [CompetencyController::class, 'positionRequirements']);
            Route::put('positions/{position}/competency-requirements', [CompetencyController::class, 'syncPositionRequirements']);
        });

        // ── Performance / KPI ─────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|performance.view|performance.manage')->group(function () {
            Route::get('performance-meta', [PerformanceController::class, 'meta']);
            Route::get('performance-cycles', [PerformanceController::class, 'cycles']);
            Route::post('performance-cycles', [PerformanceController::class, 'storeCycle']);
            Route::post('goals', [PerformanceController::class, 'storeGoal']);
            Route::put('goals/{goal}', [PerformanceController::class, 'updateGoal']);
            Route::post('employee-reviews', [PerformanceController::class, 'storeReview']);
            Route::put('employee-reviews/{employeeReview}', [PerformanceController::class, 'updateReview']);
            Route::post('employee-reviews/{employeeReview}/finalize', [PerformanceController::class, 'finalizeReview']);
        });

        // ── HR Reports ────────────────────────────────────────────────────────
        Route::middleware('role_or_permission:admin|audit_logs.view|performance.view')->group(function () {
            Route::get('reports/competency-gaps', [HrReportController::class, 'competencyGaps']);
            Route::get('reports/performance-kpi', [HrReportController::class, 'performanceKpi']);
            Route::get('reports/manager-dashboard', [HrReportController::class, 'managerDashboard']);
            Route::get('reports/hr-overview', [HrReportController::class, 'hrOverview']);
            Route::get('reports/workforce-movement', [HrReportController::class, 'workforceMovement']);
            Route::get('reports/workforce-structure', [HrReportController::class, 'workforceStructure']);
            Route::get('reports/recruitment', [HrReportController::class, 'recruitment']);
            Route::get('reports/turnover', [HrReportController::class, 'turnover']);
            Route::get('reports/attendance-leave', [HrReportController::class, 'attendanceLeave']);
            Route::get('reports/payroll-benefits', [HrReportController::class, 'payrollBenefits']);
            Route::get('reports/training', [HrReportController::class, 'training']);
            Route::get('reports/awards-discipline', [HrReportController::class, 'awardsDiscipline']);
            Route::get('reports/executive-summary', [HrReportController::class, 'executiveSummary']);
            // Cross-company group report (M3)
            Route::get('reports/group-summary', [HrReportController::class, 'groupSummary']);
        });
    });
});
