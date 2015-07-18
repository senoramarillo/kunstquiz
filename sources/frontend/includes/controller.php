<?php

function requestQuestion($SID){
	$context = stream_context_create(array('http' => array('header' => 'Connection: close\r\n')));
	return file_get_contents("http://localhost/api/question/random/", false, $context);
}

function getQuestion($SID){
	
	$requestQuestion = requestQuestion($SID);
	$questionJSONObject = json_decode($requestQuestion, TRUE);
	
	$question = $questionJSONObject['question'];
	$hint = $questionJSONObject['hint'];
	$image = $questionJSONObject['image'];
	
	$rightAnswer = $questionJSONObject['answers']['rightAnswer'];
	$wrongAnswers = array(
        $questionJSONObject['answers']['wrongAnswer1'],
        $questionJSONObject['answers']['wrongAnswer2'],
        $questionJSONObject['answers']['wrongAnswer3']
    );

	$wikilink = $questionJSONObject['wikilink'];

	$rightAnswerPosition = rand(0,3);
	$answerButtons[$rightAnswerPosition] = $rightAnswer;
	
	$counter = 0;
	for ($i=0; $i<4;$i++){
		if($i!=$rightAnswerPosition){
			$answerButtons[$i] = $wrongAnswers[$counter];
			$counter++;
		}
	}											
		
	return array(
        'question' => $question,
        'hint' => $hint,
        'image' => $image,
        'rightAnswer' => $rightAnswerPosition,
        'answerButtons' => $answerButtons,
        'wikilink' => $wikilink,
    );
}

session_start();
$questionData = getQuestion($SID);
$_SESSION['questionCounter'] = $_SESSION['questionCounter'] + 1;
$_SESSION['questionData']=$questionData;
if(isset($_POST['questionCount'])){
	$_SESSION['questionCount'] = $_POST['questionCount'];
	$_SESSION['askedQuestions'] = array();
}

/*
while(in_array($questionData['image'], $_SESSION['askedQuestions'])) {
    $questionData = getQuestion($SID);
		$_SESSION['questionData']=$questionData;
}
*/

array_push($_SESSION['askedQuestions'], $questionData['image']);
