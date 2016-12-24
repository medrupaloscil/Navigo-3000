<?php
/**
 * Created by PhpStorm.
 * User: medrupaloscil
 * Date: 13/12/2016
 * Time: 15:13
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Card;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ApiController extends Controller
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var string Uniquely identifies the secured area
     */
    private $providerKey;

    /**
     * @Route("/api/v1/connection", name="apiConnection")
     */
    public function apiConnectionAction(Request $request) {

        $authenticationUtils = $this->get('security.authentication_utils');

        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();


        return new Response(array('last_username' => $lastUsername,'error' => $error));
    }

    /**
     * @Route("/api/v1/linkCardToUser", name="apiLinkCardToUser")
     * @Security("has_role('ROLE_USERS')")
     */
    public function linkCardToUserAction(Request $request) {

        $navigo = $request->request->get("navigo");

        if ($navigo != null) {

            $em = $this->getDoctrine()->getManager();
            $cards = $em->getRepository('AppBundle:Card');

            $card = $cards->findOneBy(array('cardId' => $navigo));

            if ($card == null) {

                $card = new Card();
                $card->setCardId($navigo);
                $em->persist($card);
                $em->flush();

                $user = $this->getUser();
                $user->setCard($card);

                $em->persist($user);
                $em->flush();

                $response = new Response(array("message" => "User updated"));
            } else {
                $response = new Response(array("message" => "Card already in our Database, please contact an administrator to link your card."), 200);
            }
        } else {
            $response = new Response(array("message" => "Data Error"));
        }

        return $response;
    }

    /**
     * @Route("/api/v1/linkCardToUser", name="apiLinkCardToUser")
     * @Security("has_role('ROLE_USERS')")
     */
    public function changePasswordAction(Request $request, User $user) {

        $password = $request->request->get("password");

        if ($password != null) {

            if (strlen($password) < 8) {
                $response = new Response(array("message" => "Password must have at least 8 characters"), 200);
            } else {
                $em = $this->getDoctrine()->getManager();

                $user->setPassword(sha1($password));

                $em->persist($user);
                $em->flush();

                $response = new Response(array("message" => "Password Updated"), 200);
            }
        } else {
            $response = new Response(array("message" => "Data Error"));
        }

        return $response;
    }

}