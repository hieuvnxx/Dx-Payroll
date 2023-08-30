<p align="center"><a href="https://dx.smartosc.com/" target="_blank"><img src="https://dx.smartosc.com/wp-content/uploads/2022/08/logo-color-page-1.png" width="400" alt="Laravel Logo"></a></p>

## About Dx SmartOSC Payroll Package

Dx SmartOSC Payroll Package is accessible, powerful, and provides library for specify HRM Payroll Vietnam country (Asian)

## Prerequirements

- Laravel version 9.*
- PHP version 8.0
- Composer 2

## Installation
- Install the lastest version
```sh
  composer require dxsmartosc/payroll
```

```sh
  php artisan migrate
  php artisan db:seed --class="Dx\Payroll\Database\Seeders\RefreshTokenSeeder"
  php artisan dxpayroll:migrateZohoForm

  manual update section name for employee form, payroll module. This below is sample for sbs staging site:
  Employee form:
  section label:Education => name:Skills & Languages
  section label:WorkExperience => name:Work experience/Kinh nghiệm công việc
  section label:Training_at_SmartOSC => name:Training at SmartOSC/Đào tạo ở SmartOSC
  section label:Dependent => name:Dependents & Family Members/Thành viên gia đình và cá nhân
  section label:Social_Insurances => name:Social Insurance/Bảo hiểm xã hội
  section label:Languages => name:SI/TU Adjustment
  section label:Salary_History => name:Salary History
  section label:Allowance1 => name:Allowances
  section label:Others => name:Bonus & Others
  section label:Passport => name:Passport/Hộ chiếu
  section label:Attendance_History => name:Attendance History/Lịch sử hiện diện
  section label:History_of_Employee_information => name:Employee information History/Lịch sử thông tin nhân viên

  Monthly form:
  section label:working_salary_detail1 => name:Attendance details/Bảng công chi tiết

  Payslip form:
  section label:salary_total => name:Total Salary/Tổng lương
  section label:bonus_table => name:Total Bonus/ Tổng thưởng
  section label:Deduction => name:Total Deduction/Tổng giám trừ
  section label:basic_salary_detail => name:Chi tiết lương cơ bản
  section label:kpi_salary_detail => name:Chi tiết lương KPI

  Constant configuration form:
  section label:Level_Salary1 => name:Chính sách lương cơ bản và trợ cấp
  section label:Income_Tax_Rate1 => name:Quy định tính thuế TNCN
  section label:bonus_policy => name:Quy định thưởng

  php artisan dxpayroll:migrateMasterDataFormSectionFieldZoho
  php artisan dxpayroll:migrateDataDateDimensionTable
  php artisan dxpayroll:migrateDataOrganizationFromZoho
  php artisan dxpayroll:migrateDataPayrollModuleFromZoho
```