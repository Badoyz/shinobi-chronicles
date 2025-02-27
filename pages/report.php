<?php
function report() {
    require_once 'classes/ReportManager.php';

	global $system;
	global $player;
	global $self_link;
	
	$page = 'report';
	if(!empty($_GET['page'])) {
		$page = $_GET['page'];
	}

    if($player->staff_manager->isModerator()) {
        $reportManager = new ReportManager($system, $player, true);
        //Display mod submenu
        require 'templates/staff/mod/report_submenu.php';
    }
	// User reporting content
	else {
        $reportManager = new ReportManager($system, $player);
	}
	
	// Submit report
	if(!empty($_POST['submit_report'])) {
		// Content already reported(if not profile) 
		
		try {
			$content_id = (int)$system->clean($_POST['content_id']);
			$report_type = (int)$system->clean($_POST['report_type']);
			$reason = $system->clean($_POST['reason']);
			$notes = $system->clean($_POST['notes']);

            switch($report_type) {
                case ReportManager::REPORT_TYPE_PROFILE:
                    $result = $system->query("SELECT `user_name`, `staff_level` FROM `users` WHERE `user_id`='$content_id' LIMIT 1");
                    if(! $system->db_last_num_rows) {
                        throw new Exception("Invalid user!");
                    }

                    $content_data = $system->db_fetch($result);
                    $user_id = $content_id;
                    $staff_level = $content_data['staff_level'];
                    $time = time();
                    $content = '';
                    break;
                case ReportManager::REPORT_TYPE_PM:
                    $content_data = Inbox::getInfoFromMessageId($system, $content_id);
                    if(!$content_data) {
                        throw new Exception("Invalid message!");
                    }

                    $user_id = $content_data['sender_id'];
                    $user_name = $content_data['user_name'];
                    $staff_level = $content_data['staff_level'];
                    $time = $content_data['time'];
                    $content = htmlspecialchars($content_data['message'], ENT_QUOTES);
                    break;
                case ReportManager::REPORT_TYPE_CHAT:
                    $result = $system->query("SELECT `user_name`, `message`, `time` FROM `chat` WHERE `post_id`='$content_id' LIMIT 1");
                    if($system->db_last_num_rows == 0) {
                        throw new Exception("Invalid user!");
                    }

                    $content_data = $system->db_fetch($result);

                    $result = $system->query("SELECT `user_id`, `staff_level` FROM `users` WHERE `user_name`='" . $content_data['user_name'] . "' LIMIT 1");
                    if(! $system->db_last_num_rows) {
                        throw new Exception("Invalid user!");
                    }
                    $result = $system->db_fetch($result);
                    $user_id = $result['user_id'];
                    $staff_level = $result['staff_level'];
                    $time = $content_data['time'];
                    $content = $content_data['message'];
                    break;
                default:
                    throw new Exception("Invalid report type!");
            }
		
			if($user_id == $player->user_id && !$player->staff_manager->isModerator()) {
				throw new Exception("You cannot report yourself!");
			}

			// Check for existing report
			if($report_type != ReportManager::REPORT_TYPE_PROFILE && $reportManager->checkIfReported($content_id, $report_type)) {
				throw new Exception("Content already reported!");
			}
			
			// Reason
			if(!isset(ReportManager::$report_reasons[$reason])) {
				throw new Exception("Invalid reason!");
			}
			
			if(strlen($notes) > ReportManager::MAX_NOTE_SIZE) {
				throw new Exception("Notes are too long! (" . strlen($notes) . "/" . ReportManager::MAX_NOTE_SIZE . " chars)");
			}

            if($reportManager->submitReport($report_type, $content_id, $content, $user_id, $staff_level, ReportManager::$report_reasons[$reason], $notes)) {
				$system->message("Report sent!");
				$page = '';
			}
			else {
				$system->message("Error submitting report!");
			}
		} catch (Exception $e) {
			$system->message($e->getMessage());
		}
		$system->printMessage();
	}
	// Handle report
	if((!empty($_POST['handle_report']) || !empty($_POST['alter_report'])) && $player->staff_manager->isModerator()) {
		$page = 'view_report';
		
		try {
			$report_id = (int)$system->clean($_GET['report_id']);
            $report = $reportManager->getReport($report_id);

			if($report['status'] != ReportManager::VERDICT_UNHANDLED && !$player->staff_manager->isHeadModerator()) {
				throw new Exception("Report has already been handled!");
			}

            if(isset($_POST['handle_report'])) {
                if ($_POST['handle_report'] == ReportManager::$report_verdicts[ReportManager::VERDICT_GUILTY]) {
                    $verdict = ReportManager::VERDICT_GUILTY;
                }
                else if ($_POST['handle_report'] == ReportManager::$report_verdicts[ReportManager::VERDICT_NOT_GUILTY]) {
                    $verdict = ReportManager::VERDICT_NOT_GUILTY;
                }
            }
            elseif(isset($_POST['alter_report'])) {
                if ($_POST['alter_report'] == ReportManager::$report_verdicts[ReportManager::VERDICT_GUILTY]) {
                    $verdict = ReportManager::VERDICT_GUILTY;
                }
                else if ($_POST['alter_report'] == ReportManager::$report_verdicts[ReportManager::VERDICT_NOT_GUILTY]) {
                    $verdict = ReportManager::VERDICT_NOT_GUILTY;
                }
            }
			else {
				throw new Exception("Invalid verdict!");
			}

            $reportManager->updateReportVerdict($report_id, $verdict);
			if($system->db_last_affected_rows == 1) {
				$system->message("Report handled!");
			}
			else {
				$system->message("Error handling report!");
			}
		} catch (Exception $e) {
			$system->message($e->getMessage());
		}
		$system->printMessage();
	}
	// Display page
	if($page == 'report') {
		try {
			$report_type = $_GET['report_type'];
			$content_id = (int)$system->clean($_GET['content_id']);

            switch($report_type) {
                case ReportManager::REPORT_TYPE_PROFILE:
                    $user_result = $system->query("SELECT `user_name` FrOM `users` WHERE `user_id`='$content_id' LIMIT 1");
                    if(!$system->db_last_num_rows) {
                        throw new Exception("Invalid user!");
                    }

                    $content_data = $system->db_fetch($user_result);
                    $user_id = $content_id;
                    $user_name = $content_data['user_name'];

                    break;
                case ReportManager::REPORT_TYPE_PM:
                    $content_data = Inbox::getInfoFromMessageId($system, $content_id);
                    if(!$content_data) {
                        throw new Exception("Invalid message!");
                    }

                    $user_id = $content_data['sender_id'];
                    $user_name = $content_data['user_name'];
                    break;
                case ReportManager::REPORT_TYPE_CHAT:
                    $result = $system->query("SELECT `user_name`, `message` FROM `chat` WHERE `post_id`='$content_id' LIMIT 1");
                    if($system->db_last_num_rows == 0) {
                        throw new Exception("Invalid user!");
                    }

                    $content_data = $system->db_fetch($result);

                    $result = $system->query("SELECT `user_id` FROM `users` WHERE `user_name`='" . $content_data['user_name'] . "' LIMIT 1");
                    if($system->db_last_num_rows == 0) {
                        throw new Exception("Invalid user!");
                    }
                    $result = $system->db_fetch($result);
                    $user_id = $result['user_id'];
                    $user_name = $content_data['user_name'];
                    break;
                default:
                    throw new Exception("Invalid report type1!");
            }
		
			// Check for existing report
			if($report_type != 1) {
				$result = $system->query("SELECT `report_id` FROM `reports` WHERE `content_id`='$content_id' AND `report_type`='$report_type'");
				if($system->db_last_num_rows > 0) {
					throw new Exception("This content has already been reported!");
				}
			}
		
			if($user_name == $player->user_name && !$player->isModerator()) {
				throw new Exception("You cannot report yourself!");
			}
						
			require 'templates/submit_report.php';
		} catch (Exception $e) {
			$system->message($e->getMessage());
		}
		$system->printMessage();
	}
	else if($page == 'view_all_reports' && $player->staff_manager->isModerator()) {
        $reports = $reportManager->getActiveReports();

        require 'templates/staff/mod/view_all_reports.php';
	}
	else if($page == 'view_report' && $player->staff_manager->isModerator()) {
		try {
			$report_id = (int)$system->clean($_GET['report_id']);
			if(!$report_id) {
				throw new Exception("Report ID not given!");
			}

            $report = $reportManager->getReport($report_id);
			if(!$report) {
                throw new Exception("Report not found!");
            }

            // Fetch usernames
			$result = $system->query("SELECT `user_id`, `user_name` FROM `users` WHERE `user_id` IN (" . $report['user_id'] . ',' . $report['reporter_id'] . ")");
			$user_names = array();
			while($row = $system->db_fetch($result)) {
				$user_names[$row['user_id']] = $row['user_name'];
			}
			
			require 'templates/staff/mod/view_report.php';
		} catch (Exception $e) {
			$system->message($e->getMessage());
			$system->printMessage();
		}
	}
	
}