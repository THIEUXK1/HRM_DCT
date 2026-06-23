# PROJECT_CONTEXT

This document details the configuration and operational context of the integration between the ERP HRM system and the ZKTime database.

## ZKTime Badge Number Synchronization

### Overview
- **Data Source**: The ZKTime database serves as the source of truth for the device biometric and badge identities. The primary table is `USERINFO` (or custom table configured in `attendance_sources`).
- **Target Field**: The `Badgenumber` field in the ZKTime database represents the unique fingerprint code or card ID of an employee on the time attendance device.
- **HRM Profile Mapping**: The value of `Badgenumber` is mapped and synchronized to the `biometric_id` ("Mã vân tay" / Fingerprint code) field in the `employee_profiles` table in HRM.

### Mapping Rule
- The synchronization maps records based on:
  `employees.employee_code` = `ZKTime.USERINFO.Badgenumber`
- If the column name for the employee code in the HRM database varies (e.g., `code`, `staff_code`, `employee_no`), the system dynamically checks the schema to use the correct existing column.
- Mapping by name is strictly avoided to prevent human identity mismatch errors.

### Safe Write Constraints
- **Default Behavior**: The sync operation is non-destructive. The `biometric_id` in HRM is updated **only** when it is currently empty.
- **Overriding Existing Data**: To overwrite existing `biometric_id` values when they differ from ZKTime's `Badgenumber`, the `--force` option must be supplied.
- **Warnings and Logging**: Discrepancies (different existing codes without `--force` or duplicate `Badgenumber` values in ZKTime) are flagged and reported in the warning log instead of being blindly written.

## ZKTeco Direct Device Employee Synchronization

### Overview
- **Objective**: Synchronize employee profiles (fingerprint code/biometric_id, name, card number, and fingerprint templates) directly from HRM to physical ZKTeco attendance devices over UDP/TCP port 4370.
- **Biometric Templates**: Pushes base64 templates stored in `employee_biometric_templates` table to the hardware.
- **Asynchronous Execution**: Synchronization is performed running in the background via queue worker (`SyncZkTecoEmployeesJob`) with state logging inside `zkteco_sync_batches` and `zkteco_sync_logs`.

### Mapping Rule
- **ERP code -> ZKTeco user id**: Maps `employee_profiles.biometric_id` to ZKTeco User ID (PIN).
- **Name**: Maps `employees.full_name` (truncated to 24 characters).
- **Card number**: Maps `employee_profiles.card_number`.
- **Templates**: Decodes and pushes binary fingerprint templates.

### Connectivity & Dry-run
- **Testing/Local Simulation**: Direct connection to hardware fails locally without device or PHP `sockets` extension. Mock devices with IP `127.0.0.1` and `127.0.0.2` (simulating offline) are supported.
- **Dry-run report**: Allows users to preview matches, missing biometric_id warnings, and device online/offline status before modifying hardware profiles.
- **Artisan Commands**:
  - `php artisan zkteco:device-test {device_id}`: Test device TCP connection.
  - `php artisan zkteco:sync-employees {--employee=ID} {--device=ID} {--dry-run} {--force}`: CLI trigger.
  - `php artisan zkteco:sync-batch {batch_id}`: CLI status and log tracker.
