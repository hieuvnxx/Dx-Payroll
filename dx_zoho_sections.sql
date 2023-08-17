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

 Date: 17/08/2023 11:33:21
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for dx_zoho_sections
-- ----------------------------
DROP TABLE IF EXISTS `dx_zoho_sections`;
CREATE TABLE `dx_zoho_sections`  (
  `id` bigint unsigned NOT NULL,
  `form_id` bigint unsigned NOT NULL,
  `section_id` bigint unsigned NULL,
  `section_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `section_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 104 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of dx_zoho_sections
-- ----------------------------
INSERT INTO `dx_zoho_sections` VALUES (1, 1, 425479000003497081, 'Journeys', 'Journeys');
INSERT INTO `dx_zoho_sections` VALUES (2, 1, 425479000003826961, 'Flight_ticket_Single_type', 'Flight Ticket Single Type');
INSERT INTO `dx_zoho_sections` VALUES (3, 1, 425479000003829871, 'Flight_ticket_Single_type1', 'Flight Ticket Single Type1');
INSERT INTO `dx_zoho_sections` VALUES (4, 1, 425479000020735011, 'Travel_Insurance', 'Travel Insurance');
INSERT INTO `dx_zoho_sections` VALUES (5, 2, 425479000008372031, 'Onsite_Schedule_Information', 'Onsite Schedule Information');
INSERT INTO `dx_zoho_sections` VALUES (6, 2, 425479000003810747, 'Flight_Ticket_Expense_Information_for_DM', 'Flight Ticket Expense Information For DM');
INSERT INTO `dx_zoho_sections` VALUES (7, 2, 425479000005795003, 'Combo_ticket_detailed', 'Combo Ticket Detailed');
INSERT INTO `dx_zoho_sections` VALUES (8, 2, 425479000020736109, 'Travel_Insurance1', 'Travel Insurance1');
INSERT INTO `dx_zoho_sections` VALUES (9, 3, 425479000003642055, 'Chi_tiet_cac_khoan3', 'Chi Tiet Cac Khoan3');
INSERT INTO `dx_zoho_sections` VALUES (10, 4, 425479000003641689, 'Chi_tiet_cac_khoan2', 'Chi Tiet Cac Khoan2');
INSERT INTO `dx_zoho_sections` VALUES (11, 7, 425479000014410731, 'Request_details', 'Request Details');
INSERT INTO `dx_zoho_sections` VALUES (12, 7, 425479000014434003, 'Granted_asset_details', 'Granted Asset Details');
INSERT INTO `dx_zoho_sections` VALUES (13, 7, 425479000014487401, 'Old_asset_information_IT_section', 'Old Asset Information IT Section');
INSERT INTO `dx_zoho_sections` VALUES (14, 8, 425479000000035965, 'Medical', 'Medical');
INSERT INTO `dx_zoho_sections` VALUES (15, 11, 425479000021013431, 'Level_Salary1', 'Chính sách lương cơ bản và trợ cấp');
INSERT INTO `dx_zoho_sections` VALUES (16, 11, 425479000021013437, 'Income_Tax_Rate1', 'Quy định tính thuế TNCN');
INSERT INTO `dx_zoho_sections` VALUES (17, 11, 425479000021014993, 'bonus_policy', 'Quy định thưởng');
INSERT INTO `dx_zoho_sections` VALUES (18, 14, 425479000019858253, 'Y_u_c_u_thi_t_b_v_t_i_kho_n', 'Y U C U Thi T B V T I Kho N');
INSERT INTO `dx_zoho_sections` VALUES (19, 18, 425479000000036003, 'Education', 'Skills & Languages');
INSERT INTO `dx_zoho_sections` VALUES (20, 18, 425479000000036001, 'WorkExperience', 'Work experience/Kinh nghiệm công việc');
INSERT INTO `dx_zoho_sections` VALUES (21, 18, 425479000000802957, 'Training_at_SmartOSC', 'Training at SmartOSC/Đào tạo ở SmartOSC');
INSERT INTO `dx_zoho_sections` VALUES (22, 18, 425479000000036005, 'Dependent', 'Dependents & Family Members/Thành viên gia đình và cá nhân');
INSERT INTO `dx_zoho_sections` VALUES (23, 18, 425479000000802989, 'Social_Insurances', 'Social Insurance/Bảo hiểm xã hội');
INSERT INTO `dx_zoho_sections` VALUES (24, 18, 425479000000802973, 'Languages', 'SI/TU Adjustment');
INSERT INTO `dx_zoho_sections` VALUES (25, 18, 425479000000281045, 'Salary_History', 'Salary History');
INSERT INTO `dx_zoho_sections` VALUES (26, 18, 425479000000307541, 'Allowance1', 'Allowances');
INSERT INTO `dx_zoho_sections` VALUES (27, 18, 425479000000307659, 'Others', 'Bonus & Others');
INSERT INTO `dx_zoho_sections` VALUES (28, 18, 425479000001434959, 'Passport', 'Passport/Hộ chiếu');
INSERT INTO `dx_zoho_sections` VALUES (29, 18, 425479000006119991, 'Attendance_History', 'Attendance History/Lịch sử hiện diện');
INSERT INTO `dx_zoho_sections` VALUES (30, 18, 425479000011072659, 'History_of_Employee_information', 'Employee information History/Lịch sử thông tin nhân viên');
INSERT INTO `dx_zoho_sections` VALUES (31, 21, 425479000016742812, 'SALARY_BONUS', 'SALARY BONUS');
INSERT INTO `dx_zoho_sections` VALUES (32, 24, 425479000020728495, 'Guest_information_detail', 'Guest Information Detail');
INSERT INTO `dx_zoho_sections` VALUES (33, 24, 425479000020728971, 'Meeting_room_needed', 'Meeting Room Needed');
INSERT INTO `dx_zoho_sections` VALUES (34, 24, 425479000020729433, 'Restaurant_Booking_Details', 'Restaurant Booking Details');
INSERT INTO `dx_zoho_sections` VALUES (35, 24, 425479000020729847, 'Gift_Details', 'Gift Details');
INSERT INTO `dx_zoho_sections` VALUES (36, 24, 425479000020730191, 'travel_tour_details', 'Travel Tour Details');
INSERT INTO `dx_zoho_sections` VALUES (37, 25, 425479000000036025, 'Expense', 'Expense');
INSERT INTO `dx_zoho_sections` VALUES (38, 25, 425479000021280597, 'Hn_checklist', 'Hn Checklist');
INSERT INTO `dx_zoho_sections` VALUES (39, 25, 425479000021305291, 'HCM_checklist', 'HCM Checklist');
INSERT INTO `dx_zoho_sections` VALUES (40, 25, 425479000021305551, 'DN_checklist', 'DN Checklist');
INSERT INTO `dx_zoho_sections` VALUES (41, 26, 425479000014432385, 'BBBG', 'BBBG');
INSERT INTO `dx_zoho_sections` VALUES (42, 26, 425479000014497755, 'Old_asset_information', 'Old Asset Information');
INSERT INTO `dx_zoho_sections` VALUES (43, 26, 425479000014497835, 'New_asset_information', 'New Asset Information');
INSERT INTO `dx_zoho_sections` VALUES (44, 28, 425479000018494501, 'Payment_Schedule1', 'Payment Schedule1');
INSERT INTO `dx_zoho_sections` VALUES (45, 31, 425479000016886408, 'Log_Timesheet_Details1', 'Log Timesheet Details1');
INSERT INTO `dx_zoho_sections` VALUES (46, 34, 425479000003621213, 'working_salary_detail', 'Working Salary Detail');
INSERT INTO `dx_zoho_sections` VALUES (47, 35, 425479000020985601, 'working_salary_detail1', 'Attendance details/Bảng công chi tiết');
INSERT INTO `dx_zoho_sections` VALUES (48, 38, 425479000005232359, 'Total_amount_of_advance_payment2', 'Total Amount Of Advance Payment2');
INSERT INTO `dx_zoho_sections` VALUES (49, 38, 425479000005232361, 'Total_actual_spent_expense1', 'Total Actual Spent Expense1');
INSERT INTO `dx_zoho_sections` VALUES (50, 38, 425479000005232363, 'Details_of_Actual_Spent_Expense1', 'Details Of Actual Spent Expense1');
INSERT INTO `dx_zoho_sections` VALUES (51, 38, 425479000005232365, 'Advance_Clearance_Amount1', 'Advance Clearance Amount1');
INSERT INTO `dx_zoho_sections` VALUES (52, 38, 425479000020453583, 'Onsite_Report_Detail', 'Onsite Report Detail');
INSERT INTO `dx_zoho_sections` VALUES (53, 39, 425479000005035584, 'Onsite_Request_Information', 'Onsite Request Information');
INSERT INTO `dx_zoho_sections` VALUES (54, 39, 425479000005209683, 'Total_amount_of_advance_payment', 'Total Amount Of Advance Payment');
INSERT INTO `dx_zoho_sections` VALUES (55, 39, 425479000005209189, 'Advance_payment_details', 'Advance Payment Details');
INSERT INTO `dx_zoho_sections` VALUES (56, 39, 425479000005550716, 'Details_payment_by_AF', 'Details Payment By AF');
INSERT INTO `dx_zoho_sections` VALUES (57, 39, 425479000005043487, 'Number_of_times_received_advance_payment_amount', 'Number Of Times Received Advance Payment Amount');
INSERT INTO `dx_zoho_sections` VALUES (58, 40, 425479000003831763, 'Other_expense3', 'Other Expense3');
INSERT INTO `dx_zoho_sections` VALUES (59, 40, 425479000003831761, 'Hotel_cost_information3', 'Hotel Cost Information3');
INSERT INTO `dx_zoho_sections` VALUES (60, 40, 425479000021336385, 'Flight_ticket_detailed', 'Flight Ticket Detailed');
INSERT INTO `dx_zoho_sections` VALUES (61, 40, 425479000003832351, 'Travel_advance', 'Travel Advance');
INSERT INTO `dx_zoho_sections` VALUES (62, 40, 425479000005235133, 'Perdiem', 'Perdiem');
INSERT INTO `dx_zoho_sections` VALUES (63, 42, 425479000001316736, 'Information1', 'Information1');
INSERT INTO `dx_zoho_sections` VALUES (64, 45, 425479000021813615, 'Meeting_Booking', 'Meeting Booking');
INSERT INTO `dx_zoho_sections` VALUES (65, 45, 425479000022047223, 'Advance_Clearance', 'Advance Clearance');
INSERT INTO `dx_zoho_sections` VALUES (66, 45, 425479000022062715, 'Onsite_Advance_Payment_Request1', 'Onsite Advance Payment Request1');
INSERT INTO `dx_zoho_sections` VALUES (67, 45, 425479000022062805, 'Onsite_Advance_Clearance', 'Onsite Advance Clearance');
INSERT INTO `dx_zoho_sections` VALUES (68, 47, 425479000021004613, 'salary_total', 'Total Salary/Tổng lương');
INSERT INTO `dx_zoho_sections` VALUES (69, 47, 425479000021005009, 'bonus_table', 'Total Bonus/ Tổng thưởng');
INSERT INTO `dx_zoho_sections` VALUES (70, 47, 425479000021258280, 'Deduction', 'Total Deduction/Tổng giám trừ');
INSERT INTO `dx_zoho_sections` VALUES (71, 47, 425479000021005279, 'basic_salary_detail', 'Chi tiết lương cơ bản');
INSERT INTO `dx_zoho_sections` VALUES (72, 47, 425479000021006381, 'kpi_salary_detail', 'Chi tiết lương KPI');
INSERT INTO `dx_zoho_sections` VALUES (73, 48, 425479000000442083, 'Total_Salary1', 'Total Salary1');
INSERT INTO `dx_zoho_sections` VALUES (74, 48, 425479000000442161, 'Working_Salary', 'Working Salary');
INSERT INTO `dx_zoho_sections` VALUES (75, 48, 425479000000442261, 'Compensations_Benefits', 'Compensations Benefits');
INSERT INTO `dx_zoho_sections` VALUES (76, 48, 425479000000442343, 'Deductions4', 'Deductions4');
INSERT INTO `dx_zoho_sections` VALUES (77, 48, 425479000000442427, 'Employee_Information8', 'Employee Information8');
INSERT INTO `dx_zoho_sections` VALUES (78, 49, 425479000018527705, 'Upload_file', 'Upload File');
INSERT INTO `dx_zoho_sections` VALUES (79, 49, 425479000020920923, 'B_ng_tr_ng_th_ng_tin', 'B Ng Tr Ng Th Ng Tin');
INSERT INTO `dx_zoho_sections` VALUES (80, 49, 425479000020930125, 'B_ng_c_ng_th_c', 'B Ng C Ng Th C');
INSERT INTO `dx_zoho_sections` VALUES (81, 50, 425479000020920401, 'A', 'A');
INSERT INTO `dx_zoho_sections` VALUES (82, 50, 425479000020920541, 'B', 'B');
INSERT INTO `dx_zoho_sections` VALUES (83, 51, 425479000003968705, 'Details_of_request', 'Details Of Request');
INSERT INTO `dx_zoho_sections` VALUES (84, 53, 425479000017049649, 'Appraisee_Detail', 'Appraisee Detail');
INSERT INTO `dx_zoho_sections` VALUES (85, 55, 425479000020564677, 'Participant', 'Participant');
INSERT INTO `dx_zoho_sections` VALUES (86, 55, 425479000020569124, 'Participants', 'Participants');
INSERT INTO `dx_zoho_sections` VALUES (87, 56, 425479000001033003, 'Current_Working_Time_Summary', 'Current Working Time Summary');
INSERT INTO `dx_zoho_sections` VALUES (88, 56, 425479000001035035, 'Summary', 'Summary');
INSERT INTO `dx_zoho_sections` VALUES (89, 56, 425479000001002542, 'Details', 'Details');
INSERT INTO `dx_zoho_sections` VALUES (90, 59, 425479000021916583, 'Policy_Details', 'Policy Details');
INSERT INTO `dx_zoho_sections` VALUES (91, 60, 425479000000263006, 'Level_Salary', 'Level Salary');
INSERT INTO `dx_zoho_sections` VALUES (92, 60, 425479000000291003, 'Overtime_Tax_Rate', 'Overtime Tax Rate');
INSERT INTO `dx_zoho_sections` VALUES (93, 60, 425479000000291418, 'Deductions', 'Deductions');
INSERT INTO `dx_zoho_sections` VALUES (94, 60, 425479000000307191, 'Income_Tax_Rate', 'Income Tax Rate');
INSERT INTO `dx_zoho_sections` VALUES (95, 61, 425479000015214377, 'Comment_Section', 'Comment Section');
INSERT INTO `dx_zoho_sections` VALUES (96, 61, 425479000014016589, 'Attachment_List', 'Attachment List');
INSERT INTO `dx_zoho_sections` VALUES (97, 61, 425479000014244059, 'Approval_History', 'Approval History');
INSERT INTO `dx_zoho_sections` VALUES (98, 64, 425479000016755942, 'Training_Section', 'Training Section');
INSERT INTO `dx_zoho_sections` VALUES (99, 64, 425479000016756402, 'QA_Section', 'QA Section');
INSERT INTO `dx_zoho_sections` VALUES (100, 65, 425479000020734637, 'Travel_Insurance_Information', 'Travel Insurance Information');
INSERT INTO `dx_zoho_sections` VALUES (101, 66, 425479000005221865, 'Flight_Cost_Information', 'Flight Cost Information');
INSERT INTO `dx_zoho_sections` VALUES (102, 67, 425479000003641279, 'purchase_options1', 'Purchase Options1');
INSERT INTO `dx_zoho_sections` VALUES (103, 68, 425479000018487741, 'Payment_schedule', 'Payment Schedule');

SET FOREIGN_KEY_CHECKS = 1;
