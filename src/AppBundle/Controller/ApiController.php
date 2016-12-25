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
     * @Route("/api/v1/validity", name="apiValidity")
     */
    public function validityAction(Request $request) {

        $id = $request->request->get('card-id');

        $em = $this->getDoctrine()->getManager();
        $cards = $em->getRepository('AppBundle:Card');

        $card = $cards->findOneBy(array('cardId' => $id));

        if ($card == null) {
            $response = new Response(array("success" => false, "message" => "There is no card with id: $id."));
        } else {
            $response = new Response(array("success" => true, "card" => $card));
        }

        return $response;
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

    /**
     * @Route("/api/v1/uploadImage", name="apiUploadImage")
     * @Security("has_role('ROLE_USERS')")
     */
    function uploadImageAction(Request $request) {

        if (count($_FILES) > 0) {
            $target_dir = $this->get('kernel')->getRootDir() . "/../web/cdn/";
            $target_file = $target_dir . basename($_FILES["file"]["name"]);
            $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
            $uploadOk = 1;
            if(!empty($_FILES["file"]["tmp_name"])) {
                $check = getimagesize($_FILES["file"]["tmp_name"]);
                if($check === false) $uploadOk = 0;
            } else {
                $uploadOk = 0;
            }
            if (file_exists($target_file)) $uploadOk = 0;
            if ($_FILES["file"]["size"] > 1000000) $uploadOk = 0;
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) $uploadOk = 0;
            if ($uploadOk == 0) {
                $response = new Response(array("message" => "An error happend"));
            } else {
                if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {

                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getUser();
                    $final_file = 'cdn/'. basename($_FILES["file"]["name"]);
                    $user->setPicture($final_file);
                    $em->persist($user);
                    $em->flush();

                    $response = new Response(array("message" => "File uploaded"));
                } else {
                    $response = new Response(array("message" => "Error when moving file"));
                }
            }
        } else {
            $response = new Response(array("message" => "No file"));
        }

        return $response;
    }
}