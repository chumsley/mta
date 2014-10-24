<?php
require_once("peerreview/inc/common.php");

foreach($recentPeerReviewAssignments as $assignmentID)
{
	try{
		$html = "";
		if($globalDataMgr->isJobDone($assignmentID, 'disqualifyindependentsfromscores'))
			continue;
		//Get all the assignments
		$assignmentHeaders = $globalDataMgr->getAssignmentHeaders();
		
		$currentAssignment = $globalDataMgr->getAssignment($assignmentID);
		$configuration = $globalDataMgr->getAssignment($assignmentID);
		
		$windowSize = $configuration->disqualifyWindowSize;//$windowSize = require_from_post("windowsize");
		$independentThreshold = 70;//$independentThreshold = floatval(require_from_post("threshold"));
		
		$assignments = $currentAssignment->getAssignmentsBefore($windowSize);
		$userNameMap = $globalDataMgr->getUserDisplayMapByAssignment($assignmentID);
		$students = $globalDataMgr->getStudentsByAssignment($assignmentID);
		$independents = $currentAssignment->getIndependentUsers();
		
		$numIndependents = sizeof($independents);
		
		$html .= "<h2>Used Assignments</h2>";
		foreach($assignments as $asn){
		    $html .= $asn->name . "<br>";
		}
		
		$html .= "<table width='100%'>\n";
		$html .= "<tr><td><h2>Student</h2></td><td><h2>Review Avg</h2></td><td><h2>Status</h2></td></tr>\n";
		$currentRowType = 0;
		$disqualifiedIndependents = 0;
		foreach($students as $student)
		{
		    $html .= "<tr class='rowType$currentRowType'><td>".$userNameMap[$student->id]."</td><td>";
		
		    # Don't disqualify someone for never having been marked
		    if(count_valid_peer_review_marks_for_assignments($student, $assignments) < 1)
			{
				$currentRowType = ($currentRowType+1)%2;
				$html .= "</td><td>&nbsp</td></tr>\n";
		    	continue;
			}
		    $score = compute_peer_review_score_for_assignments($student, $assignments) * 100;
		    $html .= precisionFloat($score);
		    $html .= "</td><td>\n";
		    if($score < $independentThreshold)
		    {
		    	if(array_key_exists($student->id, $independents))
				{			
		    		unset($independents[$student->id]);
					$globalDataMgr->demote($student, $independentThreshold);
		      		$html .= "Disqualified (forced to supervised)";
					$disqualifiedIndependents++;
		      	}
		    }
		    $html .= "&nbsp</td></tr>\n";
		    $currentRowType = ($currentRowType+1)%2;
		}
		$html .= "</table>\n";
		
		$currentAssignment->saveIndependentUsers($independents);
		
		$summary = "Disqualified $disqualifiedIndependents of $numIndependents independents";
		
		$globalDataMgr->createNotification($assignmentID, 'disqualifyindependentsfromscores', 1, $summary, $html);
	}catch(Exception $exception){
		$globalDataMgr->createNotification($assignmentID, 'disqualifyindependentsfromscores', 0, cleanString($exception->getMessage()), "");
	}

}

?>