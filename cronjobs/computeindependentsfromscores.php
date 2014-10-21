<?php
require_once("peerreview/inc/common.php");

foreach($recentPeerReviewAssignments as $assignmentID)
{
	try{
		if($globalDataMgr->isJobDone($assignmentID, 'computeindependentsfromscores'))
			continue;
		//Get all the assignments
		$assignmentHeaders = $globalDataMgr->getAssignmentHeaders();
		
		$currentAssignment = $globalDataMgr->getAssignment($assignmentID);
		
		$windowSize = 4;//$windowSize = require_from_post("windowsize");
		$independentThreshold = 70;//$independentThreshold = require_from_post("threshold");
		$keep = true;//maybe set in course configurations
		
		$assignments = $currentAssignment->getAssignmentsBefore($windowSize);
		$userNameMap = $globalDataMgr->getUserDisplayMapByAssignment($assignmentID);
		$students = $globalDataMgr->getStudentsByAssignment($assignmentID);
		if($keep){
		    $independents = $currentAssignment->getIndependentUsers();
		}else{
		    $independents = array();
		}
		$addedIndependents = 0;
		
		$html = "<h2>Used Assignments</h2>";
		foreach($assignments as $asn){
		    $html .= $asn->name . "<br>";
		}
		
		$html .= "<table width='100%'>\n";
		$html .= "<tr><td><h2>Student</h2></td><td><h2>Review Avg</h2></td><td><h2>Status</h2></td></tr>\n";
		$currentRowType = 0;
		foreach($students as $student)
		{
		    $html .= "<tr class='rowType$currentRowType'><td>".$userNameMap[$student->id]."</td><td>";
		    $score = compute_peer_review_score_for_assignments($student, $assignments) * 100;
		    $html .= precisionFloat($score);
		    $html .= "</td><td>\n";
		    if($score >= $independentThreshold && !array_key_exists($student->id, $independents))
		    {
		        $independents[] = $student;
				$addedIndependents++;
		        $html .= "Independent";
		    }
		    $html .= "</td></tr>\n";
		    $currentRowType = ($currentRowType+1)%2;
		}
		$html .= "</table>\n";
		
		$currentAssignment->saveIndependentUsers($independents);
		
		$summary = ($keep) ? "Kept previous independents and added $addedIndependents independents" : "Deleted previous independents and added $addedIndependents independents"; 
		
		$globalDataMgr->createNotification($assignmentID, 'computeindependentsfromscores', 1, $summary, $html);
	}catch(Exception $exception){
		$globalDataMgr->createNotification($assignmentID, 'computeindependentsfromscores', 0, cleanString($exception->getMessage()), "");
	}

}

?>