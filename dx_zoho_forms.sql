/*
 Navicat Premium Data Transfer

 Source Server         : localhost3380
 Source Server Type    : MySQL
 Source Server Version : 80032
 Source Host           : localhost:3380
 Source Schema         : cafe-amazon

 Target Server Type    : MySQL
 Target Server Version : 80032
 File Encoding         : 65001

 Date: 17/08/2023 11:33:02
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for dx_zoho_forms
-- ----------------------------
DROP TABLE IF EXISTS `dx_zoho_forms`;
CREATE TABLE `dx_zoho_forms`  (
  `id` bigint unsigned NOT NULL,
  `zoho_id` bigint unsigned NOT NULL,
  `form_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `form_link_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(0) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 69 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of dx_zoho_forms
-- ----------------------------
INSERT INTO `dx_zoho_forms` VALUES (1, 425479000003130585, '(For Admin) Flight Ticket expense/ Chi phí vé máy bay (Bộ phận Admin)', 'Flight_Ticket_expense', 1);
INSERT INTO `dx_zoho_forms` VALUES (2, 425479000003810573, '(For DM) Flight Ticket Expense/ Chi phí vé máy bay (Trưởng phòng phê duyệt)', 'for_dm_flight_ticket_expense', 1);
INSERT INTO `dx_zoho_forms` VALUES (3, 425479000003642037, 'Advance Clearance/ Yêu cầu hoàn ứng', 'Reimbursement_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (4, 425479000003641671, 'Advance/Payment request/ Yêu cầu tạm ứng/ thanh toán', 'Advance_Payment_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (5, 425479000000035743, 'Asset', 'asset', 1);
INSERT INTO `dx_zoho_forms` VALUES (6, 425479000014367038, 'Asset Master Data/ Danh Sách Tài Sản', 'asset_master_data', 1);
INSERT INTO `dx_zoho_forms` VALUES (7, 425479000014375130, 'Asset request/ Yêu cầu tài sản', 'Asset_Request', 1);
INSERT INTO `dx_zoho_forms` VALUES (8, 425479000000035747, 'Benefit', 'benefit', 1);
INSERT INTO `dx_zoho_forms` VALUES (9, 425479000008668001, 'Cam kết bảo mật dự án', 'Report_Timesheet_Details', 1);
INSERT INTO `dx_zoho_forms` VALUES (10, 425479000014014923, 'Client Master Data', 'client_master_data', 1);
INSERT INTO `dx_zoho_forms` VALUES (11, 425479000021013401, 'Constant configuration/Cấu hình hằng số', 'setting', 1);
INSERT INTO `dx_zoho_forms` VALUES (12, 425479000001395059, 'Country List', 'Country_List', 1);
INSERT INTO `dx_zoho_forms` VALUES (13, 425479000016754394, 'Criteria/Tiêu chí', 'Criteria', 1);
INSERT INTO `dx_zoho_forms` VALUES (14, 425479000003466061, 'Customer Master Data', 'service_evaluation', 1);
INSERT INTO `dx_zoho_forms` VALUES (15, 425479000000035681, 'Department/ Phòng Ban', 'department', 1);
INSERT INTO `dx_zoho_forms` VALUES (16, 425479000000035683, 'Designation/Chức danh', 'designation', 1);
INSERT INTO `dx_zoho_forms` VALUES (17, 425479000000035689, 'E-signature', 'companypolicy', 1);
INSERT INTO `dx_zoho_forms` VALUES (18, 425479000000035679, 'Employee/Nhân viên', 'employee', 1);
INSERT INTO `dx_zoho_forms` VALUES (19, 425479000011297009, 'Exit Process/Quy trình nghỉ việc', 'Exit_Process', 1);
INSERT INTO `dx_zoho_forms` VALUES (20, 425479000000035723, 'Expense Master Data', 'applytraining', 1);
INSERT INTO `dx_zoho_forms` VALUES (21, 425479000016742176, 'FINAL RESULT/KÊT QUẢ CUỐI CÙNG', 'FINAL_RESULT', 1);
INSERT INTO `dx_zoho_forms` VALUES (22, 425479000021221003, 'Form Master Data/Kho biểu mẫu', 'form_master_data', 1);
INSERT INTO `dx_zoho_forms` VALUES (23, 425479000021016053, 'Formula Source/Kho công thức', 'fomular', 1);
INSERT INTO `dx_zoho_forms` VALUES (24, 425479000007440353, 'Guest Welcome', 'contract_management', 1);
INSERT INTO `dx_zoho_forms` VALUES (25, 425479000000035735, 'Guest Welcome Policy', 'travelexpenses', 1);
INSERT INTO `dx_zoho_forms` VALUES (26, 425479000014418273, 'Handover report/ Biên Bản Bàn Giao', 'Handover_report1', 1);
INSERT INTO `dx_zoho_forms` VALUES (27, 425479000021815001, 'Hieu Test', 'Hieu_Test', 1);
INSERT INTO `dx_zoho_forms` VALUES (28, 425479000000035719, 'Invoice Request', 'training', 1);
INSERT INTO `dx_zoho_forms` VALUES (29, 425479000000035693, 'Leave', 'leave', 1);
INSERT INTO `dx_zoho_forms` VALUES (30, 425479000022087003, 'Link', 'Link', 1);
INSERT INTO `dx_zoho_forms` VALUES (31, 425479000016885396, 'Log Timesheet', 'Log_Timesheet', 1);
INSERT INTO `dx_zoho_forms` VALUES (32, 425479000000986026, 'Log Timesheet Approval', 'Time_Log', 1);
INSERT INTO `dx_zoho_forms` VALUES (33, 425479000002768787, 'Monthly personal KPI/KPI cá nhân hàng tháng', 'Monthly_personal_KPI', 1);
INSERT INTO `dx_zoho_forms` VALUES (34, 425479000003608937, 'Monthly Working Time Report/Báo cáo thời gian làm việc trong tháng', 'monthy_worktime_report', 1);
INSERT INTO `dx_zoho_forms` VALUES (35, 425479000020985583, 'Monthly working time/Bảng công', 'monthly_working_time', 1);
INSERT INTO `dx_zoho_forms` VALUES (36, 425479000002442048, 'Offboard Process/Quy trình nghỉ việc', 'Offboard_process', 1);
INSERT INTO `dx_zoho_forms` VALUES (37, 425479000002380217, 'Onboard process/Quy trình gia nhập', 'Onboard2', 1);
INSERT INTO `dx_zoho_forms` VALUES (38, 425479000005232329, 'Onsite Advance Clearance/ Yêu cầu hoàn trả chi phí công tác', 'onsite_advance_clearance1', 1);
INSERT INTO `dx_zoho_forms` VALUES (39, 425479000005017001, 'Onsite Advance Request/ Yêu cầu thanh toán tạm ứng công tác', 'onsite_advance_payment_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (40, 425479000003831733, 'Onsite expense/ Chi phí công tác', 'onsite_expense', 1);
INSERT INTO `dx_zoho_forms` VALUES (41, 425479000001321095, 'Onsite Policies', 'Onsite_Policies', 1);
INSERT INTO `dx_zoho_forms` VALUES (42, 425479000001316718, 'Onsite Request/Yêu cầu đi công tác', 'Onsite_Request', 1);
INSERT INTO `dx_zoho_forms` VALUES (43, 425479000021007419, 'OT Request/Yêu cầu làm ngoài giờ', 'ot_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (44, 425479000000266235, 'Overtime Registration', 'Overtime_Registration', 1);
INSERT INTO `dx_zoho_forms` VALUES (45, 425479000021759288, 'Payment Approval Request', 'Device_Account_Request', 1);
INSERT INTO `dx_zoho_forms` VALUES (46, 425479000000575571, 'Payroll Summary/Thống kê tiền lương', 'Payroll_Summary1', 1);
INSERT INTO `dx_zoho_forms` VALUES (47, 425479000021003771, 'Payslip/ Bảng lương', 'payslip1', 1);
INSERT INTO `dx_zoho_forms` VALUES (48, 425479000000441945, 'Payslip/Bảng lương', 'Payslip', 1);
INSERT INTO `dx_zoho_forms` VALUES (49, 425479000000145001, 'Project Code Request', 'Overtime', 1);
INSERT INTO `dx_zoho_forms` VALUES (50, 425479000000263058, 'Project/ Dự án', 'Smart_Project', 1);
INSERT INTO `dx_zoho_forms` VALUES (51, 425479000003641001, 'Purchasing request/ Yêu cầu mua hàng', 'Purchasing_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (52, 425479000003201475, 'Quick Service Support/ Yêu cầu hỗ trợ nhanh', 'Ticket_support', 1);
INSERT INTO `dx_zoho_forms` VALUES (53, 425479000017049053, 'Rank Approval', 'Job_Rank_Approval', 1);
INSERT INTO `dx_zoho_forms` VALUES (54, 425479000008669297, 'Report Timesheet Summary', 'Report_Timesheet', 1);
INSERT INTO `dx_zoho_forms` VALUES (55, 425479000008670609, 'Report Timesheet vs Punch Time', 'Report_Timesheet_vs_Punch_Time', 1);
INSERT INTO `dx_zoho_forms` VALUES (56, 425479000001002065, 'Report Working Time/Báo cáo thời gian làm việc', 'Report_Working_Time', 1);
INSERT INTO `dx_zoho_forms` VALUES (57, 425479000021386198, 'Resignation Form', 'Resignation_Form', 1);
INSERT INTO `dx_zoho_forms` VALUES (58, 425479000021015295, 'Salary factor/Nhân tố lương', 'factor_master_data', 1);
INSERT INTO `dx_zoho_forms` VALUES (59, 425479000021916001, 'Service request policy', 'Service_request_policy', 1);
INSERT INTO `dx_zoho_forms` VALUES (60, 425479000000240015, 'Settings', 'Settings', 1);
INSERT INTO `dx_zoho_forms` VALUES (61, 425479000014014009, 'SmartOSC Contract Management', 'smartosc_contract_management', 1);
INSERT INTO `dx_zoho_forms` VALUES (62, 425479000000100003, 'Task', 'P_Task', 1);
INSERT INTO `dx_zoho_forms` VALUES (63, 425479000000259738, 'Task list/ Danh sách nhiệm vụ', 'Task_list', 1);
INSERT INTO `dx_zoho_forms` VALUES (64, 425479000016755302, 'Training - QA Information/Thông tin đào tạo - QA', 'Training_QA_Information', 1);
INSERT INTO `dx_zoho_forms` VALUES (65, 425479000000035731, 'Travel Insurance Payment', 'travelrequest', 1);
INSERT INTO `dx_zoho_forms` VALUES (66, 425479000005221647, 'Vendor Payment', 'flight_ticket_payment', 1);
INSERT INTO `dx_zoho_forms` VALUES (67, 425479000003641257, 'Vendor Quotation/ Yêu cầu báo giá', 'Process_for_purchasing_request', 1);
INSERT INTO `dx_zoho_forms` VALUES (68, 425479000000035727, 'WO Master Data', 'trainingfeedback', 1);

SET FOREIGN_KEY_CHECKS = 1;
