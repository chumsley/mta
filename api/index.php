<?php
require '../vendor/autoload.php';
require_once("../inc/common.php");
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \JsonSchema\Validator as JsonValidator;

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$container = new \Slim\Container;
$app = new \Slim\App(["settings"=>$config]);



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

$jsonDecodeMW = function ($request, $response, $next) {
	$json_body = decode_json_throw_errors($request->getBody());
	$request = $request->withAttribute('requestDecodedJson', $json_body);
	$response = $next($request, $response);
	return $response;	
};

$jsonvalidatemw = function ($request, $response, $next) {
	$json_body = json_decode($request->getBody());
	$validator = new JsonValidator;
	$validator->check($data, (object)['$ref' => 'file://' . realpath('./peermatch_get.json')]);
};

$app->post('/validate', function (Request $request, Response $response) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$schema = decode_json_throw_errors(file_get_contents('./peermatch/get/request.json'));
	$validator = new League\JsonGuard\Validator($json_body, $schema);
	if ($validator->fails()) {
		print_r($validator->errors());
	} else {
		echo "not";
	}
	//$schemaArray = $myService->schemaArray;
})->add($jsonDecodeMW);


################# COURSE ########################

$app->get('/course/get', function (Request $request, Respo    nse $response) use ($dataMgr) {
    //TODO ADD ERROR CATCHING
    # $schema = json_decode(file_get_contents('./json/cour    se/get/request.json'));
	$json_body = json_decode($request->getBody());
	/*$validator = new League\JsonGuard\Validator($json_bo    dy, $schema);
    if($validator->fails()) {
        print_r($validator->errors());
        return NULL;
        throw new Exception("RIP");
    }*/
    $params = (array) $json_body;
    $courseID = new CourseID($params['courseID']);
    $dataMgr->setCourseFromID($courseID);

    $courseInfo = $dataMgr->getCourseInfo($courseID);
	return $response->withJson($courseInfo);
});

$app->post('/course/create', function (Request $request, R    esponse $response) use ($dataMgr) {
    # needs JSON validation
    //TODO ADD ERROR CATCHING
    $params = $request->getBody();
	$params = json_decode($params, true);
 
	$dataMgr->createCourse($params['name'], $params['displ    ayName'], $params['authType'], $params['registrationType']    , isset_bool($params['browsable']));
    return $response;
 });
  
$app->post('/course/update', function (Request $request, R    esponse $response) use ($dataMgr) {
    # needs JSON validation
    //TODO ADD ERROR CATCHING
	$params = $request->getBody();
    $params = json_decode($params, true);
    $courseID = new CourseID($params['courseID']);
    $dataMgr->setCourseFromID($courseID);
    $courseInfo = (array) $dataMgr->getCourseInfo($courseI    D);
 
    foreach($params as $key => $value) {
        $temp = $params[$key];
        $courseInfo[$key] = $temp;
    }
    $dataMgr->setCourseInfo($courseID, $courseInfo['name']    , $courseInfo['displayName'], $courseInfo['authType'], $co    urseInfo['registrationType'], isset_bool($courseInfo['brow    sable']));
	 
    return $response->withJson($dataMgr->getCourseInfo($co    urseID));
 });

$app->post('/course/delete', function (Request $request, Response $response)     use ($dataMgr) {
	# needs JSON validation
    //TODO ADD ERROR CATCHING
    $params = $request->getBody();
    $params = json_decode($params, true);
    $courseID = new CourseID($params['courseID']);
    $dataMgr->setCourseFromID($courseID);

    $dataMgr->deleteCourse($courseID);
    return $response;
});

########################### GRADES #######################

# NEXT SECTION OF ENDPOINTS TO ADD

$app->get('/peermatch/get/',  function (Request $request, Response $response) use ($dataMgr) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$schema = decode_json_throw_errors(file_get_contents('./peermatch_json/get/request.json'));
	$validator = new League\JsonGuard\Validator($json_body, $schema);
	if ($validator->fails()) {
		print_r($validator->errors());
		return NULL;
		throw new Exception(sprintf("[peermatch][get] Validation failed %s", $stringempty));
	} 
	$assignmentID = $json_body->assignmentID;
	$db = $dataMgr->getDatabase();
	$sh = $db->prepare("SELECT  peer_review_assignment_submissions.authorID, peer_review_assignment_matches.reviewerID
       			FROM peer_review_assignment_submissions JOIN peer_review_assignment_matches ON
       		peer_review_assignment_matches.submissionID = peer_review_assignment_submissions.submissionID
       		WHERE peer_review_assignment_submissions.assignmentID = ?
       		ORDER BY peer_review_assignment_submissions.authorID");
	$sh->execute(array($assignmentID));
        $reviewerAssignment = array();
        while($res = $sh->fetch())
        {
            if(!array_key_exists($res->authorID, $reviewerAssignment))
            {
                $reviewerAssignment[$res->authorID] = array();
            }
            array_push($reviewerAssignment[$res->authorID], $res->reviewerID);
        }
	$return_array['matchesMap'] = $reviewerAssignment;
	$newResponse = $response->withJson($return_array);
	return $newResponse;
})->add($jsonDecodeMW);

$app->post('/peermatch/create', function (Request $request, Response $response) use ($dataMgr) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$schema = decode_json_throw_errors(file_get_contents('./peermatch_json/create/request.json'));
	$validator = new League\JsonGuard\Validator($json_body, $schema);
	if ($validator->fails()) {
		print_r($validator->errors());
		return NULL;
	}
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

})->add($jsonDecodeMW);


/*
$app->post('/peermatch/delete', function (Request $request, Response $response) use ($dataMgr) {
	$json_body = $request->getAttribute('requestDecodedJson');
	$schema = decode_json_throw_errors(file_get_contents('./peermatch_json/delete/request.json'));
	$validator = new League\JsonGuard\Validator($json_body, $schema);
	/*
	if ($validator->fails()) {
		print_r($validator->errors());
		return NULL;
	}
	$db = $dataMgr->getDatabase();
	$assignmentID = $json_body->assignmentID;
	$getAllMatches = $db->prepare("SELECT * from peer_review_assignment_submissions where assignmentID = ?");
	$getSubmissionIds = $db->prepare("SELECT submissionid FROM PEER_REVIEW_ASSIGNMENT_SUBMISSIONS where authorID = ? and assignmentID = ?");
	$checkForMatch = $db->prepare("SELECT matchID FROM PEER_REVIEW_ASSIGNMENT_MATCHES where submissionID=? AND reviewerID = ?;");
}
 */

$app->run();
