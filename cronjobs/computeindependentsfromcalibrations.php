<?php
require_once("peerreview/inc/common.php");
require_once("peerreview/inc/calibrationutils.php");

foreach($recentPeerReviewAssignments as $assignmentID)
{
	try{
		$html = "";
		if($globalDataMgr->isJobDone($assignmentID, 'computeindependentsfromcalibrations'))
			continue;
		$currentAssignment = $globalDataMgr->getAssignment($assignmentID);
		
		$userNameMap = $globalDataMgr->getUserDisplayMapByAssignment($assignmentID);
		$students = $globalDataMgr->getStudentsByAssignment($assignmentID);
		$keep = true;//maybe set in course configurations
		
		if($keep){
		    $independents = $currentAssignment->getIndependentUsers();
		}else{
		    $independents = array();
		}
		
		$html .= "<table width='100%'>\n";
		$html .= "<tr><td><h2>Student</h2></td><td><h2>Weighted Average Score</h2></td><td><h2>Effective Calibration Reviews Done</h2></td><td><h2>Status</h2></td></tr>\n";
		$currentRowType = 0;
		$addedIndependents = 0;
		foreach($students as $student)
		{
		    $html .= "<tr class='rowType$currentRowType'><td>".$userNameMap[$student->id]."</td>";
			$weightedAverage = $globalDataMgr->getWeightedAverage($student, $currentAssignment);
		    $html .= "<td>$weightedAverage</td>";
			$numReviews = $globalDataMgr->numCalibrationReviews($student);
			$html .= "<td>".$numReviews."</td>";
		    $html .= "</td><td>\n";
		    if($weightedAverage >= $currentAssignment->calibrationThresholdScore && $numReviews >= $currentAssignment->calibrationMinCount && !array_key_exists($student->id, $independents))
		    {
		        $independents[] = $student;
		        $html .= "Independent";
				$addedIndependents++;
		    }
		    $html .= "</td></tr>\n";
		    $currentRowType = ($currentRowType+1)%2;
		}
		$html .= "</table>\n";
		
		$currentAssignment->saveIndependentUsers($independents);	
		
		$summary = ($keep) ? "Kept previous independents and added $addedIndependents independents" : "Deleted previous independents and added $addedIndependents independents";
		
		$globalDataMgr->createNotification($assignmentID, 'computeindependentsfromcalibrations', 1, $summary, $html);
	}catch(Exception $exception){
		$globalDataMgr->createNotification($assignmentID, 'computeindependentsfromcalibrations', 0, cleanString($exception->getMessage()), "");
	}

}

?>