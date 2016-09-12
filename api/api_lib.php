<?php
require_once("../inc/common.php");
require_once("../peerreview/inc/common.php");
require_once("../inc/datamanager.php");
require_once("../peerreview/inc/datamanagers/pdoassignmentdatamanager.php");
require_once("../config.php");

function mock_submissions($courseID, $assignmentID){
    global $dataMgr, $NOW;
    $text_to_add = "The ouick brown fox jumped over the fence";
    $DELETE_FLAG = FALSE; # i don't think this should ever be set...
    try{
        $db = $dataMgr->getDatabase();
        $db->beginTransaction();

        if ($DELETE_FLAG == TRUE )
            {
                $sh = $db->prepare("DELETE FROM peer_review_assignment_submissions;");
                $sh->execute();
                $sh = $db->prepare("DELETE FROM peer_review_assignment_essays;");
                $sh->execute();

            }

        $sh = $db->prepare("SELECT userID FROM users WHERE userType = 'student' AND courseID = :courseID;");
        $sh->execute(array('courseID' => $courseID));
        $students = $sh->fetchall();

        foreach ($students as $student){
            $sh= $db->prepare("Insert INTO peer_review_assignment_submissions (assignmentID, authorID, noPublicUse, submissionTimestamp) Values(?,?,?, ".$dataMgr->from_unixtime("?").");");
            $sh->execute(array($assignmentID,$student->userID, 0, $NOW ));
            $lastID = $db->lastInsertId();

            $sh = $db->prepare("Insert INTO peer_review_assignment_essays  Values(?,?,?);");
            $sh->execute(array($lastID, $text_to_add, NULL));
        }
    $db->commit();
    }
    catch (Exception $e){
       echo $e->getMessage();
    }
}
function update_assignment($assignment_params){
    global $dataMgr;
    $assignment = $dataMgr->getAssignment(new AssignmentID($assignment_params["assignmentID"]));

    save_assignment($assignment, $assignment_params);
}

function create_assignment($assignment_params){
    global $dataMgr;
    $assignmentType = $assignment_params["assignmentType"];
    $assignment = $dataMgr->createAssignmentInstance(null, $assignmentType);
    save_assignment($assignment, $assignment_params);
}

function save_assignment($assignment, $assignment_params){
   global $dataMgr; 
    $assignmentType = $assignment_params['assignmentType'];
    $submission_type = $assignment_params['submissionType'];
    $submissionSettingsType = $submission_type ."SubmissionSettings";

    foreach ($assignment_params as $key => $value){
        if ($key == "submissionSettings"){
            $assignment->submissionSettings = new $submissionSettingsType(); 
            foreach($value as $setting => $sub_value){
               $assignment->submissionSettings->$setting = $sub_value; 
            }         
        }
        else{    
        $assignment->$key = $value;
        }
    }
    $dataMgr->saveAssignment($assignment, $assignmentType);
    return $assignment;
}

function create_radio_question($assignment, $rubric_params){
    #assumes new radio/peer review rubric. 

    # sets question id to null bc auto gen. 
    $question = new RadioButtonQuestion(NULL, 
                                        $rubric_params["name"],
                                        $rubric_params["question"]); 
    $radio_button_options = $rubric_params["options"];
    foreach($radio_button_options as $key => $value){
        $option = new RadioButtonOption($value["label"], $value["score"]);
        $question->options[] = $option; 
    }
    $question->hidden = $rubric_params["hidden"];
    $assignment->saveReviewQuestion($question);
    }


function update_radio_question(PeerReviewAssignment $assignment, $new_question_params){
    #assuming completly filled in and validated. 
    $questionID = $new_question_params["questionID"];
    unset($new_question_params["questionID"]);
    $newQuestionID = new QuestionID($questionID);# must be in class form
    $old_review_question = $assignment->getReviewQuestion($newQuestionID);
    $old_review_question->options = array();
    foreach($old_review_question as $key => $value){
        if ($key == "questionID"){
            continue;
        }
        elseif ($key == "options"){
            foreach($new_question_params["options"] as $key => $value){
                $score = $value["score"];
                $label = $value["label"];
                $option = new RadioButtonOption($label, $score);
                $old_review_question->options[] = $option;
            }        
        }
        else{
            $old_review_question->$key = $new_question_params[$key];
        }
    }
    $assignment->saveReviewQuestion($old_review_question);
}

function make_peer_review($assignment, $params){

    $review = new Review($assignment);
    $review->matchID = new MatchID($params["matchID"]);
    $review->reviewerID = $params["reviewerID"];
    $review->submissionID = $assignment->getSubmissionID($review->matchID);
    $answer = new ReviewAnswer();

    $answer->$params["answerType"] = $params["answerValue"];
    $review->answers[$params['questionID']] = $answer;

    print_r($review);
    $assignment->saveReview($review);
}

?>
