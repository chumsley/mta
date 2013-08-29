<?php

require_once(dirname(__FILE__)."/../../inc/common.php");
require_once("inc/assignment.php");

class PeerReviewLeaderBoardAssignment extends Assignment
{
    function __construct(AssignmentID $id = NULL, $name, AssignmentDataManager $dataMgr)
    {
        parent::__construct($id, $name, $dataMgr);
    }

    protected function _duplicate()
    {
        global $NOW;
        $obj = clone $this;
        return $obj;
    }

    protected function _loadFromPost($POST)
    {
    }

    function _getFormHTML()
    {
        return "";
    }

    function _getFormScripts()
    {
        return "";
    }

    function _getValidationCode()
    {
        return "";
    }

    function _showForUser(UserID $userID)
    {
        return true;
    }

    function _getHeaderHTML(UserID $userID)
    {
        global $dataMgr;

        $html = "";
        $results = $this->dataMgr->getLeaderResults();

        $html .= "<table width='100%'>";
        $maxNumber = 10;
        $numPerRow = 2;
        $userRank = 0;

        $cellWidth = 100 / $numPerRow;

        for($i = 0; $i < $maxNumber; )
        {
            $html .= "<tr>";
            for($j = 0; $j < $numPerRow; $j++, $i++)
            {
                $html .= "<td width='$cellWidth%'>";
                if($i < sizeof($results)){
                    $html .= $i+1 .": ";
                    $res = $results[$i];
                    $name = $res->alias;
                    if(is_null($name) || strlen($name) == 0){
                        $name = "Anonymous";
                    }
                    $name .= " (".precisionFloat($res->points) . ")";
                    if($res->userID == $userID)
                    {
                        $name = "<b>$name</b>";
                        $userRank = $i+1;
                    }
                    $html .= $name;
                }
                $html .= "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";
        for($i = $maxNumber; $i < sizeof($results); $i++){
            $res = $results[$i];
            if($res->userID == $userID)
            {
                $userRank = $i+1;
                break;
            }
        }
        if($userRank > 0)
            $html .= "You are currently ranked at $userRank";
        return $html;
    }

    function getAssignmentTypeDisplayName()
    {
        return "Peer Review Leader Board";
    }
}

