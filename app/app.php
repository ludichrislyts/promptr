<?php
    require_once __DIR__."/../vendor/autoload.php";
    require_once __DIR__."/../src/Question.php";
    require_once __DIR__."/../src/Answer.php";
    require_once __DIR__."/../src/Topic.php";
    require_once __DIR__."/../src/Promptr.php";

    $app = new Silex\Application();

    $app['debug'] = true;

    $server = 'mysql:host=localhost;dbname=promptr_app';
    $username = 'root';
    $password = 'root';
    $DB = new PDO($server, $username, $password);

    $app->register(new Silex\Provider\TwigServiceProvider(), array(
      'twig.path' => __DIR__.'/../views'));

    use Symfony\Component\HttpFoundation\Request;
    Request::enableHttpMethodParameterOverride();
    
/////////////////////////////////////////////////////////////////
////////////////// BEGIN ADMIN PAGES ////////////////////////////
/////////////////////////////////////////////////////////////////
// PROMPTR-ADMIM.TWIG
// this route is manually entered and used only to populate the database
    $app->get("/admin", function() use ($app){
        $topics = Topic::getAll();
        $all_promptrs = Promptr::getAll();
        // find promptrs that need questions (newly added -- need to do this to get promptr id)
        $empty_promptrs = [];
        foreach($all_promptrs as $promptr){
            if(empty($promptr->getQuestions())){
                array_push($empty_promptrs, $promptr);
            }
        }
        
        return $app['twig']->render('promptr-admin.html.twig', array(
                                    'topics' => $topics, 
                                    'promptrs' => $empty_promptrs));
    });
// renders after adding a blank promptr
    $app->post("/admin", function() use ($app){
        $promptr_name = $_POST['promptr_name'];
        $topic_id = $_POST['topic_id'];
        $new_promptr = new Promptr($promptr_name, $topic_id);
        $new_promptr->save();
        $topics = Topic::getAll();
        $all_promptrs = Promptr::getAll();
        // find promptrs that need questions (newly added -- need to do this to get promptr id)
        $empty_promptrs = [];
        foreach($all_promptrs as $promptr){
            if(empty($promptr->getQuestions())){
                array_push($empty_promptrs, $promptr);
            }
        }        
        return $app['twig']->render('promptr-admin.html.twig', array(
                                    'topics' => $topics, 
                                    'promptrs' => $empty_promptrs));
    });
// //Admin page after all promptr delete -- refreshes admin page with topics only
    $app->delete("/admin", function() use ($app){
        Promptr::deleteAll();        
        return $app['twig']->render("promptr-admin.html.twig", array(
                                    'topics' => Topic::getAll(),
                                    'promptrs' => Promptr::getAll()));
    });
//  UNPOPULATED HOME PAGE -- SHOULD ONLY BE REACHED AFTER DELETE ALL PEFORMED
// ON ADMIN PAGE    
    $app->delete("/", function() use ($app){
        Promptr::deleteAll();
        Topic::deleteAll();
        Question::deleteAll();
        Answer::deleteAll();
        return $app['twig']->render('index.html.twig');
    });
/////////////////////////////////////////////////////////////////
/////////////////// END ADMIN PAGES /////////////////////////////
/////////////////////////////////////////////////////////////////

// INDEX.HTML.TWIG
// home page displays list of topics, popular promptrs, and option to create a new promptr
    $app->get("/", function() use ($app){
        $topics = Topic::getAll();
        $promptrs = Promptr::getAll();
        return $app['twig']->render('index.html.twig', array(
                                    'topics' => $topics, 
                                    'promptrs' => $promptrs));
    });
// PROMPTR.HTML.TWIG
// START PAGE for creating a new promptr
    $app->get("/promptr/{id}", function($id) use ($app){
        $promptr = Promptr::find($id);
        return $app['twig']->render('promptr.html.twig', array (
                                    'promptr' => $promptr,
                                    'questions' => $promptr->getQuestions()));

    });
// PROMPTR.HTML.TWIG 
// CONTINUE CREATING NEW PROMPTR ROUTE
    $app->post("/promptr/{id}", function($id) use ($app){
        $promptr = Promptr::find($id);
        $new_question_text = $_POST['question'];
        $new_description = $_POST['description'];
        $new_question = new Question($new_question_text, $new_description);
        $new_question->save();
        $promptr->addQuestion($new_question->getId());
        return $app['twig']->render('promptr.html.twig', array (
                                    'promptr' => $promptr,
                                    'questions' => $promptr->getQuestions()));
    });
// PROMPTRS.HTML.TWIG
// ADD PROMPTR -- adds a prompter and displays promptrs within the topic
    $app->post("/promptrs", function() use ($app){
        $promptr_name = $_POST['promptr_name'];
        $topic_id = $_POST['topic_id'];
        $new_promptr = new Promptr($promptr_name, $topic_id);
        $new_promptr->save();
        return $app['twig']->render('promptrs.html.twig', array (
                                    'promptrs' => Promptr::getAll(),
                                    'topic' => $topic_id,
                                    'topic_picked' => true));// flag for included template
    });
// PROMPTRS.HTML.TWIG
// DELETE ALL PROMPTRS -- ADMIN ONLY (no duh)
    $app->get("/deleteAllPromptrs", function() use ($app){
        Promptr::deleteAll();
        return $app['twig']->render('promptrs.html.twig', array (
                                    'promptrs' => Promptr::getAll()));
    });
// TOPIC.HTML.TWIG
// TOPIC MAIN PAGE -- display all promptrs within a specific topic
    $app->get("/topic/{id}", function($id) use ($app){
        $topic = Topic::find($id);
        $promptrs = $topic->getPromptrs();
        return $app['twig']->render("topic.html.twig", array(
                                    'topic' => $topic, 
                                    'promptrs' => $promptrs));
    });
// PROMPTR.HTML.TWIG
//delete question from NEW PROMPTR route -- then displays promptr page
    $app->delete("/promptr/{id}/delete_question/{qId}", function($id, $qId) use ($app){
        $question_id = $qId;
        $promptr = Promptr::find($id);
        $question = Question::findById($question_id);
        $question->delete();
        $questions = $promptr->getQuestions();
        return $app['twig']->render("promptr.html.twig", array(
                                    'promptr' => $promptr, 
                                    'questions' => $questions));
    });
// QUESTION.HTML.TWIG
// run through a promptr
    // first page of promptr run - displays first question in promptr
    // question array -- takes answer from user
    $app->get("/promptr/{id}/question", function($id) use ($app){
        $promptr = Promptr::find($id);
        $first_question = $promptr->getQuestions()[0];
        return $app['twig']->render('question.html.twig', array(
                                    'question' => $first_question,
                                    'promptr' => $promptr));
    });
// QUESTION.HTML.TWIG
// the following pages of promptr run -- adding more answers
    $app->post("/promptr/{id}/question/{quid}", function($id, $quid) use ($app){
        $end_flag = false;
        $answer_field = $_POST['answer'];
        $new_answer = new Answer($answer_field, $quid);
        $new_answer->save();
        ++$quid;
        $question = Question::findById($quid);
        $promptr = Promptr::find($id);
        if($question != null){
            $question->addAnswer($new_answer->getId());
            $questions = $promptr->getQuestions();
            $last_question = end($questions);
            if ($question == $last_question)
            {
                $end_flag = true;
            }
        }
        return $app['twig']->render('question.html.twig', array(
                                    'question' => $question,
                                    'end' => $end_flag,
                                    'promptr' => $promptr));
    });
// DISPLAY.HTML.TWIG
// DISPLAY FINISHED answers to promptr run
    $app->get("/promptr/{id}/display", function($id) use ($app){
        $promptr = Promptr::find($id);
        $questions = $promptr->getQuestions();
        return $app['twig']->render('display.html.twig',array(
                                    'promptr' => $promptr, 
                                    'questions' => $questions));
    });

    return $app;
?>
