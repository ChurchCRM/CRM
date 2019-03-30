<?php

use ChurchCRM\Utils\LoggerUtils;
use BotMan\BotMan\Interfaces\Middleware\Received;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use Monolog\Handler\StreamHandler;
use ChurchCRM\Event;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\WordTokenizer ;
use Phpml\Classification\NaiveBayes;
use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;
use \Phpml\Math\Set;

require_once __DIR__."/ChatbotIntent.php";
foreach (glob("Intents/*.php") as $filename) {
    include $filename;
}

class ReceivedIntentClassificationMiddleware implements Received
{
    private $vectorizer;
    private $svcClassifier;
    private $intents;
    private $intentsReference;

    public function __construct()
    {
        LoggerUtils::getChatBotLogger()->debug("Construction Intent Classification Middleware. Training Models.");
        // initialize the tokenizer and the classifier
        $this->vectorizer = new TokenCountVectorizer(new WordTokenizer());
        $this->svcClassifier =  new SVC(Kernel::LINEAR, // $kernel
         1.0,            // $cost
         3,              // $degree
         null,           // $gamma
         0.0,            // $coef0
         0.001,          // $tolerance
         100,            // $cacheSize
         true,           // $shrinking
         true            // $probabilityEstimates, set to true
        );
 
        // load our intent classes, and train the model accordingly.
        $this->intents  = array();
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'ChatbotIntent')) {
                $this->intents[] = new $class();
            }
        }
        //$this->intents = [new EventsQuestionIntent(), new DemographicQuestionIntent()];
        $this->intentsReference =[];
        foreach ($this->intents as $intent) {
            $this->intentsReference[$intent->getLabel()] = $intent;
            LoggerUtils::getChatBotLogger()->debug("Training model for intent: " . $intent->getLabel());
            $samples = $intent->getSamples();
            $this->vectorizer->fit($samples);
            //LoggerUtils::getChatBotLogger()->info("Vocabulary: " . json_encode($this->vectorizer->getVocabulary()));
            $this->vectorizer->transform($samples);
            $labels = array_fill(0, count($samples), $intent->getLabel());
            //LoggerUtils::getChatBotLogger()->info("Samples: " . json_encode($samples).".  Labels: " . json_encode($labels));
            $this->svcClassifier->train($samples, $labels);
        }
        LoggerUtils::getChatBotLogger()->debug("Vocabulary: " . json_encode($this->vectorizer->getVocabulary()));
        LoggerUtils::getChatBotLogger()->debug("All models trained");
    }
    /**
     * Handle an incoming message.
     *
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function received(IncomingMessage $message, $next, BotMan $bot)
    {
        // now let's assess the message and determine what intent it may match
        $m = [strtolower($message->getText())];
        $this->vectorizer->transform($m);
        LoggerUtils::getChatBotLogger()->debug("message transformed to modeled vector. Sample: " . json_encode($m));

        // ensure that the "set" resulting from transforming the question into the learned vocabulary has at least one "1"
        $mSet =  new Set($m[0]);
        if ($mSet->contains(1)) {
            LoggerUtils::getChatBotLogger()->debug("Predicting intent of message.");
            $predictionProbabilities = $this->svcClassifier->predictProbability($m);
            LoggerUtils::getChatBotLogger()->debug("Probability: " . json_encode($predictionProbabilities));
            $prediction = $this->svcClassifier->predict($m)[0];
            LoggerUtils::getChatBotLogger()->info("Incoming bot message predicted intent: " . $prediction);
           
            // Ensure the SVC is at least 50% sure of the predicted probability
            if ($predictionProbabilities[0][$prediction] > .5) {
                $message->addExtras("MatchedIntent", $this->intentsReference[$prediction]);
            } else {
                LoggerUtils::getChatBotLogger()->info("Not usign prediciton; chance too low: " . $predictionProbabilities[0][$prediction]);
            }

            
            if (false) {
                // after we've derived an intent, let's remove the intent-causing words
                //  to find out what other context exists in the message
                $questionVectorizer = new TokenCountVectorizer(new WordTokenizer());
                LoggerUtils::getChatBotLogger()->debug(json_encode($m));
                $questionVectorizer->fit([$message->getText()]);
                LoggerUtils::getChatBotLogger()->debug("Question tokenized");
            }
        } else {
            LoggerUtils::getChatBotLogger()->debug("No vocabulary words matched in incoming message.  Not attempting prediction");
        }
      
        return $next($message);
    }

    public function getIntents()
    {
        return $this->intents;
    }
}
