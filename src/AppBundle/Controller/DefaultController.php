<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request) {
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/test-card", name="testCard")
     */
    public function testCardAction(Request $request) {

        $result = null;
        $card = null;
        $id = $request->request->get('card-id');

        if ($id != null) {
            $em = $this->getDoctrine()->getManager();
            $cards = $em->getRepository('AppBundle:Card');
            $card = $cards->findOneBy(array('cardId' => $id));
            if ($card == null) {
                $result = "error";
                $card = "There is no card with id: $id.";
            } else {
                $result = "success";
            }
        }

        return $this->render('default/test-card.html.twig', array(
            "result" => $result,
            "card" => $card
        ));
    }
}
