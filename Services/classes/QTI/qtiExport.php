<?php

/**
 * To export a question in QTI
 *
 */

namespace UJM\ExoBundle\Services\classes\QTI;


use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Security\Core\SecurityContextInterface;


abstract class qtiExport
{

    protected $doctrine;
    protected $securityContext;
    protected $container;
    protected $qtiRepos;
    protected $node;
    protected $document;
    protected $responseDeclaration = array();
    protected $nbResponseDeclaration = 0;
    protected $outcomeDeclaration;
    protected $modalFeedback;
    protected $itemBody;
    protected $question;
    protected $resourcesLinked = array();

    /**
     * Constructor
     *
     * @access public
     *
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine Dependency Injection
     * @param \Symfony\Component\Security\Core\SecurityContextInterface $securityContext Dependency Injection
     * @param \Symfony\Component\DependencyInjection\Container $container
     *
     */
    public function __construct(Registry $doctrine, SecurityContextInterface $securityContext, $container)
    {
        $this->doctrine        = $doctrine;
        $this->securityContext = $securityContext;
        $this->container       = $container;
        $this->document = new \DOMDocument();
        $this->document->preserveWhiteSpace = false;
        $this->document->formatOutput = true;
    }

    /**
     * Generate object tags for the attached files
     *
     * @access protected
     *
     */
    protected function objetcTags()
    {
//        $imgs = $this->document->getElementsByTagName("img");
//        foreach ($imgs as $img) {
//            echo 'ok';
//        }
        $prompt = $this->document->getElementsByTagName('prompt')->item(0);
        //$txt = $prompt->nodeValue;
        $txt = html_entity_decode($prompt->nodeValue);
        $regex = '(<a.*?</a>)';
        preg_match_all($regex, $txt, $matches);
        foreach ($matches[0] as $matche) {
            $url = substr($matche, stripos($matche, 'href="/') + 7, stripos($matche, '">') - 10);
            $url = explode('/', $url);
            $nodeId = $url[6];
            echo $nodeId."<br>";

        }
        die();
    }

    /**
     * Generate head of QTI
     *
     * @access protected
     *
     * @param String $identifier type question
     * @param String $title title of question
     *
     */
    protected function qtiHead($identifier, $title)
    {
        $this->node = $this->document->CreateElement('assessmentItem');
        $this->node->setAttribute("xmlns", "http://www.imsglobal.org/xsd/imsqti_v2p1");
        $this->node->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $this->node->setAttribute("xsi:schemaLocation", "http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/imsqti_v2p1.xsd");
        $this->node->setAttribute("identifier", $identifier);
        $this->node->setAttribute("title", $title);
        $this->node->setAttribute("adaptive", "false");
        $this->node->setAttribute("timeDependent", "false");
        $this->document->appendChild($this->node);
    }

    /**
     * Add a new tag responseDeclaration to node
     *
     * @access potected
     *
     * @param String $baseType
     *
     */
    protected function qtiResponseDeclaration($identifier, $baseType, $cardinality)
    {
        $this->responseDeclaration[$this->nbResponseDeclaration] = $this->document->CreateElement('responseDeclaration');

        $newRespDec = $this->responseDeclaration[$this->nbResponseDeclaration];
        $newRespDec->setAttribute("identifier", $identifier);
        $newRespDec->setAttribute("cardinality", $cardinality);
        $newRespDec->setAttribute("baseType", $baseType);
        $this->node->appendChild($newRespDec);

        $this->nbResponseDeclaration++;
    }

    /**
     * add the tag outcomeDeclaration to the node
     *
     * @access protected
     *
     *
     */
    protected function qtiOutComeDeclaration()
    {
        $this->outcomeDeclaration = $this->document->CreateElement('outcomeDeclaration');
        $this->outcomeDeclaration->setAttribute("identifier", "SCORE");
        $this->outcomeDeclaration->setAttribute("cardinality", "single");
        $this->outcomeDeclaration->setAttribute("baseType", "float");
        $this->node->appendChild($this->outcomeDeclaration);
    }

    /**
     * add the tag modalFeedback to the node
     *
     * @access protected
     *
     * @param String $feedBack
     *
     */
    protected function qtiFeedBack($feedBack)
    {
        $this->modalFeedback = $this->document->CreateElement('modalFeedback');
        $this->modalFeedback->setAttribute("outcomeIdentifier","FEEDBACK");
        $this->modalFeedback->setAttribute("identifier","COMMENT");
        $this->modalFeedback->setAttribute("showHide","show");
        $modalFeedbacktxt = $this->document->CreateTextNode($feedBack);
        $this->modalFeedback->appendChild($modalFeedbacktxt);
        $this->node->appendChild($this->modalFeedback);
    }

    /**
     * add a tag p in itemBody for the description
     *
     * @access protected
     *
     */
    protected function qtiDescription()
    {
        $describe = $this->question->getDescription();
        if ($describe != NULL && $describe != '') {
            $describeTag = $this->document->createCDATASection($describe);
            $this->itemBody->appendChild($describeTag);
        }
    }

    /**
     * add the tag itemBody in node
     *
     * @access protected
     *
     */
    protected function itemBodyTag()
    {
        $this->itemBody = $this->document->CreateElement('itemBody');
        $this->node->appendChild($this->itemBody);
        $this->qtiDescription();
    }

    /**
     *
     * @access protected
     *
     * @param array of String $docLinked attached files
     *
     * @return BinaryFileResponse QTI zip
     *
     */
    protected function getResponse()
    {
        //$this->objetcTags();

        $tmpFileName = tempnam($this->qtiRepos->getUserDir().'tmp', "xb_");
        $zip = new \ZipArchive();
        $zip->open($tmpFileName, \ZipArchive::CREATE);
        $zip->addFile($this->qtiRepos->getUserDir().'testfile.xml', 'SchemaQTI.xml');

        foreach ($this->resourcesLinked as $file) {
            $zip->addFile($file['url'], $file['name']);
        }

        $zip->close();
        $response = new BinaryFileResponse($tmpFileName);
        //$response->headers->set('Content-Type', $content->getContentType());
        $response->headers->set('Content-Type', 'application/application/zip');
        $response->headers->set('Content-Disposition', "attachment; filename=QTI-Archive.zip");
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate'); // HTTP 1.1.
        $response->headers->set('Pragma', 'no-cache'); // HTTP 1.0.
        $response->headers->set('Expires', '0'); // Proxies.

        return $response;
    }

    /**
     * abstract method to export the question
     *
     * @access public
     * @param \UJM\ExoBundle\Entity\Interaction $interaction
     * @param qtiRepository $qtiRepos
     */
    abstract public function export(\UJM\ExoBundle\Entity\Interaction $interaction, qtiRepository $qtiRepos);

    /**
     * abstract method
     * Add the tag correctResponse in responseDeclaration
     *
     * @access protected
     */
    abstract protected function correctResponseTag();

    /**
     * abstract method
     * Add the tag prompt
     *
     * @access protected
     */
    abstract protected function promptTag();
}