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

require __DIR__."/Intents/EventsQuestionIntent.php";
require __DIR__."/Intents/DemographicQuestionIntent.php";

class ReceivedIntentClassificationMiddleware implements Received
{
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
        // initialize the tokenizer and the classifier
        $vectorizer = new TokenCountVectorizer(new WordTokenizer ());
        $svcClassifier =  new SVC(   Kernel::LINEAR, // $kernel
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
        $intents = [new EventsQuestionIntent(), new DemographicQuestionIntent()];
        $intentsReference =[];
        foreach ($intents as $intent)
        {
            $intentsReference[$intent->getLabel()] = $intent;
            LoggerUtils::getChatBotLogger()->info("Training model for intent: " . $intent->getLabel());
            $samples = $intent->getSamples();
            $vectorizer->fit($samples);
            LoggerUtils::getChatBotLogger()->info("Vocabulary: " . json_encode($vectorizer->getVocabulary()));
            $vectorizer->transform($samples);
            $labels = array_fill(0,count($samples ),$intent->getLabel());
            LoggerUtils::getChatBotLogger()->info("Samples: " . json_encode($samples).".  Labels: " . json_encode($labels));
            $svcClassifier->train($samples, $labels);
        }
        LoggerUtils::getChatBotLogger()->info("All models trained");

        // now let's assess the message and determine what intent it may match
        $m = [strtolower($message->getText())];
        $vectorizer->transform($m);
        LoggerUtils::getChatBotLogger()->info("message transformed to modeled vector. Sample: " . json_encode($m));

        // ensure that the "set" resulting from transforming the question into the learned vocabulary has at least one "1"
        $mSet =  new Set($m[0]);
        if ($mSet->contains(1)) {
            LoggerUtils::getChatBotLogger()->info("Predicting intent of message.");
            $prediction = $svcClassifier->predictProbability($m);
            LoggerUtils::getChatBotLogger()->info("Probability: " . json_encode($prediction));
            $prediction = $svcClassifier->predict($m);
            LoggerUtils::getChatBotLogger()->info("Prediction: " . json_encode($prediction));
    
            // after we've derived an intent, let's remove the intent-causing words 
            //  to find out what other context exists in the message
            $questionVectorizer = new TokenCountVectorizer(new WordTokenizer ());
            LoggerUtils::getChatBotLogger()->info(json_encode($m));
            $questionVectorizer->fit([$message->getText()]);
            LoggerUtils::getChatBotLogger()->info("Question tokenized");

            $message->addExtras("MatchedIntent",$intentsReference[$prediction[0]]);
        }
      

      

        return $next($message);
    }
}