<?php

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_fetch_forms_path()
{
    return "forms";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_form_components_path(string $formLinkName)
{
    return "forms/$formLinkName/components";
}

/**
 * @description this function return uri path to get records
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_records_path(string $formLinkName)
{
    return "forms/$formLinkName/getRecords";
}

/**
 * @description this function return uri path to get data by id
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_data_by_id_path(string $formLinkName)
{
    return "forms/$formLinkName/getDataByID";
}

/**
 * @description this function return uri path to get record by id
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_record_by_id_path(string $formLinkName)
{
    return "forms/$formLinkName/getRecordByID";
}

/**
 * @description this function return uri path to get record by id
 * @param string $formLinkName
 * @return string
 */
function zoho_people_insert_record_json_path(string $formLinkName)
{
    return "forms/json/$formLinkName/insertRecord";
}

/**
 * @description this function return uri path to get record by id
 * @param string $formLinkName
 * @return string
 */
function zoho_people_update_record_json_path(string $formLinkName)
{
    return "forms/json/$formLinkName/updateRecord";
}

/**
 * @description this function return uri path to get record by id
 * @param string $formLinkName
 * @return string
 */
function zoho_people_delete_records_path()
{
    return "deleteRecords";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_attendance_by_user_path()
{
    return "attendance/getUserReport";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_attendance_by_entries_path()
{
    return "attendance/getAttendanceEntries";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_attendance_bulk_import_path()
{
    return "attendance/bulkImport";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_attendance_get_regularization_records_path()
{
    return "attendance/getRegularizationRecords";
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_shift_configuration_path()
{
    return "attendance/getShiftConfiguration";
}


/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function zoho_people_get_holidays_path()
{
    return "leave/v2/holidays/get";
}