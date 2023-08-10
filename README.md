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

- Then. Publish this Service Provider
```sh
  php artisan migrate
  php artisan dxpayroll:migrateZohoForm
  php artisan dxpayroll:migrateDataDateDimensionTable
  php artisan dxpayroll:migrateDataOrganizationFromZoho
  php artisan dxpayroll:migrateDataPayrollModuleFromZoho
```