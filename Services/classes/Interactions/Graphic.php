<?php

/**
 *
 * Services for the graphic
 */

namespace UJM\ExoBundle\Services\classes\Interactions;

use UJM\ExoBundle\Form\InteractionGraphicType;

class Graphic extends Interaction {

    /**
     * implement the abstract method
     * To process the user's response for a paper(or a test)
     *
     * @access public
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $paperID id Paper or 0 if it's just a question test and not a paper
     *
     * @return mixed[]
     */
     public function response(\Symfony\Component\HttpFoundation\Request $request, $paperID = 0)
     {
        $answers = $request->request->get('answers'); // Answer of the student
        $graphId = $request->request->get('graphId'); // Id of the graphic interaction
        $coords = preg_split('[;]', $answers); // Divide the answer zones into cells

        $em = $this->doctrine->getManager();

        $rightCoords = $em->getRepository('UJMExoBundle:Coords')
            ->findBy(array('interactionGraphic' => $graphId));

        $interG = $em->getRepository('UJMExoBundle:InteractionGraphic')
            ->find($graphId);

        $doc = $em->getRepository('UJMExoBundle:Document')
            ->findOneBy(array('id' => $interG->getDocument()));

        $point = $this->mark($answers, $request, $rightCoords, $coords);

        $session = $request->getSession();

        $penalty = $this->getPenalty($interG->getInteraction(), $session, $paperID);

        $score = $point - $penalty; // Score of the student with penalty

        // Not negatif score
        if ($score < 0) {
            $score = 0;
        }

        if (!preg_match('/[0-9]+/', $answers)) {
            $answers = '';
        }

        $total = $this->maxScore($interG); // Score max

        $res = array(
            'point' => $point, // Score of the student without penalty
            'penalty' => $penalty, // Penalty (hints)
            'interG' => $interG, // The entity interaction graphic (for the id ...)
            'coords' => $rightCoords, // The coordonates of the right answer zones
            'doc' => $doc, // The answer picture (label, src ...)
            'total' => $total, // Score max if all answers right and no penalty
            'rep' => $coords, // Coordonates of the answer zones of the student's answer
            'score' => $score, // Score of the student (right answer - penalty)
            'response' => $answers // The student's answer (with all the informations of the coordonates)
        );

        return $res;

     }

     /**
      * implement the abstract method
      * To calculate the score
      *
      * @access public
      *
      * @param String $answers
      * @param \Symfony\Component\HttpFoundation\Request $request
      * @param doctrineCollection of \UJM\ExoBundle\Entity\Coords $rightCoords
      * @param array[string] $coords
      *
      * @return float
      */
     public function mark($answers= null, $request= null, $rightCoords= null, $coords= null)
     {
         $max = $request->request->get('nbpointer'); // Number of answer zones
         $verif = array();
         $coords = preg_split('[;]', $answers); // Divide the answer zones into cells
         $point = $z = 0;

         for ($i = 0; $i < $max - 1; $i++) {
             for ($j = 0; $j < $max - 1; $j++) {
                 if (preg_match('/[0-9]+/', $coords[$j])) {
                     list($xa,$ya) = explode("-", $coords[$j]); // Answers of the student
                     list($xr,$yr) = explode(",", $rightCoords[$i]->getValue()); // Right answers

                     $valid = $rightCoords[$i]->getSize(); // Size of the answer zone

                     // If answer student is in right answer
                     if ((($xa + 8) < ($xr + $valid)) && (($xa + 8) > ($xr)) &&
                         (($ya + 8) < ($yr + $valid)) && (($ya + 8) > ($yr))
                     ) {
                         // Not get points twice for one answer
                         if ($this->alreadyDone($rightCoords[$i]->getValue(), $verif, $z)) {
                             $point += $rightCoords[$i]->getScoreCoords(); // Score of the student without penalty
                             $verif[$z] = $rightCoords[$i]->getValue(); // Add this answer zone to already answered zones
                             $z++;
                         }
                     }
                 }
             }
         }

         return $point;
     }

    /**
      * implement the abstract method
      * Get score max possible for a graphic question
      *
      * @access public
      *
      * @param \UJM\ExoBundle\Entity\InteractionGraphic $interGraph
      *
      * @return float
      */
     public function maxScore($interGraph = null)
     {
         $em = $this->doctrine->getManager();
         $scoreMax = 0;

         $rightCoords = $em->getRepository('UJMExoBundle:Coords')
                           ->findBy(array('interactionGraphic' => $interGraph->getId()));

         foreach ($rightCoords as $score) {
             $scoreMax += $score->getScoreCoords();
         }

         return $scoreMax;
     }

     /**
     * implement the abstract method
     *
     * @access public
     * @param Integer $interId id of interaction
     *
     * @return \UJM\ExoBundle\Entity\InteractionGraphic
     */
     public function getInteractionX($interId)
     {
         $em = $this->doctrine->getManager();
         $interGraphic = $em->getRepository('UJMExoBundle:InteractionGraphic')
                            ->getInteractionGraphic($interId);

         return $interGraphic;
     }

     /**
      * implement the abstract method
      *
      * call getAlreadyResponded and prepare the interaction to displayed if necessary
      *
      * @access public
      * @param \UJM\ExoBundle\Entity\Interaction $interactionToDisplay interaction (question) to displayed
      * @param Symfony\Component\HttpFoundation\Session\SessionInterface $session
      * @param \UJM\ExoBundle\Entity\InteractionX (qcm, graphic, open, ...) $interactionX
      *
      * @return \UJM\ExoBundle\Entity\Response
      */
     public function getResponseGiven($interactionToDisplay, $session, $interactionX)
     {
         $responseGiven = $this->getAlreadyResponded($interactionToDisplay, $session);

         return $responseGiven;
     }

     /**
      * implements the abstract method
      *
      * @access public
      *
      * @param \UJM\ExoBundle\Entity\Interaction $interaction
      * @param integer $exoID
      * @param mixed[] An array of parameters to pass to the view
      *
      * @return \Symfony\Component\HttpFoundation\Response
      */
     public function show($interaction, $exoID, $vars)
     {
         $interactionGraph = $this->doctrine
                                  ->getManager()
                                  ->getRepository('UJMExoBundle:InteractionGraphic')
                                  ->getInteractionGraphic($interaction->getId());

         $repository = $this->doctrine
                            ->getManager()
                            ->getRepository('UJMExoBundle:Coords');

         $listeCoords = $repository->findBy(array('interactionGraphic' => $interactionGraph));

         $vars['interactionToDisplayed'] = $interactionGraph;
         $vars['listeCoords']        = $listeCoords;
         $vars['exoID']              = $exoID;

         return $this->templating->renderResponse('UJMExoBundle:InteractionGraphic:paper.html.twig', $vars);
     }

     /**
      * implements the abstract method
      *
      * @access public
      *
      * @param \UJM\ExoBundle\Entity\Interaction $interaction
      * @param integer $exoID
      * @param integer $catID
      * @param Claroline\Entity\User $user
      *
      * @return \Symfony\Component\HttpFoundation\Response
      */
     public function edit($interaction, $exoID, $catID, $user)
     {
         $em = $this->doctrine->getEntityManager();
         $docID = -1;
         $interactionGraph = $this->doctrine
                                  ->getManager()
                                  ->getRepository('UJMExoBundle:InteractionGraphic')
                                  ->getInteractionGraphic($interaction->getId());

         $position = $em->getRepository('UJMExoBundle:Coords')->findBy(
             array('interactionGraphic' => $interactionGraph->getId()
             )
         );

         if ($user->getId() != $interactionGraph->getInteraction()->getQuestion()->getUser()->getId()) {
             $docID = $interactionGraph->getDocument()->getId();
         }

         $editForm = $this->formFactory->create(
             new InteractionGraphicType($user, $catID, $docID), $interactionGraph
                 );

         $linkedCategory = $this->questionService->getLinkedCategories();

         $variables['entity']         = $interactionGraph;
         $variables['edit_form']      = $editForm->createView();
         $variables['nbResponses']    = $this->getNbReponses($interaction);
         $variables['linkedCategory'] = $linkedCategory;
         $variables['position']       = $position;
         $variables['exoID']          = $exoID;
         $variables['locker']         = $this->categoryService->getLockCategory();

         if ($exoID != -1) {
             $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);
             $variables['_resource'] = $exercise;
         }

         return $this->templating->renderResponse('UJMExoBundle:InteractionGraphic:edit.html.twig', $variables);
     }

     /**
     * Graphic question : Check if the suggested answer zone isn't already right in order not to have points twice
     *
     * @access private
     *
     * @param String $coor coords of one right answer
     * @param array $verif list of the student's placed answers zone
     * @param integer $z number of rights placed answers by the user
     *
     * @return boolean
     */
    private function alreadyDone($coor, $verif, $z)
    {
        $resu = true;

        for ($v = 0; $v < $z; $v++) {
            // if already placed at this right place
            if ($coor == $verif[$v]) {
                $resu = false;
                break;
            } else {
                $resu = true;
            }
        }

        return $resu;
    }
}
