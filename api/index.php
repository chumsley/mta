<?php
require '../vendor/autoload.php';
require_once("../inc/common.php");
require_once("api_lib.php");

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \JsonSchema\Validator as JsonValidator;

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$config['determineRouteBeforeAppMiddleware'] = true;
$container = new \Slim\Container;
$container['dataMgr'] = $dataMgr;
$container['settings']['displayErrorDetails'] = true;
$validationPaths = ['peermatch_get' => 'json/peermatch/get/', 
'peermatch_create' => 'json/peermatch/create/'];
$container['validationPaths'] = $validationPaths;
$app = new \Slim\App($container); //$container); 



function decode_json_throw_errors($inputString) {
	$json_body = json_decode($inputString);
	switch (json_last_error()) {
	case JSON_ERROR_NONE:
		break;
	case JSON_ERROR_DEPTH:
		throw new Exception('JSON Maximum stack depth exceeded');
		break;
	case JSON_ERROR_STATE_MISMATCH:
		throw new Exception('Underflow or the modes mismatch');
		break;
	case JSON_ERROR_CTRL_CHAR:
		throw new Exception('Unexpected control character found');
		break;
	case JSON_ERROR_SYNTAX:
		throw new Exception('Syntax error, malformed JSON');
		break;
	case JSON_ERROR_UTF8:
		throw new Exception('Malformed UTF-8 characters, possibly incorrectly encoded');
		break;
	default:
		throw new Exception('Unknown Error');
		break;
	}
	return $json_body;
}

function getSchema($route_name) { 
	list($data_type, $endpoint) = explode(":", $route_name, 2);
	$full_schema = decode_json_throw_errors(file_get_contents('./json/' . $data_type . '.json'));
	$request_schema ='';
	$response_schema = '';
	foreach ($full_schema->links as $endpoint_schema) { 
		//TODO: this is ugly
		if (strcmp(str_replace('"','',json_encode($endpoint_schema->title)), $endpoint) == 0) { 
		  $request_schema = $endpoint_schema->schema;
		  $response_schema = $endpoint_schema->targetSchema;
		}
	}
	return array($request_schema, $response_schema);
}

$jsonDecodeMW = function ($request, $response, $next) {
	$json_body = decode_json_throw_errors($request->getBody());
	$request = $request->withAttribute('requestDecodedJson', $json_body);
	$response = $next($request, $response);
	return $response;	
};

$jsonvalidateMW = function ($request, $response, $next) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$route_name = $request->getAttribute('route')->getName();
	//TODO: maybe surround this with a try/catch
	//$request_schema = decode_json_throw_errors(file_get_contents('./json/' . $route_name . 'request.json'));
	list($request_schema, $response_schema) = getSchema($route_name);
	$validator = new League\JsonGuard\Validator($json_body, $request_schema);
	if ($validator->fails()) {
		print_r($validator->errors());
		return NULL;
	}
	$next($request, $response);
	//$response_schema = decode_json_throw_errors(file_get_contents('./json/' . $route_name . 'response.json'));
	
	if(json_decode($response->getBody())){
		$validator = new League\JsonGuard\Validator(json_decode($response->getBody()), $response_schema);
		if ($validator->fails()) {
			print_r($validator->errors());
			return NULL;
		}
	}
	return $response;
};


################# COURSE ########################

$app->get('/course/get', function (Request $request, Response $response) use ($dataMgr) {
	$json_body = json_decode($request->getBody());
    $params = (array) $json_body;
    if (isset($params['courseID'])) {	
		$courseID = new CourseID($params['courseID']);
		$dataMgr->setCourseFromID($courseID);
		$returnVal = $dataMgr->getCourseInfo($courseID);
	}
	else {
		$returnVal = $dataMgr->getCourses();
	}
	return $response->withJson($returnVal);
});

$app->post('/course/create', function (Request $request, Response $response) use ($dataMgr) {
    # needs JSON validation
    //TODO ADD ERROR CATCHING
    $params = $request->getBody();
	$params = json_decode($params, true);
 
	$dataMgr->createCourse($params['name'], $params['displayName'], $params['authType'], $params['registrationType'], isset_bool($params['browsable']));
    return $response;
 });
  
$app->post('/course/update', function (Request $request, Response $response) use ($dataMgr) {
    # needs JSON validation
    //TODO ADD ERROR CATCHING
	$params = $request->getBody();
    $params = json_decode($params, true);
    $courseID = new CourseID($params['courseID']);
    $dataMgr->setCourseFromID($courseID);
    $courseInfo = (array) $dataMgr->getCourseInfo($courseID);
 
    foreach($params as $key => $value) {
        $temp = $params[$key];
        $courseInfo[$key] = $temp;
    }
    $dataMgr->setCourseInfo($courseID, $courseInfo['name'], $courseInfo['displayName'], $courseInfo['authType'], $courseInfo['registrationType'], isset_bool($courseInfo['browsable']));
	 
    return $response->withJson($dataMgr->getCourseInfo($courseID));
 });

$app->post('/course/delete', function (Request $request, Response $response) use ($dataMgr) {
	# needs JSON validation
    //TODO ADD ERROR CATCHING
    $params = $request->getBody();
    $params = json_decode($params, true);
    $courseID = new CourseID($params['courseID']);
    $dataMgr->setCourseFromID($courseID);

    $dataMgr->deleteCourse($courseID);
    return $response;
});

####################### ASSIGNMENTS ###########################3
$app->get('/assignment/get', function (Request $request, Response $response) use ($dataMgr){


    $params = json_decode($request->getBody(), true);
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    
    if ($params["assignmentIDs"] == "all"){
    
        $assignments = $dataMgr->getAssignments();
        $newResponse = $response->withJson($assignments);
        return $newResponse;
    }
    else {
        $assignment = array(); 
        foreach ($params["assignmentIDs"] as $id){
            $assignments[] = $dataMgr->getAssignment(new AssignmentID($id));
        }
        $newResponse = $response->withJson($assignments);
        return $newResponse;
    }

});

$app->post('/assignment/create', function (Request $request, Response $response) use ($dataMgr){
    
    $params = $request->getBody();
    $params = json_decode($params, true);
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    $assignment = create_assignment($params); 
});

$app->post('/assignment/update', function( Request $request, Response $response) use ($dataMgr){
    $json_params = $request->getBody();
    $params = json_decode($json_params,true); 
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    $assignment = update_assignment($params);
    
});



#################### PEERREVIEWS ######################333
$app->get('/rubric/get',function (Request $request, Response $response) use ($dataMgr){

    $params = json_decode($request->getBody(),true);

    $assignment = $dataMgr->getAssignment(new AssignmentID($params['assignmentID']));
    $questions = $assignment->getReviewQuestions($assignment);
    $newResponse = $response->withJson($questions);
    return $newResponse;
});

$app->post('/rubric/update',function(Request $request, Response $response) use($dataMgr){
    //TODO Make this a patch
    $params = $request->getBody();
    $params = json_decode($params,true);
    $assignment = $dataMgr->getAssignment(new AssignmentID($params["assignmentID"]));
    update_radio_question($assignment, $params); 
    $response->getBody()->write("finished");
});

$app->post('/rubric/create',function(Request $request, Response $response) use ($dataMgr){
    
    $params = json_decode($request->getBody(),true);
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    #actually don't think you even need the course name, but not 100% about the whole mta system so its here
    unset($params['courseID']); #get rid of it cause not needed for the rubric
    $assignment = $dataMgr->getAssignment(new AssignmentID($params['assignmentID'])); 
    create_radio_question($assignment, $params);

});

$app->get('/peerreviewscores/get', function(Request $request, Response $response) use($dataMgr){

    $params = json_decode($request->getBody(),true);
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    $assignment = $dataMgr->getAssignment(new AssignmentID($params['assignmentID']));
    $review = $assignment->getReview(new MatchID($params['matchID']));
    $newResponse = $response->withJson($review);
    return $newResponse;
});


############################ STUDENT SIDE TESTING ###############
$app->post('/makesubmissions', function (Request $request, Response $response) use
    ($dataMgr){#takes in course name and assignment id
        $params = $request->getBody();
        $params = json_decode($params,true);
        mockSubmissions($params["courseID"], $params["assignmentID"]); 
});

$app->post('/peerreviewscores/create', function(Request $request, Response $response) use($dataMgr){

    $params = json_decode($request->getBody(),true);
    $dataMgr->setCourseFromID(new CourseID($params['courseID']));
    $assignment = $dataMgr->getAssignment(new AssignmentID($params['assignmentID']));
    make_peer_review($assignment, $params);
});

$app->get('/getcourseidfromname', function (Request $request, Response $response) use ($dataMgr) {
	$params = $request->getBody();
    $params = json_decode($params,true);
	$dataMgr->setCourseFromName($params['courseName']);
	return $response->withJson($dataMgr->courseID);
});

########################### GRADES #######################

# NEXT SECTION OF ENDPOINTS TO ADD

#/peermatch/get
$app->get('/peermatch/get/',  function (Request $request, Response $response) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$dataMgr = $this->dataMgr;
	$assignmentID = $json_body->assignmentID;
	//TODO: handle exceptions and errors around the database calls
	$db = $dataMgr->getDatabase();
	$sh = $db->prepare("SELECT  peer_review_assignment_submissions.authorID, peer_review_assignment_matches.reviewerID
       			FROM peer_review_assignment_submissions JOIN peer_review_assignment_matches ON
       		peer_review_assignment_matches.submissionID = peer_review_assignment_submissions.submissionID
       		WHERE peer_review_assignment_submissions.assignmentID = ?
       		ORDER BY peer_review_assignment_submissions.authorID");
	$sh->execute(array($assignmentID));
	//Note, authorID is authorID for submission, 
	//reviewerID is person matched to review submission, by authorID, on assignmentID
	$peerMatchDBMap = array();
        while($res = $sh->fetch())
        {
            if(!array_key_exists($res->authorID, $peerMatchDBMap))
            {
                $peerMatchDBMap[$res->authorID] = array('studentID' => (int)$res->authorID, 'matchList'=> array());
            }
            array_push($peerMatchDBMap[$res->authorID]['matchList'], (int)$res->reviewerID);
        }
	$peerMatchesArray = array();
	foreach ($peerMatchDBMap as $peerMatch) {
		array_push($peerMatchesArray, $peerMatch);
	}
	$return_array['peerMatches'] = $peerMatchesArray;
	$return_array['assignmentID'] = $assignmentID;
	$newResponse = $response->withJson($return_array);
	return $newResponse;
})->add($jsonvalidateMW)->add($jsonDecodeMW)->setName('peermatch:get');

$app->post('/peermatch/create', function (Request $request, Response $response) use ($dataMgr) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$db = $dataMgr->getDatabase();
	$assignmentID = $json_body->assignmentID;
	$peerMatches = $json_body->peerMatches;
	$getSubmissionIds = $db->prepare("SELECT submissionid FROM PEER_REVIEW_ASSIGNMENT_SUBMISSIONS where authorID = ? and assignmentID = ?");
	$checkForMatch = $db->prepare("SELECT matchID FROM PEER_REVIEW_ASSIGNMENT_MATCHES where submissionID=? AND reviewerID = ?;");
	$insertMatch = $db->prepare("INSERT INTO PEER_REVIEW_ASSIGNMENT_MATCHES (submissionID, reviewerID, instructorForced, calibrationState) values (?,?,0,'none')");
	foreach ($peerMatches as $peerMatch) {
			$db->beginTransaction();
			$studentID = $peerMatch->studentID;
			$getSubmissionIds->execute(array($studentID, $assignmentID));
			$matchingList = $peerMatch->matchList;
			$res  = $getSubmissionIds->fetch();
			//TODO: do we really need this?? check the type output for pdo object fetch()
			//TODO: this should probably be an error, return an error code
			if ($res) {
				$submissionID = $res->submissionID;
			} else {
				$db->commit();
				continue;
			}

			foreach($matchingList as $matchedReviewer) {
				$checkForMatch->execute(array($submissionID, $matchedReviewer));
				
				if (!$checkForMatch->fetch()){
					$insertMatch->execute(array($submissionID, $matchedReviewer));
				}
			}
			 
			$db->commit();
	}
})->add($jsonvalidateMW)->add($jsonDecodeMW)->setName('peermatch:create');


$app->post('/peermatch/delete', function (Request $request, Response $response) use ($dataMgr) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$db = $dataMgr->getDatabase();
	$assignmentID = $json_body->assignmentID;
	$db->beginTransaction();
	//$getAllMatches = $db->prepare("SELECT * from PEER_REVIEW_ASSIGNMENT_MATCHES where assignmentID = ?");

	$deleteAllMatches = $db->prepare("DELETE FROM peer_review_assignment_matches
			WHERE submissionID in (
				SELECT peer_review_assignment_submissions.submissionID 
				FROM peer_review_assignment_submissions 
				INNER JOIN peer_review_assignment_matches ON (peer_review_assignment_matches.submissionID = peer_review_assignment_submissions.submissionID) 
				WHERE peer_review_assignment_submissions.assignmentID = ?)");
	//$getAllMatches->execute(array($assignmentID));
	//$allMatches = $getAllMatches->fetchAll();
	$deleteAllMatches->execute(array($assignmentID));
	$db->commit();
})->setName('peermatch:delete')->add($jsonvalidateMW)->add($jsonDecodeMW);


$app->run();
